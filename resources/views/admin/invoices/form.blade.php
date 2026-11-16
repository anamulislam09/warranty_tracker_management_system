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
                    Bills are created automatically 15 days before a period starts. Use this only to create the next bill of a customer by hand.
                    The period and payment schedule always come from the customer's own schedule.
                @endif
            </p>

            <div class="card">
                <div class="card-body">
                    <form method="POST" id="invoice-form" data-drafts="{{ json_encode($drafts) }}"
                        data-plans="{{ json_encode($planPrices) }}" data-symbol="{{ config('billing.symbol') }}"
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
                                    <select name="account_id" id="account_id" class="form-select" required>
                                        <option value="">Select a customer</option>
                                        @foreach ($accounts as $a)
                                            <option value="{{ $a->id }}" @selected(old('account_id', $invoice->account_id) == $a->id)>{{ $a->name }}</option>
                                        @endforeach
                                    </select>
                                @endif
                            </div>
                            <div class="col-lg-6 col-md-6 col-sm-12">
                                <label class="form-label">Payment schedule / period</label>
                                @if ($invoice->exists)
                                    <input class="form-control" value="{{ $invoice->cycle_label }} - {{ $invoice->period_label }}" disabled>
                                @else
                                    <input class="form-control" id="schedule-info" value="" placeholder="Choose a customer" disabled>
                                @endif
                            </div>
                        </div>

                        @unless ($invoice->exists)
                            <div class="alert alert-info py-2 d-none" id="draft-info"></div>
                            <div class="alert alert-warning py-2 d-none" id="draft-warning"></div>
                        @endunless

                        <div class="row mb-3">
                            <div class="col-lg-6 col-md-6 col-sm-12">
                                <label class="form-label">Plan {{ $invoice->exists ? '*' : '' }}</label>
                                @if ($invoice->exists)
                                    <select name="plan_id" id="plan_id" class="form-select" required>
                                        @foreach ($plans as $p)
                                            <option value="{{ $p->id }}" @selected(old('plan_id', $invoice->plan_id) == $p->id)>{{ $p->name }} ({{ config('billing.symbol') }}{{ number_format($p->monthly_price) }}/month)</option>
                                        @endforeach
                                    </select>
                                @else
                                    <input class="form-control" id="plan-info" value="" placeholder="From the customer's plan" disabled>
                                @endif
                            </div>
                            <div class="col-lg-6 col-md-6 col-sm-12">
                                <label class="form-label">Amount ({{ config('billing.symbol') }}) *</label>
                                <input type="number" step="0.01" min="0.01" name="amount" placeholder="Amount"
                                    class="form-control" value="{{ old('amount', $invoice->amount) }}" required>
                                <div class="form-text" id="quote-text">Plan monthly price x months - the discount of the schedule. You can change it.</div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-lg-6 col-md-6 col-sm-12">
                                <label class="form-label">Due Date *</label>
                                <input type="date" name="due_date" placeholder="dd/mm/yy" class="form-control"
                                    value="{{ old('due_date', optional($invoice->due_date)->toDateString()) }}" required>
                                <div class="form-text">Unpaid after this date: the customer can only pay. One month later the account is suspended.</div>
                            </div>
                            <div class="col-lg-6 col-md-6 col-sm-12">
                                <label class="form-label">Note</label>
                                <textarea name="note" rows="2" maxlength="500" class="form-control"
                                    placeholder="e.g. Upgrade difference Personal to Business">{{ old('note', $invoice->note) }}</textarea>
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <button class="btn btn-primary" id="save-btn"><i class="bi bi-check-lg"></i> Save Bill</button>
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
            const drafts = JSON.parse(form.dataset.drafts), plans = JSON.parse(form.dataset.plans), sym = form.dataset.symbol;
            const acc = document.getElementById('account_id');
            const amount = form.querySelector('[name="amount"]'), due = form.querySelector('[name="due_date"]');
            const note = document.getElementById('quote-text');
            const money = n => sym + Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            const setDate = (el, v) => window.setDateValue ? setDateValue(el, v) : (el.value = v);

            // edit: choosing another plan fills the price of the bill again
            const plan = document.getElementById('plan_id');
            if (plan) plan.addEventListener('change', () => {
                const price = parseFloat(plans[plan.value] || 0);
                if (price) amount.value = (price * {{ (int) $invoice->months ?: 1 }} * (1 - {{ (float) $invoice->discount_percent }} / 100)).toFixed(2);
            });

            if (!acc) return;                               // edit mode ends here
            const info = document.getElementById('schedule-info'), planInfo = document.getElementById('plan-info');
            const warn = document.getElementById('draft-warning'), save = document.getElementById('save-btn'), infoBox = document.getElementById('draft-info');

            function show() {
                const d = drafts[acc.value];
                warn.classList.add('d-none');
                infoBox.classList.add('d-none');
                save.disabled = false;
                if (!acc.value || !d) { info.value = ''; planInfo.value = ''; return; }

                if (!d.schedule) {                          // no plan / schedule at all
                    info.value = ''; planInfo.value = '';
                    warn.textContent = d.message; warn.classList.remove('d-none'); save.disabled = true;
                    return;
                }
                info.value = d.schedule + ' - ' + d.period;
                planInfo.value = d.plan;
                amount.value = Number(d.amount).toFixed(2);
                setDate(due, d.due);
                note.textContent = d.discount > 0 ? 'Includes the ' + d.discount + '% discount of the schedule.' : 'No discount in this schedule.';
                if (!d.can) { warn.textContent = d.message; warn.classList.remove('d-none'); save.disabled = true; }
                else if (d.first) {
                    infoBox.textContent = 'This is the first bill of a new customer.' +
                        (d.free_until ? ' The customer uses the system free until ' + d.free_until + '; this bill is for the period after that.' : '') +
                        ' You may create it now, or leave it: it is created automatically 15 days before the period starts.';
                    infoBox.classList.remove('d-none');
                }
            }

            acc.addEventListener('change', show);
            show();
        })();
    </script>
@endpush
