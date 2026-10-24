@extends('layouts.app')
@section('title', $category->exists ? 'Edit Category' : 'Add Category')
@section('content')
<h3 class="page-title mb-3">{{ $category->exists ? 'Edit Category' : 'Add Category' }}</h3>
<div class="card" style="max-width:640px"><div class="card-body">
    <form method="POST" enctype="multipart/form-data" action="{{ $category->exists ? route('admin.categories.update', $category) : route('admin.categories.store') }}">
        @csrf @if ($category->exists) @method('PUT') @endif
        <div class="mb-3"><label class="form-label">Category Name *</label><input name="name" class="form-control" value="{{ old('name', $category->name) }}" required></div>
        <div class="mb-3"><label class="form-label">Description</label><textarea name="description" rows="2" maxlength="255" class="form-control">{{ old('description', $category->description) }}</textarea></div>
        <div class="mb-3">
            <label class="form-label">Category Image</label>
            @if ($category->image_url)
                <div class="mb-2"><img src="{{ $category->image_url }}" alt="" class="rounded border" style="height:80px">
                    <div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="remove_image" value="1" id="remove_image"><label class="form-check-label small" for="remove_image">Remove current image</label></div></div>
            @endif
            <input type="file" name="image" class="form-control" accept=".jpg,.jpeg,.png,.webp">
            <div class="form-text">JPG, PNG or WEBP, max 1 MB. Subcategories do not have images.</div>
        </div>
        <div class="row g-3 mb-3">
            <div class="col-6"><label class="form-label">Sort order</label><input type="number" min="0" name="sort_order" class="form-control" value="{{ old('sort_order', $category->sort_order ?? 0) }}"></div>
        </div>
        <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="is_active" @checked(old('is_active', $category->is_active))><label class="form-check-label" for="is_active">Active</label></div>
        <div class="d-flex gap-2"><button class="btn btn-primary"><i class="bi bi-check-lg"></i> Save Category</button><a href="{{ route('admin.categories.index') }}" class="btn btn-outline-secondary">Cancel</a></div>
    </form>
</div></div>
@endsection
