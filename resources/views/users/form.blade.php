@extends('layouts.app')
@section('title', $user->exists ? 'Edit User' : 'Add User')
@section('content')
@php $selected = old('permissions', $user->permissions ?? []); @endphp
<h3 class="page-title mb-3">{{ $user->exists ? 'Edit User' : 'Add User' }}</h3>
<div class="card" style="max-width:760px"><div class="card-body">
    <form method="POST" id="user-form" data-roles='@json($rolePerms)' action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}">
        @csrf @if ($user->exists) @method('PUT') @endif
        <div class="row g-3">
            <div class="col-12 col-md-6"><label class="form-label">Name *</label><input name="name" class="form-control" value="{{ old('name', $user->name) }}" required></div>
            <div class="col-12 col-md-6"><label class="form-label">Email *</label><input type="email" name="email" class="form-control" value="{{ old('email', $user->email) }}" required></div>
            <div class="col-12 col-md-6"><label class="form-label">Mobile</label><input name="mobile" class="form-control" value="{{ old('mobile', $user->mobile) }}"></div>
            <div class="col-12 col-md-6"><label class="form-label">Status *</label>
                <select name="status" class="form-select"><option value="active" @selected(old('status', $user->status) === 'active')>Active</option><option value="inactive" @selected(old('status', $user->status) === 'inactive')>Inactive</option></select></div>
            <div class="col-12"><label class="form-label">Password {{ $user->exists ? '(leave blank to keep)' : '*' }}</label>
                <input type="password" name="password" class="form-control" {{ $user->exists ? '' : 'required' }}></div>

            <div class="col-12">
                <label class="form-label">Role</label>
                <select name="role_id" id="role_id" class="form-select">
                    <option value="">- No role (only the extra permissions below) -</option>
                    @foreach ($roles as $r)
                        <option value="{{ $r->id }}" @selected(old('role_id', $user->role_id) == $r->id)>{{ $r->name }}@if($r->description) &mdash; {{ $r->description }}@endif</option>
                    @endforeach
                </select>
                <div class="form-text">Roles are created by the system admin. A role gives a ready-made set of permissions.</div>
            </div>

            <div class="col-12">
                <label class="form-label d-block">Extra permissions <small class="text-muted">(on top of the role)</small></label>
                @foreach ($permissions as $group => $items)
                    <div class="fw-semibold small text-muted mt-2">{{ $group }}</div>
                    @foreach ($items as $perm)
                        <div class="form-check">
                            <input class="form-check-input perm-box" type="checkbox" name="permissions[]" value="{{ $perm->slug }}" id="perm{{ $perm->id }}" @checked(in_array($perm->slug, $selected))>
                            <label class="form-check-label" for="perm{{ $perm->id }}">{{ $perm->label }} <span class="badge text-bg-secondary-subtle text-secondary d-none from-role">from role</span></label>
                        </div>
                    @endforeach
                @endforeach
            </div>
        </div>
        <div class="mt-4 d-flex gap-2"><button class="btn btn-primary"><i class="bi bi-check-lg"></i> Save User</button><a href="{{ route('users.index') }}" class="btn btn-outline-secondary">Cancel</a></div>
    </form>
</div></div>
@endsection

@push('scripts')
<script>
(function () {
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
                cb.checked = true; cb.disabled = true; tag.classList.remove('d-none');
            } else {
                if (cb.disabled) { cb.checked = cb.dataset.was === '1'; cb.disabled = false; }
                tag.classList.add('d-none');
            }
        });
    }
    select.addEventListener('change', apply);
    apply();
})();
</script>
@endpush
