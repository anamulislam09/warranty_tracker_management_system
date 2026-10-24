@extends('layouts.app')
@section('title', $subcategory->exists ? 'Edit Subcategory' : 'Add Subcategory')
@section('content')
<h3 class="page-title mb-3">{{ $subcategory->exists ? 'Edit Subcategory' : 'Add Subcategory' }}</h3>
<div class="card" style="max-width:640px"><div class="card-body">
    <form method="POST" action="{{ $subcategory->exists ? route('admin.subcategories.update', $subcategory) : route('admin.subcategories.store') }}">
        @csrf @if ($subcategory->exists) @method('PUT') @endif
        <div class="mb-3"><label class="form-label">Category *</label>
            <select name="category_id" class="form-select" required>
                <option value="">Select a category</option>
                @foreach ($categories as $c)<option value="{{ $c->id }}" @selected(old('category_id', $subcategory->category_id) == $c->id)>{{ $c->name }}</option>@endforeach
            </select></div>
        <div class="mb-3"><label class="form-label">Subcategory Name *</label><input name="name" class="form-control" value="{{ old('name', $subcategory->name) }}" required></div>
        <div class="mb-3"><label class="form-label">Description</label><textarea name="description" rows="2" maxlength="255" class="form-control">{{ old('description', $subcategory->description) }}</textarea></div>
        <div class="row g-3 mb-3"><div class="col-6"><label class="form-label">Sort order</label><input type="number" min="0" name="sort_order" class="form-control" value="{{ old('sort_order', $subcategory->sort_order ?? 0) }}"></div></div>
        <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="is_active" @checked(old('is_active', $subcategory->is_active))><label class="form-check-label" for="is_active">Active</label></div>
        <div class="d-flex gap-2"><button class="btn btn-primary"><i class="bi bi-check-lg"></i> Save Subcategory</button><a href="{{ route('admin.subcategories.index') }}" class="btn btn-outline-secondary">Cancel</a></div>
    </form>
</div></div>
@endsection
