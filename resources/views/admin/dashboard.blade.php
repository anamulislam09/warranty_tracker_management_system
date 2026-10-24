@extends('layouts.app')
@section('title', 'Admin Dashboard')
@section('content')
<h3 class="page-title mb-1">Admin Dashboard</h3>
<p class="text-muted">Overview of customers, products and warranties.</p>

<div class="row g-3 mb-3">
    @foreach ([['Total Customers',$stats['accounts'],'bi-people','primary'],['Pending Approval',$stats['pending'],'bi-hourglass-split','warning'],['Active Customers',$stats['active'],'bi-person-check','success'],['Inactive',$stats['inactive'],'bi-person-x','danger'],
               ['Total Users',$stats['users'],'bi-person-badge','info'],['Total Products',$stats['products'],'bi-box-seam','primary'],['Outstanding Bills','৳'.number_format($stats['billing_due']),'bi-cash-stack','danger'],['Overdue Customers',$stats['overdue_customers'],'bi-exclamation-triangle','warning'],['Active Warranties',$stats['w_active'],'bi-shield-check','success'],['Expiring Soon',$stats['w_expiring'],'bi-clock-history','warning']] as [$l,$v,$i,$c])
        <div class="col-6 col-lg-3"><div class="card stat-card h-100"><div class="card-body d-flex align-items-center gap-3">
            <div class="icon bg-{{ $c }}-subtle text-{{ $c }}"><i class="bi {{ $i }}"></i></div>
            <div><div class="text-muted small">{{ $l }}</div><div class="num">{{ $v }}</div></div>
        </div></div></div>
    @endforeach
</div>

@if ($pending->isNotEmpty())
<div class="card mb-3 border border-warning"><div class="card-body">
    <h6 class="section-title mb-3"><i class="bi bi-hourglass-split text-warning"></i> Waiting for approval ({{ $pending->count() }})</h6>
    <div class="table-responsive"><table class="table mb-0">
        <thead><tr><th>Name</th><th>Email</th><th>Type</th><th>Plan</th><th>Registered</th><th></th></tr></thead>
        <tbody>
        @foreach ($pending as $account)
            <tr><td><a href="{{ route('admin.accounts.show', $account) }}" class="fw-semibold text-decoration-none">{{ $account->name }}</a></td>
                <td>{{ $account->email }}</td><td>{{ ucfirst($account->account_type) }}</td><td>{{ $account->plan->name ?? '-' }}</td>
                <td>{{ $account->created_at->format(config('warranty.date_format')) }}</td>
                <td class="text-end">@include('admin.accounts._status_buttons')</td></tr>
        @endforeach
        </tbody></table></div>
</div></div>
@endif

<div class="row g-3">
    <div class="col-lg-7"><div class="card h-100"><div class="card-body">
        <div class="d-flex justify-content-between mb-3"><h6 class="section-title mb-0">Latest Customers</h6><a href="{{ route('admin.accounts.index') }}" class="small">View all</a></div>
        <div class="table-responsive"><table class="table mb-0">
            <thead><tr><th>Name</th><th>Type</th><th>Plan</th><th>Status</th><th>Joined</th></tr></thead>
            <tbody>@foreach ($latest as $a)
                <tr><td><a href="{{ route('admin.accounts.show', $a) }}" class="text-decoration-none fw-semibold">{{ $a->name }}</a><br><small class="text-muted">{{ $a->email }}</small></td>
                    <td>{{ ucfirst($a->account_type) }}</td><td>{{ $a->plan->name ?? '-' }}</td><td><x-status-badge :status="$a->status" /></td><td>{{ $a->created_at->format(config('warranty.date_format')) }}</td></tr>
            @endforeach</tbody></table></div>
    </div></div></div>
    <div class="col-lg-5"><div class="card h-100"><div class="card-body">
        <h6 class="section-title mb-3">Customers per Plan</h6>
        @foreach ($plans as $p)
            <div class="d-flex justify-content-between border-bottom py-2"><span>{{ $p->name }}</span><span class="badge text-bg-primary">{{ $p->accounts_count }}</span></div>
        @endforeach
    </div></div></div>
    <div class="col-12"><div class="card"><div class="card-body">
        <h6 class="section-title mb-3">Expiring Warranties (next 30 days, all customers)</h6>
        <div class="table-responsive"><table class="table mb-0">
            <thead><tr><th>Product</th><th>Component</th><th>Customer</th><th>Expiry</th><th>Days left</th></tr></thead>
            <tbody>@forelse ($expiring as $w)
                <tr><td>{{ $w->product->name }}</td><td>{{ $w->component }}</td><td>{{ $w->product->account->name ?? '-' }}</td>
                    <td>{{ $w->end_date->format(config('warranty.date_format')) }}</td><td><span class="badge text-bg-warning">{{ $w->days_remaining }}</span></td></tr>
            @empty<tr><td colspan="5" class="text-center text-muted">Nothing expiring soon.</td></tr>@endforelse</tbody></table></div>
    </div></div></div>
</div>
@endsection
