@extends('layouts.app')
@section('title', $plan->exists ? 'Edit Plan' : 'Add Plan')
@section('content')
@php $v = fn ($k) => old($k, $plan->$k); @endphp
<h3 class="page-title mb-3">{{ $plan->exists ? 'Edit Plan' : 'Add Subscription Plan' }}</h3>
<div class="card" style="max-width:760px"><div class="card-body">
    <form method="POST" action="{{ $plan->exists ? route('admin.plans.update', $plan) : route('admin.plans.store') }}">
        @csrf @if ($plan->exists) @method('PUT') @endif
        <div class="row g-3">
            <div class="col-12 col-md-6"><label class="form-label">Plan Name *</label><input name="name" class="form-control" value="{{ $v('name') }}" required></div>
            <div class="col-12 col-md-6"><label class="form-label">Description</label><input name="description" class="form-control" value="{{ $v('description') }}"></div>
            <div class="col-6"><label class="form-label">Monthly Price (BDT) *</label><input type="number" step="0.01" min="0" name="monthly_price" class="form-control" value="{{ $v('monthly_price') ?? 0 }}" required></div>
            <div class="col-6"><label class="form-label">Annual Price (BDT) *</label><input type="number" step="0.01" min="0" name="annual_price" class="form-control" value="{{ $v('annual_price') ?? 0 }}" required></div>
            <div class="col-12 col-md-4"><label class="form-label">Maximum Products * <small class="text-muted">(0 = unlimited)</small></label><input type="number" min="0" name="max_products" class="form-control" value="{{ $v('max_products') ?? 0 }}" required></div>
            <div class="col-12 col-md-4"><label class="form-label">Storage Limit (MB) *</label><input type="number" min="1" name="storage_limit_mb" class="form-control" value="{{ $v('storage_limit_mb') }}" required></div>
            <div class="col-12 col-md-4"><label class="form-label">Maximum Users *</label><input type="number" min="1" name="max_users" class="form-control" value="{{ $v('max_users') }}" required></div>
            <div class="col-12">
                @foreach (['reminder_enabled' => 'Reminder feature enabled', 'document_upload_enabled' => 'Document upload enabled', 'is_active' => 'Plan active'] as $f => $l)
                    <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" name="{{ $f }}" value="1" id="{{ $f }}" @checked(old($f, $plan->$f))><label class="form-check-label" for="{{ $f }}">{{ $l }}</label></div>
                @endforeach
            </div>
        </div>
        <div class="mt-4 d-flex gap-2"><button class="btn btn-primary"><i class="bi bi-check-lg"></i> Save Plan</button><a href="{{ route('admin.plans.index') }}" class="btn btn-outline-secondary">Cancel</a></div>
    </form>
</div></div>
@endsection
