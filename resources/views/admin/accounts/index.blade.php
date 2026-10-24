@extends('layouts.app')
@section('title', 'Customers')
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div><h3 class="page-title mb-1">Customers</h3><p class="text-muted mb-0">Approve, create and manage your SaaS customers.</p></div>
    <a href="{{ route('admin.accounts.create') }}" class="btn btn-primary"><i class="bi bi-person-plus"></i> Add Customer</a>
</div>
<div class="card mb-3"><div class="card-body">
    <form method="GET" class="row g-2">
        <div class="col-12 col-md-5"><input name="q" value="{{ request('q') }}" class="form-control" placeholder="Search by name, email or phone..."></div>
        <div class="col-6 col-md-2"><select name="status" class="form-select"><option value="">All Status</option>@foreach (['pending','active','inactive'] as $s)<option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>@endforeach</select></div>
        <div class="col-6 col-md-3"><select name="plan_id" class="form-select"><option value="">All Plans</option>@foreach ($plans as $p)<option value="{{ $p->id }}" @selected(request('plan_id') == $p->id)>{{ $p->name }}</option>@endforeach</select></div>
        <div class="col-12 col-md-2 d-flex gap-2"><button class="btn btn-primary flex-fill">Filter</button><a href="{{ route('admin.accounts.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-repeat"></i></a></div>
    </form>
</div></div>
<div class="card"><div class="table-responsive">
    <table class="table table-hover mb-0">
        <thead><tr><th>Customer</th><th>Phone</th><th>Country</th><th>Type</th><th>Plan</th><th>Status</th><th>Registered</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        @forelse ($accounts as $account)
            <tr>
                <td><a href="{{ route('admin.accounts.show', $account) }}" class="fw-semibold text-decoration-none">{{ $account->name }}</a><br><small class="text-muted">{{ $account->email }}</small></td>
                <td>{{ $account->phone ?: '-' }}</td>
                <td>{{ config('warranty.countries')[$account->country_code] ?? $account->country_code }}</td>
                <td>{{ ucfirst($account->account_type) }}</td>
                <td>{{ $account->plan->name ?? '-' }}</td>
                <td><x-status-badge :status="$account->status" /></td>
                <td>{{ $account->created_at->format(config('warranty.date_format')) }}</td>
                <td class="text-end text-nowrap">
                    @include('admin.accounts._status_buttons')
                    <a href="{{ route('admin.accounts.edit', $account) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                    <form method="POST" action="{{ route('admin.accounts.destroy', $account) }}" class="d-inline" data-confirm="Delete this customer and ALL of its data?">@csrf @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                </td>
            </tr>
        @empty
            <tr><td colspan="8" class="text-center text-muted py-4">No customers found.</td></tr>
        @endforelse
        </tbody>
    </table>
</div></div>
<div class="mt-3 d-flex justify-content-center">{{ $accounts->links() }}</div>
@endsection
