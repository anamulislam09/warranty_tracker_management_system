@extends('layouts.app')
@section('title', $invoice->exists ? 'Edit Bill' : 'New Bill')
@section('content')
<h3 class="page-title mb-1">{{ $invoice->exists ? 'Edit Bill '.$invoice->invoice_no : 'New Bill' }}</h3>
<p class="text-muted">
    @if ($invoice->exists) Changing the plan or amount fixes the price of <strong>this month only</strong>. Already received payments stay.
    @else Normally bills are created automatically. Use this for a missed month or an extra / adjustment bill. @endif
</p>

<div class="card" style="max-width:760px"><div class="card-body">
    <form method="POST" id="invoice-form"
          data-plans="{{ json_encode($planPrices) }}" data-account-plans="{{ json_encode($accountPlans) }}" data-due-day="{{ $dueDay }}"
          action="{{ $invoice->exists ? route('admin.invoices.update', $invoice) : route('admin.invoices.store') }}">
        @csrf @if ($invoice->exists) @method('PUT') @endif
        <div class="row g-3">
            <div class="col-12 col-md-6">
                <label class="form-label">Customer *</label>
                @if ($invoice->exists)
                    <input class="form-control" value="{{ $invoice->account->name }}" disabled>
                @else
                    <select name="account_id" class="form-select" required>
                        <option value="">Select a customer</option>
                        @foreach ($accounts as $a)<option value="{{ $a->id }}" @selected(old('account_id', $invoice->account_id) == $a->id)>{{ $a->name }} ({{ optional($a->plan)->name ?: 'no plan' }})</option>@endforeach
                    </select>
                @endif
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label">Billing Month *</label>
                @if ($invoice->exists)
                    <input class="form-control" value="{{ $invoice->month_label }}" disabled>
                @else
                    <select name="billing_month" class="form-select" required>
                        @foreach ($months as $v => $l)<option value="{{ $v }}" @selected(old('billing_month', today()->format('Y-m')) === $v)>{{ $l }}</option>@endforeach
                    </select>
                @endif
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label">Plan *</label>
                <select name="plan_id" class="form-select" required>
                    <option value="">Select a plan</option>
                    @foreach ($plans as $p)<option value="{{ $p->id }}" @selected(old('plan_id', $invoice->plan_id) == $p->id)>{{ $p->name }} ({{ config('billing.symbol') }}{{ number_format($p->monthly_price) }}/month)</option>@endforeach
                </select>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label">Amount ({{ config('billing.symbol') }}) *</label>
                <input type="number" step="0.01" min="0.01" name="amount" class="form-control" value="{{ old('amount', $invoice->amount) }}" required>
                <div class="form-text">Filled from the plan price. Change it for a discount or a part-month (upgrade) amount.</div>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label">Due Date *</label>
                <input type="date" name="due_date" class="form-control" value="{{ old('due_date', optional($invoice->due_date)->toDateString()) }}" required>
                <div class="form-text">Unpaid after this date &rarr; the customer is suspended automatically.</div>
            </div>
            <div class="col-12"><label class="form-label">Note</label><textarea name="note" rows="2" maxlength="500" class="form-control" placeholder="e.g. Upgrade difference Personal to Business">{{ old('note', $invoice->note) }}</textarea></div>
        </div>
        <div class="mt-4 d-flex gap-2">
            <button class="btn btn-primary"><i class="bi bi-check-lg"></i> Save Bill</button>
            <a href="{{ $invoice->exists ? route('admin.invoices.show', $invoice) : route('admin.invoices.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div></div>
@endsection

@push('scripts')
<script>
(function () {
    const form = document.getElementById('invoice-form');
    const plans = JSON.parse(form.dataset.plans), accountPlans = JSON.parse(form.dataset.accountPlans), dueDay = parseInt(form.dataset.dueDay, 10);
    const acc = form.querySelector('[name="account_id"]'), month = form.querySelector('[name="billing_month"]');
    const plan = form.querySelector('[name="plan_id"]'), amount = form.querySelector('[name="amount"]'), due = form.querySelector('[name="due_date"]');
    const pad = n => String(n).padStart(2, '0');

    function priceFromPlan() { if (plans[plan.value] !== undefined) amount.value = parseFloat(plans[plan.value]).toFixed(2); }
    function dueFromMonth() {
        if (!month || !month.value) return;
        const [y, m] = month.value.split('-').map(Number);
        const day = Math.min(dueDay, new Date(y, m, 0).getDate());
        setDateValue(due, y + '-' + pad(m) + '-' + pad(day));
    }

    plan.addEventListener('change', priceFromPlan);
    if (acc) acc.addEventListener('change', function () {
        const pid = accountPlans[acc.value];
        if (pid) { jQuery(plan).val(pid).trigger('change.select2'); priceFromPlan(); }
    });
    if (month) month.addEventListener('change', dueFromMonth);

    // sensible defaults for a new bill
    if (acc && !amount.value && acc.value && accountPlans[acc.value]) { jQuery(plan).val(accountPlans[acc.value]).trigger('change.select2'); priceFromPlan(); }
    if (month && !due.value) dueFromMonth();
})();
</script>
@endpush
