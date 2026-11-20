<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Product categories. The picture is sent as multipart/form-data (field "image").
 * A multipart request cannot use PUT, so the update is a POST to /admin/categories/{id}.
 */
class CategoryController extends ApiController
{
    private function rules(?Category $category = null): array
    {
        return [
            'name'        => ['required', 'string', 'max:100', Rule::unique('categories', 'name')->ignore($category)],
            'description' => 'nullable|string|max:255',
            'image'       => 'nullable|image|mimes:jpg,jpeg,png,webp|max:1024',
            'remove_image' => 'nullable|boolean',
            'sort_order'  => 'nullable|integer|min:0',
            'is_active'   => 'nullable|boolean',
        ];
    }

    private function row(Category $c): array
    {
        return [
            'id'                  => $c->id,
            'name'                => $c->name,
            'description'         => $c->description,
            'image_url'           => $c->image_url,
            'sort_order'          => (int) $c->sort_order,
            'is_active'           => (bool) $c->is_active,
            'subcategories_count' => (int) ($c->subcategories_count ?? $c->subcategories()->count()),
            'products_count'      => (int) ($c->products_count ?? 0),
        ];
    }

    /** Saves the picture under public/uploads/categories and gives back the path to keep. */
    private function savePicture(Request $request, ?Category $category): ?string
    {
        $path = $category ? $category->image : null;

        if ($request->boolean('remove_image') && $path) {
            File::delete(public_path($path));
            $path = null;
        }
        if ($request->hasFile('image')) {
            if ($path) {
                File::delete(public_path($path));
            }
            $file = $request->file('image');
            $name = Str::random(24) . '.' . $file->extension();
            $file->move(public_path('uploads/categories'), $name);
            $path = 'uploads/categories/' . $name;
        }

        return $path;
    }

    public function index(Request $request)
    {
        $page = Category::withCount(['subcategories', 'products'])->orderBy('sort_order')->orderBy('name')->paginate($this->perPage($request, 10, 100));

        return $this->ok($page->getCollection()->map(fn ($c) => $this->row($c))->values(), 'OK', $this->pageMeta($page));
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());

        $category = Category::create([
            'name' => $data['name'], 'description' => $data['description'] ?? null,
            'image' => $this->savePicture($request, null),
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : true,
        ]);

        return $this->ok($this->row($category), 'Category created successfully.');
    }

    public function update(Request $request, int $id)
    {
        $category = Category::find($id);
        if (! $category) {
            return $this->fail('Category not found.', 'not_found');
        }
        $data = $request->validate($this->rules($category));

        $category->update([
            'name' => $data['name'], 'description' => $data['description'] ?? null,
            'image' => $this->savePicture($request, $category),
            'sort_order' => $data['sort_order'] ?? $category->sort_order,
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : $category->is_active,
        ]);

        return $this->ok($this->row($category->loadCount(['subcategories', 'products'])), 'Category updated successfully.');
    }

    public function destroy(int $id)
    {
        $category = Category::find($id);
        if (! $category) {
            return $this->fail('Category not found.', 'not_found');
        }
        if ($category->subcategories()->exists()) {
            return $this->fail('Delete or move its subcategories first.', 'in_use');
        }
        if (Product::withTrashed()->withoutGlobalScopes()->where('category_id', $category->id)->exists()) {
            return $this->fail('This category is used by products, so it cannot be deleted. Set it inactive instead.', 'in_use');
        }
        if ($category->image) {
            File::delete(public_path($category->image));
        }
        $category->delete();

        return $this->ok(null, 'Category deleted successfully.');
    }
}
