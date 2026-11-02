@extends('layouts.app')
@section('title', $invoice->invoice_no)
@section('content')
    @php
        $m = fn($v) => \App\Models\Invoice::money($v);
        $fmt = config('warranty.date_format', 'd/m/y');
    @endphp
    <nav class="small mb-2"><a href="{{ route('admin.invoices.index') }}" class="text-decoration-none"><i
                class="bi bi-receipt"></i> Bills</a> &rsaquo; {{ $invoice->invoice_no }}</nav>

    <div class="card mb-3">
        <div class="card-body d-flex flex-column flex-md-row justify-content-between gap-3">
            <div>
                <h3 class="page-title mb-1">{{ $invoice->invoice_no }} <x-status-badge :status="$invoice->display_status" /></h3>
                <div class="text-muted"><a href="{{ route('admin.accounts.show', $invoice->account_id) }}"
                        class="text-decoration-none">{{ $invoice->account->name }}</a> &middot; {{ $invoice->period_label }}
                    &middot; {{ $invoice->plan_name }} plan &middot; {{ $invoice->cycle_label }}</div>
                @if ($advance > 0)
                    <div class="mt-1"><span class="badge text-bg-info">Customer advance balance: {{ $m($advance) }}</span></div>
                @endif
            </div>
            <div class="d-flex flex-wrap gap-2 align-items-start">
                <a href="{{ route('admin.payments.create', ['invoice_id' => $invoice->id]) }}" class="btn btn-success"><i class="bi bi-cash-coin"></i> Add Payment</a>
                <a href="{{ route('admin.invoices.edit', $invoice) }}" class="btn btn-primary"><i class="bi bi-pencil"></i> Edit</a>
                @if ($invoice->status !== 'cancelled')
                    <form method="POST" action="{{ route('admin.invoices.cancel', $invoice) }}"
                        data-confirm="Cancel (waive) this bill? The customer will not have to pay it." data-confirm-button="Yes, cancel bill">
                        @csrf @method('PATCH')
                        <button class="btn btn-outline-secondary"><i class="bi bi-slash-circle"></i> Cancel Bill</button>
                    </form>
                @endif
                @unless ($invoice->allocations->count())
                    <form method="POST" action="{{ route('admin.invoices.destroy', $invoice) }}" data-confirm="Delete this bill?">
                        @csrf @method('DELETE')
                        <button class="btn btn-outline-danger"><i class="bi bi-trash"></i> Delete</button>
                    </form>
                @endunless
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-lg-5">
            <div class="card h-100">
                <div class="card-body">
                    <h6 class="fw-semibold mb-3"><i class="bi bi-receipt text-primary"></i> Bill details</h6>
                    <table class="table table-sm mb-0">
                        <tbody>
                            <tr><th class="text-muted fw-normal">Period</th><td>{{ $invoice->period_label }} ({{ $invoice->months }} {{ \Illuminate\Support\Str::plural('month', $invoice->months) }})</td></tr>
                            <tr><th class="text-muted fw-normal">Plan</th><td>{{ $invoice->plan_name }}</td></tr>
                            <tr><th class="text-muted fw-normal">Price before discount</th><td>{{ $m($invoice->base_amount) }}</td></tr>
                            <tr><th class="text-muted fw-normal">Discount</th><td>@if ((float) $invoice->discount_amount > 0)<span class="text-success">- {{ $m($invoice->discount_amount) }} ({{ (float) $invoice->discount_percent }}%)</span>@else - @endif</td></tr>
                            <tr><th class="text-muted fw-normal">Amount</th><td class="fw-semibold">{{ $m($invoice->amount) }}</td></tr>
                            <tr><th class="text-muted fw-normal">Paid</th><td class="text-success fw-semibold">{{ $m($invoice->paid_amount) }}</td></tr>
                            <tr><th class="text-muted fw-normal">Balance</th><td class="{{ $invoice->balance > 0 && $invoice->status !== 'cancelled' ? 'text-danger' : '' }} fw-semibold">{{ $invoice->status === 'cancelled' ? '-' : $m($invoice->balance) }}</td></tr>
                            <tr><th class="text-muted fw-normal">Due date</th><td>{{ $invoice->due_date->format($fmt) }}</td></tr>
                            <tr><th class="text-muted fw-normal">Created</th><td>{{ $invoice->created_at->format(config('warranty.datetime_format', 'd/m/y h:i A')) }} &middot; {{ optional($invoice->creator)->name ?: 'automatic' }}</td></tr>
                        </tbody>
                    </table>
                    @if ($invoice->note)
                        <p class="small text-muted mt-2 mb-0">{{ $invoice->note }}</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-7">
            <div class="card h-100">
                <div class="card-body">
                    <h6 class="fw-semibold mb-3"><i class="bi bi-cash-coin text-success"></i> Payments applied to this bill ({{ $invoice->allocations->count() }})</h6>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead><tr><th>Paid on</th><th class="text-end">Applied</th><th class="text-end">Payment</th><th>Method</th><th>Reference</th><th class="text-end"></th></tr></thead>
                            <tbody>
                                @forelse ($invoice->allocations as $a)
                                    @php $p = $a->payment; @endphp
                                    <tr>
                                        <td>{{ $p->paid_on->format($fmt) }}</td>
                                        <td class="text-end fw-semibold">{{ $m($a->amount) }}</td>
                                        <td class="text-end text-muted">{{ $m($p->amount) }}</td>
                                        <td>{{ config('billing.methods')[$p->method] ?? $p->method }}</td>
                                        <td>{{ $p->reference ?: '-' }}</td>
                                        <td class="text-end text-nowrap">
                                            <a href="{{ route('admin.payments.edit', $p) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                                            <form method="POST" action="{{ route('admin.payments.destroy', $p) }}" class="d-inline" data-confirm="Delete this payment?">
                                                @csrf @method('DELETE')
                                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="text-center text-muted py-3">No payment applied yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <p class="small text-muted mt-2 mb-0">A payment is applied to the oldest unpaid bill first. The "Payment" column is the full amount received.</p>
                </div>
            </div>
        </div>
    </div>
@endsection
