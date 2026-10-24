<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * All product / document + warranty saving logic lives here, so the web controllers
 * and the future Android API controllers use exactly the same code.
 */
class ProductService
{
    public function __construct(private WarrantyService $warranties) {}

    /** Apply the business rules for item type and single / multiple warranty. */
    private function normalize(array $data, array $rows): array
    {
        $rows = array_values($rows);
        $type = $data['item_type'] ?? 'product';

        if ($type === 'document') {
            // documents: no category, no purchase info, exactly one validity period
            foreach (['category_id', 'subcategory_id', 'brand', 'model', 'purchase_date', 'purchase_price',
                      'seller_name', 'seller_phone', 'invoice_number', 'purchase_location'] as $k) {
                $data[$k] = null;
            }
            $data['warranty_mode'] = 'single';
            $rows = [array_merge($rows[0], ['component' => 'Validity', 'warranty_type' => 'other'])];
        } else {
            $data['document_type'] = null;
            $data['issued_by'] = null;
            if (($data['warranty_mode'] ?? 'single') === 'single') {
                // single: the warranty belongs to the product itself
                $rows = [array_merge($rows[0], ['component' => 'Whole Product'])];
            }
            // multiple: every row is the warranty of one component (e.g. Compressor)
        }

        $data['currency'] = $data['currency'] ?? 'BDT';

        return [$data, $rows];
    }

    private function payload(array $row, Product $product): array
    {
        $calculated = $this->warranties->endDate($row['start_date'], (int) $row['duration'], $row['duration_unit']);
        $manual     = ! empty($row['override_end']) && ! empty($row['end_date']);

        return [
            'account_id'      => $product->account_id,
            'component'       => $row['component'] ?? 'Whole Product',
            'warranty_type'   => $row['warranty_type'] ?? 'manufacturer',
            'start_date'      => $row['start_date'],
            'duration'        => (int) $row['duration'],
            'duration_unit'   => $row['duration_unit'],
            'end_date'        => $manual ? $row['end_date'] : $calculated->toDateString(),
            'end_date_source' => $manual ? 'manual' : 'calculated',
            'provider'        => $row['provider'] ?? null,
            'terms'           => $row['terms'] ?? null,
            'reminder_days'   => array_map('intval', $row['reminder_days'] ?? []),
        ];
    }

    public function save(array $data, ?Product $product = null): Product
    {
        return DB::transaction(function () use ($data, $product) {
            $rows = $data['warranties'];
            unset($data['warranties']);
            [$data, $rows] = $this->normalize($data, $rows);

            if ($product) {
                $product->update($data);
            } else {
                $product = Product::create($data + ['user_id' => auth()->id()]);
            }

            $keep = [];
            foreach ($rows as $row) {
                $payload  = $this->payload($row, $product);
                $warranty = ! empty($row['id']) ? $product->warranties()->find($row['id']) : null;

                if ($warranty) {
                    $warranty->update($payload);
                } else {
                    $warranty = $product->warranties()->create($payload);
                }
                $keep[] = $warranty->id;
            }

            $product->warranties()->whereNotIn('id', $keep)->delete();

            return $product->load('warranties', 'category', 'subcategory');
        });
    }

    /** Renew a document: the old record becomes inactive, a new active record is created. */
    public function renew(Product $old, array $data): Product
    {
        return DB::transaction(function () use ($old, $data) {
            $new = $old->replicate();
            $new->serial_number    = $data['serial_number'] ?? $old->serial_number;
            $new->issued_by        = $data['issued_by'] ?? $old->issued_by;
            $new->is_active        = true;
            $new->renewed_from_id  = $old->id;
            $new->user_id          = auth()->id();
            $new->save();

            $row = [
                'component' => 'Validity', 'warranty_type' => 'other',
                'start_date' => $data['start_date'], 'duration' => $data['duration'], 'duration_unit' => $data['duration_unit'],
                'override_end' => $data['override_end'] ?? false, 'end_date' => $data['end_date'] ?? null,
                'reminder_days' => $data['reminder_days'] ?? config('warranty.default_reminders'),
            ];
            $new->warranties()->create($this->payload($row, $new));

            $old->update(['is_active' => false]);

            return $new->load('warranties');
        });
    }
}
