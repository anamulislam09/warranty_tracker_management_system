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

    /** Text shown to the customer when he tries to login while the account is not active. */
    public function inactiveMessage(): string
    {
        if ($this->status === 'pending') {
            return 'Your account is waiting for admin approval.';
        }

        if ($this->suspended_reason === 'overdue_bill') {
            $invoice = $this->invoices()->overdue()->orderBy('due_date')->first();
            $month   = $invoice ? $invoice->month_label : 'a previous month';

            return "Your account is suspended because the bill for {$month} was not paid by the due date. "
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
