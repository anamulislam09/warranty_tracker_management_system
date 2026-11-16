@extends('layouts.app')
@section('title', 'Admin Dashboard')
@section('content')
@php
    $m = fn ($v) => \App\Models\Invoice::money($v);
    $fmt = config('warranty.date_format', 'd/m/y');
@endphp

<div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
    <div>
        <h3 class="page-title mb-1">Admin Dashboard</h3>
        <p class="text-muted mb-0">
            Customers and billing &middot;
            {{ \Illuminate\Support\Carbon::parse($from)->format($fmt) }} &ndash; {{ \Illuminate\Support\Carbon::parse($to)->format($fmt) }}
            @if ($isDefault) <span class="badge text-bg-light border">this month + last 3 months</span> @endif
        </p>
    </div>
    <form method="GET" class="d-flex flex-wrap gap-2 align-items-end">
        <div><label class="form-label small text-muted mb-1">From</label>
            <input type="date" name="from" value="{{ $from }}" class="form-control" placeholder="dd/mm/yy"></div>
        <div><label class="form-label small text-muted mb-1">To</label>
            <input type="date" name="to" value="{{ $to }}" class="form-control" placeholder="dd/mm/yy"></div>
        <button class="btn btn-primary"><i class="bi bi-funnel"></i> Apply</button>
        <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary" title="Back to the default"><i class="bi bi-arrow-repeat"></i></a>
    </form>
</div>

{{-- ---------- customers: every card opens the customer list with the same status and dates ---------- --}}
<div class="row g-3 mb-3">
    @foreach ($customerCards as [$label, $value, $icon, $color, $url])
        <div class="col-6 col-lg-3">
            <a href="{{ $url }}" class="text-decoration-none text-reset">
                <div class="card stat-card h-100"><div class="card-body d-flex align-items-center gap-3">
                    <div class="icon bg-{{ $color }}-subtle text-{{ $color }}"><i class="bi {{ $icon }}"></i></div>
                    <div><div class="text-muted small">{{ $label }}</div><div class="num">{{ $value }}</div>
                        <div class="small text-primary">View list <i class="bi bi-arrow-right"></i></div></div>
                </div></div>
            </a>
        </div>
    @endforeach
</div>

{{-- ---------- money: bills issued in the dates ---------- --}}
<div class="row g-3 mb-3">
    @foreach ($moneyCards as [$label, $value, $icon, $color, $url])
        <div class="col-6 col-lg-3">
            <a href="{{ $url }}" class="text-decoration-none text-reset">
                <div class="card stat-card h-100"><div class="card-body d-flex align-items-center gap-3">
                    <div class="icon bg-{{ $color }}-subtle text-{{ $color }}"><i class="bi {{ $icon }}"></i></div>
                    <div><div class="text-muted small">{{ $label }}</div><div class="num" style="font-size:1.3rem">{{ $m($value) }}</div>
                        <div class="small text-primary">Details <i class="bi bi-arrow-right"></i></div></div>
                </div></div>
            </a>
        </div>
    @endforeach
</div>
<p class="small text-muted mb-3">Money numbers are for the bills issued in the selected dates: <strong>Collected</strong> is what was received for those bills, <strong>Outstanding</strong> is what is still unpaid, <strong>Overdue</strong> is the unpaid part of bills past their due date.</p>

{{-- ---------- charts: always this month + the 3 months before (the filter does not change them) ---------- --}}
<div class="row g-3 mb-3">
    <div class="col-12 col-lg-4">
        <div class="card h-100"><div class="card-body">
            <h6 class="section-title mb-1">Customers by status</h6>
            <p class="small text-muted">Registered in the selected dates</p>
            <div style="height:260px"><canvas id="chartStatus"></canvas></div>
        </div></div>
    </div>
    <div class="col-12 col-lg-4">
        <div class="card h-100"><div class="card-body">
            <h6 class="section-title mb-1">New customers</h6>
            <p class="small text-muted">This month + last 3 months</p>
            <div style="height:260px"><canvas id="chartNew"></canvas></div>
        </div></div>
    </div>
    <div class="col-12 col-lg-4">
        <div class="card h-100"><div class="card-body">
            <h6 class="section-title mb-1">Customers who became inactive</h6>
            <p class="small text-muted">This month + last 3 months</p>
            <div style="height:260px"><canvas id="chartInactive"></canvas></div>
        </div></div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12 col-lg-7">
        <div class="card h-100"><div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div><h6 class="section-title mb-1">Money collected</h6><p class="small text-muted mb-2">This month + last 3 months, by the date the payment was received</p></div>
                <a href="{{ route('admin.payments.index') }}" class="small">Payments</a>
            </div>
            <div style="height:260px"><canvas id="chartCollected"></canvas></div>
        </div></div>
    </div>
    <div class="col-12 col-lg-5">
        <div class="card h-100"><div class="card-body">
            <h6 class="section-title mb-3">Customers per plan</h6>
            @foreach ($plans as $p)
                <div class="d-flex justify-content-between border-bottom py-2"><span>{{ $p->name }}</span><span class="badge text-bg-primary">{{ $p->accounts_count }}</span></div>
            @endforeach
        </div></div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
(function () {
    const c = @json($charts);
    const sym = @json(config('billing.symbol'));

    // writes the number on top of every bar
    const valueLabels = {
        id: 'valueLabels',
        afterDatasetsDraw(chart, args, opts) {
            const ctx = chart.ctx;
            ctx.save(); ctx.font = 'bold 12px sans-serif'; ctx.fillStyle = '#333'; ctx.textAlign = 'center';
            chart.data.datasets.forEach((ds, i) => chart.getDatasetMeta(i).data.forEach((bar, idx) => {
                const v = ds.data[idx];
                if (v) ctx.fillText(opts && opts.money ? sym + Number(v).toLocaleString('en-US') : v, bar.x, bar.y - 6);
            }));
            ctx.restore();
        }
    };

    function bars(id, label, data, color, money) {
        new Chart(document.getElementById(id), {
            type: 'bar',
            data: { labels: c.labels, datasets: [{ label: label, data: data, backgroundColor: color, borderRadius: 6, maxBarThickness: 56 }] },
            options: {
                maintainAspectRatio: false,
                plugins: { legend: { display: false }, valueLabels: { money: !!money } },
                scales: { y: { beginAtZero: true, grace: '15%', ticks: { precision: 0 } } }
            },
            plugins: [valueLabels]
        });
    }

    bars('chartNew', 'New customers', c.newCustomers, '#0d6efd');
    bars('chartInactive', 'Became inactive', c.inactive, '#dc3545');
    bars('chartCollected', 'Collected', c.collected, '#198754', true);

    new Chart(document.getElementById('chartStatus'), {
        type: 'doughnut',
        data: { labels: c.status.labels, datasets: [{ data: c.status.values, backgroundColor: c.status.colors, borderWidth: 2 }] },
        options: { maintainAspectRatio: false, cutout: '62%', plugins: { legend: { position: 'bottom' } } }
    });
})();
</script>
@endpush
