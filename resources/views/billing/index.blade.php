@extends('layouts.app')
@section('title', 'Billing')
@section('content')
    @php
        $m = fn($v) => \App\Models\Invoice::money($v);
        $fmt = config('warranty.date_format', 'd/m/y');
        $schedule = $account->schedule;
        $pct = $current && (float) $current->amount > 0 ? min(100, round((float) $current->paid_amount / (float) $current->amount * 100)) : 0;
    @endphp
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h3 class="page-title mb-1">Billing</h3>
            <p class="text-muted mb-0">Your payment schedule, bills, payments and invoices.</p>
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

    {{-- ---------- payment schedule: what it is, what is paid, what is due ---------- --}}
    <div class="row g-3 mb-3">
        <div class="col-12 col-lg-4">
            <div class="card h-100"><div class="card-body">
                <div class="text-muted small mb-1"><i class="bi bi-calendar-range text-primary"></i> Payment schedule</div>
                @if ($schedule)
                    <div class="fs-4 fw-bold">{{ $schedule->name }}</div>
                    <div class="small text-muted">One bill every {{ $schedule->months }} {{ $schedule->months == 1 ? 'month' : 'months' }}
                        @if ((float) $schedule->discount_percent > 0) &middot; <span class="text-success fw-semibold">{{ (float) $schedule->discount_percent }}% discount</span> @endif</div>
                    <div class="small mt-2">Plan: <strong>{{ optional($account->plan)->name ?: '-' }}</strong>
                        @if ($account->plan) <span class="text-muted">({{ $m($account->plan->monthly_price) }}/month)</span> @endif</div>
                @else
                    <div class="text-muted">No payment schedule yet. Please contact support.</div>
                @endif
            </div></div>
        </div>

        <div class="col-12 col-lg-5">
            <div class="card h-100"><div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="text-muted small mb-1"><i class="bi bi-receipt text-primary"></i> Current bill</div>
                    @if ($current)<x-status-badge :status="$current->display_status" />@endif
                </div>
                @if ($current)
                    <div class="fw-bold">{{ $current->period_label }}</div>
                    <div class="row text-center my-2">
                        <div class="col-4"><div class="small text-muted">Bill</div><div class="fw-bold">{{ $m($current->amount) }}</div></div>
                        <div class="col-4"><div class="small text-muted">Paid</div><div class="fw-bold text-success">{{ $m($current->paid_amount) }}</div></div>
                        <div class="col-4"><div class="small text-muted">Due</div><div class="fw-bold {{ $current->balance > 0 ? 'text-danger' : 'text-success' }}">{{ $m($current->balance) }}</div></div>
                    </div>
                    <div class="progress" style="height:10px"><div class="progress-bar bg-success" style="width:{{ $pct }}%"></div></div>
                    <div class="d-flex justify-content-between small text-muted mt-1">
                        <span>{{ $pct }}% paid</span><span>Pay by {{ $current->due_date->format($fmt) }}</span>
                    </div>
                @else
                    <div class="text-muted">No bill yet.</div>
                @endif
            </div></div>
        </div>

        <div class="col-12 col-lg-3">
            <div class="card h-100"><div class="card-body">
                <div class="text-muted small mb-1"><i class="bi bi-calendar-event text-primary"></i> Next bill</div>
                @if (isset($draft['quote']))
                    <div class="fw-bold">{{ $draft['period_label'] }}</div>
                    <div>{{ $m($draft['quote']['amount']) }}</div>
                    @if (! empty($draft['free_until']))
                        <div class="small text-success fw-semibold mt-1"><i class="bi bi-gift"></i> Free use until {{ $draft['free_until']->format($fmt) }}</div>
                    @endif
                    <div class="small text-muted mt-1">
                        @if ($draft['can']) Ready: it is created today.
                        @else It is created on <strong>{{ $draft['opens_on']->format($fmt) }}</strong> and sent to your email. @endif
                    </div>
                @else
                    <div class="text-muted small">{{ $draft['message'] ?? '-' }}</div>
                @endif
            </div></div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3">
            <div class="card stat-card h-100"><div class="card-body d-flex align-items-center gap-3">
                <div class="icon bg-success-subtle text-success"><i class="bi bi-cash-coin"></i></div>
                <div><div class="text-muted small">Total paid</div><div class="num" style="font-size:1.2rem">{{ $m($paidTotal) }}</div></div>
            </div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card stat-card h-100"><div class="card-body d-flex align-items-center gap-3">
                <div class="icon bg-warning-subtle text-warning"><i class="bi bi-hourglass-split"></i></div>
                <div><div class="text-muted small">Total due</div><div class="num" style="font-size:1.2rem">{{ $m($dueTotal) }}</div>
                    <div class="small text-muted">{{ $open->count() }} unpaid {{ \Illuminate\Support\Str::plural('bill', $open->count()) }}</div></div>
            </div></div>
        </div>
    </div>

    {{-- ---------- pay online (one bill at a time) ---------- --}}
    <div class="card mb-3"><div class="card-body">
        <h6 class="fw-semibold mb-3"><i class="bi bi-credit-card text-primary"></i> Pay online</h6>

        @unless ($gatewayEnabled)
            <div class="alert alert-secondary py-2 small mb-3">Online payment is coming soon. Until then, please pay the administrator directly &mdash; your payment appears here as soon as it is recorded.</div>
        @endunless

        @forelse ($open as $i)
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 py-2 border-bottom">
                <div>
                    <div class="fw-semibold">{{ $i->period_label }} <x-status-badge :status="$i->display_status" /></div>
                    <div class="small text-muted">{{ $i->cycle_label }} &middot; due {{ $i->due_date->format($fmt) }} &middot; balance {{ $m($i->balance) }}</div>
                </div>
                <form method="POST" action="{{ route('Payment.create_payment') }}" class="d-flex align-items-center gap-2">
                    @csrf
                    <input type="hidden" name="invoice_id" value="{{ $i->id }}">
                    <button class="btn btn-success btn-sm text-nowrap" @disabled(! $gatewayEnabled)><i class="bi bi-credit-card"></i> Pay {{ $m($i->balance) }}</button>
                </form>
            </div>
        @empty
            <p class="text-muted mb-0"><i class="bi bi-check-circle text-success"></i> You have no unpaid bill.</p>
        @endforelse
        <p class="small text-muted mt-2 mb-0">You pay the whole balance of a bill; the amount cannot be changed.</p>
    </div></div>

    {{-- ---------- my online payments (what the gateway answered) ---------- --}}
    @if ($attempts->isNotEmpty())
        <div class="card mb-3"><div class="card-body">
            <h6 class="fw-semibold mb-3"><i class="bi bi-credit-card-2-front text-primary"></i> My online payments</h6>
            @foreach ($attempts as $a)
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 py-2 border-bottom">
                    <div>
                        <div class="fw-semibold">{{ $m($a->amount) }} <x-status-badge :status="$a->status" /></div>
                        <div class="small text-muted">{{ $a->invoice->period_label }} &middot; {{ $a->created_at->format(config('warranty.datetime_format', 'd/m/y h:i A')) }} &middot; ref {{ $a->tran_id }}</div>
                    </div>
                    @if (in_array($a->status, ['pending', 'initiated', 'failed', 'cancelled', 'expired']))
                        <form method="POST" action="{{ route('billing.online.check', $a) }}">
                            @csrf
                            <button class="btn btn-sm btn-outline-primary text-nowrap" title="Ask the gateway again"><i class="bi bi-arrow-repeat"></i> Check status</button>
                        </form>
                    @endif
                </div>
            @endforeach
            <p class="small text-muted mt-2 mb-0">Paid but the bill is not updated? Press "Check status": we ask the payment gateway and update your bill if it was paid.</p>
        </div></div>
    @endif

    {{-- ---------- bills ---------- --}}
    <div class="card mb-3"><div class="card-body">
        <h6 class="fw-semibold mb-3"><i class="bi bi-receipt text-primary"></i> My bills</h6>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>Period</th><th>Schedule</th><th class="text-end">Amount</th><th class="text-end">Paid</th><th class="text-end">Due</th><th>Due date</th><th>Status</th><th class="text-end">Invoice</th></tr></thead>
                <tbody>
                    @forelse ($invoices as $i)
                        <tr>
                            <td class="fw-semibold">{{ $i->period_label }}<br><small class="text-muted">{{ $i->invoice_no }}</small></td>
                            <td>{{ $i->cycle_label }}</td>
                            <td class="text-end">{{ $m($i->amount) }}@if ((float) $i->discount_percent > 0)<br><small class="text-success">{{ (float) $i->discount_percent }}% discount</small>@endif</td>
                            <td class="text-end text-success">{{ $m($i->paid_amount) }}</td>
                            <td class="text-end {{ $i->balance > 0 && $i->status !== 'cancelled' ? 'text-danger fw-semibold' : '' }}">{{ $i->status === 'cancelled' ? '-' : $m($i->balance) }}</td>
                            <td>{{ $i->due_date->format($fmt) }}</td>
                            <td><x-status-badge :status="$i->display_status" /></td>
                            <td class="text-end text-nowrap">
                                @if ($i->payments->count())
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#pay-{{ $i->id }}" title="Payments of this bill"><i class="bi bi-list-ul"></i> {{ $i->payments->count() }}</button>
                                @endif
                                <a href="{{ route('billing.invoice.download', $i) }}" class="btn btn-sm btn-outline-dark" title="Download invoice (PDF)"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
                            </td>
                        </tr>
                        @if ($i->payments->count())
                            <tr class="collapse" id="pay-{{ $i->id }}">
                                <td colspan="8" class="bg-light">
                                    <div class="small fw-semibold mb-1">Payments of this bill</div>
                                    @foreach ($i->payments as $p)
                                        <div class="d-flex justify-content-between small border-bottom py-1">
                                            <span>{{ $p->paid_on->format($fmt) }} &middot; {{ config('billing.methods')[$p->method] ?? $p->method }}@if ($p->reference) &middot; {{ $p->reference }}@endif</span>
                                            <strong>{{ $m($p->amount) }}</strong>
                                        </div>
                                    @endforeach
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-3">No bills yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-2">{{ $invoices->links() }}</div>
        <p class="small text-muted mb-0">The PDF shows the watermark <strong>DUE</strong> while something is unpaid and <strong>PAID</strong> when the bill is fully paid.</p>
    </div></div>

    {{-- ---------- payment history ---------- --}}
    <div class="card mb-3"><div class="card-body">
        <h6 class="fw-semibold mb-3"><i class="bi bi-cash-coin text-success"></i> Payment history</h6>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>Date</th><th class="text-end">Amount</th><th>Method</th><th>Reference</th><th>For bill</th></tr></thead>
                <tbody>
                    @forelse ($payments as $p)
                        <tr>
                            <td>{{ $p->paid_on->format($fmt) }}</td>
                            <td class="text-end fw-semibold">{{ $m($p->amount) }}</td>
                            <td>{{ config('billing.methods')[$p->method] ?? $p->method }}</td>
                            <td>{{ $p->reference ?: '-' }}</td>
                            <td class="small">{{ $p->invoice->period_label }} <span class="text-muted">({{ $p->invoice->cycle_label }})</span></td>
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
