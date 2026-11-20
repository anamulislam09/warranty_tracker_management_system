<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'           => $this->id,
            'invoice_id'   => $this->invoice_id,
            'invoice_no'   => $this->whenLoaded('invoice', fn () => $this->invoice->invoice_no),
            'period_label' => $this->whenLoaded('invoice', fn () => $this->invoice->period_label),
            'amount'       => (float) $this->amount,
            'paid_on'      => $this->paid_on->toDateString(),
            'method'       => $this->method,
            'method_label' => config('billing.methods')[$this->method] ?? $this->method,
            'reference'    => $this->reference,
            'note'         => $this->note,
            'is_online'    => $this->isOnline(),
        ];
    }
}
