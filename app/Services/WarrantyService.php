<?php

namespace App\Services;

use Illuminate\Support\Carbon;

class WarrantyService
{
    /** End date = start + duration - 1 day (spec section 8). */
    public function endDate(string $start, int $duration, string $unit): Carbon
    {
        $start = Carbon::parse($start)->startOfDay();

        $end = match ($unit) {
            'day'   => $start->copy()->addDays($duration),
            'month' => $start->copy()->addMonthsNoOverflow($duration),
            default => $start->copy()->addYearsNoOverflow($duration),
        };

        return $end->subDay();
    }
}
