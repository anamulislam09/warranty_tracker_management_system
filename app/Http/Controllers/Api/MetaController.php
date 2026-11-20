<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Models\PaymentSchedule;

/** Lists the app needs for its dropdowns. Nothing here is secret. */
class MetaController extends ApiController
{
    private function list(array $map): array
    {
        return collect($map)->map(fn ($name, $code) => ['code' => $code, 'name' => $name])->values()->all();
    }

    /** For the registration screen - no login needed. */
    public function registration()
    {
        return $this->ok([
            'countries'    => $this->list(config('warranty.countries')),
            'languages'    => $this->list(config('warranty.languages')),
            'account_types' => [
                ['code' => 'personal', 'name' => 'Personal'], ['code' => 'family', 'name' => 'Family'], ['code' => 'business', 'name' => 'Business'],
            ],
            'payment_schedules' => PaymentSchedule::active()->get()->map(fn ($s) => [
                'id' => $s->id, 'name' => $s->name, 'months' => (int) $s->months,
                'discount_percent' => (float) $s->discount_percent, 'label' => $s->label,
            ])->values(),
        ]);
    }

    /** Fixed values for the forms (warranty, document, file upload ...). */
    public function app()
    {
        return $this->ok([
            'item_types'      => [['code' => 'product', 'name' => 'Product'], ['code' => 'document', 'name' => 'Document']],
            'warranty_modes'  => [['code' => 'single', 'name' => 'One warranty for the whole product'], ['code' => 'multiple', 'name' => 'Warranty per component']],
            'warranty_types'  => $this->list(['manufacturer' => 'Manufacturer', 'extended' => 'Extended', 'seller' => 'Seller', 'other' => 'Other']),
            'duration_units'  => $this->list(['day' => 'Day', 'month' => 'Month', 'year' => 'Year']),
            'reminder_options' => config('warranty.reminder_options'),          // days before the end date
            'default_reminders' => config('warranty.default_reminders'),
            'expiring_days'   => config('warranty.expiring_days'),             // "expiring" = this many days or less left
            'product_statuses' => ['active', 'expiring', 'expired', 'upcoming', 'inactive'],
            'document_types'  => config('warranty.document_types'),
            'file_types'      => $this->list(['invoice' => 'Invoice', 'warranty_card' => 'Warranty card', 'receipt' => 'Receipt', 'other' => 'Other']),
            'file_rules'      => ['max_size_kb' => 5120, 'extensions' => ['pdf', 'jpg', 'jpeg', 'png', 'webp']],
            'currencies'      => config('warranty.currencies'),
            'countries'       => $this->list(config('warranty.countries')),
            'timezones'       => $this->list(config('warranty.timezones')),
            'languages'       => $this->list(config('warranty.languages')),
            'payment_methods' => $this->list(config('billing.methods')),
            'date_format'     => 'Y-m-d',                                       // every date in the API
        ]);
    }

    /** Categories with their subcategories, for the product form. */
    public function categories()
    {
        $categories = Category::with('subcategories')->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();

        return $this->ok(CategoryResource::collection($categories)->resolve(request()));
    }
}
