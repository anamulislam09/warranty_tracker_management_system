@extends('layouts.app')
@section('title', $payment->exists ? 'Edit Payment' : 'Receive Payment')
@section('content')
    <div class="row">
        <div class="col-11 mx-auto">
            <div class="d-flex justify-content-between align-items-center">
                <h3 class="page-title">{{ $payment->exists ? 'Edit Payment' : 'Receive Payment' }}</h3>
                <button type="button" class="btn btn-warning align-self-start align-self-sm-auto"
                    onclick="window.history.back()"><i class="bi bi-arrow-left"></i> Back</button>
            </div>

            <div class="card">
                <div class="card-body">
                    <form method="POST" id="payment-form" data-info="{{ json_encode($info) }}"
                        data-symbol="{{ config('billing.symbol') }}"
                        action="{{ $payment->exists ? route('admin.payments.update', $payment) : route('admin.payments.store') }}">
                        @csrf
                        @if ($payment->exists)
                            @method('PUT')
                        @endif

                        <div class="row mb-3">
                            <div class="col-lg-6 col-md-6 col-sm-12">
                                <label class="form-label">Customer *</label>
                                @if ($payment->exists)
                                    <input class="form-control" value="{{ $payment->account->name }}" disabled>
                                @else
                                    <select id="account_id" name="account_id" class="form-select" required>
                                        <option value="">Select a customer</option>
                                        @foreach ($accounts as $a)
                                            <option value="{{ $a->id }}" @selected(old('account_id', optional($selected)->account_id) == $a->id)>{{ $a->name }}</option>
                                        @endforeach
                                    </select>
                                @endif
                            </div>
                            <div class="col-lg-6 col-md-6 col-sm-12">
                                <label class="form-label">Pay this bill first</label>
                                @if ($payment->exists)
                                    <select name="invoice_id" class="form-select">
                                        <option value="">Auto - oldest unpaid bill first</option>
                                        @foreach ($targets as $t)
                                            <option value="{{ $t->id }}" @selected(old('invoice_id', $payment->invoice_id) == $t->id)>{{ $t->period_label }} - {{ \App\Models\Invoice::money($t->amount) }} ({{ ucfirst($t->status) }})</option>
                                        @endforeach
                                    </select>
                                @else
                                    <select id="invoice_id" name="invoice_id" class="form-select"
                                        data-selected="{{ old('invoice_id', optional($selected)->id) }}"></select>
                                @endif
                                <div class="form-text">Leave it on "Auto" to pay the oldest bill first. Money above the dues is kept as the customer's advance.</div>
                            </div>
                        </div>

                        @unless ($payment->exists)
                            <div class="alert alert-info py-2 d-none" id="account-info"></div>
                        @endunless

                        <div class="row mb-3">
                            <div class="col-lg-4 col-md-4 col-sm-12">
                                <label class="form-label">Amount ({{ config('billing.symbol') }}) *</label>
                                <input type="number" step="0.01" min="0.01" id="amount" name="amount" placeholder="Amount"
                                    class="form-control" value="{{ old('amount', $payment->amount) }}" required>
                            </div>
                            <div class="col-lg-4 col-md-4 col-sm-12">
                                <label class="form-label">Date Paid *</label>
                                <input type="date" name="paid_on" placeholder="dd/mm/yy" class="form-control"
                                    value="{{ old('paid_on', optional($payment->paid_on)->toDateString()) }}" required>
                            </div>
                            <div class="col-lg-4 col-md-4 col-sm-12">
                                <label class="form-label">Method *</label>
                                <select name="method" class="form-select">
                                    @foreach ($methods as $v => $l)
                                        <option value="{{ $v }}" @selected(old('method', $payment->method) === $v)>{{ $l }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-lg-6 col-md-6 col-sm-12">
                                <label class="form-label">Reference / Transaction ID</label>
                                <input name="reference" placeholder="e.g. bKash TrxID, receipt no" class="form-control"
                                    value="{{ old('reference', $payment->reference) }}">
                            </div>
                            <div class="col-lg-6 col-md-6 col-sm-12">
                                <label class="form-label">Note</label>
                                <input name="note" placeholder="Note" maxlength="500" class="form-control"
                                    value="{{ old('note', $payment->note) }}">
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <button class="btn btn-success"><i class="bi bi-check-lg"></i> Save Payment</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function() {
            const form = document.getElementById('payment-form');
            const acc = document.getElementById('account_id'), inv = document.getElementById('invoice_id');
            if (!acc) return; // edit mode: nothing to fill
            const info = JSON.parse(form.dataset.info), sym = form.dataset.symbol;
            const amount = document.getElementById('amount'), box = document.getElementById('account-info');
            const money = n => sym + Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            function fill(selected) {
                const a = info[acc.value] || { invoices: [], due: 0, advance: 0 };
                inv.innerHTML = '<option value="">Auto - oldest unpaid bill first</option>';
                a.invoices.forEach(i => {
                    const o = document.createElement('option');
                    o.value = i.id; o.textContent = i.label;
                    if (String(i.id) === String(selected)) o.selected = true;
                    inv.appendChild(o);
                });
                jQuery(inv).trigger('change.select2');

                if (!acc.value) { box.classList.add('d-none'); return; }
                box.classList.remove('d-none');
                box.innerHTML = 'Total due: <strong>' + money(a.due) + '</strong> &middot; Advance already paid: <strong>' + money(a.advance) + '</strong>';
            }
            function balanceOf(id) {
                const hit = ((info[acc.value] || {}).invoices || []).find(i => String(i.id) === String(id));
                return hit ? hit.balance : null;
            }

            acc.addEventListener('change', () => {
                fill('');
                const a = info[acc.value];
                if (a && a.due > 0) amount.value = a.due.toFixed(2);
            });
            inv.addEventListener('change', () => {
                const b = balanceOf(inv.value);
                if (b !== null) amount.value = b.toFixed(2);
            });

            fill(inv.dataset.selected);
            if (!amount.value && inv.dataset.selected) {
                const b = balanceOf(inv.dataset.selected);
                if (b !== null) amount.value = b.toFixed(2);
            }
        })();
    </script>
@endpush
