<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\Product;
use App\Models\Subcategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Subcategories belong to a category. */
class SubcategoryController extends ApiController
{
    private function rules(Request $request, ?Subcategory $sub = null): array
    {
        return [
            'category_id' => 'required|exists:categories,id',
            'name'        => ['required', 'string', 'max:100', Rule::unique('subcategories', 'name')->where('category_id', $request->input('category_id'))->ignore($sub)],
            'description' => 'nullable|string|max:255',
            'sort_order'  => 'nullable|integer|min:0',
            'is_active'   => 'nullable|boolean',
        ];
    }

    private function row(Subcategory $s): array
    {
        return [
            'id'          => $s->id,
            'category'    => $s->category ? ['id' => $s->category->id, 'name' => $s->category->name] : null,
            'name'        => $s->name,
            'description' => $s->description,
            'sort_order'  => (int) $s->sort_order,
            'is_active'   => (bool) $s->is_active,
            'products_count' => (int) ($s->products_count ?? 0),
        ];
    }

    public function index(Request $request)
    {
        $page = Subcategory::with('category')->withCount('products')
            ->when($request->category_id, fn ($q, $id) => $q->where('category_id', $id))
            ->orderBy('category_id')->orderBy('sort_order')->orderBy('name')
            ->paginate($this->perPage($request, 15, 100));

        return $this->ok($page->getCollection()->map(fn ($s) => $this->row($s))->values(), 'OK', $this->pageMeta($page));
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules($request));

        $sub = Subcategory::create([
            'category_id' => $data['category_id'], 'name' => $data['name'], 'description' => $data['description'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0, 'is_active' => $request->has('is_active') ? $request->boolean('is_active') : true,
        ]);

        return $this->ok($this->row($sub->load('category')), 'Subcategory created successfully.');
    }

    public function update(Request $request, int $id)
    {
        $sub = Subcategory::find($id);
        if (! $sub) {
            return $this->fail('Subcategory not found.', 'not_found');
        }
        $data = $request->validate($this->rules($request, $sub));

        $sub->update([
            'category_id' => $data['category_id'], 'name' => $data['name'], 'description' => $data['description'] ?? null,
            'sort_order' => $data['sort_order'] ?? $sub->sort_order,
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : $sub->is_active,
        ]);

        return $this->ok($this->row($sub->load('category')), 'Subcategory updated successfully.');
    }

    public function destroy(int $id)
    {
        $sub = Subcategory::find($id);
        if (! $sub) {
            return $this->fail('Subcategory not found.', 'not_found');
        }
        if (Product::withTrashed()->withoutGlobalScopes()->where('subcategory_id', $sub->id)->exists()) {
            return $this->fail('This subcategory is used by products, so it cannot be deleted. Set it inactive instead.', 'in_use');
        }
        $sub->delete();

        return $this->ok(null, 'Subcategory deleted successfully.');
    }
}
