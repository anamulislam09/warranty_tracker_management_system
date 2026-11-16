@extends('layouts.app')
@section('title', 'Online Payments')
@section('content')
    @php
        $m = fn($v) => \App\Models\Invoice::money($v);
        $dt = config('warranty.datetime_format', 'd/m/y h:i A');
        $c = fn($s) => (int) ($counts[$s] ?? 0);
    @endphp
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h3 class="page-title mb-1">Online Payments</h3>
            <p class="text-muted mb-0">Every payment a customer tried through the payment gateway, and what the gateway answered.</p>
        </div>
    </div>

    <div class="row g-3 mb-3">
        @foreach ([['Success', $c('success'), 'success', 'success'], ['Pending', $c('pending') + $c('initiated'), 'warning', 'pending'], ['Failed / cancelled', $c('failed') + $c('cancelled') + $c('expired'), 'secondary', ''], ['Needs review', $c('needs_review'), 'danger', 'needs_review']] as [$label, $n, $color, $filter])
            <div class="col-6 col-lg-3">
                <a href="{{ $filter ? route('admin.online-payments.index', ['status' => $filter]) : route('admin.online-payments.index') }}" class="text-decoration-none text-reset">
                    <div class="card stat-card h-100"><div class="card-body py-3">
                        <div class="text-muted small">{{ $label }}</div><div class="num text-{{ $color }}">{{ $n }}</div>
                    </div></div>
                </a>
            </div>
        @endforeach
    </div>

    <div class="alert alert-info small py-2">
        <i class="bi bi-info-circle"></i>
        If a customer paid at the gateway but the bill here is still unpaid, open that payment and press <strong>Verify with gateway</strong>.
        The system asks the gateway itself; if the gateway says the payment is successful, the payment is recorded and the bill is updated.
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2">
                <div class="col-12 col-md-3"><input name="q" value="{{ request('q') }}" class="form-control" placeholder="Our reference / gateway reference"></div>
                <div class="col-6 col-md-2">
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        @foreach (['success' => 'Success', 'pending' => 'Pending', 'initiated' => 'Started', 'failed' => 'Failed', 'cancelled' => 'Cancelled', 'expired' => 'Expired', 'needs_review' => 'Needs review'] as $v => $l)
                            <option value="{{ $v }}" @selected(request('status') === $v)>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <select name="account_id" class="form-select">
                        <option value="">All Customers</option>
                        @foreach ($accounts as $a)
                            <option value="{{ $a->id }}" @selected(request('account_id') == $a->id)>{{ $a->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2"><input type="date" name="from" class="form-control" value="{{ request('from') }}" placeholder="From"></div>
                <div class="col-6 col-md-2"><input type="date" name="to" class="form-control" value="{{ request('to') }}" placeholder="To"></div>
                <div class="col-12 d-flex gap-2"><button class="btn btn-primary">Filter</button><a href="{{ route('admin.online-payments.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-repeat"></i></a></div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr><th>Started</th><th>Customer / bill</th><th>Our reference</th><th>Gateway reference</th><th class="text-end">Amount</th><th>Status</th><th class="text-end">Actions</th></tr>
                </thead>
                <tbody>
                    @forelse ($attempts as $a)
                        <tr>
                            <td class="small">{{ $a->created_at->format($dt) }}<br><span class="text-muted">{{ $a->gateway }}</span></td>
                            <td>{{ $a->account->name }}<br><a href="{{ route('admin.invoices.show', $a->invoice_id) }}" class="small text-decoration-none">{{ $a->invoice->period_label }}</a></td>
                            <td class="small"><code>{{ $a->tran_id }}</code></td>
                            <td class="small">{{ $a->gateway_reference ?: '-' }}</td>
                            <td class="text-end fw-semibold">{{ $m($a->amount) }}</td>
                            <td><x-status-badge :status="$a->status" />@if ($a->verified_by)<br><small class="text-muted">verified: {{ $a->verified_by }}</small>@endif</td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('admin.online-payments.show', $a) }}" class="btn btn-sm btn-outline-primary" title="Details"><i class="bi bi-eye"></i></a>
                                @if ($a->canVerify())
                                    <form method="POST" action="{{ route('admin.online-payments.verify', $a) }}" class="d-inline"
                                        data-confirm="Ask the gateway about this payment? If it says success, the payment is recorded on the bill." data-confirm-button="Yes, verify" data-confirm-icon="question">
                                        @csrf
                                        <button class="btn btn-sm btn-success"><i class="bi bi-shield-check"></i> Verify</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">No online payment yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $attempts->links() }}</div>
@endsection
