@extends('layouts.app')
@section('title', 'Online Payment')
@section('content')
    @php
        $m = fn($v) => \App\Models\Invoice::money($v);
        $dt = config('warranty.datetime_format', 'd/m/y h:i A');
        $json = fn($v) => $v ? json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
    @endphp
    <nav class="small mb-2"><a href="{{ route('admin.online-payments.index') }}" class="text-decoration-none"><i class="bi bi-credit-card"></i> Online Payments</a> &rsaquo; {{ $attempt->tran_id }}</nav>

    <div class="card mb-3">
        <div class="card-body d-flex flex-column flex-md-row justify-content-between gap-3">
            <div>
                <h4 class="page-title mb-1">{{ $m($attempt->amount) }} <x-status-badge :status="$attempt->status" /></h4>
                <div class="text-muted">{{ $attempt->account->name }} &middot; <a href="{{ route('admin.invoices.show', $attempt->invoice_id) }}" class="text-decoration-none">{{ $attempt->invoice->invoice_no }} ({{ $attempt->invoice->period_label }})</a></div>
            </div>
            <div class="d-flex flex-wrap gap-2 align-items-start">
                @if ($attempt->canVerify())
                    <form method="POST" action="{{ route('admin.online-payments.verify', $attempt) }}"
                        data-confirm="Ask the gateway about this payment? If it says success, the payment is recorded on the bill." data-confirm-button="Yes, verify" data-confirm-icon="question">
                        @csrf
                        <button class="btn btn-success"><i class="bi bi-shield-check"></i> Verify with gateway</button>
                    </form>
                @endif
                @if ($attempt->payment_id)
                    <a href="{{ route('admin.invoices.show', $attempt->invoice_id) }}" class="btn btn-outline-primary"><i class="bi bi-cash-coin"></i> See the recorded payment</a>
                @endif
            </div>
        </div>
    </div>

    @if ($attempt->failure_reason)
        <div class="alert alert-{{ $attempt->status === 'needs_review' ? 'danger' : 'secondary' }}">{{ $attempt->failure_reason }}</div>
    @endif

    <div class="row g-3">
        <div class="col-12 col-lg-5">
            <div class="card h-100"><div class="card-body">
                <h6 class="fw-semibold mb-3">Details</h6>
                <table class="table table-sm mb-0"><tbody>
                    <tr><th class="text-muted fw-normal">Gateway</th><td>{{ $attempt->gateway }}</td></tr>
                    <tr><th class="text-muted fw-normal">Our reference</th><td><code>{{ $attempt->tran_id }}</code></td></tr>
                    <tr><th class="text-muted fw-normal">Unique id</th><td class="small"><code>{{ $attempt->unique_id }}</code></td></tr>
                    <tr><th class="text-muted fw-normal">Request step</th><td>{{ $attempt->request_step }} <small class="text-muted">({{ [0 => 'created, not sent to the gateway', 1 => 'sent to the gateway, waiting', 2 => 'gateway answer processed'][$attempt->request_step] ?? '' }})</small></td></tr>
                    <tr><th class="text-muted fw-normal">Gateway session</th><td class="small">{{ $attempt->gateway_session_key ?: '-' }}</td></tr>
                    <tr><th class="text-muted fw-normal">Gateway reference</th><td>{{ $attempt->gateway_reference ?: '-' }}</td></tr>
                    <tr><th class="text-muted fw-normal">Gateway status</th><td>{{ $attempt->gateway_status ?: '-' }}</td></tr>
                    <tr><th class="text-muted fw-normal">Card</th><td>{{ $attempt->card_type ?: '-' }} {{ $attempt->card_no }}</td></tr>
                    <tr><th class="text-muted fw-normal">Amount</th><td>{{ $m($attempt->amount) }} ({{ $attempt->currency }})</td></tr>
                    <tr><th class="text-muted fw-normal">Started by</th><td>{{ optional($attempt->user)->name ?: '-' }} <small class="text-muted">{{ $attempt->ip_address }}</small></td></tr>
                    <tr><th class="text-muted fw-normal">Started</th><td>{{ $attempt->created_at->format($dt) }}</td></tr>
                    <tr><th class="text-muted fw-normal">Callbacks</th><td>{{ $attempt->callback_count }} @if ($attempt->last_callback_at) <small class="text-muted">(last {{ $attempt->last_callback_at->format($dt) }})</small> @endif</td></tr>
                    <tr><th class="text-muted fw-normal">Verified</th><td>@if ($attempt->verified_at){{ $attempt->verified_at->format($dt) }} &middot; {{ $attempt->verified_by }}@if ($attempt->verifier) ({{ $attempt->verifier->name }})@endif @else - @endif</td></tr>
                </tbody></table>
            </div></div>
        </div>
        <div class="col-12 col-lg-7">
            <div class="card h-100"><div class="card-body">
                <h6 class="fw-semibold mb-3">What was stored</h6>
                @foreach ([['Sent to the gateway', $attempt->request_payload], ['Came back from the gateway (browser / IPN)', $attempt->callback_payload], ['Our checks with the gateway (verify)', $attempt->validation_payload]] as [$title, $data])
                    <div class="small fw-semibold mt-2">{{ $title }}</div>
                    @if ($data)
                        <pre class="bg-light border rounded p-2 small mb-2" style="max-height:220px;overflow:auto">{{ $json($data) }}</pre>
                    @else
                        <div class="small text-muted mb-2">Nothing yet.</div>
                    @endif
                @endforeach
            </div></div>
        </div>
    </div>
@endsection
