<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Invoice;
use App\Models\InvoiceNoticeLog;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Everything about bills and payments lives here: the scheduled commands, the super admin screens,
 * the customer billing page and (later) the online payment gateway all use these methods.
 *
 * How the money flows
 * -------------------
 *  - A BILL covers one billing period (1 / 3 / 6 / 12 months). Amount = plan monthly price x months - discount.
 *  - A PAYMENT belongs to the customer. It is applied to the oldest unpaid bills first
 *    (or to the bill the payer chose first). Whatever is left over is the customer's ADVANCE.
 *  - The advance is used automatically when the next bill is created.
 *  - reallocate() rebuilds "which payment paid which bill" from scratch, so adding / editing / deleting a payment
 *    or editing a bill can never leave the numbers out of sync.
 */
class BillingService
{
    // ------------------------------------------------------------------ small helpers
    public static function dateFormat(): string
    {
        return config('warranty.date_format') ?: 'd/m/y';
    }

    /** ['2026-11' => 'November 2026', ...] newest first, for month dropdowns */
    public static function monthOptions(int $back = 24, int $forward = 2): array
    {
        $options = [];
        $start = today()->startOfMonth()->addMonthsNoOverflow($forward);
        for ($i = 0; $i < $back + $forward + 1; $i++) {
            $m = $start->copy()->subMonthsNoOverflow($i);
            $options[$m->format('Y-m')] = $m->format('F Y');
        }
        return $options;
    }

    public static function monthFromInput(?string $value): ?Carbon
    {
        if (! $value || ! preg_match('/^\d{4}-\d{2}$/', $value)) return null;
        return Carbon::createFromFormat('!Y-m', $value);
    }

    public function dueDateFor(Carbon $month): Carbon
    {
        $month = $month->copy()->startOfMonth();
        $day   = min(max((int) config('billing.due_day'), 1), $month->daysInMonth);
        return $month->day($day);
    }

    /**
     * Price of one bill: plan monthly price x months - discount for the cycle.
     * @return array{cycle:string, months:int, base:float, discount_percent:float, discount_amount:float, amount:float}
     */
    public static function quote(?Plan $plan, string $cycle): array
    {
        $cfg      = config("billing.cycles.{$cycle}") ?: config('billing.cycles.monthly');
        $cycleKey = config("billing.cycles.{$cycle}") ? $cycle : 'monthly';
        $months   = (int) $cfg['months'];
        $base     = round((float) optional($plan)->monthly_price * $months, 2);
        $percent  = (float) $cfg['discount'];
        $discount = round($base * $percent / 100, 2);

        return [
            'cycle' => $cycleKey,
            'months' => $months,
            'base' => $base,
            'discount_percent' => $percent,
            'discount_amount' => $discount,
            'amount' => round($base - $discount, 2),
        ];
    }

    /** First day of the next period that has to be billed (null = billing has not started). */
    public function nextPeriodStart(Account $account): ?Carbon
    {
        $last = Invoice::where('account_id', $account->id)->orderByDesc('billing_month')->first();

        if ($last) {
            return ($last->period_end ?: $last->billing_month->copy()->endOfMonth())->copy()->addDay()->startOfDay();
        }
        return $account->billing_starts_on ? $account->billing_starts_on->copy()->startOfMonth() : null;
    }

    /** The day the monthly check will suspend the account if this bill is still unpaid. */
    public function suspensionDateFor(Invoice $invoice): Carbon
    {
        $firstLateDay = $invoice->due_date->copy()->addMonthsNoOverflow((int) config('billing.grace_months'))->addDay();
        $checkDay     = (int) config('billing.due_day') + 1;                    // the scheduler runs on this day of the month

        $candidate = $firstLateDay->copy()->day(min($checkDay, $firstLateDay->daysInMonth));
        if ($candidate->lt($firstLateDay)) {
            $next      = $firstLateDay->copy()->startOfMonth()->addMonthNoOverflow();
            $candidate = $next->day(min($checkDay, $next->daysInMonth));
        }
        return $candidate;
    }

    // ------------------------------------------------------------------ bills
    public function createInvoice(
        Account $account,
        Carbon $start,
        ?Plan $plan = null,
        ?string $cycle = null,
        ?float $amount = null,
        ?Carbon $due = null,
        ?string $note = null,
        ?int $userId = null
    ): Invoice {
        $plan  ??= $account->plan;
        $start = $start->copy()->startOfMonth();
        $q     = self::quote($plan, $cycle ?: ($account->billing_cycle ?: 'monthly'));

        $final    = $amount ?? $q['amount'];
        $discount = $amount === null ? $q['discount_amount'] : max(0, round($q['base'] - $amount, 2));
        $percent  = $amount === null ? $q['discount_percent'] : ($q['base'] > 0 ? round($discount / $q['base'] * 100, 2) : 0);

        return DB::transaction(function () use ($account, $start, $plan, $q, $final, $discount, $percent, $due, $note, $userId) {
            $invoice = Invoice::create([
                'account_id'       => $account->id,
                'plan_id'          => optional($plan)->id,
                'plan_name'        => optional($plan)->name,
                'billing_month'    => $start,
                'period_end'       => $start->copy()->addMonthsNoOverflow($q['months'])->subDay(),
                'cycle'            => $q['cycle'],
                'months'           => $q['months'],
                'base_amount'      => $q['base'],
                'discount_percent' => $percent,
                'discount_amount'  => $discount,
                'amount'           => $final,
                'due_date'         => $due ?? $this->dueDateFor($start),
                'status'           => 'unpaid',
                'note'             => $note,
                'created_by'       => $userId,
            ]);
            $invoice->update(['invoice_no' => sprintf('INV-%s-%05d', $start->format('Ym'), $invoice->id)]);

            return $invoice;
        });
    }

    /** Tells the customer owner about a new bill (in-app + email). */
    public function announce(Invoice $invoice): void
    {
        $invoice->refresh();
        $money = Invoice::money($invoice->amount);
        $off   = (float) $invoice->discount_percent > 0 ? ' (includes ' . (float) $invoice->discount_percent . '% discount)' : '';

        if ($invoice->status === 'paid') {
            $text = "Your {$invoice->plan_name} plan {$invoice->cycle_label} bill of {$money}{$off} was paid from your advance balance. Thank you.";
        } else {
            $text = "Your {$invoice->plan_name} plan {$invoice->cycle_label} bill of {$money}{$off} is due by "
                . $invoice->due_date->format(self::dateFormat())
                . '. If it is not paid by then, your account is restricted (only the Billing page can be used), '
                . 'and after one more month it is suspended.';
        }

        $this->notifyOwners($invoice->account, "Bill for {$invoice->period_label}", $text, $invoice, 'generated');
    }

    /**
     * Creates the bill of the new period for every active customer whose period has started.
     * Scheduled ONCE a month (1st). Safe to run again by hand: it only creates what is missing.
     */
    public function generateMonthly(): int
    {
        $monthStart = today()->startOfMonth();
        $created    = 0;

        Account::with('plan')
            ->where('status', 'active')
            ->whereNotNull('plan_id')
            ->whereNotNull('billing_starts_on')
            ->chunkById(100, function ($accounts) use ($monthStart, &$created) {
                foreach ($accounts as $account) {
                    if (! $account->plan || (float) $account->plan->monthly_price <= 0) continue;

                    for ($guard = 0; $guard < 24; $guard++) {          // normally exactly one; more only to catch up
                        $start = $this->nextPeriodStart($account);
                        if (! $start || $start->gt($monthStart)) break;

                        $due = $this->dueDateFor($start);
                        if ($due->lt(today())) {                        // generated late -> a few extra days
                            $due = today()->addDays((int) config('billing.late_grace_days'));
                        }

                        $invoice = $this->createInvoice($account, $start, null, null, null, $due);
                        $this->reallocate($account);                    // advance balance pays the new bill automatically
                        $this->announce($invoice);
                        $created++;
                    }
                }
            });

        return $created;
    }

    // ------------------------------------------------------------------ payments + advance
    public function advanceBalance(Account $account): float
    {
        return $account->advanceBalance();
    }

    /**
     * Rebuilds how every payment of the customer is used:
     * oldest bill first (a payment can name a bill that gets paid first), the rest becomes advance.
     * Then updates paid amount + status of every bill, and reactivates the customer if nothing is overdue any more.
     */
    public function reallocate(Account $account): void
    {
        DB::transaction(function () use ($account) {
            PaymentAllocation::where('account_id', $account->id)->delete();

            $invoices  = Invoice::where('account_id', $account->id)->orderBy('billing_month')->orderBy('id')->get();
            $remaining = $invoices->where('status', '!=', 'cancelled')
                ->mapWithKeys(fn($i) => [$i->id => round((float) $i->amount, 2)])->all();

            $rows = [];
            foreach (Payment::where('account_id', $account->id)->orderBy('paid_on')->orderBy('id')->get() as $payment) {
                $left  = round((float) $payment->amount, 2);
                $order = array_keys($remaining);

                if ($payment->invoice_id && isset($remaining[$payment->invoice_id])) {
                    $order = array_merge([$payment->invoice_id], array_diff($order, [$payment->invoice_id]));
                }

                foreach ($order as $invoiceId) {
                    if ($left <= 0.004) break;
                    $take = round(min($left, $remaining[$invoiceId]), 2);
                    if ($take <= 0.004) continue;

                    $rows[] = [
                        'payment_id' => $payment->id,
                        'invoice_id' => $invoiceId,
                        'account_id' => $account->id,
                        'amount' => $take,
                        'created_at' => now(),
                        'updated_at' => now()
                    ];
                    $remaining[$invoiceId] = round($remaining[$invoiceId] - $take, 2);
                    $left = round($left - $take, 2);
                }
            }
            if ($rows) PaymentAllocation::insert($rows);

            foreach ($invoices as $invoice) {
                if ($invoice->status === 'cancelled') {
                    if ((float) $invoice->paid_amount !== 0.0) $invoice->update(['paid_amount' => 0]);
                    continue;
                }
                $paid   = round((float) $invoice->amount - $remaining[$invoice->id], 2);
                $status = $paid >= (float) $invoice->amount - 0.004 ? 'paid' : ($paid > 0.004 ? 'partial' : 'unpaid');
                $invoice->update(['paid_amount' => $paid, 'status' => $status]);
            }
        });

        $this->reactivateIfCleared($account);
    }

    /** Used after a bill was edited / cancelled. */
    public function refresh(Invoice $invoice): Invoice
    {
        $this->reallocate($invoice->account);
        return $invoice->refresh();
    }

    /**
     * Saves a payment of the customer. $target = the bill that should be paid first (optional).
     * $userId = the admin who received it (null = paid online by the customer).
     */
    public function recordPayment(Account $account, array $data, ?int $userId, ?Invoice $target = null): Payment
    {
        return DB::transaction(function () use ($account, $data, $userId, $target) {
            // an online gateway may call back twice: the same reference must not be counted twice
            if ($userId === null && ! empty($data['reference'])) {
                $duplicate = Payment::where('account_id', $account->id)->where('method', $data['method'])
                    ->where('reference', $data['reference'])->first();
                if ($duplicate) return $duplicate;
            }

            $payment = Payment::create($data + [
                'account_id' => $account->id,
                'invoice_id' => optional($target)->id,
                'received_by' => $userId,
            ]);
            $this->reallocate($account);
            $payment->refresh();

            $advance = $account->advanceBalance();
            $text = 'We received ' . Invoice::money($payment->amount) . ' on ' . $payment->paid_on->format(self::dateFormat()) . '. '
                . ($advance > 0
                    ? 'Your advance balance is ' . Invoice::money($advance) . '; it is adjusted against your next bill automatically.'
                    : 'Thank you.');
            $this->notifyOwners($account, 'Payment received', $text);

            return $payment;
        });
    }

    public function updatePayment(Payment $payment, array $data, ?Invoice $target = null): Payment
    {
        $payment->update($data + ['invoice_id' => optional($target)->id]);
        $this->reallocate($payment->account);
        return $payment->refresh();
    }

    public function deletePayment(Payment $payment): void
    {
        $account = $payment->account;
        $payment->delete();
        $this->reallocate($account);
    }

    // ------------------------------------------------------------------ suspend / reactivate
    /**
     * Scheduled once a month. Suspends every active customer that has a bill unpaid for MORE than
     * the grace time (1 month) after its due date. (In that month the customer could login but only pay.)
     */
    public function suspendOverdue(): int
    {
        $suspended = 0;

        Account::where('status', 'active')
            ->whereHas('invoices', fn($q) => $q->suspendable())
            ->get()
            ->each(function (Account $account) use (&$suspended) {
                $account->update(['status' => 'inactive', 'suspended_reason' => 'overdue_bill']);

                $invoice = $account->invoices()->suspendable()->orderBy('due_date')->first();
                if ($invoice) {
                    $this->notifyOwners(
                        $account,
                        'Account suspended',
                        "Your account was suspended because the bill for {$invoice->period_label} ("
                            . Invoice::money($invoice->balance) . ' due) has been unpaid for more than a month after '
                            . $invoice->due_date->format(self::dateFormat())
                            . '. Please pay the outstanding bill and contact support to reactivate your account.',
                        $invoice,
                        'suspended'
                    );
                }
                $suspended++;
            });

        return $suspended;
    }

    public function reactivateIfCleared(?Account $account): void
    {
        if (! $account) return;
        $account->refresh();

        if (
            $account->status === 'inactive' && $account->suspended_reason === 'overdue_bill'
            && ! $account->invoices()->suspendable()->exists()
        ) {

            $account->update(['status' => 'active', 'suspended_reason' => null]);
            $this->notifyOwners($account, 'Account reactivated', 'Thank you! Your account is active again.');
        }
    }

    // ------------------------------------------------------------------ daily notifications
    /** "Bill due in 3 days", "Bill due today", "Bill overdue" and the final warning before suspension. */
    public function sendReminders(): int
    {
        $sent = 0;
        $fmt  = self::dateFormat();

        foreach (config('billing.reminder_days_before') as $days) {
            $target = today()->addDays($days)->toDateString();

            Invoice::with('account')->open()->whereDate('due_date', $target)->get()
                ->each(function (Invoice $invoice) use ($days, $fmt, &$sent) {
                    if (optional($invoice->account)->status !== 'active') return;

                    $title = $days === 0 ? 'Bill due today' : "Bill due in {$days} day(s)";
                    $text  = "The balance of your {$invoice->period_label} bill is " . Invoice::money($invoice->balance)
                        . ', due on ' . $invoice->due_date->format($fmt)
                        . '. After the due date your account is restricted until you pay.';

                    if ($this->notifyOwners($invoice->account, $title, $text, $invoice, "reminder_{$days}")) $sent++;
                });
        }

        // overdue: restricted now, suspended later
        Invoice::with('account')->overdue()->get()->each(function (Invoice $invoice) use ($fmt, &$sent) {
            if (optional($invoice->account)->status !== 'active') return;

            $suspendOn = $this->suspensionDateFor($invoice);

            if ($this->notifyOwners(
                $invoice->account,
                'Bill overdue - payment required',
                "Your {$invoice->period_label} bill (" . Invoice::money($invoice->balance) . ') is overdue. '
                    . 'Your account is restricted: you can only open the Billing page and pay. '
                    . 'Please pay before ' . $suspendOn->format($fmt) . ' or your account will be suspended and you will not be able to login.',
                $invoice,
                'overdue'
            )) $sent++;

            if (
                today()->eq($suspendOn->copy()->subDays(3))
                && $this->notifyOwners(
                    $invoice->account,
                    'Final warning - account will be suspended',
                    'Your account will be suspended on ' . $suspendOn->format($fmt) . ' if the ' . $invoice->period_label
                        . ' bill (' . Invoice::money($invoice->balance) . ') is not paid.',
                    $invoice,
                    'final_warning'
                )
            ) $sent++;
        });

        return $sent;
    }

    // ------------------------------------------------------------------ notifications
    /**
     * In-app notification + email to the owner(s) of the customer.
     * When $noticeType is given, the same notice is never created twice for the same bill.
     */
    public function notifyOwners(Account $account, string $title, string $message, ?Invoice $invoice = null, ?string $noticeType = null): bool
    {
        if ($invoice && $noticeType) {
            $log = InvoiceNoticeLog::firstOrCreate(['invoice_id' => $invoice->id, 'type' => $noticeType]);
            if (! $log->wasRecentlyCreated) return false;
        }

        User::where('account_id', $account->id)->where('role', 'owner')->where('status', 'active')->get()
            ->each(function (User $owner) use ($account, $title, $message) {
                UserNotification::create([
                    'account_id' => $account->id,
                    'user_id' => $owner->id,
                    'type' => 'billing',
                    'title' => $title,
                    'message' => $message,
                ]);
                try {
                    Mail::raw("{$title}\n\n{$message}", fn($m) => $m->to($owner->email)->subject($title));
                } catch (\Throwable $e) {
                    report($e);
                }
            });

        return true;
    }
}
