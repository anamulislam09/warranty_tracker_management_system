<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** How often a customer pays: Monthly (1), Quarterly (3), Half-yearly (6), Yearly (12) ... with an optional discount. */
class PaymentSchedule extends Model
{
    protected $fillable = ['name', 'months', 'discount_percent', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['discount_percent' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function accounts() { return $this->hasMany(Account::class); }

    /** "Yearly - every 12 months - 3% discount" for dropdowns */
    public function getLabelAttribute(): string
    {
        $text = $this->name . ' - every ' . $this->months . ' ' . ($this->months == 1 ? 'month' : 'months');
        if ((float) $this->discount_percent > 0) {
            $text .= ' - ' . (float) $this->discount_percent . '% discount';
        }
        return $text;
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true)->orderBy('sort_order')->orderBy('months');
    }
}
