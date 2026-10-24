<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;

class Warranty extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'account_id', 'product_id', 'component', 'warranty_type', 'start_date', 'duration', 'duration_unit',
        'end_date', 'end_date_source', 'provider', 'terms', 'reminder_days',
    ];

    public const VALIDITY = 'Validity';   // component name used for a document's validity period

    protected $appends = ['status', 'days_remaining'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'reminder_days' => 'array'];
    }

    public function product() { return $this->belongsTo(Product::class); }

    public function isValidity(): bool { return $this->component === self::VALIDITY; }

    /** Valid now and not about to expire. Documents ignore the start date (a renewed document is already valid). */
    public function scopeCurrentlyValid($q)
    {
        $today = today()->toDateString();
        $soon  = today()->addDays(config('warranty.expiring_days'))->toDateString();

        return $q->where('end_date', '>', $soon)
                 ->where(fn ($q) => $q->where('start_date', '<=', $today)->orWhere('component', self::VALIDITY));
    }
    public function account() { return $this->belongsTo(Account::class); }

    public function getDaysRemainingAttribute(): int
    {
        return max(0, (int) today()->diffInDays($this->end_date, false));
    }

    /** upcoming | active | expiring | expired  (calculated, never stored) */
    public function getStatusAttribute(): string
    {
        $today = today();
        if ($today->lt($this->start_date) && ! $this->isValidity()) return 'upcoming';
        if ($today->gt($this->end_date)) return 'expired';
        return $this->days_remaining <= config('warranty.expiring_days') ? 'expiring' : 'active';
    }

    public function getProgressAttribute(): int
    {
        $total   = max(1, $this->start_date->diffInDays($this->end_date) + 1);
        $elapsed = $this->start_date->diffInDays(today(), false) + 1;
        return (int) max(0, min(100, round($elapsed / $total * 100)));
    }
}
