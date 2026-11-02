@extends('layouts.app')
@section('title', 'Billing Report')
@section('content')
    @php
        $m = fn($v) => \App\Models\Invoice::money($v);
        $fmt = config('warranty.date_format', 'd/m/y');
        $q = request()->query();
    @endphp
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h3 class="page-title mb-1">Billing Report</h3>
            <p class="text-muted mb-0">Collections and outstanding dues, customer-wise and month-wise.</p>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2">
                <div class="col-6 col-md-3"><label class="form-label small text-muted mb-1">From month</label>
                    <select name="from" class="form-select">@foreach ($months as $v => $l)<option value="{{ $v }}" @selected($from->format('Y-m') === $v)>{{ $l }}</option>@endforeach</select></div>
                <div class="col-6 col-md-3"><label class="form-label small text-muted mb-1">To month</label>
                    <select name="to" class="form-select">@foreach ($months as $v => $l)<option value="{{ $v }}" @selected($to->format('Y-m') === $v)>{{ $l }}</option>@endforeach</select></div>
                <div class="col-12 col-md-4"><label class="form-label small text-muted mb-1">Customer</label>
                    <select name="account_id" class="form-select"><option value="">All Customers</option>@foreach ($accounts as $a)<option value="{{ $a->id }}" @selected($accountId == $a->id)>{{ $a->name }}</option>@endforeach</select></div>
                <div class="col-12 col-md-2 d-flex align-items-end gap-2"><button class="btn btn-primary flex-fill">Apply</button><a href="{{ route('admin.reports.billing') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-repeat"></i></a></div>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-3">
        @foreach ([['Billed', $sum->billed, 'primary', 'bi-receipt'], ['Collected', $sum->collected, 'success', 'bi-cash-coin'], ['Outstanding', $sum->billed - $sum->collected, 'warning', 'bi-hourglass-split'], ['Overdue', $overdueAmount, 'danger', 'bi-exclamation-triangle'], ['Advance held', $advance, 'info', 'bi-piggy-bank']] as [$label, $val, $color, $icon])
            <div class="col-6 col-lg">
                <div class="card stat-card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="icon bg-{{ $color }}-subtle text-{{ $color }}"><i class="bi {{ $icon }}"></i></div>
                        <div>
                            <div class="text-muted small">{{ $label }}</div>
                            <div class="num" style="font-size:1.15rem">{{ $m($val) }}</div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- dues, customer-wise --}}
    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <h6 class="fw-semibold mb-0"><i class="bi bi-people text-danger"></i> Outstanding dues by customer
                    <small class="text-muted fw-normal">(unpaid bills starting up to {{ $to->format('F Y') }})</small></h6>
                <a href="{{ route('admin.reports.billing.export', array_merge($q, ['type' => 'dues'])) }}" class="btn btn-sm btn-outline-success"><i class="bi bi-download"></i> CSV</a>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Customer</th><th>Periods due</th><th class="text-end">Total due</th><th>Oldest due date</th><th>Account</th><th class="text-end"></th></tr></thead>
                    <tbody>
                        @forelse ($dues as $row)
                            <tr>
                                <td><a href="{{ route('admin.accounts.show', $row->account->id) }}" class="fw-semibold text-decoration-none">{{ $row->account->name }}</a><br><small class="text-muted">{{ $row->account->email }} &middot; {{ $row->account->cycleLabel() }}</small></td>
                                <td>
                                    @foreach ($row->invoices as $i)
                                        <a href="{{ route('admin.invoices.show', $i) }}" class="badge text-bg-{{ $i->is_overdue ? 'danger' : 'warning' }} text-decoration-none mb-1" title="Due {{ $i->due_date->format($fmt) }}">{{ $i->period_label }} &middot; {{ $m($i->balance) }}</a>
                                    @endforeach
                                </td>
                                <td class="text-end fw-bold text-danger">{{ $m($row->balance) }}</td>
                                <td>{{ $row->oldest->format($fmt) }} @if ($row->overdue)<span class="badge text-bg-danger">overdue - payment only</span>@endif</td>
                                <td><x-status-badge :status="$row->account->status" /></td>
                                <td class="text-end"><a href="{{ route('admin.payments.create', ['invoice_id' => $row->invoices->first()->id]) }}" class="btn btn-sm btn-success text-nowrap"><i class="bi bi-cash-coin"></i> Receive</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4"><i class="bi bi-check-circle text-success"></i> No outstanding dues.</td></tr>
                        @endforelse
                    </tbody>
                    @if ($dues->count())
                        <tfoot><tr class="fw-bold"><td>Total ({{ $dues->count() }} customers)</td><td></td><td class="text-end text-danger">{{ $m($dues->sum('balance')) }}</td><td colspan="3"></td></tr></tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>

    {{-- month-wise --}}
    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <h6 class="fw-semibold mb-0"><i class="bi bi-calendar3 text-primary"></i> Collection by billing period
                    <small class="text-muted fw-normal">({{ $from->format('M Y') }} &ndash; {{ $to->format('M Y') }})</small></h6>
                <a href="{{ route('admin.reports.billing.export', array_merge($q, ['type' => 'monthly'])) }}" class="btn btn-sm btn-outline-success"><i class="bi bi-download"></i> CSV</a>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Period starts</th><th class="text-end">Bills</th><th class="text-end">Paid bills</th><th class="text-end">Billed</th><th class="text-end">Collected</th><th class="text-end">Due</th><th style="min-width:140px">Collected %</th></tr></thead>
                    <tbody>
                        @forelse ($monthly as $r)
                            @php $pct = $r->billed > 0 ? round($r->collected / $r->billed * 100) : 0; @endphp
                            <tr>
                                <td class="fw-semibold">{{ $r->billing_month->format('F Y') }}</td>
                                <td class="text-end">{{ $r->invoices }}</td>
                                <td class="text-end">{{ $r->paid_count }}</td>
                                <td class="text-end">{{ $m($r->billed) }}</td>
                                <td class="text-end text-success">{{ $m($r->collected) }}</td>
                                <td class="text-end {{ $r->billed - $r->collected > 0 ? 'text-danger fw-semibold' : '' }}">{{ $m($r->billed - $r->collected) }}</td>
                                <td><div class="progress" style="height:8px"><div class="progress-bar bg-success" style="width:{{ $pct }}%"></div></div><small class="text-muted">{{ $pct }}%</small></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">No bills in this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
