@extends('layouts.app')
@section('title', 'Renew '.$product->name)
@section('content')
@php
    $old  = $product->warranties->first();
    $defStart = old('start_date', optional($old)->end_date ? $old->end_date->copy()->addDay()->toDateString() : today()->toDateString());
@endphp
<h3 class="page-title mb-1">Renew {{ $product->document_type ?: 'Document' }}</h3>
<p class="text-muted">{{ $product->name }} &middot; current expiry: <strong>{{ optional(optional($old)->end_date)->format(config('warranty.date_format')) }}</strong>.
    After renewing, the current document becomes <strong>inactive</strong> and a new active one is created.</p>

<div class="card" style="max-width:720px"><div class="card-body">
    <form method="POST" id="renew-form" action="{{ route('products.renew.store', $product) }}">
        @csrf
        <div class="row g-3">
            <div class="col-12 col-md-6"><label class="form-label">New Document Number</label>
                <input name="serial_number" class="form-control" value="{{ old('serial_number', $product->serial_number) }}"></div>
            <div class="col-12 col-md-6"><label class="form-label">Issued By</label>
                <input name="issued_by" class="form-control" value="{{ old('issued_by', $product->issued_by) }}"></div>
            <div class="col-12 col-md-4"><label class="form-label">New Start Date *</label>
                <input type="date" name="start_date" id="r-start" class="form-control" value="{{ $defStart }}" required></div>
            <div class="col-6 col-md-4"><label class="form-label">Duration *</label>
                <input type="number" min="1" name="duration" id="r-duration" class="form-control" value="{{ old('duration', optional($old)->duration ?: 5) }}" required></div>
            <div class="col-6 col-md-4"><label class="form-label">Unit *</label>
                <select name="duration_unit" id="r-unit" class="form-select">
                    @foreach (['day' => 'Days', 'month' => 'Months', 'year' => 'Years'] as $v => $l)<option value="{{ $v }}" @selected(old('duration_unit', optional($old)->duration_unit ?: 'year') === $v)>{{ $l }}</option>@endforeach
                </select></div>
            <div class="col-12 col-md-6"><label class="form-label">New Expiry Date <span class="badge text-bg-success-subtle text-success">auto calculated</span></label>
                <input type="date" name="end_date" id="r-end" class="form-control" readonly>
                <div class="form-check mt-1"><input class="form-check-input" type="checkbox" value="1" name="override_end" id="r-override"><label class="form-check-label small" for="r-override">Edit expiry date manually</label></div></div>
            <div class="col-12">
                <label class="form-label d-block">Remind me before expiry</label>
                @php $rem = old('reminder_days', optional($old)->reminder_days ?? config('warranty.default_reminders')); @endphp
                @foreach (config('warranty.reminder_options') as $d)
                    <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="reminder_days[]" value="{{ $d }}" @checked(in_array($d, $rem))>
                        <label class="form-check-label small">{{ $d === 0 ? 'On expiry' : $d.' days' }}</label></div>
                @endforeach
            </div>
        </div>
        <div class="mt-4 d-flex gap-2">
            <button class="btn btn-success"><i class="bi bi-arrow-repeat"></i> Renew Document</button>
            <a href="{{ route('products.show', $product) }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div></div>
@endsection

@push('scripts')
<script>
(function () {
    const s = document.getElementById('r-start'), d = document.getElementById('r-duration'), u = document.getElementById('r-unit'),
          e = document.getElementById('r-end'), o = document.getElementById('r-override');
    const pad = n => String(n).padStart(2, '0');
    function calc() {
        if (o.checked || !s.value || !d.value) return;
        const dt = new Date(s.value + 'T00:00:00'), n = parseInt(d.value, 10);
        if (u.value === 'day') dt.setDate(dt.getDate() + n);
        else {
            const months = u.value === 'year' ? n * 12 : n, day = dt.getDate();
            dt.setDate(1); dt.setMonth(dt.getMonth() + months);
            dt.setDate(Math.min(day, new Date(dt.getFullYear(), dt.getMonth() + 1, 0).getDate()));
        }
        dt.setDate(dt.getDate() - 1);
        setDateValue(e, dt.getFullYear() + '-' + pad(dt.getMonth() + 1) + '-' + pad(dt.getDate()));
    }
    [s, d, u].forEach(x => x.addEventListener('input', calc));
    o.addEventListener('change', () => { setDateEditable(e, o.checked); calc(); });
    calc();
})();
</script>
@endpush
