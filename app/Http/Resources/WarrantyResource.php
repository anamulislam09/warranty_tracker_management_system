<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/** One warranty of a product (a component), or the validity period of a document. */
class WarrantyResource extends JsonResource
{
    /** 0 before the start, 100 after the end, otherwise the share of the days that have passed. */
    private function progressPercent(): int
    {
        $total = max(1, (int) $this->start_date->diffInDays($this->end_date, false));
        $done  = (int) $this->start_date->diffInDays(today(), false);

        return (int) max(0, min(100, round($done / $total * 100)));
    }

    public function toArray($request): array
    {
        return [
            'id'              => $this->id,
            'component'       => $this->component,
            'warranty_type'   => $this->warranty_type,         // manufacturer | extended | seller | other
            'start_date'      => $this->start_date->toDateString(),
            'duration'        => (int) $this->duration,
            'duration_unit'   => $this->duration_unit,         // day | month | year
            'end_date'        => $this->end_date->toDateString(),
            'end_date_source' => $this->end_date_source,       // calculated | manual
            'provider'        => $this->provider,
            'terms'           => $this->terms,
            'reminder_days'   => $this->reminder_days ?? [],
            'status'          => $this->status,                // upcoming | active | expiring | expired
            'days_remaining'  => $this->days_remaining,
            'progress'        => $this->progressPercent(),     // 0-100, how much of the warranty time has passed
        ];
    }
}
