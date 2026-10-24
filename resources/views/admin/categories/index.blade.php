@extends('layouts.app')
@section('title', 'Categories')
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div><h3 class="page-title mb-1">Categories</h3><p class="text-muted mb-0">Categories (with image) used by all customers. Add subcategories under each category.</p></div>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.subcategories.index') }}" class="btn btn-outline-primary">Subcategories</a>
        <a href="{{ route('admin.categories.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Add Category</a>
    </div>
</div>
<div class="card"><div class="table-responsive">
    <table class="table table-hover mb-0">
        <thead><tr><th>#</th><th>Image</th><th>Category</th><th>Subcategories</th><th>Products</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        @forelse ($categories as $c)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>@if ($c->image_url)<img src="{{ $c->image_url }}" alt="" class="rounded" style="width:44px;height:44px;object-fit:cover">@else<span class="cat-icon"><i class="bi bi-image"></i></span>@endif</td>
                <td><strong>{{ $c->name }}</strong><br><small class="text-muted">{{ $c->description }}</small></td>
                <td><a href="{{ route('admin.subcategories.index', ['category_id' => $c->id]) }}" class="text-decoration-none">{{ $c->subcategories_count }}</a></td>
                <td>{{ $c->products_count }}</td>
                <td><x-status-badge :status="$c->is_active ? 'active' : 'inactive'" /></td>
                <td class="text-end text-nowrap">
                    <a href="{{ route('admin.subcategories.create', ['category_id' => $c->id]) }}" class="btn btn-sm btn-outline-primary" title="Add subcategory"><i class="bi bi-plus"></i> Sub</a>
                    <a href="{{ route('admin.categories.edit', $c) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                    <form method="POST" action="{{ route('admin.categories.destroy', $c) }}" class="d-inline" data-confirm="Delete this category?">@csrf @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="text-center text-muted py-4">No categories yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div></div>
@endsection
