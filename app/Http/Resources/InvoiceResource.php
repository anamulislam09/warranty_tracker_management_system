<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/** A bill. One bill covers one period of the customer's payment schedule. */
class InvoiceResource extends JsonResource
{
    public function toArray($request): array
    {
        $i = $this->resource;

        return [
            'id'               => $i->id,
            'invoice_no'       => $i->invoice_no,
            'status'           => $i->display_status,           // unpaid | partial | paid | overdue | cancelled
            'status_text'      => $i->status_text,
            'period_label'     => $i->period_label,
            'billing_month'    => $i->billing_month->toDateString(),
            'period_end'       => optional($i->period_end)->toDateString(),
            'plan_name'        => $i->plan_name,
            'schedule_name'    => $i->cycle_label,
            'months'           => (int) $i->months,
            'base_amount'      => (float) $i->base_amount,
            'discount_percent' => (float) $i->discount_percent,
            'discount_amount'  => (float) $i->discount_amount,
            'amount'           => (float) $i->amount,
            'paid_amount'      => (float) $i->paid_amount,
            'balance'          => round((float) $i->balance, 2),
            'due_date'         => $i->due_date->toDateString(),
            'is_overdue'       => (bool) $i->is_overdue,
            'note'             => $i->note,
            'currency'         => 'BDT',
            'created_at'       => optional($i->created_at)->toIso8601String(),
            'download_url'     => route('api.billing.invoices.download', $i->id),
            'payments'         => $this->whenLoaded('payments', fn () => PaymentResource::collection($i->payments)->resolve($request)),
        ];
    }
}
