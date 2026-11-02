@extends('layouts.app')

@section('title', $permission->exists ? 'Edit Permission' : 'Add Permission')

@section('content')
    <div class="row">
        <div class="col-11 mx-auto">

            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3 class="page-title mb-3">
                    {{ $permission->exists ? 'Edit Permission' : 'Add Permission' }}
                </h3>

                <button type="button" class="btn btn-warning align-self-start align-self-sm-auto"
                    onclick="window.history.back()">
                    <i class="bi bi-arrow-left"></i> Back
                </button>
            </div>

            <div class="card">
                <div class="card-body">
                    <form method="POST"
                        action="{{ $permission->exists ? route('admin.permissions.update', $permission) : route('admin.permissions.store') }}">

                        @csrf

                        @if ($permission->exists)
                            @method('PUT')
                        @endif

                        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-2 g-3">

                            {{-- Label --}}
                            <div class="col">
                                <label class="form-label">Label *</label>
                                <input name="label" class="form-control" value="{{ old('label', $permission->label) }}"
                                    placeholder="e.g. Export products" required>
                            </div>

                            {{-- Key --}}
                            <div class="col">
                                <label class="form-label">Key *</label>
                                <input name="slug" class="form-control" value="{{ old('slug', $permission->slug) }}"
                                    placeholder="e.g. products.export" {{ $permission->is_system ? 'readonly' : '' }}
                                    required>

                                <div class="form-text">
                                    Lowercase, with a dot: <code>module.action</code>.
                                    {{ $permission->is_system ? 'System keys cannot be changed.' : '' }}
                                </div>
                            </div>

                            {{-- Group --}}
                            <div class="col">
                                <label class="form-label">Group *</label>
                                <input name="group" class="form-control" value="{{ old('group', $permission->group) }}"
                                    placeholder="e.g. Products" required>
                            </div>

                        </div>

                        {{-- Active --}}
                        <div class="form-check form-switch mt-3 mb-3">
                            <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1"
                                id="is_active" @checked(old('is_active', $permission->is_active))>

                            <label class="form-check-label" for="is_active">
                                Active
                            </label>
                        </div>

                        {{-- @unless ($permission->is_system)
                            <div class="alert alert-info small">
                                A custom permission only has an effect after a developer
                                protects a route with <code>permission:your.key</code>.
                            </div>
                        @endunless --}}

                        {{-- Buttons --}}
                        <div class="mt-4 d-flex gap-2">
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-check-lg"></i> Save Permission
                            </button>
                        </div>

                    </form>
                </div>
            </div>

        </div>
    </div>
@endsection
