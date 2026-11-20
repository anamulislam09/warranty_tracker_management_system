<?php

namespace App\Http\Resources;

use App\Services\OnlinePaymentService;
use Illuminate\Http\Resources\Json\JsonResource;

/** One online payment try (SSLCommerz). The app follows it with its unique_id. */
class OnlinePaymentResource extends JsonResource
{
    public function toArray($request): array
    {
        $a = $this->resource;

        return [
            'unique_id'         => $a->unique_id,
            'tran_id'           => $a->tran_id,
            'invoice_id'        => $a->invoice_id,
            'amount'            => (float) $a->amount,
            'currency'          => $a->currency,
            // initiated | pending | success | failed | cancelled | expired | needs_review
            'status'            => $a->status,
            'is_final'          => in_array($a->status, ['success', 'failed', 'cancelled', 'expired', 'needs_review'], true),
            'message'           => app(OnlinePaymentService::class)->messageFor($a),
            'gateway'           => $a->gateway,
            'gateway_reference' => $a->gateway_reference,
            'card_type'         => $a->card_type,
            'card_no'           => $a->card_no,
            'created_at'        => optional($a->created_at)->toIso8601String(),
            'verified_at'       => optional($a->verified_at)->toIso8601String(),
        ];
    }
}
