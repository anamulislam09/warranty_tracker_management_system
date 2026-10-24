<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductRequest;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(private ProductService $service) {}

    public function index(Request $request)
    {
        return Product::with(['category', 'warranties'])
            ->search($request->q)->status($request->status)
            ->when($request->category_id, fn ($q, $id) => $q->where('category_id', $id))
            ->latest()->paginate(20);
    }

    public function show(Product $product)
    {
        return $product->load(['category', 'warranties', 'documents']);
    }

    public function store(ProductRequest $request)
    {
        if ($request->user()->account->productLimitReached()) {
            return response()->json(['message' => 'Plan product limit reached.'], 422);
        }
        return response()->json($this->service->save($request->validated()), 201);
    }

    public function update(ProductRequest $request, Product $product)
    {
        $data = $request->validated();
        $data['item_type'] = $product->item_type;
        return $this->service->save($data, $product);
    }

    public function destroy(Product $product)
    {
        $product->delete();
        return response()->json(['message' => 'Deleted.']);
    }
}
