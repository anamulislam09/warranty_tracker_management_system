@extends('layouts.app')
@section('title', 'Products & Documents')
@section('content')
@php $user = auth()->user(); @endphp

<style>
    /* product rows = blue tint, document rows = amber tint; left border shows the status */
    .table > tbody > tr.row-product > td  { --bs-table-bg: #eef5ff; }
    .table > tbody > tr.row-document > td { --bs-table-bg: #fff4e0; }
    .table > tbody > tr.st-active   > td:first-child { border-left: 4px solid #198754; }
    .table > tbody > tr.st-expiring > td:first-child { border-left: 4px solid #ffc107; }
    .table > tbody > tr.st-expired  > td:first-child { border-left: 4px solid #dc3545; }
    .table > tbody > tr.st-upcoming > td:first-child { border-left: 4px solid #0dcaf0; }
    .table > tbody > tr.st-none     > td:first-child { border-left: 4px solid #adb5bd; }

    .m-card { border-left: 4px solid #adb5bd; }
    .m-card.is-product  { background: #eef5ff; }
    .m-card.is-document { background: #fff4e0; }
    .m-card.st-active   { border-left-color: #198754; }
    .m-card.st-expiring { border-left-color: #ffc107; }
    .m-card.st-expired  { border-left-color: #dc3545; }
    .m-card.st-upcoming { border-left-color: #0dcaf0; }

    .legend-box { display: inline-block; width: 14px; height: 14px; border-radius: 3px; border: 1px solid #cfd8e8; vertical-align: -2px; }
    .hist-dot { width: 12px; height: 12px; border-radius: 50%; flex: 0 0 12px; margin-top: .35rem; }
</style>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <h3 class="page-title mb-1">Products &amp; Documents</h3>
        <p class="text-muted mb-0">Manage all your products, documents and their warranty / validity.</p>
    </div>
    @if ($user->hasPermission('products.create'))
        <a href="{{ route('products.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Add New</a>
    @endif
</div>

<div class="card mb-3"><div class="card-body">
    <form method="GET" class="row g-2">
        <div class="col-12 col-md-3"><input name="q" value="{{ request('q') }}" class="form-control" placeholder="Search name, brand, model, number..."></div>
        <div class="col-6 col-md-2">
            <select name="type" class="form-select">
                <option value="">All Types</option>
                <option value="product" @selected(request('type') === 'product')>Products</option>
                <option value="document" @selected(request('type') === 'document')>Documents</option>
            </select>
        </div>
        <div class="col-6 col-md-3">
            <select name="category_id" class="form-select">
                <option value="">All Categories</option>
                @foreach ($categories as $c)<option value="{{ $c->id }}" @selected(request('category_id') == $c->id)>{{ $c->name }}</option>@endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <select name="status" class="form-select">
                <option value="">All Status</option>
                @foreach (['active' => 'Active', 'expiring' => 'Expiring Soon', 'expired' => 'Expired'] as $v => $l)<option value="{{ $v }}" @selected(request('status') === $v)>{{ $l }}</option>@endforeach
            </select>
        </div>
        <div class="col-6 col-md-2 d-flex gap-2">
            <button class="btn btn-primary flex-fill">Search</button>
            <a href="{{ route('products.index') }}" class="btn btn-outline-secondary" title="Clear"><i class="bi bi-arrow-repeat"></i></a>
        </div>
    </form>
</div></div>

{{-- legend --}}
<div class="d-flex flex-wrap gap-3 small text-muted mb-2">
    <span><span class="legend-box" style="background:#eef5ff"></span> Product</span>
    <span><span class="legend-box" style="background:#fff4e0"></span> Document</span>
    <span class="ms-md-auto">Left edge:
        <span class="text-success fw-semibold">&#9608; Active</span>
        <span class="text-warning fw-semibold">&#9608; Expiring</span>
        <span class="text-danger fw-semibold">&#9608; Expired</span>
    </span>
</div>

{{-- Desktop table --}}
<div class="card d-none d-md-block"><div class="table-responsive">
    <table class="table table-hover mb-0">
        <thead><tr><th>Name</th><th>Type / Category</th><th>Details</th><th>Purchased</th><th>Warranty / Validity</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        @forelse ($products as $p)
            <tr class="row-{{ $p->item_type }} st-{{ $p->status }}">
                <td>
                    <a href="{{ route('products.show', $p) }}" class="fw-semibold text-decoration-none">{{ $p->name }}</a><br>
                    <small class="text-muted">{{ $p->serial_number ? ($p->isDocument() ? 'No: ' : 'SN: ').$p->serial_number : '' }}</small>
                    @if ($p->relationLoaded('history') && $p->history->isNotEmpty())
                        <div><button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" data-bs-toggle="modal" data-bs-target="#history-{{ $p->id }}">
                            <i class="bi bi-clock-history"></i> Renewed {{ $p->history->count() }}&times; &middot; History</button></div>
                    @endif
                </td>
                <td>
                    @if ($p->isDocument()) <span class="badge text-bg-warning">Document</span><br><small class="text-muted">{{ $p->document_type }}</small>
                    @else <span class="badge text-bg-primary">Product</span><br><small class="text-muted">{{ optional($p->category)->name }}@if($p->subcategory) &rsaquo; {{ $p->subcategory->name }}@endif</small> @endif
                </td>
                <td>{{ $p->isDocument() ? $p->issued_by : $p->brand }}<br><small class="text-muted">{{ $p->model }}</small></td>
                <td>{{ optional($p->purchase_date)->format(config('warranty.date_format')) ?: '-' }}</td>
                <td class="small">
                    @foreach ($p->warranties as $w)<div>{{ $p->isDocument() ? 'Expires' : $w->component }} &middot; <span class="text-muted">{{ $w->end_date->format(config('warranty.date_format')) }}</span></div>@endforeach
                </td>
                <td><x-status-badge :status="$p->status" /></td>
                <td class="text-end text-nowrap">
                    <a href="{{ route('products.show', $p) }}" class="btn btn-sm btn-outline-primary" title="View"><i class="bi bi-eye"></i></a>
                    @if ($user->hasPermission('products.edit'))<a href="{{ route('products.edit', $p) }}" class="btn btn-sm btn-outline-secondary" title="Edit"><i class="bi bi-pencil"></i></a>@endif
                    @if ($user->hasPermission('products.delete'))
                        <form method="POST" action="{{ route('products.destroy', $p) }}" class="d-inline" data-confirm="Delete this item?">@csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button></form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="text-center text-muted py-4">Nothing found.</td></tr>
        @endforelse
        </tbody>
    </table>
</div></div>

{{-- Mobile cards --}}
<div class="d-md-none">
    @forelse ($products as $p)
        <div class="card m-card is-{{ $p->item_type }} st-{{ $p->status }} mb-2"><div class="card-body">
            <div class="d-flex justify-content-between gap-2">
                <a href="{{ route('products.show', $p) }}" class="fw-semibold text-decoration-none text-dark stretched-link">{{ $p->name }}</a>
                <x-status-badge :status="$p->status" />
            </div>
            <div class="small text-muted">
                @if ($p->isDocument()) <span class="badge text-bg-warning">Document</span> {{ $p->document_type }}
                @else <span class="badge text-bg-primary">Product</span> {{ optional($p->category)->name }} @if($p->subcategory) &rsaquo; {{ $p->subcategory->name }} @endif @if($p->brand) &middot; {{ $p->brand }} @endif @endif
            </div>
            @if ($p->purchase_date)<div class="small mt-2">Purchased: {{ $p->purchase_date->format(config('warranty.date_format')) }}</div>@endif
            @foreach ($p->warranties as $w)<div class="small">{{ $p->isDocument() ? 'Expires' : $w->component }}: <span class="text-muted">{{ $w->end_date->format(config('warranty.date_format')) }}</span></div>@endforeach
            @if ($p->relationLoaded('history') && $p->history->isNotEmpty())
                <button type="button" class="btn btn-link btn-sm p-0 mt-1 text-decoration-none position-relative" style="z-index:2" data-bs-toggle="modal" data-bs-target="#history-{{ $p->id }}">
                    <i class="bi bi-clock-history"></i> Renewed {{ $p->history->count() }}&times; &middot; History</button>
            @endif
        </div></div>
    @empty
        <p class="text-center text-muted">Nothing found.</p>
    @endforelse
</div>

{{-- pagination + rows per page --}}
<div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3 mt-3">
    <div class="flex-grow-1 w-100">{{ $products->links() }}</div>
    <form method="GET" class="d-flex align-items-center gap-2 flex-shrink-0">
        @foreach (request()->except(['per_page', 'page']) as $k => $v)
            @if (is_scalar($v))<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endif
        @endforeach
        <label class="small text-muted mb-0 text-nowrap">Per page</label>
        <div style="width:90px">
            <select name="per_page" class="form-select form-select-sm" onchange="this.form.submit()">
                @foreach ([10, 25, 50, 100] as $n)<option value="{{ $n }}" @selected((int) request('per_page', 10) === $n)>{{ $n }}</option>@endforeach
            </select>
        </div>
    </form>
</div>

{{-- renewal history popups --}}
@foreach ($products as $p)
    @if ($p->relationLoaded('history') && $p->history->isNotEmpty())
        @php $versions = $p->history->concat([$p])->values(); $count = $versions->count(); @endphp
        <div class="modal fade" id="history-{{ $p->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title"><i class="bi bi-arrow-repeat text-primary"></i> Renewal history</h5>
                            <div class="small text-muted">{{ $p->name }} &middot; renewed {{ $p->history->count() }} {{ \Illuminate\Support\Str::plural('time', $p->history->count()) }}</div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        @foreach ($versions->reverse()->values() as $idx => $v)
                            @php
                                $n       = $count - 1 - $idx;                 // 0 = original
                                $isLast  = $v->is($p);
                                $vw      = $v->warranties->first();
                                $dot     = $isLast ? (['active'=>'success','expiring'=>'warning','expired'=>'danger','upcoming'=>'info'][$v->status] ?? 'secondary') : 'secondary';
                                $renewedOn = $isLast ? null : optional($versions[$n + 1])->created_at;
                            @endphp
                            <div class="d-flex gap-3 pb-3 {{ ! $loop->last ? 'border-bottom mb-3' : '' }}">
                                <span class="hist-dot bg-{{ $dot }}"></span>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between flex-wrap gap-1">
                                        <strong>{{ $n === 0 ? 'Original' : 'Renewal '.$n }}</strong>
                                        @if ($isLast) <span><span class="badge text-bg-primary">Current</span> <x-status-badge :status="$v->status" /></span>
                                        @else <span class="badge text-bg-secondary">Renewed on {{ optional($renewedOn)->format(config('warranty.date_format')) }}</span> @endif
                                    </div>
                                    <div class="small text-muted">Document No: {{ $v->serial_number ?: '-' }}@if($v->issued_by) &middot; {{ $v->issued_by }}@endif</div>
                                    @if ($vw)<div class="small">Valid {{ $vw->start_date->format(config('warranty.date_format')) }} &rarr; <strong>{{ $vw->end_date->format(config('warranty.date_format')) }}</strong></div>@endif
                                    @unless ($isLast)<a href="{{ route('products.show', $v) }}" class="small text-decoration-none">Open this version <i class="bi bi-box-arrow-up-right"></i></a>@endunless
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @endif
@endforeach
@endsection
