@extends('layouts.app')
@section('title', 'Users & Access')
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <h3 class="page-title mb-1">Users &amp; Access</h3>
        <p class="text-muted mb-0">Your users see only your own data. Plan: <strong>{{ $account->plan->name ?? 'None' }}</strong> ({{ $users->count() }} / {{ $account->plan->max_users ?? 0 }} users)</p>
    </div>
    <a href="{{ route('users.create') }}" class="btn btn-primary"><i class="bi bi-person-plus"></i> Add User</a>
</div>
<div class="card"><div class="table-responsive">
    <table class="table table-hover mb-0">
        <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Extra permissions</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        @foreach ($users as $u)
            <tr>
                <td class="fw-semibold">{{ $u->name }}</td>
                <td>{{ $u->email }}<br><small class="text-muted">{{ $u->mobile }}</small></td>
                <td>
                    @if ($u->role === 'owner') <span class="badge text-bg-primary">Owner (full access)</span>
                    @elseif ($u->assignedRole) <span class="badge text-bg-info">{{ $u->assignedRole->name }}</span>
                    @else <span class="text-muted">No role</span> @endif
                </td>
                <td class="small">
                    @foreach ($u->permissions ?? [] as $slug)<span class="badge text-bg-light border">{{ $labels[$slug] ?? $slug }}</span> @endforeach
                </td>
                <td><x-status-badge :status="$u->status" /></td>
                <td class="text-end text-nowrap">
                    @if ($u->role === 'member')
                        <a href="{{ route('users.edit', $u) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                        <form method="POST" action="{{ route('users.destroy', $u) }}" class="d-inline" data-confirm="Delete this user?">@csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div></div>
@endsection
