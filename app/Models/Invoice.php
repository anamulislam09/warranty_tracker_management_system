<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = [
        'account_id', 'plan_id', 'plan_name', 'invoice_no', 'billing_month', 'amount', 'paid_amount',
        'due_date', 'status', 'note', 'created_by',
    ];

    protected function casts(): array
    {
        return ['billing_month' => 'date', 'due_date' => 'date', 'amount' => 'decimal:2', 'paid_amount' => 'decimal:2'];
    }

    public function account() { return $this->belongsTo(Account::class); }
    public function plan() { return $this->belongsTo(Plan::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function payments() { return $this->hasMany(Payment::class)->orderByDesc('paid_on')->orderByDesc('id'); }

    public function getBalanceAttribute(): float
    {
        return max(0, (float) $this->amount - (float) $this->paid_amount);
    }

    public function getMonthLabelAttribute(): string
    {
        return $this->billing_month->format('F Y');
    }

    public function getIsOverdueAttribute(): bool
    {
        return in_array($this->status, ['unpaid', 'partial'], true) && $this->due_date->lt(today());
    }

    /** unpaid | partial | paid | cancelled | overdue */
    public function getDisplayStatusAttribute(): string
    {
        return $this->is_overdue ? 'overdue' : $this->status;
    }

    public function scopeOpen($q)
    {
        return $q->whereIn('status', ['unpaid', 'partial']);
    }

    public function scopeOverdue($q)
    {
        return $q->whereIn('status', ['unpaid', 'partial'])->where('due_date', '<', today()->toDateString());
    }

    public static function money($value): string
    {
        return config('billing.symbol') . number_format((float) $value, 2);
    }
}
