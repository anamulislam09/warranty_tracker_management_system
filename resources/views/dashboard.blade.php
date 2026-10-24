@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
<h3 class="page-title mb-1">Dashboard</h3>
<p class="text-muted">Overview of your products and warranties.</p>

<div class="row g-3 mb-4">
    @foreach ([['Products & Documents',$stats['products'],'bi-box-seam','primary'],['Active Warranties',$stats['active'],'bi-shield-check','success'],['Expiring Soon',$stats['expiring'],'bi-clock-history','warning'],['Expired',$stats['expired'],'bi-x-circle','danger']] as [$label,$val,$icon,$color])
        <div class="col-6 col-lg-3">
            <div class="card stat-card h-100"><div class="card-body d-flex align-items-center gap-3">
                <div class="icon bg-{{ $color }}-subtle text-{{ $color }}"><i class="bi {{ $icon }}"></i></div>
                <div><div class="text-muted small">{{ $label }}</div><div class="num">{{ $val }}</div></div>
            </div></div>
        </div>
    @endforeach
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card h-100"><div class="card-body">
            <h6 class="section-title mb-3">Upcoming Expiries (next 90 days)</h6>
            @forelse ($upcoming as $w)
                <a href="{{ route('products.show', $w->product_id) }}" class="d-flex justify-content-between align-items-center text-decoration-none text-dark py-2 border-bottom">
                    <div><div class="fw-semibold">{{ $w->product->name }}</div><small class="text-muted">{{ $w->component }} &middot; {{ $w->end_date->format(config('warranty.date_format')) }}</small></div>
                    <span class="badge text-bg-warning">{{ $w->days_remaining }} days</span>
                </a>
            @empty
                <p class="text-muted mb-0">No warranties expiring soon.</p>
            @endforelse
        </div></div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100"><div class="card-body">
            <h6 class="section-title mb-3">Recent Notifications</h6>
            @forelse ($notifications as $n)
                <a href="{{ route('notifications.read', $n) }}" class="d-block text-decoration-none text-dark py-2 border-bottom">
                    <div class="fw-semibold">@if(! $n->read_at)<i class="bi bi-circle-fill text-primary small me-1"></i>@endif{{ $n->title }}</div>
                    <small class="text-muted">{{ $n->message }}</small>
                </a>
            @empty
                <p class="text-muted mb-0">No notifications yet.</p>
            @endforelse
        </div></div>
    </div>
    <div class="col-12">
        <div class="card"><div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="section-title mb-0">Recently Added Products</h6>
                @if (auth()->user()->hasPermission('products.view'))<a href="{{ route('products.index') }}" class="small">View all</a>@endif
            </div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Product</th><th>Type / Category</th><th>Purchased</th><th>Status</th></tr></thead>
                    <tbody>
                    @forelse ($recent as $p)
                        <tr>
                            <td><a href="{{ route('products.show', $p) }}" class="fw-semibold text-decoration-none">{{ $p->name }}</a></td>
                            <td>{{ $p->isDocument() ? 'Document - '.$p->document_type : optional($p->category)->name }}</td>
                            <td>{{ optional($p->purchase_date)->format(config('warranty.date_format')) ?: '-' }}</td>
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
