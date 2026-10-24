@extends('layouts.app')
@section('title', $account->name)
@section('content')
@php
    $plan = $account->plan;
    $used = $account->storageUsedBytes();
    $prod = $account->productCount();
@endphp
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div><h3 class="page-title mb-1">{{ $account->name }} <x-status-badge :status="$account->status" /></h3>
        <p class="text-muted mb-0">{{ $account->email }} @if($account->phone) &middot; {{ $account->phone }} @endif</p></div>
    <div class="d-flex gap-2 flex-wrap">@include('admin.accounts._status_buttons')
        <a href="{{ route('admin.accounts.edit', $account) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i> Edit</a></div>
</div>
<div class="row g-3">
    <div class="col-lg-6"><div class="card h-100"><div class="card-body">
        <h6 class="section-title mb-3">Details</h6>
        <table class="table table-sm mb-0"><tbody>
            <tr><th class="text-muted fw-normal">Type</th><td>{{ ucfirst($account->account_type) }}</td></tr>
            <tr><th class="text-muted fw-normal">Country</th><td>{{ config('warranty.countries')[$account->country_code] ?? $account->country_code }}</td></tr>
            <tr><th class="text-muted fw-normal">Registered</th><td>{{ $account->created_at->format(config('warranty.datetime_format')) }}</td></tr>
            <tr><th class="text-muted fw-normal">Approved</th><td>{{ optional($account->approved_at)->format(config('warranty.datetime_format')) ?: '-' }}</td></tr>
            <tr><th class="text-muted fw-normal">Plan</th><td>{{ $plan->name ?? '-' }} ({{ ucfirst($account->billing_cycle) }})</td></tr>
            <tr><th class="text-muted fw-normal">Plan valid until</th><td>{{ optional($account->plan_ends_at)->format(config('warranty.date_format')) ?: '-' }}</td></tr>
            <tr><th class="text-muted fw-normal">Notes</th><td>{{ $account->notes ?: '-' }}</td></tr>
        </tbody></table>
    </div></div></div>
    <div class="col-lg-6"><div class="card h-100"><div class="card-body">
        <h6 class="section-title mb-3">Usage vs plan</h6>
        @if ($plan)
            @php $pp = $plan->max_products ? min(100, round($prod / $plan->max_products * 100)) : 0; $sp = min(100, round($used / ($plan->storage_limit_mb * 1048576) * 100)); $up = min(100, round($account->users->count() / $plan->max_users * 100)); @endphp
            <div class="small d-flex justify-content-between"><span>Products</span><span>{{ $prod }} / {{ $plan->max_products ?: 'Unlimited' }}</span></div>
            <div class="progress mb-3" style="height:8px"><div class="progress-bar" style="width:{{ $pp }}%"></div></div>
            <div class="small d-flex justify-content-between"><span>Storage</span><span>{{ round($used / 1048576, 1) }} MB / {{ $plan->storage_limit_mb }} MB</span></div>
            <div class="progress mb-3" style="height:8px"><div class="progress-bar bg-info" style="width:{{ $sp }}%"></div></div>
            <div class="small d-flex justify-content-between"><span>Users</span><span>{{ $account->users->count() }} / {{ $plan->max_users }}</span></div>
            <div class="progress" style="height:8px"><div class="progress-bar bg-success" style="width:{{ $up }}%"></div></div>
        @else <p class="text-muted">No plan assigned.</p> @endif
    </div></div></div>
    <div class="col-12"><div class="card"><div class="card-body">
        <h6 class="section-title mb-3">Users ({{ $account->users->count() }})</h6>
        <div class="table-responsive"><table class="table mb-0">
            <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Last login</th></tr></thead>
            <tbody>@foreach ($account->users as $u)
                <tr><td>{{ $u->name }}</td><td>{{ $u->email }}</td><td>{{ ucfirst($u->role) }}</td><td><x-status-badge :status="$u->status" /></td><td>{{ optional($u->last_login_at)->format(config('warranty.datetime_format')) ?: '-' }}</td></tr>
            @endforeach</tbody></table></div>
    </div></div></div>
</div>
@endsection
