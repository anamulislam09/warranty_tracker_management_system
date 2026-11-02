@extends('layouts.app')
@section('title', 'Bills')
@section('content')
    @php
        $m = fn($v) => \App\Models\Invoice::money($v);
        $fmt = config('warranty.date_format', 'd/m/y');
    @endphp
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h3 class="page-title mb-1">Bills (Invoices)</h3>
            <p class="text-muted mb-0">One bill per customer per billing period (monthly, quarterly, half-yearly or yearly).</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.payments.create') }}" class="btn btn-success"><i class="bi bi-cash-coin"></i> Receive
                Payment</a>
            <a href="{{ route('admin.invoices.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> New
                Bill</a>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2">
                <div class="col-12 col-md-3"><input name="q" value="{{ request('q') }}" class="form-control"
                        placeholder="Bill no, customer name / email"></div>
                <div class="col-6 col-md-3">
                    <select name="account_id" class="form-select">
                        <option value="">All Customers</option>
                        @foreach ($accounts as $a)
                            <option value="{{ $a->id }}" @selected(request('account_id') == $a->id)>{{ $a->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <select name="month" class="form-select">
                        <option value="">Period starts</option>
                        @foreach ($months as $v => $l)
                            <option value="{{ $v }}" @selected(request('month') === $v)>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        @foreach (['unpaid' => 'Unpaid', 'partial' => 'Partial', 'paid' => 'Paid', 'overdue' => 'Overdue', 'cancelled' => 'Cancelled'] as $v => $l)
                            <option value="{{ $v }}" @selected(request('status') === $v)>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2 d-flex gap-2"><button class="btn btn-primary flex-fill">Filter</button><a
                        href="{{ route('admin.invoices.index') }}" class="btn btn-outline-secondary"><i
                            class="bi bi-arrow-repeat"></i></a></div>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-4">
            <div class="card">
                <div class="card-body py-2">
                    <div class="text-muted small">Billed</div>
                    <div class="fw-bold">{{ $m($totals->billed) }}</div>
                </div>
            </div>
        </div>
        <div class="col-4">
            <div class="card">
                <div class="card-body py-2">
                    <div class="text-muted small">Collected</div>
                    <div class="fw-bold text-success">{{ $m($totals->paid) }}</div>
                </div>
            </div>
        </div>
        <div class="col-4">
            <div class="card">
                <div class="card-body py-2">
                    <div class="text-muted small">Due</div>
                    <div class="fw-bold text-danger">{{ $m($totals->billed - $totals->paid) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Bill No</th>
                        <th>Customer</th>
                        <th>Period</th>
                        <th>Plan / Cycle</th>
                        <th class="text-end">Amount</th>
                        <th class="text-end">Paid</th>
                        <th class="text-end">Balance</th>
                        <th>Due Date</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($invoices as $i)
                        <tr>
                            <td><a href="{{ route('admin.invoices.show', $i) }}"
                                    class="fw-semibold text-decoration-none">{{ $i->invoice_no }}</a></td>
                            <td><a href="{{ route('admin.accounts.show', $i->account_id) }}"
                                    class="text-decoration-none">{{ $i->account->name }}</a></td>
                            <td>{{ $i->period_label }}</td>
                            <td>{{ $i->plan_name }}<br><small class="text-muted">{{ $i->cycle_label }}</small></td>
                            <td class="text-end">{{ $m($i->amount) }}@if ((float) $i->discount_percent > 0)<br><small class="text-success">{{ (float) $i->discount_percent }}% off</small>@endif</td>
                            <td class="text-end text-success">{{ $m($i->paid_amount) }}</td>
                            <td class="text-end {{ $i->balance > 0 && $i->status !== 'cancelled' ? 'text-danger fw-semibold' : '' }}">
                                {{ $i->status === 'cancelled' ? '-' : $m($i->balance) }}</td>
                            <td>{{ $i->due_date->format($fmt) }}</td>
                            <td><x-status-badge :status="$i->display_status" /></td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('admin.payments.create', ['invoice_id' => $i->id]) }}" class="btn btn-sm btn-success" title="Add payment"><i class="bi bi-cash-coin"></i></a>
                                <a href="{{ route('admin.invoices.show', $i) }}" class="btn btn-sm btn-outline-primary" title="View"><i class="bi bi-eye"></i></a>
                                <a href="{{ route('admin.invoices.edit', $i) }}" class="btn btn-sm btn-outline-secondary" title="Edit"><i class="bi bi-pencil"></i></a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center text-muted py-4">No bills found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $invoices->links() }}</div>
@endsection
