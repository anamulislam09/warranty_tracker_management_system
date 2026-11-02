@extends('layouts.app')
@section('title', $invoice->exists ? 'Edit Bill' : 'New Bill')
@section('content')
    <div class="row">
        <div class="col-11 mx-auto">
            <div class="d-flex justify-content-between align-items-center">
                <h3 class="page-title">{{ $invoice->exists ? 'Edit Bill ' . $invoice->invoice_no : 'New Bill' }}</h3>
                <button type="button" class="btn btn-warning align-self-start align-self-sm-auto"
                    onclick="window.history.back()"><i class="bi bi-arrow-left"></i> Back</button>
            </div>
            <p class="text-muted">
                @if ($invoice->exists)
                    Changing the plan or amount fixes the price of <strong>this bill only</strong>. Payments already received stay.
                @else
                    Normally bills are created automatically. Use this for a missed period or an extra / adjustment bill.
                @endif
            </p>

            <div class="card">
                <div class="card-body">
                    <form method="POST" id="invoice-form" data-plans="{{ json_encode($planPrices) }}"
                        data-cycles="{{ json_encode($cycles) }}" data-account-plans="{{ json_encode($accountPlans) }}"
                        data-account-cycles="{{ json_encode($accountCycles) }}" data-due-day="{{ $dueDay }}"
                        data-cycle="{{ $invoice->cycle }}" data-symbol="{{ config('billing.symbol') }}"
                        action="{{ $invoice->exists ? route('admin.invoices.update', $invoice) : route('admin.invoices.store') }}">
                        @csrf
                        @if ($invoice->exists)
                            @method('PUT')
                        @endif

                        <div class="row mb-3">
                            <div class="col-lg-6 col-md-6 col-sm-12">
                                <label class="form-label">Customer *</label>
                                @if ($invoice->exists)
                                    <input class="form-control" value="{{ $invoice->account->name }}" disabled>
                                @else
                                    <select name="account_id" class="form-select" required>
                                        <option value="">Select a customer</option>
                                        @foreach ($accounts as $a)
                                            <option value="{{ $a->id }}" @selected(old('account_id', $invoice->account_id) == $a->id)>
                                                {{ $a->name }} ({{ optional($a->plan)->name ?: 'no plan' }})</option>
                                        @endforeach
                                    </select>
                                @endif
                            </div>
                            <div class="col-lg-6 col-md-6 col-sm-12">
                                <label class="form-label">{{ $invoice->exists ? 'Period' : 'Period starts in *' }}</label>
                                @if ($invoice->exists)
                                    <input class="form-control" value="{{ $invoice->period_label }}" disabled>
                                @else
                                    <select name="billing_month" class="form-select" required>
                                        @foreach ($months as $v => $l)
                                            <option value="{{ $v }}" @selected(old('billing_month', today()->format('Y-m')) === $v)>{{ $l }}</option>
                                        @endforeach
                                    </select>
                                @endif
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-lg-6 col-md-6 col-sm-12">
                                <label class="form-label">Billing Cycle *</label>
                                @if ($invoice->exists)
                                    <input class="form-control" value="{{ $invoice->cycle_label }}" disabled>
                                @else
                                    <select name="cycle" class="form-select" required>
                                        @foreach ($cycles as $key => $c)
                                            <option value="{{ $key }}" @selected(old('cycle', 'monthly') === $key)>{{ $c['label'] }}@if ($c['discount'] > 0) - {{ $c['discount'] + 0 }}% discount @endif</option>
                                        @endforeach
                                    </select>
                                @endif
                            </div>
                            <div class="col-lg-6 col-md-6 col-sm-12">
                                <label class="form-label">Plan *</label>
                                <select name="plan_id" class="form-select" required>
                                    <option value="">Select a plan</option>
                                    @foreach ($plans as $p)
                                        <option value="{{ $p->id }}" @selected(old('plan_id', $invoice->plan_id) == $p->id)>{{ $p->name }}
                                            ({{ config('billing.symbol') }}{{ number_format($p->monthly_price) }}/month)</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-lg-6 col-md-6 col-sm-12">
                                <label class="form-label">Amount ({{ config('billing.symbol') }}) *</label>
                                <input type="number" step="0.01" min="0.01" name="amount" placeholder="Amount"
                                    class="form-control" value="{{ old('amount', $invoice->amount) }}" required>
                                <div class="form-text" id="quote-text">Filled from the plan price x months - discount. You can change it.</div>
                            </div>
                            <div class="col-lg-6 col-md-6 col-sm-12">
                                <label class="form-label">Due Date *</label>
                                <input type="date" name="due_date" placeholder="dd/mm/yy" class="form-control"
                                    value="{{ old('due_date', optional($invoice->due_date)->toDateString()) }}" required>
                                <div class="form-text">Unpaid after this date: the customer can only pay. One month later the account is suspended.</div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-12">
                                <label class="form-label">Note</label>
                                <textarea name="note" rows="2" maxlength="500" class="form-control"
                                    placeholder="e.g. Upgrade difference Personal to Business">{{ old('note', $invoice->note) }}</textarea>
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <button class="btn btn-primary"><i class="bi bi-check-lg"></i> Save Bill</button>
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
            const form = document.getElementById('invoice-form');
            const plans = JSON.parse(form.dataset.plans), cycles = JSON.parse(form.dataset.cycles);
            const accountPlans = JSON.parse(form.dataset.accountPlans), accountCycles = JSON.parse(form.dataset.accountCycles);
            const dueDay = parseInt(form.dataset.dueDay, 10), sym = form.dataset.symbol;
            const acc = form.querySelector('[name="account_id"]'), month = form.querySelector('[name="billing_month"]');
            const cycle = form.querySelector('[name="cycle"]'), plan = form.querySelector('[name="plan_id"]');
            const amount = form.querySelector('[name="amount"]'), due = form.querySelector('[name="due_date"]');
            const note = document.getElementById('quote-text');
            const pad = n => String(n).padStart(2, '0');
            const money = n => sym + n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            const setDate = (el, v) => window.setDateValue ? setDateValue(el, v) : (el.value = v);

            function fillAmount() {
                const price = parseFloat(plans[plan.value] || 0);
                const c = cycles[cycle ? cycle.value : form.dataset.cycle];
                if (!price || !c) return;
                const base = price * c.months, off = base * c.discount / 100;
                amount.value = (base - off).toFixed(2);
                note.textContent = c.months + ' month(s) x ' + money(price) + ' = ' + money(base) +
                    (c.discount > 0 ? ' - ' + c.discount + '% discount (' + money(off) + ')' : '') + ' = ' + money(base - off);
            }
            function dueFromMonth() {
                if (!month || !month.value) return;
                const [y, m] = month.value.split('-').map(Number);
                setDate(due, y + '-' + pad(m) + '-' + pad(Math.min(dueDay, new Date(y, m, 0).getDate())));
            }

            plan.addEventListener('change', fillAmount);
            if (cycle) cycle.addEventListener('change', fillAmount);
            if (month) month.addEventListener('change', dueFromMonth);
            if (acc) acc.addEventListener('change', function() {
                if (accountPlans[acc.value]) jQuery(plan).val(accountPlans[acc.value]).trigger('change.select2');
                if (accountCycles[acc.value]) jQuery(cycle).val(accountCycles[acc.value]).trigger('change.select2');
                fillAmount();
            });

            if (month && !due.value) dueFromMonth();
            if (acc && !amount.value && acc.value) acc.dispatchEvent(new Event('change'));
        })();
    </script>
@endpush
