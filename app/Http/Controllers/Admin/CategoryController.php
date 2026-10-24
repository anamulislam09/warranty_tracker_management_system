<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    private function rules(?Category $category = null): array
    {
        return [
            'name'        => ['required', 'string', 'max:100', \Illuminate\Validation\Rule::unique('categories', 'name')->ignore($category)],
            'description' => 'nullable|string|max:255',
            'image'       => 'nullable|image|mimes:jpg,jpeg,png,webp|max:1024',
            'sort_order'  => 'nullable|integer|min:0',
        ];
    }

    private function prepare(Request $request, array $data, ?Category $category = null): array
    {
        if ($request->boolean('remove_image') && $category?->image) {
            File::delete(public_path($category->image));
            $data['image'] = null;
        }
        if ($request->hasFile('image')) {
            if ($category?->image) File::delete(public_path($category->image));
            $file = $request->file('image');
            $name = Str::random(24) . '.' . $file->extension();
            $file->move(public_path('uploads/categories'), $name);
            $data['image'] = 'uploads/categories/' . $name;
        } else {
            unset($data['image']);
            if ($request->boolean('remove_image')) $data['image'] = null;
        }

        return $data + ['is_active' => $request->boolean('is_active'), 'sort_order' => $request->integer('sort_order')];
    }

    public function index()
    {
        return view('admin.categories.index', [
            'categories' => Category::withCount(['subcategories', 'products'])->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.categories.form', ['category' => new Category(['is_active' => true])]);
    }

    public function store(Request $request)
    {
        $data = $this->prepare($request, $request->validate($this->rules()));
        unset($data['remove_image']);
        Category::create($data);

        return redirect()->route('admin.categories.index')->with('success', 'Category created.');
    }

    public function edit(Category $category)
    {
        return view('admin.categories.form', compact('category'));
    }

    public function update(Request $request, Category $category)
    {
        $data = $this->prepare($request, $request->validate($this->rules($category)), $category);
        unset($data['remove_image']);
        $category->update($data);

        return redirect()->route('admin.categories.index')->with('success', 'Category updated.');
    }

    public function destroy(Category $category)
    {
        if ($category->subcategories()->exists()) {
            return back()->with('error', 'Delete or move its subcategories first.');
        }
        if (Product::withTrashed()->withoutGlobalScopes()->where('category_id', $category->id)->exists()) {
            return back()->with('error', 'This category is used by products. Set it inactive instead.');
        }
        if ($category->image) File::delete(public_path($category->image));
        $category->delete();

        return back()->with('success', 'Category deleted.');
    }
}
