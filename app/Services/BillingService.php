<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Invoice;
use App\Models\InvoiceNoticeLog;
use App\Models\Payment;
use App\Models\PaymentSchedule;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Everything about bills and payments lives here: the scheduled commands, the super admin screens,
 * the customer billing page and (later) the online payment gateway all use these methods.
 *
 * The rules
 * ---------
 *  - Every customer has a PAYMENT SCHEDULE (Monthly / Quarterly / Half-yearly / Yearly ...), managed by the super admin.
 *    One bill covers one period of the schedule: Yearly = 1 bill a year, Half-yearly = 2, Quarterly = 4.
 *  - Bill amount = plan monthly price x months - the discount % of the schedule.
 *  - The bill of the next period is created 15 days before that period starts. Never earlier (no advance bills),
 *    and never for a period that is not in the customer's schedule.
 *  - A bill can have many payments, but a payment always belongs to one bill and can never be more than what is
 *    still due (no advance payments).
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

    /** Price of one bill: plan monthly price x months - the discount of the schedule. */
    public static function quote(?Plan $plan, ?PaymentSchedule $schedule): array
    {
        $months   = max(1, (int) optional($schedule)->months);
        $base     = round((float) optional($plan)->monthly_price * $months, 2);
        $percent  = (float) optional($schedule)->discount_percent;
        $discount = round($base * $percent / 100, 2);

        return [
            'months' => $months, 'base' => $base, 'discount_percent' => $percent,
            'discount_amount' => $discount, 'amount' => round($base - $discount, 2),
        ];
    }

    public function periodEnd(Carbon $start, int $months): Carbon
    {
        return $start->copy()->addMonthsNoOverflow($months)->subDay();
    }

    /** The day the bill of the period that starts on $start may be created (15 days before). */
    public function invoiceOpensOn(Carbon $start): Carbon
    {
        return $start->copy()->subDays((int) config('billing.invoice_days_before'));
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
    /**
     * What the next bill of a customer would be, and whether it may be created today.
     * The only bill that can ever be created is the next period of the customer's own payment schedule,
     * and only from 15 days before that period starts.
     * Exception: when the admin creates it by hand ($manual), the FIRST bill of a new customer may be created at any time.
     * (A customer that is created in the middle of a month uses the rest of that month for free; billing starts next month.)
     */
    public function nextInvoiceDraft(Account $account, bool $manual = false): array
    {
        $plan = $account->plan;
        $schedule = $account->schedule;

        if (! $plan || ! $schedule) {
            return ['can' => false, 'message' => 'This customer has no plan or no payment schedule.'];
        }
        if ((float) $plan->monthly_price <= 0) {
            return ['can' => false, 'message' => 'The plan of this customer is free, so there is nothing to bill.'];
        }
        $start = $this->nextPeriodStart($account);
        if (! $start) {
            return ['can' => false, 'message' => 'Billing has not started for this customer yet.'];
        }

        $quote = self::quote($plan, $schedule);
        $end   = $this->periodEnd($start, $quote['months']);
        $opens = $this->invoiceOpensOn($start);
        $due   = $this->dueDateFor($start);
        if ($due->lt(today())) {                                   // created late -> a few extra days
            $due = today()->addDays((int) config('billing.late_grace_days'));
        }

        $isFirst = ! Invoice::where('account_id', $account->id)->exists();

        $draft = [
            'first' => $isFirst,
            'free_until' => $isFirst && $start->gt(today()) ? $start->copy()->subDay() : null,
            'start' => $start, 'end' => $end, 'opens_on' => $opens, 'due' => $due,
            'quote' => $quote, 'plan' => $plan, 'schedule' => $schedule,
            'period_label' => $quote['months'] <= 1 ? $start->format('F Y') : $start->format('M Y') . ' - ' . $end->format('M Y'),
        ];

        if (today()->lt($opens) && ! ($manual && $isFirst)) {
            return $draft + ['can' => false, 'message' => 'Advance bills are not allowed. The next bill (' . $draft['period_label']
                . ') can be created from ' . $opens->format(self::dateFormat()) . '.'];
        }
        return $draft + ['can' => true, 'message' => ''];
    }

    public function createInvoice(Account $account, array $draft, ?float $amount = null, ?Carbon $due = null,
                                  ?string $note = null, ?int $userId = null): Invoice
    {
        $q        = $draft['quote'];
        $schedule = $draft['schedule'];
        $plan     = $draft['plan'];
        $start    = $draft['start'];

        $final    = $amount ?? $q['amount'];
        $discount = $amount === null ? $q['discount_amount'] : max(0, round($q['base'] - $amount, 2));
        $percent  = $amount === null ? $q['discount_percent'] : ($q['base'] > 0 ? round($discount / $q['base'] * 100, 2) : 0);

        return DB::transaction(function () use ($account, $draft, $q, $schedule, $plan, $start, $final, $discount, $percent, $due, $note, $userId) {
            $invoice = Invoice::create([
                'account_id'          => $account->id,
                'plan_id'             => $plan->id,
                'plan_name'           => $plan->name,
                'billing_month'       => $start,
                'period_end'          => $draft['end'],
                'cycle'               => Str::slug($schedule->name),
                'payment_schedule_id' => $schedule->id,
                'schedule_name'       => $schedule->name,
                'months'              => $q['months'],
                'base_amount'         => $q['base'],
                'discount_percent'    => $percent,
                'discount_amount'     => $discount,
                'amount'              => $final,
                'due_date'            => $due ?? $draft['due'],
                'status'              => 'unpaid',
                'note'                => $note,
                'created_by'          => $userId,
            ]);
            $invoice->update(['invoice_no' => sprintf('INV-%s-%05d', $start->format('Ym'), $invoice->id)]);

            return $invoice;
        });
    }

    /** Tells the customer owner about a new bill (in-app + email with the invoice PDF). */
    public function announce(Invoice $invoice): void
    {
        $invoice->refresh();
        $off = (float) $invoice->discount_percent > 0 ? ' (includes ' . (float) $invoice->discount_percent . '% discount)' : '';

        $this->notifyOwners(
            $invoice->account, "New bill: {$invoice->period_label}",
            "Your {$invoice->plan_name} plan {$invoice->cycle_label} bill of " . Invoice::money($invoice->amount) . "{$off} is ready. "
                . 'Please pay by ' . $invoice->due_date->format(self::dateFormat())
                . '. After that your account is restricted (only the Billing page can be used), and after one more month it is suspended. '
                . 'The invoice is attached to the email and can be downloaded from your Billing page.',
            $invoice, 'generated', true
        );
    }

    /**
     * Creates the bills that are due: for every active customer, the next period of his payment schedule,
     * 15 days before it starts. Runs every day; it only creates what is missing, so nobody is billed twice.
     */
    public function generateInvoices(): int
    {
        $created = 0;

        Account::with(['plan', 'schedule'])
            ->where('status', 'active')
            ->whereNotNull('plan_id')
            ->whereNotNull('payment_schedule_id')
            ->whereNotNull('billing_starts_on')
            ->chunkById(100, function ($accounts) use (&$created) {
                foreach ($accounts as $account) {
                    for ($guard = 0; $guard < 24; $guard++) {            // normally one; more only to catch up
                        $draft = $this->nextInvoiceDraft($account);
                        if (! $draft['can']) break;

                        $this->announce($this->createInvoice($account, $draft));
                        $created++;
                    }
                }
            });

        return $created;
    }

    // ------------------------------------------------------------------ payments
    /** Paid amount + status of a bill = its payments. Reactivates the customer when nothing is overdue any more. */
    public function refresh(Invoice $invoice): Invoice
    {
        $paid = round((float) $invoice->payments()->sum('amount'), 2);

        if ($invoice->status === 'cancelled') {
            $status = 'cancelled';
        } elseif ($paid >= (float) $invoice->amount - 0.004) {
            $status = 'paid';
        } else {
            $status = $paid > 0.004 ? 'partial' : 'unpaid';
        }

        $invoice->update(['paid_amount' => $paid, 'status' => $status]);
        $this->reactivateIfCleared($invoice->account);

        return $invoice->refresh();
    }

    /**
     * Saves a payment for a bill. $userId = the admin who received it (null = paid online by the customer).
     * @throws \DomainException when the amount is more than the bill still needs (no advance payments)
     */
    public function recordPayment(Invoice $invoice, array $data, ?int $userId): Payment
    {
        $isNew = true;

        $payment = DB::transaction(function () use ($invoice, $data, $userId, &$isNew) {
            $invoice->refresh();

            // an online gateway may call back twice: the same reference must not be counted twice
            if ($userId === null && ! empty($data['reference'])) {
                $duplicate = Payment::where('account_id', $invoice->account_id)->where('method', $data['method'])
                    ->where('reference', $data['reference'])->first();
                if ($duplicate) { $isNew = false; return $duplicate; }
            }

            if ($invoice->status === 'cancelled') {
                throw new \DomainException('This bill is cancelled.');
            }
            if ((float) $data['amount'] > $invoice->balance + 0.004) {
                throw new \DomainException('The amount is more than the balance of this bill (' . Invoice::money($invoice->balance) . '). Advance payments are not allowed.');
            }

            $payment = $invoice->payments()->create($data + ['account_id' => $invoice->account_id, 'received_by' => $userId]);
            $this->refresh($invoice);

            return $payment;
        });

        if ($isNew) {
            $invoice->refresh();
            $left = $invoice->balance > 0.004
                ? 'The balance of this bill is ' . Invoice::money($invoice->balance) . '.'
                : 'This bill is fully paid. Thank you!';

            $this->notifyOwners(
                $invoice->account, 'Payment received - ' . $invoice->invoice_no,
                'We received ' . Invoice::money($payment->amount) . ' on ' . $payment->paid_on->format(self::dateFormat())
                    . " for the bill {$invoice->period_label}. {$left} Your updated invoice ({$invoice->status_text}) is attached.",
                $invoice, "payment-{$payment->id}", true
            );
        }

        return $payment;
    }

    /** @throws \DomainException */
    public function updatePayment(Payment $payment, array $data): Payment
    {
        if ($payment->isOnline()) {
            throw new \DomainException('An online payment cannot be edited.');
        }
        $invoice = $payment->invoice;
        $limit   = $invoice->balance + (float) $payment->amount;       // what this payment may be at most

        if ((float) $data['amount'] > $limit + 0.004) {
            throw new \DomainException('The amount is more than this bill allows (' . Invoice::money($limit) . '). Advance payments are not allowed.');
        }

        $payment->update($data);
        $this->refresh($invoice);

        return $payment->refresh();
    }

    /** @throws \DomainException for online payments */
    public function deletePayment(Payment $payment): void
    {
        if ($payment->isOnline()) {
            throw new \DomainException('An online payment cannot be deleted.');
        }
        $invoice = $payment->invoice;
        $payment->delete();
        $this->refresh($invoice);
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
            ->whereHas('invoices', fn ($q) => $q->suspendable())
            ->get()
            ->each(function (Account $account) use (&$suspended) {
                $account->update(['status' => 'inactive', 'suspended_reason' => 'overdue_bill']);

                $invoice = $account->invoices()->suspendable()->orderBy('due_date')->first();
                if ($invoice) {
                    $this->notifyOwners(
                        $account, 'Account suspended',
                        "Your account was suspended because the bill for {$invoice->period_label} ("
                            . Invoice::money($invoice->balance) . ' due) has been unpaid for more than a month after '
                            . $invoice->due_date->format(self::dateFormat())
                            . '. Please pay the outstanding bill and contact support to reactivate your account.',
                        $invoice, 'suspended'
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

        if ($account->status === 'inactive' && $account->suspended_reason === 'overdue_bill'
            && ! $account->invoices()->suspendable()->exists()) {

            $account->update(['status' => 'active', 'suspended_reason' => null]);
            $this->notifyOwners($account, 'Account reactivated', 'Thank you! Your account is active again.');
        }
    }

    // ------------------------------------------------------------------ daily notifications
    /**
     * Notifications (panel + email), checked every day:
     *  - the payment schedule ends in about a month, and when the next bill will be created
     *  - bill due in 3 days / due today
     *  - bill overdue (account restricted) and the final warning before suspension
     */
    public function sendReminders(): int
    {
        $sent = 0;
        $fmt  = self::dateFormat();

        // ---- 1 month before the end of the payment schedule period
        $months = (int) config('billing.schedule_notice_months');
        Account::with('schedule')->where('status', 'active')->whereNotNull('payment_schedule_id')->get()
            ->each(function (Account $account) use ($months, $fmt, &$sent) {
                $last = Invoice::where('account_id', $account->id)->orderByDesc('billing_month')->first();
                if (! $last || ! $last->period_end) return;

                $end = $last->period_end;
                if (today()->lt($end->copy()->subMonthsNoOverflow($months)) || today()->gt($end)) return;

                $createOn = $this->invoiceOpensOn($end->copy()->addDay());
                $text = 'Your ' . optional($account->schedule)->name . ' payment schedule ends on ' . $end->format($fmt)
                    . '. Your next bill will be created on ' . $createOn->format($fmt)
                    . ' (' . config('billing.invoice_days_before') . ' days before) and sent to you by email.';

                if ($this->notifyOwners($account, 'Your payment schedule ends soon', $text, $last, 'schedule_ending')) $sent++;
            });

        // ---- bill due in 3 days / today
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

        // ---- overdue: restricted now, suspended later
        Invoice::with('account')->overdue()->get()->each(function (Invoice $invoice) use ($fmt, &$sent) {
            if (optional($invoice->account)->status !== 'active') return;

            $suspendOn = $this->suspensionDateFor($invoice);

            if ($this->notifyOwners($invoice->account, 'Bill overdue - payment required',
                "Your {$invoice->period_label} bill (" . Invoice::money($invoice->balance) . ') is overdue. '
                . 'Your account is restricted: you can only open the Billing page and pay. '
                . 'Please pay before ' . $suspendOn->format($fmt) . ' or your account will be suspended and you will not be able to login.',
                $invoice, 'overdue')) $sent++;

            if (today()->eq($suspendOn->copy()->subDays(3))
                && $this->notifyOwners($invoice->account, 'Final warning - account will be suspended',
                    'Your account will be suspended on ' . $suspendOn->format($fmt) . ' if the ' . $invoice->period_label
                    . ' bill (' . Invoice::money($invoice->balance) . ') is not paid.',
                    $invoice, 'final_warning')) $sent++;
        });

        return $sent;
    }

    // ------------------------------------------------------------------ notifications
    /**
     * In-app notification + email to the owner(s) of the customer.
     * When $noticeType is given, the same notice is never created twice for the same bill.
     * With $attachInvoice the email carries the invoice PDF.
     */
    public function notifyOwners(Account $account, string $title, string $message, ?Invoice $invoice = null,
                                 ?string $noticeType = null, bool $attachInvoice = false): bool
    {
        if ($invoice && $noticeType) {
            $log = InvoiceNoticeLog::firstOrCreate(['invoice_id' => $invoice->id, 'type' => $noticeType]);
            if (! $log->wasRecentlyCreated) return false;
        }

        $pdf = null;
        if ($attachInvoice && $invoice) {
            try {
                $maker = app(InvoicePdf::class);
                $pdf   = ['data' => $maker->output($invoice->fresh()), 'name' => $maker->filename($invoice)];
            } catch (\Throwable $e) {
                report($e);                                   // e.g. the PDF package is not installed yet: send the mail without it
            }
        }

        User::where('account_id', $account->id)->where('role', 'owner')->where('status', 'active')->get()
            ->each(function (User $owner) use ($account, $title, $message, $pdf) {
                UserNotification::create([
                    'account_id' => $account->id, 'user_id' => $owner->id,
                    'type' => 'billing', 'title' => $title, 'message' => $message,
                ]);
                try {
                    Mail::raw("{$title}\n\n{$message}", function ($mail) use ($owner, $title, $pdf) {
                        $mail->to($owner->email)->subject($title);
                        if ($pdf) $mail->attachData($pdf['data'], $pdf['name'], ['mime' => 'application/pdf']);
                    });
                } catch (\Throwable $e) {
                    report($e);
                }
            });

        return true;
    }
}
