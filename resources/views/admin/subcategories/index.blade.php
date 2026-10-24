@extends('layouts.app')
@section('title', 'Subcategories')
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div><h3 class="page-title mb-1">Subcategories</h3><p class="text-muted mb-0">Each subcategory belongs to one category.</p></div>
    <a href="{{ route('admin.subcategories.create', ['category_id' => request('category_id')]) }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Add Subcategory</a>
</div>
<div class="card mb-3"><div class="card-body">
    <form method="GET" class="row g-2">
        <div class="col-8 col-md-4"><select name="category_id" class="form-select"><option value="">All Categories</option>@foreach ($categories as $c)<option value="{{ $c->id }}" @selected(request('category_id') == $c->id)>{{ $c->name }}</option>@endforeach</select></div>
        <div class="col-4 col-md-2"><button class="btn btn-primary w-100">Filter</button></div>
    </form>
</div></div>
<div class="card"><div class="table-responsive">
    <table class="table table-hover mb-0">
        <thead><tr><th>Subcategory</th><th>Category</th><th>Products</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        @forelse ($subs as $s)
            <tr>
                <td><strong>{{ $s->name }}</strong><br><small class="text-muted">{{ $s->description }}</small></td>
                <td>{{ $s->category->name }}</td>
                <td>{{ $s->products_count }}</td>
                <td><x-status-badge :status="$s->is_active ? 'active' : 'inactive'" /></td>
                <td class="text-end text-nowrap">
                    <a href="{{ route('admin.subcategories.edit', $s) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                    <form method="POST" action="{{ route('admin.subcategories.destroy', $s) }}" class="d-inline" data-confirm="Delete this subcategory?">@csrf @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="text-center text-muted py-4">No subcategories yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div></div>
@endsection
