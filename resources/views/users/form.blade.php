@extends('layouts.app')
@section('title', $user->exists ? 'Edit User' : 'Add User')
@section('content')
    @php
        // boxes that are ticked: after a failed save -> what was ticked, otherwise what the user can do now
        $checked = old('_perm_form') ? old('permissions', []) : $effective;
    @endphp
    <div class="row">
        <div class="col-11 mx-auto">
            <div class="d-flex justify-content-between align-items-center">
                <h3 class="page-title">{{ $user->exists ? 'Edit User' : 'Add User' }}</h3>
                <button type="button" class="btn btn-warning align-self-start align-self-sm-auto"
                    onclick="window.history.back()"><i class="bi bi-arrow-left"></i> Back</button>
            </div>

            <div class="card">
                <div class="card-body">
                    <form method="POST" id="user-form" data-roles="{{ json_encode($rolePerms) }}"
                        action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}">
                        @csrf
                        @if ($user->exists)
                            @method('PUT')
                        @endif
                        <input type="hidden" name="_perm_form" value="1">

                        <div class="row mb-3">
                            <div class="col-lg-6 col-md-6 col-sm-12">
                                <label class="form-label">Name *</label>
                                <input name="name" placeholder="Name" class="form-control"
                                    value="{{ old('name', $user->name) }}" required>
                            </div>
                            <div class="col-lg-6 col-md-6 col-sm-12">
                                <label class="form-label">Email *</label>
                                <input type="email" name="email" placeholder="Email" class="form-control"
                                    value="{{ old('email', $user->email) }}" required>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-lg-6 col-md-6 col-sm-12">
                                <label class="form-label">Mobile</label>
                                <input name="mobile" placeholder="Mobile" class="form-control"
                                    value="{{ old('mobile', $user->mobile) }}">
                            </div>
                            <div class="col-lg-6 col-md-6 col-sm-12">
                                <label class="form-label">Status *</label>
                                <select name="status" class="form-select">
                                    <option value="active" @selected(old('status', $user->status) === 'active')>Active</option>
                                    <option value="inactive" @selected(old('status', $user->status) === 'inactive')>Inactive</option>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-lg-6 col-md-6 col-sm-12">
                                <label class="form-label">Password {{ $user->exists ? '(leave blank to keep)' : '*' }}</label>
                                <input type="password" name="password" placeholder="Password" class="form-control"
                                    {{ $user->exists ? '' : 'required' }}>
                            </div>
                            <div class="col-lg-6 col-md-6 col-sm-12">
                                <label class="form-label">Role *</label>
                                <select name="role_id" id="role_id" class="form-select" required>
                                    <option value="">Select a role</option>
                                    @foreach ($roles as $r)
                                        <option value="{{ $r->id }}" @selected(old('role_id', $user->role_id) == $r->id)>{{ $r->name }}@if ($r->description) - {{ $r->description }}@endif</option>
                                    @endforeach
                                </select>
                                <div class="form-text">The role gives a ready set of permissions. You can change them below for this user.</div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-12">
                                <label class="form-label d-block">Permissions</label>
                                <div class="form-text mb-2">Tick = this user can do it. Choosing a role ticks that role's permissions.
                                    You can untick one to turn it off, or tick one the role does not have.</div>
                                <div class="row">
                                    @foreach ($permissions as $group => $items)
                                        <div class="col-lg-4 col-md-6 col-sm-12 mb-2">
                                            <div class="fw-semibold small text-muted">{{ $group }}</div>
                                            @foreach ($items as $perm)
                                                <div class="form-check">
                                                    <input class="form-check-input perm-box" type="checkbox" name="permissions[]"
                                                        value="{{ $perm->slug }}" id="perm{{ $perm->id }}" @checked(in_array($perm->slug, $checked))>
                                                    <label class="form-check-label" for="perm{{ $perm->id }}">{{ $perm->label }}
                                                        <span class="badge perm-state d-none"></span></label>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <button class="btn btn-primary"><i class="bi bi-check-lg"></i> Save User</button>
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
            const rolePerms = JSON.parse(form.dataset.roles);
            const select = document.getElementById('role_id');
            const boxes = [...form.querySelectorAll('.perm-box')];

            // small label next to every permission: from the role / extra / turned off
            function paint() {
                const fromRole = rolePerms[select.value] || [];
                boxes.forEach(cb => {
                    const tag = cb.closest('.form-check').querySelector('.perm-state');
                    const inRole = fromRole.includes(cb.value);
                    let text = '', cls = '';
                    if (inRole && cb.checked) { text = 'role'; cls = 'text-bg-secondary-subtle text-secondary'; }
                    else if (!inRole && cb.checked) { text = 'extra'; cls = 'text-bg-primary-subtle text-primary'; }
                    else if (inRole && !cb.checked) { text = 'turned off'; cls = 'text-bg-danger-subtle text-danger'; }
                    tag.className = 'badge perm-state ' + cls + (text ? '' : ' d-none');
                    tag.textContent = text;
                });
            }

            // choosing another role starts again from that role's permissions
            select.addEventListener('change', () => {
                const fromRole = rolePerms[select.value] || [];
                boxes.forEach(cb => cb.checked = fromRole.includes(cb.value));
                paint();
            });
            boxes.forEach(cb => cb.addEventListener('change', paint));
            paint();
        })();
    </script>
@endpush
