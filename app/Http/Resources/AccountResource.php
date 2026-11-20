<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/** The customer (company / family) the user belongs to, with its plan and payment schedule. */
class AccountResource extends JsonResource
{
    public function toArray($request): array
    {
        $a = $this->resource;
        $plan = $a->plan;
        $schedule = $a->schedule;

        return [
            'id'            => $a->id,
            'name'          => $a->name,
            'email'         => $a->email,
            'phone'         => $a->phone,
            'country_code'  => $a->country_code,
            'account_type'  => $a->account_type,
            'status'        => $a->status,
            'plan' => $plan ? [
                'id' => $plan->id, 'name' => $plan->name, 'monthly_price' => (float) $plan->monthly_price,
                'max_products' => (int) $plan->max_products,                   // 0 = unlimited
                'storage_limit_mb' => (int) $plan->storage_limit_mb,           // 0 = unlimited
                'max_users' => (int) $plan->max_users,                         // 0 = unlimited
                'reminder_enabled' => (bool) $plan->reminder_enabled,
                'document_upload_enabled' => (bool) $plan->document_upload_enabled,
            ] : null,
            'payment_schedule' => $schedule ? [
                'id' => $schedule->id, 'name' => $schedule->name, 'months' => (int) $schedule->months,
                'discount_percent' => (float) $schedule->discount_percent,
            ] : null,
            'billing_starts_on'  => optional($a->billing_starts_on)->toDateString(),
            // true = an overdue bill: only Billing, profile and logout work (every other call answers 402)
            'billing_restricted' => $a->isPaymentOverdue(),
        ];
    }
}
