{{-- @extends('layouts.app')
@section('title', $user->exists ? 'Edit User' : 'Add User')
@section('content')
    @php $selected = old('permissions', $user->permissions ?? []); @endphp
    <h3 class="page-title mb-3">
        {{ $user->exists ? 'Edit User' : 'Add User' }}</h3>
    <div class="card" style="max-width:760px">
        <div class="card-body">
            <form method="POST" id="user-form" data-roles='@json($rolePerms)'
                action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}">
                @csrf @if ($user->exists)
                    @method('PUT')
                @endif
                <div class="row g-3">
                    <div class="col-12 col-md-6"><label class="form-label">Name *</label><input name="name"
                            class="form-control" value="{{ old('name', $user->name) }}" required></div>
                    <div class="col-12 col-md-6"><label class="form-label">Email *</label><input type="email"
                            name="email" class="form-control" value="{{ old('email', $user->email) }}" required></div>
                    <div class="col-12 col-md-6"><label class="form-label">Mobile</label><input name="mobile"
                            class="form-control" value="{{ old('mobile', $user->mobile) }}"></div>
                    <div class="col-12 col-md-6"><label class="form-label">Status *</label>
                        <select name="status" class="form-select">
                            <option value="active" @selected(old('status', $user->status) === 'active')>Active</option>
                            <option value="inactive" @selected(old('status', $user->status) === 'inactive')>Inactive</option>
                        </select>
                    </div>
                    <div class="col-12"><label class="form-label">Password
                            {{ $user->exists ? '(leave blank to keep)' : '*' }}</label>
                        <input type="password" name="password" class="form-control" {{ $user->exists ? '' : 'required' }}>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Role</label>
                        <select name="role_id" id="role_id" class="form-select">
                            <option value="">- No role (only the extra permissions below) -</option>
                            @foreach ($roles as $r)
                                <option value="{{ $r->id }}" @selected(old('role_id', $user->role_id) == $r->id)>{{ $r->name }}
                                    @if ($r->description)
                                        &mdash; {{ $r->description }}
                                    @endif
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text">Roles are created by the system admin. A role gives a ready-made set of
                            permissions.</div>
                    </div>

                    <div class="col-12">
                        <label class="form-label d-block">Extra permissions <small class="text-muted">(on top of the
                                role)</small></label>
                        @foreach ($permissions as $group => $items)
                            <div class="fw-semibold small text-muted mt-2">{{ $group }}</div>
                            @foreach ($items as $perm)
                                <div class="form-check">
                                    <input class="form-check-input perm-box" type="checkbox" name="permissions[]"
                                        value="{{ $perm->slug }}" id="perm{{ $perm->id }}"
                                        @checked(in_array($perm->slug, $selected))>
                                    <label class="form-check-label" for="perm{{ $perm->id }}">{{ $perm->label }}
                                        <span class="badge text-bg-secondary-subtle text-secondary d-none from-role">from
                                            role</span></label>
                                </div>
                            @endforeach
                        @endforeach
                    </div>
                </div>
                <div class="mt-4 d-flex gap-2"><button class="btn btn-primary"><i class="bi bi-check-lg"></i> Save
                        User</button><a href="{{ route('users.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function() {
            const form = document.getElementById('user-form');
            const roles = JSON.parse(form.dataset.roles);
            const select = document.getElementById('role_id');
            const boxes = form.querySelectorAll('.perm-box');

            function apply() {
                const fromRole = roles[select.value] || [];
                boxes.forEach(cb => {
                    const tag = cb.parentElement.querySelector('.from-role');
                    if (fromRole.includes(cb.value)) {
                        if (!cb.disabled) cb.dataset.was = cb.checked ? '1' : '0';
                        cb.checked = true;
                        cb.disabled = true;
                        tag.classList.remove('d-none');
                    } else {
                        if (cb.disabled) {
                            cb.checked = cb.dataset.was === '1';
                            cb.disabled = false;
                        }
                        tag.classList.add('d-none');
                    }
                });
            }
            select.addEventListener('change', apply);
            apply();
        })();
    </script>
@endpush --}}
@extends('layouts.app')

@section('title', $user->exists ? 'Edit User' : 'Add User')

@section('content')
    @php
        $selected = old('permissions', $user->permissions ?? []);
        $selected = array_map('strval', (array) $selected);
    @endphp

    <div class="row">
        <div class="col-11 mx-auto">

            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3 class="page-title mb-3">
                    {{ $user->exists ? 'Edit User' : 'Add User' }}
                </h3>

                <button type="button" class="btn btn-warning align-self-start align-self-sm-auto"
                    onclick="window.history.back()">
                    <i class="bi bi-arrow-left"></i> Back
                </button>
            </div>

            <div class="card">
                <div class="card-body">

                    <form method="POST" id="user-form" data-roles='@json($rolePerms)'
                        action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}">

                        @csrf

                        @if ($user->exists)
                            @method('PUT')
                        @endif

                        {{-- Basic Information --}}
                        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3">

                            <div class="col">
                                <label class="form-label">Name *</label>
                                <input name="name" class="form-control" value="{{ old('name', $user->name) }}"
                                    placeholder="Enter full name" required>
                            </div>

                            <div class="col">
                                <label class="form-label">Email *</label>
                                <input type="email" name="email" class="form-control"
                                    value="{{ old('email', $user->email) }}" placeholder="example@email.com" required>
                            </div>

                            <div class="col">
                                <label class="form-label">Mobile</label>
                                <input name="mobile" class="form-control" value="{{ old('mobile', $user->mobile) }}"
                                    placeholder="Enter mobile number">
                            </div>

                            <div class="col">
                                <label class="form-label">Status *</label>
                                <select name="status" class="form-select" required>
                                    <option value="active" @selected(old('status', $user->status) === 'active')>
                                        Active
                                    </option>

                                    <option value="inactive" @selected(old('status', $user->status) === 'inactive')>
                                        Inactive
                                    </option>
                                </select>
                            </div>

                            <div class="col">
                                <label class="form-label">
                                    Password {{ $user->exists ? '' : '*' }}
                                </label>

                                <input type="password" name="password" class="form-control"
                                    placeholder="{{ $user->exists ? 'Leave blank to keep current password' : 'Enter password' }}"
                                    {{ $user->exists ? '' : 'required' }}>

                                @if ($user->exists)
                                    <div class="form-text">
                                        Leave blank to keep the current password.
                                    </div>
                                @endif
                            </div>

                            <div class="col">
                                <label class="form-label">Role</label>

                                <select name="role_id" id="role_id" class="form-select">
                                    <option value="">
                                        No role
                                    </option>

                                    @foreach ($roles as $r)
                                        <option value="{{ $r->id }}" @selected(old('role_id', $user->role_id) == $r->id)>
                                            {{ $r->name }}{{ $r->description ? ' — ' . $r->description : '' }}
                                        </option>
                                    @endforeach
                                </select>

                                <div class="form-text">
                                    A role provides a predefined set of permissions.
                                </div>
                            </div>

                        </div>

                        {{-- Extra Permissions --}}
                        <div class="mt-4">

                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                                <div>
                                    <h5 class="mb-1">Extra Permissions</h5>
                                    <div class="form-text">
                                        Additional permissions beyond the selected role.
                                        Role permissions are automatically included.
                                    </div>
                                </div>

                                <div class="d-flex flex-wrap gap-2">
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
                                                            <input class="form-check-input perm-box" type="checkbox"
                                                                name="permissions[]" value="{{ $perm->slug }}"
                                                                id="perm{{ $perm->id }}"
                                                                data-group="{{ $loop->parent->index }}"
                                                                @checked(in_array((string) $perm->slug, $selected))>

                                                            <label class="form-check-label w-100"
                                                                for="perm{{ $perm->id }}">
                                                                <span class="d-block">
                                                                    {{ $perm->label }}

                                                                    <span
                                                                        class="badge text-bg-secondary-subtle text-secondary d-none from-role">
                                                                        From role
                                                                    </span>
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
                                <i class="bi bi-check-lg"></i> Save User
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
            const form = document.getElementById('user-form');

            if (!form) return;

            const roles = JSON.parse(form.dataset.roles || '{}');
            const select = document.getElementById('role_id');

            const boxes = Array.from(
                form.querySelectorAll('.perm-box')
            );

            const groupSelects = form.querySelectorAll('.group-select');
            const selectAll = document.getElementById('select-all-permissions');
            const clearAll = document.getElementById('clear-all-permissions');

            function applyRole() {
                const fromRole = (roles[select.value] || []).map(String);

                boxes.forEach(cb => {
                    const tag = cb.parentElement.querySelector('.from-role');
                    const included = fromRole.includes(cb.value);

                    if (included) {
                        if (!cb.disabled) {
                            cb.dataset.was = cb.checked ? '1' : '0';
                        }

                        cb.checked = true;
                        cb.disabled = true;
                        tag.classList.remove('d-none');
                    } else {
                        if (cb.disabled) {
                            cb.checked = cb.dataset.was === '1';
                            cb.disabled = false;
                        }

                        tag.classList.add('d-none');
                    }
                });

                updateGroupCheckboxes();
            }

            function updateGroupCheckboxes() {
                groupSelects.forEach(groupCheckbox => {
                    const groupId = groupCheckbox.dataset.group;

                    const items = boxes.filter(
                        cb => cb.dataset.group === groupId
                    );

                    const checkedCount = items.filter(
                        cb => cb.checked
                    ).length;

                    groupCheckbox.checked =
                        items.length > 0 && checkedCount === items.length;

                    groupCheckbox.indeterminate =
                        checkedCount > 0 && checkedCount < items.length;
                });
            }

            groupSelects.forEach(groupCheckbox => {
                groupCheckbox.addEventListener('change', function() {
                    const groupId = this.dataset.group;

                    boxes
                        .filter(cb =>
                            cb.dataset.group === groupId && !cb.disabled
                        )
                        .forEach(cb => {
                            cb.checked = this.checked;
                        });

                    updateGroupCheckboxes();
                });
            });

            boxes.forEach(cb => {
                cb.addEventListener('change', updateGroupCheckboxes);
            });

            selectAll.addEventListener('click', function() {
                boxes.forEach(cb => {
                    if (!cb.disabled) {
                        cb.checked = true;
                    }
                });

                updateGroupCheckboxes();
            });

            clearAll.addEventListener('click', function() {
                boxes.forEach(cb => {
                    if (!cb.disabled) {
                        cb.checked = false;
                    }
                });

                updateGroupCheckboxes();
            });

            select.addEventListener('change', applyRole);

            // Re-enable role permissions before submitting so their
            // checked values are included in the request.
            form.addEventListener('submit', function() {
                boxes.forEach(cb => {
                    if (cb.disabled && cb.checked) {
                        cb.disabled = false;
                    }
                });
            });

            applyRole();
        })();
    </script>
@endpush
