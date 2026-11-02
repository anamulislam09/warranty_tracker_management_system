{{-- @extends('layouts.app')
@section('title', $role->exists ? 'Edit Role' : 'Add Role')
@section('content')
    @php $chosen = old('permissions', $selected); @endphp
    <h3 class="page-title mb-3">{{ $role->exists ? 'Edit Role' : 'Add Role' }}</h3>
    <div class="card" style="max-width:760px">
        <div class="card-body">
            <form method="POST"
                action="{{ $role->exists ? route('admin.roles.update', $role) : route('admin.roles.store') }}">
                @csrf @if ($role->exists)
                    @method('PUT')
                @endif
                <div class="row g-3">
                    <div class="col-12 col-md-6"><label class="form-label">Role Name *</label><input name="name"
                            class="form-control" value="{{ old('name', $role->name) }}" required></div>
                    <div class="col-12 col-md-6"><label class="form-label">Description</label><input name="description"
                            class="form-control" value="{{ old('description', $role->description) }}"></div>
                    <div class="col-12">
                        <label class="form-label d-block">Permissions</label>
                        @foreach ($permissions as $group => $items)
                            <div class="fw-semibold small text-muted mt-2">{{ $group }}</div>
                            @foreach ($items as $perm)
                                <div class="form-check"><input class="form-check-input" type="checkbox" name="permissions[]"
                                        value="{{ $perm->id }}" id="p{{ $perm->id }}" @checked(in_array($perm->id, $chosen))>
                                    <label class="form-check-label" for="p{{ $perm->id }}">{{ $perm->label }} <small
                                            class="text-muted">({{ $perm->slug }})</small></label>
                                </div>
                            @endforeach
                        @endforeach
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch"
                                name="is_active" value="1" id="is_active" @checked(old('is_active', $role->is_active))><label
                                class="form-check-label" for="is_active">Role active</label></div>
                    </div>
                </div>
                <div class="mt-4 d-flex gap-2"><button class="btn btn-primary"><i class="bi bi-check-lg"></i> Save
                        Role</button><a href="{{ route('admin.roles.index') }}"
                        class="btn btn-outline-secondary">Cancel</a></div>
            </form>
        </div>
    </div>
@endsection --}}


@extends('layouts.app')

@section('title', $role->exists ? 'Edit Role' : 'Add Role')

@section('content')
    @php
        $chosen = old('permissions', $selected);
        $chosen = array_map('intval', (array) $chosen);
    @endphp

    <div class="row">
        <div class="col-11 mx-auto">

            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3 class="page-title mb-3">
                    {{ $role->exists ? 'Edit Role' : 'Add Role' }}
                </h3>

                <button type="button" class="btn btn-warning align-self-start align-self-sm-auto"
                    onclick="window.history.back()">
                    <i class="bi bi-arrow-left"></i> Back
                </button>
            </div>

            <div class="card">
                <div class="card-body">

                    <form method="POST"
                        action="{{ $role->exists ? route('admin.roles.update', $role) : route('admin.roles.store') }}">

                        @csrf

                        @if ($role->exists)
                            @method('PUT')
                        @endif

                        {{-- Role Information --}}
                        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3">

                            <div class="col">
                                <label class="form-label">Role Name *</label>
                                <input name="name" class="form-control" value="{{ old('name', $role->name) }}"
                                    placeholder="e.g. Manager" required>
                            </div>

                            <div class="col">
                                <label class="form-label">Description</label>
                                <input name="description" class="form-control"
                                    value="{{ old('description', $role->description) }}"
                                    placeholder="Short role description">
                            </div>

                            <div class="col d-flex align-items-end">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" role="switch" name="is_active"
                                        value="1" id="is_active" @checked(old('is_active', $role->is_active))>

                                    <label class="form-check-label" for="is_active">
                                        Role Active
                                    </label>
                                </div>
                            </div>

                        </div>

                        {{-- Permissions --}}
                        <div class="mt-4">

                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                                <div>
                                    <h5 class="mb-1">Permissions</h5>
                                    <div class="form-text">
                                        Select the permissions this role should have.
                                    </div>
                                </div>

                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-sm btn-outline-primary"
                                        id="select-all-permissions">
                                        Select All
                                    </button>

                                    <button type="button" class="btn btn-sm btn-outline-secondary"
                                        id="clear-all-permissions">
                                        Clear All
                                    </button>
                                </div>
                            </div>

                            @error('permissions')
                                <div class="text-danger small mb-3">{{ $message }}</div>
                            @enderror

                            <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3">

                                @foreach ($permissions as $group => $items)
                                    <div class="col">

                                        <div class="card h-100 border">
                                            <div
                                                class="card-header bg-light d-flex justify-content-between align-items-center gap-2">
                                                <span class="fw-semibold">
                                                    {{ $group }}
                                                </span>

                                                <div class="form-check mb-0">
                                                    <input type="checkbox" class="form-check-input group-select"
                                                        id="group_{{ $loop->index }}" data-group="{{ $loop->index }}">

                                                    <label class="form-check-label small" for="group_{{ $loop->index }}">
                                                        All
                                                    </label>
                                                </div>
                                            </div>

                                            <div class="card-body p-3">
                                                <div class="d-flex flex-column gap-3">

                                                    @foreach ($items as $perm)
                                                        <div class="form-check">
                                                            <input class="form-check-input permission-checkbox"
                                                                type="checkbox" name="permissions[]"
                                                                value="{{ $perm->id }}" id="p{{ $perm->id }}"
                                                                data-group="{{ $loop->parent->index }}"
                                                                @checked(in_array((int) $perm->id, $chosen))>

                                                            <label class="form-check-label w-100"
                                                                for="p{{ $perm->id }}">
                                                                <span class="d-block">
                                                                    {{ $perm->label }}
                                                                </span>

                                                                <small class="text-muted">
                                                                    {{ $perm->slug }}
                                                                </small>
                                                            </label>
                                                        </div>
                                                    @endforeach

                                                </div>
                                            </div>
                                        </div>

                                    </div>
                                @endforeach

                            </div>
                        </div>

                        {{-- Actions --}}
                        <div class="mt-4 d-flex flex-wrap gap-2">
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-check-lg"></i> Save Role
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function() {
            const permissions = Array.from(
                document.querySelectorAll('.permission-checkbox')
            );

            const groupSelects = document.querySelectorAll('.group-select');
            const selectAll = document.getElementById('select-all-permissions');
            const clearAll = document.getElementById('clear-all-permissions');

            function updateGroupCheckboxes() {
                groupSelects.forEach(groupCheckbox => {
                    const groupId = groupCheckbox.dataset.group;

                    const items = permissions.filter(
                        permission => permission.dataset.group === groupId
                    );

                    const checkedCount = items.filter(
                        permission => permission.checked
                    ).length;

                    groupCheckbox.checked =
                        items.length > 0 && checkedCount === items.length;

                    groupCheckbox.indeterminate =
                        checkedCount > 0 && checkedCount < items.length;
                });
            }

            groupSelects.forEach(groupCheckbox => {
                groupCheckbox.addEventListener('change', function() {
                    permissions
                        .filter(permission =>
                            permission.dataset.group === this.dataset.group
                        )
                        .forEach(permission => {
                            permission.checked = this.checked;
                        });

                    updateGroupCheckboxes();
                });
            });

            permissions.forEach(permission => {
                permission.addEventListener('change', updateGroupCheckboxes);
            });

            selectAll.addEventListener('click', function() {
                permissions.forEach(permission => {
                    permission.checked = true;
                });

                updateGroupCheckboxes();
            });

            clearAll.addEventListener('click', function() {
                permissions.forEach(permission => {
                    permission.checked = false;
                });

                updateGroupCheckboxes();
            });

            updateGroupCheckboxes();
        })();
    </script>
@endpush
