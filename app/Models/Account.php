<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    protected $fillable = [
        'name', 'email', 'phone', 'country_code', 'account_type', 'status', 'suspended_reason', 'plan_id',
        'billing_cycle', 'payment_schedule_id', 'plan_ends_at', 'billing_starts_on', 'bill_current_month', 'approved_at', 'notes',
    ];

    protected function casts(): array
    {
        return ['plan_ends_at' => 'date', 'billing_starts_on' => 'date', 'bill_current_month' => 'boolean', 'approved_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::saving(function (Account $account) {
            // The admin chose (bill_current_month):
            //   yes -> billing starts on the 1st of THIS month (this month is billed too)
            //   no  -> this month is free, billing starts on the 1st of NEXT month
            if ($account->status === 'active' && ! $account->billing_starts_on) {
                $account->billing_starts_on = $account->bill_current_month
                    ? today()->startOfMonth()
                    : today()->addMonthNoOverflow()->startOfMonth();
            }
            // a manual status change (by the super admin) clears the "overdue bill" reason
            if ($account->isDirty('status') && ! $account->isDirty('suspended_reason')) {
                $account->suspended_reason = null;
            }
        });

        // remember every status change (used by the admin dashboard charts)
        static::updated(function (Account $account) {
            if ($account->wasChanged('status')) {
                AccountStatusLog::create([
                    'account_id' => $account->id, 'from_status' => $account->getOriginal('status'),
                    'to_status' => $account->status, 'reason' => $account->suspended_reason,
                ]);
            }
        });
    }

    public function plan() { return $this->belongsTo(Plan::class); }
    public function schedule() { return $this->belongsTo(PaymentSchedule::class, 'payment_schedule_id'); }
    public function users() { return $this->hasMany(User::class); }
    public function owner() { return $this->hasOne(User::class)->where('role', 'owner'); }
    public function invoices() { return $this->hasMany(Invoice::class); }

    /** At least one bill is unpaid after its due date -> the customer can only use the Billing page. */
    public function isPaymentOverdue(): bool
    {
        return $this->invoices()->overdue()->exists();
    }


    /** Name of the payment schedule, e.g. "Yearly" */
    public function cycleLabel(): string
    {
        return optional($this->schedule)->name ?: '-';
    }

    /** Text shown to the customer when he tries to login while the account is not active. */
    public function inactiveMessage(): string
    {
        if ($this->status === 'pending') {
            return 'Your account is waiting for admin approval.';
        }

        if ($this->suspended_reason === 'overdue_bill') {
            $invoice = $this->invoices()->overdue()->orderBy('due_date')->first();
            $period  = $invoice ? $invoice->period_label : 'an earlier period';

            return "Your account is suspended because the bill for {$period} has been unpaid for more than a month after its due date. "
                 . 'Please pay your outstanding bill and contact support to reactivate your account.';
        }

        return 'Your account is inactive. Please contact support.';
    }

    /** active products + documents (inactive = renewed documents do not count) */
    public function productCount(): int
    {
        return Product::withoutGlobalScopes()->where('account_id', $this->id)
            ->where('is_active', true)->whereNull('deleted_at')->count();
    }

    public function storageUsedBytes(): int
    {
        return (int) Document::withoutGlobalScopes()->where('account_id', $this->id)->sum('size');
    }

    // ---- plan limits (always enforced on the server) ----
    public function productLimitReached(): bool
    {
        if (! $this->plan) return true;
        return $this->plan->max_products > 0 && $this->productCount() >= $this->plan->max_products;
    }

    public function userLimitReached(): bool
    {
        if (! $this->plan) return true;
        return $this->users()->count() >= $this->plan->max_users;
    }

    public function storageLimitReached(int $incomingBytes = 0): bool
    {
        if (! $this->plan) return true;
        return ($this->storageUsedBytes() + $incomingBytes) > $this->plan->storage_limit_mb * 1048576;
    }
}
