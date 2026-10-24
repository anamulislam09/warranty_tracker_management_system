@extends('layouts.app')
@section('title', $product->name)
@section('content')
    @php
        $user = auth()->user();
        $isDoc = $product->isDocument();
        $multi = !$isDoc && $product->warranty_mode === 'multiple';
        $img = optional($product->category)->image_url;
        $ws = $product->warranties;
        $w1 = $ws->first(); // single warranty / document validity
        $canEdit = $user->hasPermission('products.edit');
        $status = $product->status;
        $code = ($isDoc ? 'DOC-' : 'PRD-') . str_pad($product->id, 6, '0', STR_PAD_LEFT);

        $theme = [
            'active' => ['success', 'bi-shield-check'],
            'expiring' => ['warning', 'bi-shield-exclamation'],
            'expired' => ['danger', 'bi-shield-x'],
            'upcoming' => ['info', 'bi-hourglass-split'],
            'inactive' => ['secondary', 'bi-archive'],
            'none' => ['secondary', 'bi-shield'],
        ][$status] ?? ['secondary', 'bi-shield'];
        $titles = $isDoc
            ? [
                'active' => 'Valid Document',
                'expiring' => 'Document Expiring Soon',
                'expired' => 'Document Expired',
                'upcoming' => 'Not Yet Valid',
                'inactive' => 'Inactive Document',
                'none' => 'No validity set',
            ]
            : [
                'active' => 'Active Warranty',
                'expiring' => 'Warranty Expiring Soon',
                'expired' => 'Warranty Expired',
                'upcoming' => 'Warranty Not Started',
                'inactive' => 'Inactive',
                'none' => 'No warranty',
            ];
        $barColor = ['active' => 'success', 'expiring' => 'warning', 'expired' => 'danger', 'upcoming' => 'info'];

        // multiple warranties: summary numbers
        $cnt = [
            'active' => $ws->where('status', 'active')->count(),
            'expiring' => $ws->where('status', 'expiring')->count(),
            'expired' => $ws->where('status', 'expired')->count(),
        ];
        $next = $ws->filter(fn($x) => in_array($x->status, ['active', 'expiring']))->sortBy('end_date')->first();

        // status history (built from the data we have)
        $events = collect([
            [
                'title' => 'Created',
                'date' => $product->created_at,
                'note' => optional($product->creator)->name,
                'color' => 'secondary',
                'link' => null,
            ],
        ]);
        foreach ($ws as $w) {
            if ($w->status === 'expired') {
                $events->push([
                    'title' => $multi ? $w->component . ' expired' : ($isDoc ? 'Document expired' : 'Warranty expired'),
                    'date' => $w->end_date,
                    'note' => null,
                    'color' => 'danger',
                    'link' => null,
                ]);
            }
        }
        if ($product->renewedFrom) {
            $events->push([
                'title' => 'Renewed from earlier document',
                'date' => $product->created_at,
                'note' => 'View previous',
                'color' => 'info',
                'link' => route('products.show', $product->renewedFrom),
            ]);
        }
        if (!$product->is_active && $product->renewedTo) {
            $events->push([
                'title' => 'Made inactive (renewed)',
                'date' => $product->renewedTo->created_at,
                'note' => 'View new one',
                'color' => 'secondary',
                'link' => route('products.show', $product->renewedTo),
            ]);
        }
        $events = $events->sortByDesc(fn($e) => $e['date']->timestamp)->values();
    @endphp

    <style>
        .ic {
            width: 36px;
            height: 36px;
            border-radius: .6rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            flex-shrink: 0;
        }

        .ic-blue {
            background: #0d5df5;
        }

        .ic-green {
            background: #19b37a;
        }

        .ic-purple {
            background: #7c3aed;
        }

        .ic-orange {
            background: #f59e0b;
        }

        .ic-grey {
            background: #64748b;
        }

        .ic-navy {
            background: #0b1f4d;
        }

        .kv th {
            width: 42%;
            font-weight: 400;
            color: #6b7a99;
        }

        .kv td {
            word-break: break-word;
        }
    </style>

    <nav class="small mb-2"><a href="{{ route('products.index') }}" class="text-decoration-none"><i class="bi bi-house"></i>
            Products &amp; Documents</a> &rsaquo; Details</nav>

    @if ($product->renewedFrom)
        <div class="alert alert-info py-2"><i class="bi bi-arrow-repeat"></i> Renewed from <a
                href="{{ route('products.show', $product->renewedFrom) }}">the previous document</a>.</div>
    @endif
    @if (!$product->is_active && $product->renewedTo)
        <div class="alert alert-secondary py-2"><i class="bi bi-archive"></i> This document is <strong>inactive</strong>. It
            was renewed &mdash; <a href="{{ route('products.show', $product->renewedTo) }}">open the new one</a>.</div>
    @elseif (!$product->is_active)
        <div class="alert alert-secondary py-2"><i class="bi bi-archive"></i> This item is <strong>inactive</strong>.</div>
    @endif

    {{-- ================= header ================= --}}
    <div class="card mb-3">
        <div class="card-body d-flex flex-column flex-md-row justify-content-between gap-3">
            <div class="d-flex gap-3 align-items-center">
                @if ($img)
                    <img src="{{ $img }}" alt="" class="rounded"
                        style="width:72px;height:72px;object-fit:cover">
                @else<span class="cat-icon" style="width:72px;height:72px;font-size:2rem"><i
                            class="bi {{ $isDoc ? 'bi-file-earmark-text' : 'bi-box-seam' }}"></i></span>
                @endif
                <div>
                    <h3 class="mb-1 page-title">{{ $product->name }}</h3>
                    @if ($isDoc)
                        <div class="text-muted">{{ $product->document_type }} @if ($product->serial_number)
                                &middot; No. {{ $product->serial_number }}
                                @endif @if ($product->issued_by)
                                    &middot; {{ $product->issued_by }}
                                @endif
                        </div>
                    @else
                        <div class="text-muted">{{ $product->brand }} @if ($product->model)
                                &middot; {{ $product->model }}
                                @endif @if ($product->serial_number)
                                    &middot; {{ $product->serial_number }}
                                @endif
                        </div>
                    @endif
                    <div class="mt-1">
                        @if ($isDoc) <span
                                class="badge text-bg-info">Document</span>
                        @else
                            <span
                                class="badge text-bg-primary-subtle text-primary">{{ optional($product->category)->name }}</span>
                            @if ($product->subcategory)
                                <span class="badge text-bg-light border">{{ $product->subcategory->name }}</span>
                            @endif
                            <span
                                class="badge text-bg-light border">{{ $multi ? 'Multiple warranties' : 'Single warranty' }}</span>
                        @endif
                        <x-status-badge :status="$status" />
                        <a href="#files-card" class="badge bg-warning text-white border text-decoration-none">
                            <i class="bi bi-paperclip"></i>
                            @if ($product->documents->count() > 0)
                                {{ $product->documents->count() }}
                                {{ \Illuminate\Support\Str::plural('file', $product->documents->count()) }} uploaded
                            @else
                                No files uploaded
                            @endif
                        </a>
                    </div>
                </div>
            </div>
            <div class="d-flex gap-2 align-items-start flex-wrap">
                @if ($isDoc && $product->is_active && $canEdit)
                    <a href="{{ route('products.renew', $product) }}" class="btn btn-success"><i
                            class="bi bi-arrow-repeat"></i> Renew</a>
                @endif
                @if ($canEdit)
                    <a href="{{ route('products.edit', $product) }}" class="btn btn-primary"><i class="bi bi-pencil"></i>
                        Edit</a>
                @endif
                @if ($user->hasPermission('products.delete'))
                    <form method="POST" action="{{ route('products.destroy', $product) }}"
                        data-confirm="Delete this item?">@csrf @method('DELETE')
                        <button class="btn btn-outline-danger"><i class="bi bi-trash"></i> Delete</button>
                    </form>
                @endif
                <button type="button" class="btn btn-warning align-self-start align-self-sm-auto"
                    onclick="window.history.back()">
                    <i class="bi bi-arrow-left"></i> Back
                </button>
            </div>
        </div>
    </div>

    <div class="row g-3">
        {{-- ============================ MAIN ============================ --}}
        <div class="col-12 col-xl-9">

            {{-- ---------- status banner ---------- --}}
            <div class="card mb-3 border-start border-4 border-{{ $theme[0] }}">
                <div class="card-body bg-{{ $theme[0] }}-subtle rounded-4">
                    <div class="row g-3 align-items-center">
                        <div class="col-12 col-md-6 d-flex gap-3 align-items-center">
                            <i class="bi {{ $theme[1] }} text-{{ $theme[0] }}"
                                style="font-size:3rem;line-height:1"></i>
                            <div>
                                <h4 class="mb-1 fw-bold text-{{ $theme[0] }}-emphasis">{{ $titles[$status] }}</h4>
                                @if ($multi)
                                    <div class="fw-semibold">{{ $ws->count() }} component
                                        {{ \Illuminate\Support\Str::plural('warranty', $ws->count()) }}</div>
                                    <div class="small">{{ $cnt['active'] }} active &middot; {{ $cnt['expiring'] }}
                                        expiring soon &middot; {{ $cnt['expired'] }} expired</div>
                                    @if ($next)
                                        <div class="small fw-semibold mt-1">Next expiry: {{ $next->component }} on
                                            {{ $next->end_date->format('d-M-Y') }} ({{ $next->days_remaining }} days)
                                        </div>
                                    @endif
                                @elseif ($w1)
                                    <div class="fw-semibold">
                                        {{ $w1->status === 'expired' ? ($isDoc ? 'Expired on ' : 'Warranty ended on ') : ($isDoc ? 'Expires on ' : 'Warranty ends on ') }}{{ $w1->end_date->format('d-M-Y') }}
                                    </div>
                                    <div class="small fw-semibold">
                                        @if ($w1->status === 'expired')
                                            Expired {{ (int) abs(today()->diffInDays($w1->end_date, false)) }} days ago
                                        @elseif ($w1->status === 'upcoming')
                                            Starts on {{ $w1->start_date->format('d-M-Y') }}
                                        @else
                                            {{ $w1->days_remaining }} days remaining
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            @if ($multi)
                                @foreach ($ws->take(4) as $w)
                                    <div class="mb-2">
                                        <div class="d-flex justify-content-between small"><span
                                                class="fw-semibold">{{ $w->component }}</span><span
                                                class="text-muted">{{ $w->end_date->format('d-M-Y') }}</span></div>
                                        <div class="progress" style="height:6px">
                                            <div class="progress-bar bg-{{ $barColor[$w->status] }}"
                                                style="width:{{ $w->progress }}%"></div>
                                        </div>
                                    </div>
                                @endforeach
                                @if ($ws->count() > 4)
                                    <div class="small text-muted">+ {{ $ws->count() - 4 }} more below</div>
                                @endif
                            @elseif ($w1)
                                <div class="d-flex justify-content-between small flex-wrap gap-1">
                                    <span>{{ $w1->duration }} {{ $w1->duration_unit }}(s)
                                        ({{ $w1->start_date->format('d-M-Y') }} &ndash;
                                        {{ $w1->end_date->format('d-M-Y') }})</span>
                                    <span class="text-muted">{{ $w1->progress }}% completed</span>
                                </div>
                                <div class="progress my-2" style="height:10px">
                                    <div class="progress-bar bg-{{ $barColor[$w1->status] }}"
                                        style="width:{{ $w1->progress }}%"></div>
                                </div>
                                <div class="d-flex justify-content-between small text-muted"><span>Started
                                        {{ $w1->start_date->format('d-M-Y') }}</span><span>Ends
                                        {{ $w1->end_date->format('d-M-Y') }}</span></div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- ---------- information cards ---------- --}}
            <div class="row g-3 mb-3">
                @if ($isDoc)
                    <div class="col-12 col-lg-6">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div class="d-flex align-items-center gap-2"><span class="ic ic-blue"><i
                                                class="bi bi-file-earmark-text"></i></span>
                                        <h6 class="mb-0 fw-semibold text-primary">Document Information</h6>
                                    </div>
                                    @if ($canEdit)
                                        <a href="{{ route('products.edit', $product) }}"
                                            class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> Edit</a>
                                    @endif
                                </div>
                                <table class="table table-sm kv mb-0">
                                    <tbody>
                                        <tr>
                                            <th>Title</th>
                                            <td>{{ $product->name }}</td>
                                        </tr>
                                        <tr>
                                            <th>Document Type</th>
                                            <td>{{ $product->document_type ?: '-' }}</td>
                                        </tr>
                                        <tr>
                                            <th>Document Number</th>
                                            <td>{{ $product->serial_number ?: '-' }}</td>
                                        </tr>
                                        <tr>
                                            <th>Issued By</th>
                                            <td>{{ $product->issued_by ?: '-' }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                                @if ($product->description)
                                    <p class="small text-muted mt-2 mb-0">{{ $product->description }}</p>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-lg-6">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center gap-2 mb-3"><span class="ic ic-purple"><i
                                            class="bi bi-calendar-check"></i></span>
                                    <h6 class="mb-0 fw-semibold" style="color:#7c3aed">Validity Information</h6>
                                </div>
                                @if ($w1)
                                    <table class="table table-sm kv mb-0">
                                        <tbody>
                                            <tr>
                                                <th>Issue / Start Date</th>
                                                <td>{{ $w1->start_date->format('d-M-Y') }}</td>
                                            </tr>
                                            <tr>
                                                <th>Validity</th>
                                                <td>{{ $w1->duration }} {{ ucfirst($w1->duration_unit) }}(s)</td>
                                            </tr>
                                            <tr>
                                                <th>Expiry Date</th>
                                                <td>{{ $w1->end_date->format('d-M-Y') }} @if ($w1->end_date_source === 'manual')
                                                        <small class="text-muted">(set manually)</small>
                                                    @endif
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Days Remaining</th>
                                                <td class="fw-semibold text-{{ $barColor[$w1->status] }}">
                                                    {{ $w1->status === 'expired' ? 'Expired' : $w1->days_remaining . ' days' }}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                @endif
                            </div>
                        </div>
                    </div>
                @else
                    <div class="col-12 col-lg-6">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div class="d-flex align-items-center gap-2"><span class="ic ic-blue"><i
                                                class="bi bi-box-seam"></i></span>
                                        <h6 class="mb-0 fw-semibold text-primary">Product Information</h6>
                                    </div>
                                    @if ($canEdit)
                                        <a href="{{ route('products.edit', $product) }}"
                                            class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> Edit</a>
                                    @endif
                                </div>
                                <table class="table table-sm kv mb-0">
                                    <tbody>
                                        <tr>
                                            <th>Product Name</th>
                                            <td>{{ $product->name }}</td>
                                        </tr>
                                        <tr>
                                            <th>Category</th>
                                            <td>{{ optional($product->category)->name ?: '-' }}</td>
                                        </tr>
                                        <tr>
                                            <th>Subcategory</th>
                                            <td>{{ optional($product->subcategory)->name ?: '-' }}</td>
                                        </tr>
                                        <tr>
                                            <th>Brand</th>
                                            <td>{{ $product->brand ?: '-' }}</td>
                                        </tr>
                                        <tr>
                                            <th>Model</th>
                                            <td>{{ $product->model ?: '-' }}</td>
                                        </tr>
                                        <tr>
                                            <th>Serial Number</th>
                                            <td>{{ $product->serial_number ?: '-' }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                                @if ($product->description)
                                    <p class="small text-muted mt-2 mb-0">{{ $product->description }}</p>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-lg-6">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div class="d-flex align-items-center gap-2"><span class="ic ic-green"><i
                                                class="bi bi-cart"></i></span>
                                        <h6 class="mb-0 fw-semibold" style="color:#137a4d">Purchase Information</h6>
                                    </div>
                                    @if ($canEdit)
                                        <a href="{{ route('products.edit', $product) }}"
                                            class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> Edit</a>
                                    @endif
                                </div>
                                <table class="table table-sm kv mb-0">
                                    <tbody>
                                        <tr>
                                            <th>Purchase Date</th>
                                            <td>{{ optional($product->purchase_date)->format('d-M-Y') ?: '-' }}</td>
                                        </tr>
                                        <tr>
                                            <th>Purchase Price</th>
                                            <td>{{ $product->purchase_price !== null ? number_format($product->purchase_price, 2) : '-' }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Currency</th>
                                            <td>{{ $product->currency }}</td>
                                        </tr>
                                        <tr>
                                            <th>Seller</th>
                                            <td>{{ $product->seller_name ?: '-' }} @if ($product->seller_phone)
                                                    <small class="text-muted">({{ $product->seller_phone }})</small>
                                                @endif
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Invoice Number</th>
                                            <td>{{ $product->invoice_number ?: '-' }}</td>
                                        </tr>
                                        <tr>
                                            <th>Purchase Location</th>
                                            <td>{{ $product->purchase_location ?: '-' }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            {{-- ---------- warranty + files ---------- --}}
            @if ($multi)
                {{-- MULTIPLE: one row per component, files below them --}}
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="d-flex align-items-center gap-2"><span class="ic ic-purple"><i
                                        class="bi bi-shield-check"></i></span>
                                <h6 class="mb-0 fw-semibold" style="color:#7c3aed">Component Warranties
                                    ({{ $ws->count() }})</h6>
                            </div>
                            @if ($canEdit)
                                <a href="{{ route('products.edit', $product) }}"
                                    class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> Edit</a>
                            @endif
                        </div>
                        @foreach ($ws as $w)
                            <div class="py-3 {{ !$loop->last ? 'border-bottom' : '' }}">
                                <div class="row g-2 align-items-center">
                                    <div class="col-12 col-md-3">
                                        <div class="fw-semibold">{{ $w->component }}</div>
                                        <small class="text-muted">{{ ucfirst($w->warranty_type) }}@if ($w->provider)
                                                &middot; {{ $w->provider }}
                                            @endif
                                        </small>
                                    </div>
                                    <div class="col-6 col-md-2"><small
                                            class="text-muted d-block">Start</small>{{ $w->start_date->format('d-M-Y') }}
                                    </div>
                                    <div class="col-6 col-md-2"><small class="text-muted d-block">End
                                            ({{ $w->duration }}
                                            {{ $w->duration_unit }}{{ $w->duration > 1 ? 's' : '' }})
                                        </small>{{ $w->end_date->format('d-M-Y') }}
                                    </div>
                                    <div class="col-12 col-md-3">
                                        <div class="progress" style="height:8px">
                                            <div class="progress-bar bg-{{ $barColor[$w->status] }}"
                                                style="width:{{ $w->progress }}%"></div>
                                        </div>
                                        <small class="text-muted">{{ $w->progress }}% &middot; <strong
                                                class="text-body">{{ $w->status === 'expired' ? 'Expired' : $w->days_remaining . ' days left' }}</strong></small>
                                    </div>
                                    <div class="col-12 col-md-2 text-md-end"><x-status-badge :status="$product->is_active ? $w->status : 'inactive'" /></div>
                                </div>
                                @if ($w->terms)
                                    <div class="small text-muted mt-1">{{ $w->terms }}</div>
                                @endif
                                @if ($w->end_date_source === 'manual')
                                    <div class="small text-muted"><i class="bi bi-pencil-square"></i> End date set
                                        manually</div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="mb-3">@include('products._show_files', ['fill' => false])</div>
            @elseif ($isDoc)
                <div class="mb-3">@include('products._show_files', ['fill' => false])</div>
            @else
                {{-- SINGLE: warranty information and files side by side --}}
                <div class="row g-3 mb-3">
                    <div class="col-12 col-lg-6">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div class="d-flex align-items-center gap-2"><span class="ic ic-purple"><i
                                                class="bi bi-shield-check"></i></span>
                                        <h6 class="mb-0 fw-semibold" style="color:#7c3aed">Warranty Information</h6>
                                    </div>
                                    @if ($canEdit)
                                        <a href="{{ route('products.edit', $product) }}"
                                            class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> Edit</a>
                                    @endif
                                </div>
                                @if ($w1)
                                    <table class="table table-sm kv mb-0">
                                        <tbody>
                                            <tr>
                                                <th>Warranty Type</th>
                                                <td>{{ ucfirst($w1->warranty_type) }} Warranty</td>
                                            </tr>
                                            <tr>
                                                <th>Start Date</th>
                                                <td>{{ $w1->start_date->format('d-M-Y') }}</td>
                                            </tr>
                                            <tr>
                                                <th>Duration</th>
                                                <td>{{ $w1->duration }} {{ ucfirst($w1->duration_unit) }}(s)</td>
                                            </tr>
                                            <tr>
                                                <th>Warranty End Date</th>
                                                <td>{{ $w1->end_date->format('d-M-Y') }} @if ($w1->end_date_source === 'manual')
                                                        <small class="text-muted">(set manually)</small>
                                                    @endif
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Days Remaining</th>
                                                <td class="fw-semibold text-{{ $barColor[$w1->status] }}">
                                                    {{ $w1->status === 'expired' ? 'Expired' : $w1->days_remaining . ' days' }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Provider</th>
                                                <td>{{ $w1->provider ?: '-' }}</td>
                                            </tr>
                                            @if ($w1->terms)
                                                <tr>
                                                    <th>Terms</th>
                                                    <td>{{ $w1->terms }}</td>
                                                </tr>
                                            @endif
                                        </tbody>
                                    </table>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-lg-6">@include('products._show_files', ['fill' => true])</div>
                </div>
            @endif

            @if ($product->notes)
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-2 mb-2"><span class="ic ic-grey"><i
                                    class="bi bi-journal-text"></i></span>
                            <h6 class="mb-0 fw-semibold">Notes</h6>
                        </div>
                        <div class="bg-light rounded-3 p-3 text-muted">{{ $product->notes }}</div>
                    </div>
                </div>
            @endif
        </div>

        {{-- ============================ SIDEBAR ============================ --}}
        <div class="col-12 col-xl-3">

            <div class="card mb-3">
                <div class="card-body">
                    <h6 class="fw-semibold mb-3"><i class="bi bi-gear text-primary"></i> Quick Actions</h6>
                    <div class="d-grid gap-2">
                        @if ($canEdit)
                            <a href="{{ route('products.edit', $product) }}" class="btn btn-primary"><i
                                    class="bi bi-pencil"></i> {{ $isDoc ? 'Edit Document' : 'Edit Warranty' }}</a>
                        @endif
                        @if ($isDoc && $product->is_active && $canEdit)
                            <a href="{{ route('products.renew', $product) }}" class="btn btn-success"><i
                                    class="bi bi-arrow-repeat"></i> Renew Document</a>
                        @endif
                        @if ($user->hasPermission('documents.manage'))
                            <a href="#files-card" class="btn btn-outline-primary"><i class="bi bi-file-earmark-plus"></i>
                                Add Document</a>
                        @endif
                        @if ($user->hasPermission('products.delete'))
                            <form method="POST" action="{{ route('products.destroy', $product) }}"
                                data-confirm="Delete this item?">@csrf @method('DELETE')
                                <button class="btn btn-outline-danger w-100"><i class="bi bi-trash"></i> Delete
                                    {{ $isDoc ? 'Document' : 'Product' }}</button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="fw-semibold mb-0"><i class="bi bi-bell text-primary"></i> Reminder Settings</h6>
                        @if ($canEdit)
                            <a href="{{ route('products.edit', $product) }}" class="btn btn-sm btn-outline-primary"><i
                                    class="bi bi-pencil"></i></a>
                        @endif
                    </div>
                    @foreach ($ws as $w)
                        @if ($multi)
                            <div class="fw-semibold small mt-2">{{ $w->component }}</div>
                        @endif
                        @forelse (collect($w->reminder_days)->sortDesc() as $d)
                            @php $when = $w->end_date->copy()->subDays($d); @endphp
                            <div
                                class="d-flex justify-content-between align-items-center py-1 small {{ $when->lt(today()) ? 'text-muted' : '' }}">
                                <span><i
                                        class="bi bi-check-square-fill text-primary me-2"></i>{{ $d === 0 ? 'On expiry' : $d . ' days before' }}</span>
                                <span>{{ $when->format('d-M-Y') }}</span>
                            </div>
                        @empty
                            <div class="small text-muted">No reminders set.</div>
                        @endforelse
                    @endforeach
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-body">
                    <h6 class="fw-semibold mb-3"><i class="bi bi-info-circle text-primary"></i> Additional Information
                    </h6>
                    <table class="table table-sm kv mb-0">
                        <tbody>
                            <tr>
                                <th>Date Added</th>
                                <td>{{ $product->created_at->format('d-M-Y h:i A') }}</td>
                            </tr>
                            <tr>
                                <th>Last Updated</th>
                                <td>{{ $product->updated_at->format('d-M-Y h:i A') }}</td>
                            </tr>
                            <tr>
                                <th>Added By</th>
                                <td>{{ optional($product->creator)->name ?: '-' }}</td>
                            </tr>
                            <tr>
                                <th>{{ $isDoc ? 'Document ID' : 'Product ID' }}</th>
                                <td>{{ $code }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-body">
                    <h6 class="fw-semibold mb-3"><i class="bi bi-clock-history text-primary"></i> Status History</h6>
                    @foreach ($events as $e)
                        <div class="d-flex gap-2 pb-3">
                            <span class="rounded-circle bg-{{ $e['color'] }} mt-1"
                                style="width:10px;height:10px;flex:0 0 10px"></span>
                            <div class="small">
                                <div class="fw-semibold">{{ $e['title'] }}</div>
                                <div class="text-muted">{{ $e['date']->format('d-M-Y') }}
                                    @if ($e['link'])
                                        &middot; <a href="{{ $e['link'] }}">{{ $e['note'] }}</a>
                                    @elseif ($e['note'])
                                        &middot; {{ $e['note'] }}
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
@endsection
