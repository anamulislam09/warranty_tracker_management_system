@extends('layouts.app')
@section('title', $account->exists ? 'Edit Customer' : 'Add Customer')
@section('content')
    @php $v = fn ($k) => old($k, $account->$k); @endphp

    <div class="row">
        <div class="col-11 mx-auto">
            <div class="d-flex justify-content-between align-items-center">
                <h3 class="page-title mb-3">
                    {{ $account->exists ? 'Edit Customer' : 'Add Customer' }}</h3>
                <button class="btn btn-warning align-self-start align-self-sm-auto" onclick="window.history.back()"><i
                        class="bi bi-arrow-left"></i>
                    Back</button>
            </div>
        </div>
    </div>

    <form method="POST"
        action="{{ $account->exists ? route('admin.accounts.update', $account) : route('admin.accounts.store') }}">
        @csrf @if ($account->exists)
            @method('PUT')
        @endif
        <div class="card mb-3">
            <div class="card-body">
                <h6 class="section-title mb-3">Customer details</h6>
                <div class="row g-3">
                    <div class="col-12 col-md-6"><label class="form-label">Name *</label><input name="name"
                            class="form-control" value="{{ $v('name') }}" required></div>
                    <div class="col-12 col-md-6"><label class="form-label">Email *</label><input type="email"
                            name="email" class="form-control" value="{{ $v('email') }}" required></div>
                    <div class="col-12 col-md-4"><label class="form-label">Phone</label><input name="phone"
                            class="form-control" value="{{ $v('phone') }}"></div>
                    <div class="col-12 col-md-4"><label class="form-label">Country *</label>
                        <select name="country_code" class="form-select">
                            @foreach (config('warranty.countries') as $c => $n)
                                <option value="{{ $c }}" @selected($v('country_code') === $c)>{{ $n }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-4"><label class="form-label">Account Type *</label>
                        <select name="account_type" class="form-select">
                            @foreach (['personal', 'family', 'business'] as $t)
                                <option value="{{ $t }}" @selected($v('account_type') === $t)>{{ ucfirst($t) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-3"><label class="form-label">Plan</label>
                        <select name="plan_id" class="form-select">
                            <option value="">- No plan -</option>
                            @foreach ($plans as $p)
                                <option value="{{ $p->id }}" @selected($v('plan_id') == $p->id)>
                                    {{ $p->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-6 col-md-6 col-sm-12">
                        <label class="form-label">Billing Cycle *</label>
                        <select name="billing_cycle" class="form-select">
                            @foreach (config('billing.cycles') as $key => $cycle)
                                <option value="{{ $key }}" @selected(old('billing_cycle', $account->billing_cycle ?: 'monthly') === $key)>
                                    {{ $cycle['label'] }}@if ($cycle['discount'] > 0)
                                        - {{ $cycle['discount'] + 0 }}% discount
                                    @endif
                                </option>
                            @endforeach
                        </select>
                    </div>
                    {{-- <div class="col-6 col-md-3"><label class="form-label">Plan valid until</label>
                        <input type="date" name="plan_ends_at" class="form-control"
                            value="{{ old('plan_ends_at', optional($account->plan_ends_at)->toDateString()) }}">
                    </div> --}}
                    <div class="col-12 col-md-3"><label class="form-label">Status *</label>
                        <select name="status" class="form-select">
                            @foreach (['pending', 'active', 'inactive'] as $s)
                                <option value="{{ $s }}" @selected($v('status') === $s)>
                                    {{ ucfirst($s) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12"><label class="form-label">Internal notes</label>
                        <textarea name="notes" rows="2" class="form-control">{{ $v('notes') }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        @unless ($account->exists)
            <div class="card mb-3">
                <div class="card-body">
                    <h6 class="section-title mb-3">Login (customer owner)</h6>
                    <div class="row g-3">
                        <div class="col-12 col-md-4"><label class="form-label">Owner Name *</label><input name="owner_name"
                                class="form-control" value="{{ old('owner_name') }}" required></div>
                        <div class="col-12 col-md-4"><label class="form-label">Owner Email *</label><input type="email"
                                name="owner_email" class="form-control" value="{{ old('owner_email') }}" required></div>
                        <div class="col-12 col-md-4"><label class="form-label">Password *</label><input type="password"
                                name="owner_password" class="form-control" required></div>
                    </div>
                </div>
            </div>
        @endunless

        <div class="d-flex gap-2">
            <button class="btn btn-primary"><i class="bi bi-check-lg"></i> Save</button>
        </div>
    </form>
@endsection
