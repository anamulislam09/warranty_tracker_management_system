<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\ProductRequest;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ProductSummaryResource;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Http\Request;

/** Products and documents with their warranties. The saving rules are the website's (ProductService). */
class ProductController extends ApiController
{
    public function __construct(private ProductService $service) {}

    private function full(Product $product): array
    {
        $product->load(['category', 'subcategory', 'warranties', 'documents', 'creator', 'renewedTo']);

        $data = $this->one(ProductResource::class, $product);
        $data['renewal_history'] = $product->isDocument()
            ? ProductSummaryResource::collection($product->renewalHistory())->resolve(request())
            : [];

        return $data;
    }

    public function index(Request $request)
    {
        $products = Product::with(['category', 'subcategory', 'warranties'])->withCount('documents')
            // a renewed document is represented by its FINAL version only
            ->where(fn ($q) => $q->where('item_type', 'product')->orWhereDoesntHave('renewedTo'))
            ->search($request->q)
            ->when(in_array($request->item_type, ['product', 'document'], true), fn ($q) => $q->where('item_type', $request->item_type))
            ->when($request->category_id, fn ($q, $id) => $q->where('category_id', $id))
            ->when($request->subcategory_id, fn ($q, $id) => $q->where('subcategory_id', $id))
            ->status($request->status)
            ->when($request->from, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->to, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->latest()->paginate($this->perPage($request, 20, 100));

        return $this->paginated($products, ProductSummaryResource::class);
    }

    public function show(Product $product)
    {
        return $this->ok($this->full($product));
    }

    public function store(ProductRequest $request)
    {
        if ($request->user()->account->productLimitReached()) {
            return $this->fail('Your plan limit is reached. Please upgrade your plan.', 'plan_limit_reached');
        }

        $product = $this->service->save($request->validated());

        return $this->created($this->full($product), 'Saved. You can now upload files.');
    }

    public function update(ProductRequest $request, Product $product)
    {
        $data = $request->validated();
        $data['item_type'] = $product->item_type;                   // the type can never change after creation

        $this->service->save($data, $product);

        return $this->ok($this->full($product->fresh()), 'Updated.');
    }

    public function destroy(Product $product)
    {
        $product->delete();                                          // soft delete

        return $this->ok(null, 'Deleted.');
    }

    /** Renew a document: the old record becomes inactive, a new active record is created. */
    public function renew(Request $request, Product $product)
    {
        if (! $product->isDocument() || ! $product->is_active) {
            return $this->fail('Only an active document can be renewed.', 'not_renewable');
        }

        $data = $request->validate([
            'start_date'      => 'required|date',
            'duration'        => 'required|integer|min:1|max:1200',
            'duration_unit'   => 'required|in:day,month,year',
            'override_end'    => 'nullable|boolean',
            'end_date'        => 'nullable|date',
            'serial_number'   => 'nullable|string|max:100',
            'issued_by'       => 'nullable|string|max:150',
            'reminder_days'   => 'nullable|array',
            'reminder_days.*' => 'integer|in:90,60,30,14,7,3,1,0',
        ]);

        $new = $this->service->renew($product, $data);

        return $this->created($this->full($new), 'Document renewed. The previous one is now inactive.');
    }
}
