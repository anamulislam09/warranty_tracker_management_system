@extends('layouts.app')
@section('title', $payment->exists ? 'Edit Payment' : 'Receive Payment')
@section('content')
<h3 class="page-title mb-3">{{ $payment->exists ? 'Edit Payment' : 'Receive Payment' }}</h3>

<div class="card" style="max-width:760px"><div class="card-body">
    <form method="POST" id="payment-form" data-open="{{ json_encode($open) }}"
          action="{{ $payment->exists ? route('admin.payments.update', $payment) : route('admin.payments.store') }}">
        @csrf @if ($payment->exists) @method('PUT') @endif
        <div class="row g-3">
            @if ($payment->exists)
                <div class="col-12 col-md-6"><label class="form-label">Customer</label><input class="form-control" value="{{ $payment->account->name }}" disabled></div>
                <div class="col-12 col-md-6"><label class="form-label">Bill</label><input class="form-control" value="{{ $payment->invoice->month_label }} - {{ $payment->invoice->invoice_no }}" disabled></div>
            @else
                <div class="col-12 col-md-6">
                    <label class="form-label">Customer *</label>
                    <select id="account_filter" name="account_filter" class="form-select">
                        <option value="">Select a customer</option>
                        @foreach ($accounts as $a)<option value="{{ $a->id }}" @selected(old('account_filter', optional($selected)->account_id) == $a->id)>{{ $a->name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label">Bill (month) *</label>
                    <select id="invoice_id" name="invoice_id" class="form-select" data-selected="{{ old('invoice_id', optional($selected)->id) }}" required></select>
                    <div class="form-text">Only unpaid / partly paid bills are listed.</div>
                </div>
            @endif

            <div class="col-12 col-md-4">
                <label class="form-label">Amount ({{ config('billing.symbol') }}) *</label>
                <input type="number" step="0.01" min="0.01" id="amount" name="amount" class="form-control" value="{{ old('amount', $payment->amount) }}" required>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label">Date Paid *</label>
                <input type="date" name="paid_on" class="form-control" value="{{ old('paid_on', optional($payment->paid_on)->toDateString()) }}" required>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label">Method *</label>
                <select name="method" class="form-select">
                    @foreach ($methods as $v => $l)<option value="{{ $v }}" @selected(old('method', $payment->method) === $v)>{{ $l }}</option>@endforeach
                </select>
            </div>
            <div class="col-12 col-md-6"><label class="form-label">Reference / Transaction ID</label><input name="reference" class="form-control" value="{{ old('reference', $payment->reference) }}" placeholder="e.g. bKash TrxID, receipt no"></div>
            <div class="col-12 col-md-6"><label class="form-label">Note</label><input name="note" class="form-control" maxlength="500" value="{{ old('note', $payment->note) }}"></div>
        </div>
        <div class="mt-4 d-flex gap-2">
            <button class="btn btn-success"><i class="bi bi-check-lg"></i> Save Payment</button>
            <a href="{{ $payment->exists ? route('admin.invoices.show', $payment->invoice_id) : route('admin.payments.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div></div>
@endsection

@push('scripts')
<script>
(function () {
    const form = document.getElementById('payment-form');
    const acc = document.getElementById('account_filter'), inv = document.getElementById('invoice_id'), amount = document.getElementById('amount');
    if (!acc) return;                                            // edit mode: nothing to fill
    const open = JSON.parse(form.dataset.open);

    function fillInvoices(selected) {
        const list = open[acc.value] || [];
        inv.innerHTML = '<option value="">' + (acc.value ? (list.length ? 'Select a bill' : 'No unpaid bill') : 'Select a customer first') + '</option>';
        list.forEach(i => {
            const o = document.createElement('option');
            o.value = i.id; o.textContent = i.label;
            if (String(i.id) === String(selected)) o.selected = true;
            inv.appendChild(o);
        });
        jQuery(inv).trigger('change.select2');
    }
    function balanceOf(id) {
        const hit = (open[acc.value] || []).find(i => String(i.id) === String(id));
        return hit ? hit.balance : null;
    }

    acc.addEventListener('change', () => { fillInvoices(''); });
    inv.addEventListener('change', () => { const b = balanceOf(inv.value); if (b !== null) amount.value = b.toFixed(2); });

    fillInvoices(inv.dataset.selected);
    if (inv.dataset.selected && !amount.value) { const b = balanceOf(inv.dataset.selected); if (b !== null) amount.value = b.toFixed(2); }
})();
</script>
@endpush
