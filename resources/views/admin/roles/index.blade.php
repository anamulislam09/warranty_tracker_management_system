@extends('layouts.app')
@section('title', 'Roles')
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div><h3 class="page-title mb-1">Roles</h3><p class="text-muted mb-0">Create roles here. Customers assign them to their own users.</p></div>
    <a href="{{ route('admin.roles.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Add Role</a>
</div>
<div class="card"><div class="table-responsive">
    <table class="table table-hover mb-0">
        <thead><tr><th>Role</th><th>Permissions</th><th>Users</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        @forelse ($roles as $r)
            <tr>
                <td><strong>{{ $r->name }}</strong><br><small class="text-muted">{{ $r->description }}</small></td>
                <td class="small">@foreach ($r->permissions as $p)<span class="badge text-bg-light border">{{ $p->label }}</span> @endforeach</td>
                <td>{{ $r->users_count }}</td>
                <td><x-status-badge :status="$r->is_active ? 'active' : 'inactive'" /></td>
                <td class="text-end text-nowrap">
                    <a href="{{ route('admin.roles.edit', $r) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                    <form method="POST" action="{{ route('admin.roles.destroy', $r) }}" class="d-inline" data-confirm="Delete this role?">@csrf @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="text-center text-muted py-4">No roles yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div></div>
@endsection
