@extends('layouts.app')
@section('title', 'Permissions')
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div><h3 class="page-title mb-1">Permissions</h3><p class="text-muted mb-0">The list of permissions that roles and customers can use.</p></div>
    <a href="{{ route('admin.permissions.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Add Permission</a>
</div>
@foreach ($permissions as $group => $items)
<div class="card mb-3"><div class="card-body">
    <h6 class="section-title mb-2">{{ $group }}</h6>
    <div class="table-responsive"><table class="table mb-0">
        <thead><tr><th>Label</th><th>Key</th><th>Type</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        @foreach ($items as $p)
            <tr>
                <td>{{ $p->label }}</td>
                <td><code>{{ $p->slug }}</code></td>
                <td>{!! $p->is_system ? '<span class="badge text-bg-primary">System</span>' : '<span class="badge text-bg-secondary">Custom</span>' !!}</td>
                <td><x-status-badge :status="$p->is_active ? 'active' : 'inactive'" /></td>
                <td class="text-end text-nowrap">
                    <a href="{{ route('admin.permissions.edit', $p) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                    @unless ($p->is_system)
                        <form method="POST" action="{{ route('admin.permissions.destroy', $p) }}" class="d-inline" data-confirm="Delete this permission?">@csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                    @endunless
                </td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
</div></div>
@endforeach
@endsection
