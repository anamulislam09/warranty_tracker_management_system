<?php

namespace App\Http\Resources;

use App\Models\Warranty;
use Illuminate\Http\Resources\Json\JsonResource;

/** A product or document in a LIST (short). The full record is ProductResource. */
class ProductSummaryResource extends JsonResource
{
    /** The warranty that matters most: the first one that is still running, else the one that ends last. */
    public static function mainWarranty($product): ?Warranty
    {
        $w = $product->warranties;
        if ($w->isEmpty()) return null;

        $live = $w->filter(fn ($x) => in_array($x->status, ['active', 'expiring']));
        if ($live->isNotEmpty()) return $live->sortBy('end_date')->first();

        $upcoming = $w->filter(fn ($x) => $x->status === 'upcoming');
        return $upcoming->isNotEmpty() ? $upcoming->sortBy('end_date')->first() : $w->sortByDesc('end_date')->first();
    }

    public function toArray($request): array
    {
        $p = $this->resource;
        $main = self::mainWarranty($p);

        return [
            'id'            => $p->id,
            'item_type'     => $p->item_type,                  // product | document
            'name'          => $p->name,
            'status'        => $p->status,                     // upcoming | active | expiring | expired | inactive | none
            'is_active'     => $p->is_active,
            'brand'         => $p->brand,
            'model'         => $p->model,
            'document_type' => $p->document_type,
            'category'      => $p->category ? ['id' => $p->category->id, 'name' => $p->category->name] : null,
            'subcategory'   => $p->subcategory ? ['id' => $p->subcategory->id, 'name' => $p->subcategory->name] : null,
            'purchase_date' => optional($p->purchase_date)->toDateString(),
            'next_expiry_date' => $main ? $main->end_date->toDateString() : null,
            'days_remaining'   => $main ? $main->days_remaining : null,
            'warranties_count' => $p->warranties->count(),
            'files_count'      => $p->documents_count ?? ($p->relationLoaded('documents') ? $p->documents->count() : null),
        ];
    }
}
