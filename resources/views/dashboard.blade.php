@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
@include('partials.scroll-box-style')

<div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
    <div>
        <h3 class="page-title mb-1">Dashboard</h3>
        <p class="text-muted mb-0">Overview of your products, documents and warranties.</p>
    </div>
    <form method="GET" class="d-flex flex-wrap gap-2 align-items-end">
        <div><label class="form-label small text-muted mb-1">Added from</label>
            <input type="date" name="from" value="{{ $from }}" class="form-control" placeholder="dd/mm/yy"></div>
        <div><label class="form-label small text-muted mb-1">to</label>
            <input type="date" name="to" value="{{ $to }}" class="form-control" placeholder="dd/mm/yy"></div>
        <button class="btn btn-primary"><i class="bi bi-funnel"></i> Apply</button>
        <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary" title="Reset"><i class="bi bi-arrow-repeat"></i></a>
    </form>
</div>

{{-- ---------- overview: every card opens the product list with the same filter ---------- --}}
<div class="row g-3 mb-3">
    @foreach ($cards as [$label, $value, $icon, $color, $url])
        <div class="col-6 col-lg-3">
            @if ($url)<a href="{{ $url }}" class="text-decoration-none text-reset">@endif
            <div class="card stat-card h-100"><div class="card-body d-flex align-items-center gap-3">
                <div class="icon bg-{{ $color }}-subtle text-{{ $color }}"><i class="bi {{ $icon }}"></i></div>
                <div><div class="text-muted small">{{ $label }}</div><div class="num">{{ $value }}</div>
                    @if ($url)<div class="small text-primary">View list <i class="bi bi-arrow-right"></i></div>@endif</div>
            </div></div>
            @if ($url)</a>@endif
        </div>
    @endforeach
</div>

{{-- ---------- plan usage ---------- --}}
@include('partials.plan-usage')

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card h-100"><div class="card-body">
            <h6 class="section-title mb-3">Upcoming Expiries (next 90 days) <span class="badge text-bg-light border">{{ $upcoming->count() }}</span></h6>
            <div class="{{ $upcoming->count() > 5 ? 'scroll-box' : '' }}">
            @forelse ($upcoming as $w)
                <a href="{{ route('products.show', $w->product_id) }}" class="d-flex justify-content-between align-items-center text-decoration-none text-dark py-2 border-bottom">
                    <div><div class="fw-semibold">{{ $w->product->name }}</div><small class="text-muted">{{ $w->component }} &middot; {{ $w->end_date->format(config('warranty.date_format', 'd/m/y')) }}</small></div>
                    <span class="badge text-bg-warning">{{ $w->days_remaining }} days</span>
                </a>
            @empty
                <p class="text-muted mb-0">No warranties expiring soon.</p>
            @endforelse
            </div>
        </div></div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100"><div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="section-title mb-0">Recent Notifications <span class="badge text-bg-light border">{{ $notifications->count() }}</span></h6>
                <a href="{{ route('notifications.index') }}" class="small">View all</a>
            </div>
            <div class="{{ $notifications->count() > 5 ? 'scroll-box' : '' }}">
            @forelse ($notifications as $n)
                <a href="{{ route('notifications.read', $n) }}" class="d-block text-decoration-none text-dark py-2 border-bottom">
                    <div class="fw-semibold">@if(! $n->read_at)<i class="bi bi-circle-fill text-primary small me-1"></i>@endif{{ $n->title }}</div>
                    <small class="text-muted">{{ $n->message }}</small>
                </a>
            @empty
                <p class="text-muted mb-0">No notifications yet.</p>
            @endforelse
            </div>
            <p class="small text-muted mt-2 mb-0"><i class="bi bi-envelope"></i> Every notification is also sent to your email.</p>
        </div></div>
    </div>
    <div class="col-12">
        <div class="card"><div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="section-title mb-0">Recently Added Products <span class="badge text-bg-light border">{{ $recent->count() }}</span></h6>
                @if (auth()->user()->hasPermission('products.view'))<a href="{{ route('products.index') }}" class="small">View all</a>@endif
            </div>
            <div class="table-responsive {{ $recent->count() > 5 ? 'scroll-box' : '' }}">
                <table class="table mb-0">
                    <thead><tr><th>Product</th><th>Type / Category</th><th>Purchased</th><th>Status</th></tr></thead>
                    <tbody>
                    @forelse ($recent as $p)
                        <tr>
                            <td><a href="{{ route('products.show', $p) }}" class="fw-semibold text-decoration-none">{{ $p->name }}</a></td>
                            <td>{{ $p->isDocument() ? 'Document - '.$p->document_type : optional($p->category)->name }}</td>
                            <td>{{ optional($p->purchase_date)->format(config('warranty.date_format', 'd/m/y')) ?: '-' }}</td>
                            <td><x-status-badge :status="$p->status" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted">No products yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div></div>
    </div>
</div>
@endsection
