<?php

namespace App\Http\Requests;

use App\Models\Subcategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // permissions are checked by route middleware
    }

    public function rules(): array
    {
        return [
            'item_type'      => 'required|in:product,document',
            'name'           => 'required|string|max:150',

            // products: category + subcategory. documents: neither.
            'category_id'    => ['nullable', 'required_if:item_type,product', 'exists:categories,id'],
            'subcategory_id' => [
                'nullable',
                Rule::requiredIf(fn () => $this->input('item_type') === 'product'
                    && Subcategory::where('category_id', $this->input('category_id'))->where('is_active', true)->exists()),
                Rule::exists('subcategories', 'id')->where('category_id', $this->input('category_id')),
            ],
            'warranty_mode'  => ['nullable', 'required_if:item_type,product', 'in:single,multiple'],

            // documents only
            'document_type'  => ['nullable', 'required_if:item_type,document', 'string', 'max:100'],
            'issued_by'      => 'nullable|string|max:150',

            'brand'             => 'nullable|string|max:100',
            'model'             => 'nullable|string|max:100',
            'serial_number'     => 'nullable|string|max:100',
            'description'       => 'nullable|string|max:500',
            'purchase_date'     => ['nullable', 'required_if:item_type,product', 'date'],
            'purchase_price'    => 'nullable|numeric|min:0',
            'currency'          => 'nullable|string|size:3',
            'seller_name'       => 'nullable|string|max:150',
            'seller_phone'      => 'nullable|string|max:30',
            'invoice_number'    => 'nullable|string|max:100',
            'purchase_location' => 'nullable|string|max:150',
            'notes'             => 'nullable|string|max:1000',

            'warranties'                   => 'required|array|min:1',
            'warranties.*.id'              => 'nullable|integer',
            'warranties.*.component'       => ['nullable', 'required_if:warranty_mode,multiple', 'string', 'max:100'],
            'warranties.*.warranty_type'   => 'nullable|in:manufacturer,extended,seller,other',
            'warranties.*.start_date'      => 'required|date',
            'warranties.*.duration'        => 'required|integer|min:1|max:1200',
            'warranties.*.duration_unit'   => 'required|in:day,month,year',
            'warranties.*.override_end'    => 'nullable|boolean',
            'warranties.*.end_date'        => 'nullable|date',
            'warranties.*.provider'        => 'nullable|string|max:150',
            'warranties.*.terms'           => 'nullable|string|max:1000',
            'warranties.*.reminder_days'   => 'nullable|array',
            'warranties.*.reminder_days.*' => 'integer|in:90,60,30,14,7,3,1,0',
        ];
    }
}
