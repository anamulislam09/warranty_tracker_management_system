<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Subcategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubcategoryController extends Controller
{
    private function rules(?Subcategory $sub = null): array
    {
        return [
            'category_id' => 'required|exists:categories,id',
            'name'        => ['required', 'string', 'max:100',
                Rule::unique('subcategories', 'name')->where('category_id', request('category_id'))->ignore($sub)],
            'description' => 'nullable|string|max:255',
            'sort_order'  => 'nullable|integer|min:0',
        ];
    }

    private function extra(Request $r): array
    {
        return ['is_active' => $r->boolean('is_active'), 'sort_order' => $r->integer('sort_order')];
    }

    public function index(Request $request)
    {
        $subs = Subcategory::with('category')->withCount('products')
            ->when($request->category_id, fn ($q, $id) => $q->where('category_id', $id))
            ->orderBy('category_id')->orderBy('sort_order')->orderBy('name')->get();

        return view('admin.subcategories.index', ['subs' => $subs, 'categories' => Category::orderBy('name')->get()]);
    }

    public function create(Request $request)
    {
        return view('admin.subcategories.form', [
            'subcategory' => new Subcategory(['is_active' => true, 'category_id' => $request->category_id]),
            'categories'  => Category::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        Subcategory::create($request->validate($this->rules()) + $this->extra($request));
        return redirect()->route('admin.subcategories.index')->with('success', 'Subcategory created.');
    }

    public function edit(Subcategory $subcategory)
    {
        return view('admin.subcategories.form', ['subcategory' => $subcategory, 'categories' => Category::orderBy('name')->get()]);
    }

    public function update(Request $request, Subcategory $subcategory)
    {
        $subcategory->update($request->validate($this->rules($subcategory)) + $this->extra($request));
        return redirect()->route('admin.subcategories.index')->with('success', 'Subcategory updated.');
    }

    public function destroy(Subcategory $subcategory)
    {
        if (Product::withTrashed()->withoutGlobalScopes()->where('subcategory_id', $subcategory->id)->exists()) {
            return back()->with('error', 'This subcategory is used by products. Set it inactive instead.');
        }
        $subcategory->delete();
        return back()->with('success', 'Subcategory deleted.');
    }
}
