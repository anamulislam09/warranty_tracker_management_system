<?php

namespace App\Http\Resources;

/** The full record of a product or document, with its warranties and files. */
class ProductResource extends ProductSummaryResource
{
    public function toArray($request): array
    {
        $p = $this->resource;

        return parent::toArray($request) + [
            'warranty_mode'     => $p->warranty_mode,          // single | multiple (products), null for documents
            'issued_by'         => $p->issued_by,
            'serial_number'     => $p->serial_number,
            'description'       => $p->description,
            'purchase_price'    => $p->purchase_price !== null ? (float) $p->purchase_price : null,
            'currency'          => $p->currency,
            'seller_name'       => $p->seller_name,
            'seller_phone'      => $p->seller_phone,
            'invoice_number'    => $p->invoice_number,
            'purchase_location' => $p->purchase_location,
            'notes'             => $p->notes,
            'warranties'        => WarrantyResource::collection($p->warranties)->resolve($request),
            'files'             => FileResource::collection($p->relationLoaded('documents') ? $p->documents : collect())->resolve($request),
            'renewed_from_id'   => $p->renewed_from_id,        // documents: the older record this one replaced
            'renewed_to_id'     => $p->relationLoaded('renewedTo') ? optional($p->renewedTo)->id : null,
            'created_by'        => optional($p->creator)->name,
            'created_at'        => optional($p->created_at)->toIso8601String(),
            'updated_at'        => optional($p->updated_at)->toIso8601String(),
        ];
    }
}
