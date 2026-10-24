<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Invoice;
use App\Models\InvoiceNoticeLog;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Everything about bills lives here: the scheduled commands and the super admin screens
 * use the same methods.
 */
class BillingService
{
    // ------------------------------------------------------------------ helpers
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

    // ------------------------------------------------------------------ invoices
    public function createInvoice(Account $account, Carbon $month, ?Plan $plan = null, ?float $amount = null,
                                  ?Carbon $due = null, ?string $note = null, ?int $userId = null): Invoice
    {
        $plan  ??= $account->plan;
        $month = $month->copy()->startOfMonth();

        return DB::transaction(function () use ($account, $month, $plan, $amount, $due, $note, $userId) {
            $invoice = Invoice::create([
                'account_id'    => $account->id,
                'plan_id'       => optional($plan)->id,
                'plan_name'     => optional($plan)->name,
                'billing_month' => $month,
                'amount'        => $amount ?? (float) optional($plan)->monthly_price,
                'due_date'      => $due ?? $this->dueDateFor($month),
                'status'        => 'unpaid',
                'note'          => $note,
                'created_by'    => $userId,
            ]);
            $invoice->update(['invoice_no' => sprintf('INV-%s-%05d', $month->format('Ym'), $invoice->id)]);

            return $invoice;
        });
    }

    /** Tells the customer owner about a new bill (in-app + email). */
    public function announce(Invoice $invoice): void
    {
        $this->notifyOwners(
            $invoice->account,
            "Bill for {$invoice->month_label}",
            "Your {$invoice->plan_name} plan bill of " . Invoice::money($invoice->amount) . ' is due by '
                . $invoice->due_date->format(config('warranty.date_format')) . '. Accounts with an unpaid bill after the due date are suspended automatically.',
            $invoice, 'generated'
        );
    }

    /**
     * Creates this month's bill for every active customer that does not have one yet.
     * Scheduled ONCE a month (1st). It is safe to run again by hand (billing:generate-invoices):
     * it only creates what is missing, so nobody is billed twice.
     */
    public function generateMonthly(): int
    {
        $month   = today()->startOfMonth();
        $created = 0;

        Account::with('plan')
            ->where('status', 'active')
            ->whereNotNull('plan_id')
            ->whereNotNull('billing_starts_on')
            ->whereDate('billing_starts_on', '<=', $month->toDateString())
            ->chunkById(100, function ($accounts) use ($month, &$created) {
                foreach ($accounts as $account) {
                    if (! $account->plan || (float) $account->plan->monthly_price <= 0) continue;
                    if (Invoice::where('account_id', $account->id)->whereDate('billing_month', $month->toDateString())->exists()) continue;

                    $due = $this->dueDateFor($month);
                    if ($due->lt(today())) {                       // generated late -> give a few extra days
                        $due = today()->addDays((int) config('billing.late_grace_days'));
                    }

                    $this->announce($this->createInvoice($account, $month, null, null, $due));
                    $created++;
                }
            });

        return $created;
    }

    // ------------------------------------------------------------------ payments
    /** Recalculates paid amount + status of the bill, and reactivates the customer when everything is cleared. */
    public function refresh(Invoice $invoice): Invoice
    {
        $paid = (float) $invoice->payments()->sum('amount');

        if ($invoice->status === 'cancelled') {
            $status = 'cancelled';
        } elseif ($paid >= (float) $invoice->amount - 0.001) {
            $status = 'paid';
        } else {
            $status = $paid > 0 ? 'partial' : 'unpaid';
        }

        $invoice->update(['paid_amount' => $paid, 'status' => $status]);
        $this->reactivateIfCleared($invoice->account);

        return $invoice;
    }

    public function recordPayment(Invoice $invoice, array $data, ?int $userId): Payment
    {
        return DB::transaction(function () use ($invoice, $data, $userId) {
            $payment = $invoice->payments()->create($data + ['account_id' => $invoice->account_id, 'received_by' => $userId]);
            $this->refresh($invoice);

            $this->notifyOwners(
                $invoice->account, 'Payment received',
                'We received ' . Invoice::money($payment->amount) . " for your {$invoice->month_label} bill on "
                    . $payment->paid_on->format(config('warranty.date_format')) . '. Thank you.',
                $invoice, "payment-{$payment->id}"
            );

            return $payment;
        });
    }

    public function updatePayment(Payment $payment, array $data): Payment
    {
        $payment->update($data);
        $this->refresh($payment->invoice);
        return $payment;
    }

    public function deletePayment(Payment $payment): void
    {
        $invoice = $payment->invoice;
        $payment->delete();
        $this->refresh($invoice);
    }

    // ------------------------------------------------------------------ suspend / reactivate
    /** Suspends every active customer that has a bill which is past its due date. */
    public function suspendOverdue(): int
    {
        $suspended = 0;

        Account::where('status', 'active')
            ->whereHas('invoices', fn ($q) => $q->overdue())
            ->get()
            ->each(function (Account $account) use (&$suspended) {
                $account->update(['status' => 'inactive', 'suspended_reason' => 'overdue_bill']);

                $invoice = $account->invoices()->overdue()->orderBy('due_date')->first();
                if ($invoice) {
                    $this->notifyOwners(
                        $account, 'Account suspended',
                        "Your account was suspended because the bill for {$invoice->month_label} ("
                            . Invoice::money($invoice->balance) . ' due) was not paid by '
                            . $invoice->due_date->format(config('warranty.date_format'))
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
            && ! $account->invoices()->overdue()->exists()) {

            $account->update(['status' => 'active', 'suspended_reason' => null]);
            $this->notifyOwners($account, 'Account reactivated', 'Thank you! Your outstanding bills are cleared and your account is active again.');
        }
    }

    // ------------------------------------------------------------------ reminders
    /** "Bill due in 3 days" and "Bill due today" notices. */
    public function sendReminders(): int
    {
        $sent = 0;

        foreach (config('billing.reminder_days_before') as $days) {
            $target = today()->addDays($days)->toDateString();

            Invoice::with('account')->open()->whereDate('due_date', $target)->get()
                ->each(function (Invoice $invoice) use ($days, &$sent) {
                    if (optional($invoice->account)->status !== 'active') return;

                    $title = $days === 0 ? 'Bill due today' : "Bill due in {$days} day(s)";
                    $text  = "The balance of your {$invoice->month_label} bill is " . Invoice::money($invoice->balance)
                        . ', due on ' . $invoice->due_date->format(config('warranty.date_format'))
                        . '. Accounts with an unpaid bill after the due date are suspended automatically.';

                    if ($this->notifyOwners($invoice->account, $title, $text, $invoice, "reminder_{$days}")) $sent++;
                });
        }

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
                    'account_id' => $account->id, 'user_id' => $owner->id,
                    'type' => 'billing', 'title' => $title, 'message' => $message,
                ]);
                try {
                    Mail::raw("{$title}\n\n{$message}", fn ($m) => $m->to($owner->email)->subject($title));
                } catch (\Throwable $e) {
                    report($e);
                }
            });

        return true;
    }
}
