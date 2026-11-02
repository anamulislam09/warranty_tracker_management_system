@extends('layouts.app')
@section('title', 'Payments')
@section('content')
    @php
        $m = fn($v) => \App\Models\Invoice::money($v);
        $fmt = config('warranty.date_format', 'd/m/y');
    @endphp
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h3 class="page-title mb-1">Payments</h3>
            <p class="text-muted mb-0">Every payment received: which customer, how much, which date, and which bills it paid.</p>
        </div>
        <a href="{{ route('admin.payments.create') }}" class="btn btn-success"><i class="bi bi-plus-lg"></i> Receive Payment</a>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2">
                <div class="col-12 col-md-3">
                    <select name="account_id" class="form-select">
                        <option value="">All Customers</option>
                        @foreach ($accounts as $a)
                            <option value="{{ $a->id }}" @selected(request('account_id') == $a->id)>{{ $a->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <select name="month" class="form-select">
                        <option value="">Bill period</option>
                        @foreach ($months as $v => $l)
                            <option value="{{ $v }}" @selected(request('month') === $v)>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <select name="method" class="form-select">
                        <option value="">All Methods</option>
                        @foreach (config('billing.methods') as $v => $l)
                            <option value="{{ $v }}" @selected(request('method') === $v)>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2"><input type="date" name="from" class="form-control" value="{{ request('from') }}" placeholder="Paid from"></div>
                <div class="col-6 col-md-2"><input type="date" name="to" class="form-control" value="{{ request('to') }}" placeholder="Paid to"></div>
                <div class="col-12 col-md-1 d-flex gap-2"><button class="btn btn-primary flex-fill">Go</button><a
                        href="{{ route('admin.payments.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-repeat"></i></a></div>
            </form>
        </div>
    </div>

    <div class="mb-2 fw-semibold">Total received (filtered): <span class="text-success">{{ $m($total) }}</span></div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr><th>Paid On</th><th>Customer</th><th class="text-end">Amount</th><th>Applied to</th><th>Method</th><th>Reference</th><th>Received by</th><th class="text-end">Actions</th></tr>
                </thead>
                <tbody>
                    @forelse ($payments as $p)
                        <tr>
                            <td>{{ $p->paid_on->format($fmt) }}</td>
                            <td>{{ $p->account->name }}</td>
                            <td class="text-end fw-semibold">{{ $m($p->amount) }}</td>
                            <td class="small">
                                @foreach ($p->allocations as $a)
                                    <div><a href="{{ route('admin.invoices.show', $a->invoice_id) }}" class="text-decoration-none">{{ $a->invoice->period_label }}</a> <span class="text-muted">{{ $m($a->amount) }}</span></div>
                                @endforeach
                                @if ($p->unallocated > 0)
                                    <div><span class="badge text-bg-info">Advance {{ $m($p->unallocated) }}</span></div>
                                @endif
                            </td>
                            <td>{{ config('billing.methods')[$p->method] ?? $p->method }}</td>
                            <td>{{ $p->reference ?: '-' }}</td>
                            <td>{{ optional($p->receiver)->name ?: 'online' }}</td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('admin.payments.edit', $p) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                                <form method="POST" action="{{ route('admin.payments.destroy', $p) }}" class="d-inline" data-confirm="Delete this payment?">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">No payments found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $payments->links() }}</div>
@endsection
