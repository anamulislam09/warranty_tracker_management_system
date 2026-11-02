<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = [
        'account_id', 'plan_id', 'plan_name', 'invoice_no', 'billing_month', 'period_end', 'cycle', 'months',
        'base_amount', 'discount_percent', 'discount_amount', 'amount', 'paid_amount',
        'due_date', 'status', 'note', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'billing_month' => 'date', 'period_end' => 'date', 'due_date' => 'date',
            'base_amount' => 'decimal:2', 'discount_percent' => 'decimal:2', 'discount_amount' => 'decimal:2',
            'amount' => 'decimal:2', 'paid_amount' => 'decimal:2',
        ];
    }

    public function account() { return $this->belongsTo(Account::class); }
    public function plan() { return $this->belongsTo(Plan::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function allocations() { return $this->hasMany(PaymentAllocation::class)->orderBy('id'); }

    public function getBalanceAttribute(): float
    {
        return max(0, (float) $this->amount - (float) $this->paid_amount);
    }

    /** "November 2026" for a monthly bill, "Nov 2026 - Apr 2027" for a longer one. */
    public function getPeriodLabelAttribute(): string
    {
        if ((int) $this->months <= 1 || ! $this->period_end) {
            return $this->billing_month->format('F Y');
        }
        return $this->billing_month->format('M Y') . ' - ' . $this->period_end->format('M Y');
    }

    public function getMonthLabelAttribute(): string
    {
        return $this->period_label;
    }

    public function getCycleLabelAttribute(): string
    {
        return config("billing.cycles.{$this->cycle}.label", ucfirst((string) $this->cycle));
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

    /** Unpaid and the due date has passed. */
    public function scopeOverdue($q)
    {
        return $q->whereIn('status', ['unpaid', 'partial'])->where('due_date', '<', today()->toDateString());
    }

    /** Unpaid for MORE than the grace time after the due date -> the account is suspended. */
    public function scopeSuspendable($q)
    {
        $limit = today()->subMonthsNoOverflow((int) config('billing.grace_months'))->toDateString();

        return $q->whereIn('status', ['unpaid', 'partial'])->where('due_date', '<', $limit);
    }

    public static function money($value): string
    {
        return config('billing.symbol') . number_format((float) $value, 2);
    }
}
