@extends('layouts.app')
@section('title', 'Subscription Plans')
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div><h3 class="page-title mb-1">Subscription Plans</h3><p class="text-muted mb-0">Customers get access according to their plan.</p></div>
    <a href="{{ route('admin.plans.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Add Plan</a>
</div>
<div class="card"><div class="table-responsive">
    <table class="table table-hover mb-0">
        <thead><tr><th>Plan</th><th>Monthly</th><th>Annual</th><th>Products</th><th>Storage</th><th>Users</th><th>Reminders</th><th>Documents</th><th>Customers</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        @foreach ($plans as $p)
            <tr>
                <td><strong>{{ $p->name }}</strong><br><small class="text-muted">{{ $p->description }}</small></td>
                <td>৳{{ number_format($p->monthly_price) }}</td><td>৳{{ number_format($p->annual_price) }}</td>
                <td>{{ $p->max_products ?: 'Unlimited' }}</td><td>{{ $p->storage_limit_mb >= 1024 ? round($p->storage_limit_mb / 1024, 1).' GB' : $p->storage_limit_mb.' MB' }}</td>
                <td>{{ $p->max_users }}</td>
                <td>{!! $p->reminder_enabled ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-x-circle text-muted"></i>' !!}</td>
                <td>{!! $p->document_upload_enabled ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-x-circle text-muted"></i>' !!}</td>
                <td>{{ $p->accounts_count }}</td>
                <td><x-status-badge :status="$p->is_active ? 'active' : 'inactive'" /></td>
                <td class="text-end text-nowrap">
                    <a href="{{ route('admin.plans.edit', $p) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                    <form method="POST" action="{{ route('admin.plans.destroy', $p) }}" class="d-inline" data-confirm="Delete this plan?">@csrf @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div></div>
<p class="small text-muted mt-2">Max products: 0 means unlimited.</p>
@endsection
