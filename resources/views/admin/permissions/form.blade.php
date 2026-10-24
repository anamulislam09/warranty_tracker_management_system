@extends('layouts.app')
@section('title', $permission->exists ? 'Edit Permission' : 'Add Permission')
@section('content')
<h3 class="page-title mb-3">{{ $permission->exists ? 'Edit Permission' : 'Add Permission' }}</h3>
<div class="card" style="max-width:640px"><div class="card-body">
    <form method="POST" action="{{ $permission->exists ? route('admin.permissions.update', $permission) : route('admin.permissions.store') }}">
        @csrf @if ($permission->exists) @method('PUT') @endif
        <div class="mb-3"><label class="form-label">Label *</label><input name="label" class="form-control" value="{{ old('label', $permission->label) }}" placeholder="e.g. Export products" required></div>
        <div class="row g-3 mb-3">
            <div class="col-12 col-md-7"><label class="form-label">Key *</label>
                <input name="slug" class="form-control" value="{{ old('slug', $permission->slug) }}" placeholder="e.g. products.export" {{ $permission->is_system ? 'readonly' : '' }} required>
                <div class="form-text">Lowercase, with a dot: <code>module.action</code>. {{ $permission->is_system ? 'System keys cannot be changed.' : '' }}</div></div>
            <div class="col-12 col-md-5"><label class="form-label">Group *</label><input name="group" class="form-control" value="{{ old('group', $permission->group) }}" required></div>
        </div>
        <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="is_active" @checked(old('is_active', $permission->is_active))><label class="form-check-label" for="is_active">Active</label></div>
        @unless ($permission->is_system)
            <div class="alert alert-info small">A custom permission only has an effect after a developer protects a route with <code>permission:your.key</code>.</div>
        @endunless
        <div class="d-flex gap-2"><button class="btn btn-primary"><i class="bi bi-check-lg"></i> Save</button><a href="{{ route('admin.permissions.index') }}" class="btn btn-outline-secondary">Cancel</a></div>
    </form>
</div></div>
@endsection
