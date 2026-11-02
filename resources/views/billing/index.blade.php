@extends('layouts.app')
@section('title', 'Billing')
@section('content')
    @php
        $m = fn($v) => \App\Models\Invoice::money($v);
        $fmt = config('warranty.date_format', 'd/m/y');
        $cycle = config('billing.cycles')[$account->billing_cycle] ?? null;
    @endphp
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h3 class="page-title mb-1">Billing</h3>
            <p class="text-muted mb-0">Your bills, payments, dues and advance balance.</p>
        </div>
    </div>

    @if ($overdueTotal > 0)
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <strong>Payment overdue: {{ $m($overdueTotal) }}.</strong>
            Your account is restricted &mdash; you can only use this Billing page until you pay.
            @if ($suspendOn)
                Please pay before <strong>{{ $suspendOn->format($fmt) }}</strong>, otherwise your account will be suspended and you will not be able to login.
            @endif
        </div>
    @endif

    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3">
            <div class="card stat-card h-100"><div class="card-body d-flex align-items-center gap-3">
                <div class="icon bg-primary-subtle text-primary"><i class="bi bi-gem"></i></div>
                <div><div class="text-muted small">Plan</div>
                    <div class="fw-bold">{{ optional($account->plan)->name ?: '-' }}</div>
                    <div class="small text-muted">{{ $account->cycleLabel() }}</div></div>
            </div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card stat-card h-100"><div class="card-body d-flex align-items-center gap-3">
                <div class="icon bg-warning-subtle text-warning"><i class="bi bi-hourglass-split"></i></div>
                <div><div class="text-muted small">Outstanding</div>
                    <div class="num" style="font-size:1.25rem">{{ $m($dueTotal) }}</div>
                    <div class="small text-muted">{{ $open->count() }} unpaid {{ \Illuminate\Support\Str::plural('bill', $open->count()) }}</div></div>
            </div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card stat-card h-100"><div class="card-body d-flex align-items-center gap-3">
                <div class="icon bg-info-subtle text-info"><i class="bi bi-piggy-bank"></i></div>
                <div><div class="text-muted small">Advance balance</div>
                    <div class="num" style="font-size:1.25rem">{{ $m($advance) }}</div>
                    <div class="small text-muted">used for your next bill</div></div>
            </div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card stat-card h-100"><div class="card-body d-flex align-items-center gap-3">
                <div class="icon bg-success-subtle text-success"><i class="bi bi-calendar-event"></i></div>
                <div><div class="text-muted small">Next bill</div>
                    @if ($nextStart && $quote)
                        <div class="fw-bold">{{ $m($quote['amount']) }}</div>
                        <div class="small text-muted">from {{ $nextStart->format($fmt) }}@if ($quote['discount_percent'] > 0) &middot; {{ (float) $quote['discount_percent'] }}% off @endif</div>
                    @else
                        <div class="fw-bold">-</div>
                    @endif
                </div>
            </div></div>
        </div>
    </div>

    {{-- ---------- pay online ---------- --}}
    <div class="card mb-3"><div class="card-body">
        <h6 class="fw-semibold mb-3"><i class="bi bi-credit-card text-primary"></i> Pay online</h6>

        @unless ($gatewayEnabled)
            <div class="alert alert-secondary py-2 small mb-3">Online payment is coming soon. Until then, please pay the administrator directly &mdash; your payment appears here as soon as it is recorded.</div>
        @endunless

        @forelse ($open as $i)
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 py-2 border-bottom">
                <div>
                    <div class="fw-semibold">{{ $i->period_label }} <x-status-badge :status="$i->display_status" /></div>
                    <div class="small text-muted">{{ $i->plan_name }} &middot; {{ $i->cycle_label }} &middot; due {{ $i->due_date->format($fmt) }}</div>
                </div>
                <form method="POST" action="{{ route('billing.pay') }}" class="d-flex align-items-center gap-2">
                    @csrf
                    <input type="hidden" name="invoice_id" value="{{ $i->id }}">
                    <input type="hidden" name="amount" value="{{ round($i->balance, 2) }}">
                    <span class="fw-bold">{{ $m($i->balance) }}</span>
                    <button class="btn btn-success btn-sm" @disabled(! $gatewayEnabled)><i class="bi bi-credit-card"></i> Pay online</button>
                </form>
            </div>
        @empty
            <p class="text-muted mb-3"><i class="bi bi-check-circle text-success"></i> You have no unpaid bill.</p>
        @endforelse

        <form method="POST" action="{{ route('billing.pay') }}" class="row g-2 align-items-end mt-2">
            @csrf
            <div class="col-12 col-md-4">
                <label class="form-label mb-1">Pay in advance ({{ config('billing.symbol') }})</label>
                <input type="number" step="0.01" min="1" name="amount" class="form-control" placeholder="Amount" required>
            </div>
            <div class="col-12 col-md-3">
                <button class="btn btn-outline-primary w-100" @disabled(! $gatewayEnabled)><i class="bi bi-piggy-bank"></i> Add advance</button>
            </div>
            <div class="col-12 col-md-5 small text-muted">An advance is kept for you and is used automatically when your next bill is created.</div>
        </form>
    </div></div>

    {{-- ---------- bills ---------- --}}
    <div class="card mb-3"><div class="card-body">
        <h6 class="fw-semibold mb-3"><i class="bi bi-receipt text-primary"></i> My bills</h6>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>Period</th><th>Plan / cycle</th><th class="text-end">Amount</th><th class="text-end">Paid</th><th class="text-end">Balance</th><th>Due date</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse ($invoices as $i)
                        <tr>
                            <td class="fw-semibold">{{ $i->period_label }}<br><small class="text-muted">{{ $i->invoice_no }}</small></td>
                            <td>{{ $i->plan_name }}<br><small class="text-muted">{{ $i->cycle_label }}</small></td>
                            <td class="text-end">{{ $m($i->amount) }}@if ((float) $i->discount_percent > 0)<br><small class="text-success">{{ (float) $i->discount_percent }}% discount</small>@endif</td>
                            <td class="text-end text-success">{{ $m($i->paid_amount) }}</td>
                            <td class="text-end {{ $i->balance > 0 && $i->status !== 'cancelled' ? 'text-danger fw-semibold' : '' }}">{{ $i->status === 'cancelled' ? '-' : $m($i->balance) }}</td>
                            <td>{{ $i->due_date->format($fmt) }}</td>
                            <td><x-status-badge :status="$i->display_status" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-3">No bills yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-2">{{ $invoices->links() }}</div>
    </div></div>

    {{-- ---------- payment history ---------- --}}
    <div class="card mb-3"><div class="card-body">
        <h6 class="fw-semibold mb-3"><i class="bi bi-cash-coin text-success"></i> Payment history</h6>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>Date</th><th class="text-end">Amount</th><th>Method</th><th>Reference</th><th>Used for</th></tr></thead>
                <tbody>
                    @forelse ($payments as $p)
                        <tr>
                            <td>{{ $p->paid_on->format($fmt) }}</td>
                            <td class="text-end fw-semibold">{{ $m($p->amount) }}</td>
                            <td>{{ config('billing.methods')[$p->method] ?? $p->method }}</td>
                            <td>{{ $p->reference ?: '-' }}</td>
                            <td class="small">
                                @foreach ($p->allocations as $a)
                                    <div>{{ $a->invoice->period_label }} <span class="text-muted">{{ $m($a->amount) }}</span></div>
                                @endforeach
                                @if ($p->unallocated > 0)<span class="badge text-bg-info">Advance {{ $m($p->unallocated) }}</span>@endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-3">No payment yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-2">{{ $payments->links() }}</div>
    </div></div>
@endsection
