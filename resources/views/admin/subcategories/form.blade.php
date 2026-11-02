@extends('layouts.app')

@section('title', $subcategory->exists ? 'Edit Subcategory' : 'Add Subcategory')

@section('content')
    <div class="row">
        <div class="col-11 mx-auto">

            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3 class="page-title">
                    {{ $subcategory->exists ? 'Edit Subcategory' : 'Add Subcategory' }}
                </h3>

                <button type="button" class="btn btn-warning align-self-start align-self-sm-auto" onclick="window.history.back()">
                    <i class="bi bi-arrow-left"></i> Back
                </button>
            </div>

            <div class="card">
                <div class="card-body">
                    <form method="POST"
                        action="{{ $subcategory->exists ? route('admin.subcategories.update', $subcategory) : route('admin.subcategories.store') }}">

                        @csrf

                        @if ($subcategory->exists)
                            @method('PUT')
                        @endif

                        <div class="row row-cols-1 row-cols-md-2 g-3 mb-3">
                            <div class="col">
                                <label class="form-label">Category *</label>
                                <select name="category_id" class="form-select" required>
                                    <option value="">Select a category</option>
                                    @foreach ($categories as $c)
                                        <option value="{{ $c->id }}" @selected(old('category_id', $subcategory->category_id) == $c->id)>
                                            {{ $c->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col">
                                <label class="form-label">Subcategory Name *</label>
                                <input name="name" placeholder="Subcategory Name" class="form-control"
                                    value="{{ old('name', $subcategory->name) }}" required>
                            </div>

                            <div class="col">
                                <label class="form-label">Description</label>
                                <input type="text" placeholder="Description" name="description" class="form-control mt-1"
                                    value="{{ old('description', $subcategory->description) }}" readonly>
                            </div>

                            <div class="col">
                                <label class="form-label">Sort order</label>
                                <input type="number" min="0" name="sort_order" class="form-control"
                                    value="{{ old('sort_order', $subcategory->sort_order ?? 0) }}">
                            </div>
                        </div>

                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1"
                                id="is_active" @checked(old('is_active', $subcategory->is_active))>
                            <label class="form-check-label" for="is_active">Active</label>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg"></i> Save Subcategory
                            </button>
                        </div>

                    </form>
                </div>
            </div>

        </div>
    </div>
@endsection
```
