<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(private ProductService $service) {}

    private function categories()
    {
        return Category::where('is_active', true)
            ->with(['subcategories' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('sort_order')->orderBy('name')->get();
    }

    public function index(Request $request)
    {
        $perPage = in_array((int) $request->per_page, [10, 25, 50, 100], true) ? (int) $request->per_page : 10;

        $products = Product::with(['category', 'subcategory', 'warranties'])
            // a renewed document is represented by its FINAL version only
            ->where(fn ($q) => $q->where('item_type', 'product')->orWhereDoesntHave('renewedTo'))
            ->search($request->q)
            ->when($request->type, fn ($q, $t) => $q->where('item_type', $t))
            ->when($request->category_id, fn ($q, $id) => $q->where('category_id', $id))
            ->status($request->status)
            ->latest()
            ->paginate($perPage)->withQueryString();

        $this->attachRenewalHistory($products->getCollection());   // only the rows of THIS page

        return view('products.index', [
            'products'   => $products,
            'categories' => Category::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    /**
     * Renewal history for the renewed documents on the current page.
     * One query per renewal LEVEL for the whole page (usually 1-3 queries),
     * not one query per document.
     */
    private function attachRenewalHistory($items): void
    {
        $docs = $items->filter(fn ($p) => $p->isDocument() && $p->renewed_from_id);
        if ($docs->isEmpty()) return;

        $ancestors = [];                                           // id => Product
        $pending   = $docs->pluck('renewed_from_id')->unique()->all();

        while ($pending) {
            $found   = Product::with('warranties')->whereIn('id', $pending)->get();
            $pending = [];
            foreach ($found as $a) {
                $ancestors[$a->id] = $a;
                if ($a->renewed_from_id && ! isset($ancestors[$a->renewed_from_id])) {
                    $pending[] = $a->renewed_from_id;
                }
            }
            $pending = array_values(array_unique($pending));
        }

        foreach ($docs as $doc) {
            $chain = collect();
            $cur   = $doc;
            while ($cur->renewed_from_id && isset($ancestors[$cur->renewed_from_id])) {
                $prev = $ancestors[$cur->renewed_from_id];
                $chain->prepend($prev);                            // oldest first
                $cur = $prev;
            }
            $doc->setRelation('history', $chain);
        }
    }

    public function create(Request $request)
    {
        $type = $request->query('type') === 'document' ? 'document' : 'product';

        return view('products.form', [
            'product'    => new Product(['item_type' => $type, 'warranty_mode' => 'single', 'currency' => 'BDT', 'purchase_date' => today()]),
            'categories' => $this->categories(),
        ]);
    }

    public function store(ProductRequest $request)
    {
        if ($request->user()->account->productLimitReached()) {
            return back()->withInput()->with('error', 'Your plan limit is reached. Please upgrade your plan.');
        }

        $product = $this->service->save($request->validated());

        return redirect()->route('products.show', $product)->with('success', 'Saved. You can now upload files.');
    }

    public function show(Product $product)
    {
        $product->load(['category', 'subcategory', 'warranties', 'documents', 'creator', 'renewedFrom', 'renewedTo']);
        return view('products.show', compact('product'));
    }

    public function edit(Product $product)
    {
        $product->load('warranties');
        return view('products.form', ['product' => $product, 'categories' => $this->categories()]);
    }

    public function update(ProductRequest $request, Product $product)
    {
        $data = $request->validated();
        $data['item_type'] = $product->item_type;          // the type can never change after creation

        $this->service->save($data, $product);

        return redirect()->route('products.show', $product)->with('success', 'Updated.');
    }

    public function destroy(Product $product)
    {
        $product->delete(); // soft delete
        return redirect()->route('products.index')->with('success', 'Deleted.');
    }

    // ---------- document renewal ----------
    public function renewForm(Product $product)
    {
        abort_unless($product->isDocument() && $product->is_active, 404);
        $product->load('warranties');
        return view('products.renew', compact('product'));
    }

    public function renew(Request $request, Product $product)
    {
        abort_unless($product->isDocument() && $product->is_active, 404);

        $data = $request->validate([
            'start_date'    => 'required|date',
            'duration'      => 'required|integer|min:1|max:1200',
            'duration_unit' => 'required|in:day,month,year',
            'override_end'  => 'nullable|boolean',
            'end_date'      => 'nullable|date',
            'serial_number' => 'nullable|string|max:100',
            'issued_by'     => 'nullable|string|max:150',
            'reminder_days' => 'nullable|array',
            'reminder_days.*' => 'integer|in:90,60,30,14,7,3,1,0',
        ]);

        $new = $this->service->renew($product, $data);

        return redirect()->route('products.show', $new)
            ->with('success', 'Document renewed. The previous one is now inactive.');
    }
}
