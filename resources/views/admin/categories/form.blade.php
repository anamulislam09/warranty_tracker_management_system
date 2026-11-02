@extends('layouts.app')
@section('title', $category->exists ? 'Edit Category' : 'Add Category')
@section('content')
    <div class="row">
        <div class="col-11 mx-auto">
            <div class="d-flex justify-content-between align-items-center">
                <h3 class="page-title">{{ $category->exists ? 'Edit Category' : 'Add Category' }}</h3>
                <button class="btn btn-warning align-self-start align-self-sm-auto" onclick="window.history.back()"><i class="bi bi-arrow-left"></i>
                    Back</button>
            </div>

            <div class="card">
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data"
                        action="{{ $category->exists ? route('admin.categories.update', $category) : route('admin.categories.store') }}">
                        @csrf @if ($category->exists)
                            @method('PUT')
                        @endif
                        <div class="row mb-3">
                            <div class="col-lg-6 col-md-6 col-sm-12">
                                <label class="form-label">Category Name *</label>
                                <input name="name" placeholder="Category Name" class="form-control" value="{{ old('name', $category->name) }}"
                                    required>
                            </div>
                            <div class="col-lg-6 col-md-6 col-sm-12">
                                <label class="form-label">Description</label>
                                <input type="text" placeholder="Description" class="form-control mt-1"
                                    value="{{ old('description', $category->description) }}" readonly>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-lg-6 col-md-6 col-sm-12">
                                <label class="form-label">Category Image</label>
                                @if ($category->image_url)
                                    <div class="mb-2"><img src="{{ $category->image_url }}" alt=""
                                            class="rounded border" style="height:80px">
                                        <div class="form-check mt-1"><input class="form-check-input" type="checkbox"
                                                name="remove_image" value="1" id="remove_image"><label
                                                class="form-check-label small" for="remove_image">Remove current
                                                image</label>
                                        </div>
                                    </div>
                                @endif
                                <input type="file" name="image" class="form-control" accept=".jpg,.jpeg,.png,.webp">
                                <div class="form-text">JPG, PNG or WEBP, max 1 MB. Subcategories do not have images.</div>
                            </div>
                            <div class="col-lg-6 col-md-6 col-sm-12">
                                <label class="form-label">Sort order</label>
                                <input type="number" min="0" name="sort_order" class="form-control"
                                    value="{{ old('sort_order', $category->sort_order ?? 0) }}">
                            </div>
                        </div>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1"
                                id="is_active" @checked(old('is_active', $category->is_active))>
                            <label class="form-check-label" for="is_active">Active</label>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-primary"><i class="bi bi-check-lg"></i> Save
                                Category</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
