<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    protected $fillable = [
        'name', 'email', 'phone', 'country_code', 'account_type', 'status', 'suspended_reason', 'plan_id',
        'billing_cycle', 'plan_ends_at', 'billing_starts_on', 'approved_at', 'notes',
    ];

    protected function casts(): array
    {
        return ['plan_ends_at' => 'date', 'billing_starts_on' => 'date', 'approved_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::saving(function (Account $account) {
            // billing starts from the 1st of the month AFTER the customer becomes active
            if ($account->status === 'active' && ! $account->billing_starts_on) {
                $account->billing_starts_on = today()->addMonthNoOverflow()->startOfMonth();
            }
            // a manual status change (by the super admin) clears the "overdue bill" reason
            if ($account->isDirty('status') && ! $account->isDirty('suspended_reason')) {
                $account->suspended_reason = null;
            }
        });
    }

    public function plan() { return $this->belongsTo(Plan::class); }
    public function users() { return $this->hasMany(User::class); }
    public function owner() { return $this->hasOne(User::class)->where('role', 'owner'); }
    public function invoices() { return $this->hasMany(Invoice::class); }

    /** At least one bill is unpaid after its due date -> the customer can only use the Billing page. */
    public function isPaymentOverdue(): bool
    {
        return $this->invoices()->overdue()->exists();
    }

    /** Money received but not used for any bill yet (paid in advance). */
    public function advanceBalance(): float
    {
        $received = (float) Payment::where('account_id', $this->id)->sum('amount');
        $used     = (float) PaymentAllocation::where('account_id', $this->id)->sum('amount');

        return max(0, round($received - $used, 2));
    }

    public function cycleLabel(): string
    {
        return config("billing.cycles.{$this->billing_cycle}.label", ucfirst((string) $this->billing_cycle));
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
