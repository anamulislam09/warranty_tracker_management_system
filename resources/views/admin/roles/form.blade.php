@extends('layouts.app')
@section('title', $role->exists ? 'Edit Role' : 'Add Role')
@section('content')
@php $chosen = old('permissions', $selected); @endphp
<h3 class="page-title mb-3">{{ $role->exists ? 'Edit Role' : 'Add Role' }}</h3>
<div class="card" style="max-width:760px"><div class="card-body">
    <form method="POST" action="{{ $role->exists ? route('admin.roles.update', $role) : route('admin.roles.store') }}">
        @csrf @if ($role->exists) @method('PUT') @endif
        <div class="row g-3">
            <div class="col-12 col-md-6"><label class="form-label">Role Name *</label><input name="name" class="form-control" value="{{ old('name', $role->name) }}" required></div>
            <div class="col-12 col-md-6"><label class="form-label">Description</label><input name="description" class="form-control" value="{{ old('description', $role->description) }}"></div>
            <div class="col-12">
                <label class="form-label d-block">Permissions</label>
                @foreach ($permissions as $group => $items)
                    <div class="fw-semibold small text-muted mt-2">{{ $group }}</div>
                    @foreach ($items as $perm)
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $perm->id }}" id="p{{ $perm->id }}" @checked(in_array($perm->id, $chosen))>
                            <label class="form-check-label" for="p{{ $perm->id }}">{{ $perm->label }} <small class="text-muted">({{ $perm->slug }})</small></label></div>
                    @endforeach
                @endforeach
            </div>
            <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="is_active" @checked(old('is_active', $role->is_active))><label class="form-check-label" for="is_active">Role active</label></div></div>
        </div>
        <div class="mt-4 d-flex gap-2"><button class="btn btn-primary"><i class="bi bi-check-lg"></i> Save Role</button><a href="{{ route('admin.roles.index') }}" class="btn btn-outline-secondary">Cancel</a></div>
    </form>
</div></div>
@endsection
