@php $g = fn ($k, $d = null) => $row[$k] ?? $d; @endphp
<div class="card warranty-row mb-3">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="mb-0 section-title"><i class="bi bi-shield-check text-primary"></i> <span class="only-product-inline">Warranty</span><span class="only-document-inline">Validity period</span></h6>
            <button type="button" class="btn btn-sm btn-outline-danger remove-warranty only-multiple"><i class="bi bi-trash"></i> Remove</button>
        </div>
        <input type="hidden" name="warranties[{{ $i }}][id]" value="{{ $g('id') }}">
        <div class="row g-3">
            <div class="col-12 col-md-6 only-multiple">
                <label class="form-label">Component <span class="text-danger">*</span></label>
                <input name="warranties[{{ $i }}][component]" class="form-control" data-required value="{{ $g('component') }}" placeholder="e.g. Compressor, Motor, Battery">
            </div>
            <div class="col-12 col-md-6 only-product">
                <label class="form-label">Warranty Type</label>
                <select name="warranties[{{ $i }}][warranty_type]" class="form-select">
                    @foreach (['manufacturer' => 'Manufacturer Warranty', 'extended' => 'Extended Warranty', 'seller' => 'Seller Warranty', 'other' => 'Other'] as $v => $l)
                        <option value="{{ $v }}" @selected($g('warranty_type', 'manufacturer') === $v)>{{ $l }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label">Start / Issue Date <span class="text-danger">*</span></label>
                <input type="date" name="warranties[{{ $i }}][start_date]" class="form-control w-start" data-required value="{{ $g('start_date') }}">
            </div>
            <div class="col-6 col-md-4">
                <label class="form-label">Duration <span class="text-danger">*</span></label>
                <input type="number" min="1" name="warranties[{{ $i }}][duration]" class="form-control w-duration" data-required value="{{ $g('duration', 12) }}">
            </div>
            <div class="col-6 col-md-4">
                <label class="form-label">Unit <span class="text-danger">*</span></label>
                <select name="warranties[{{ $i }}][duration_unit]" class="form-select w-unit">
                    @foreach (['day' => 'Days', 'month' => 'Months', 'year' => 'Years'] as $v => $l)
                        <option value="{{ $v }}" @selected($g('duration_unit', 'month') === $v)>{{ $l }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label">End / Expiry Date <span class="badge text-bg-success-subtle text-success">auto calculated</span></label>
                <input type="date" name="warranties[{{ $i }}][end_date]" class="form-control w-end" value="{{ $g('end_date') }}" readonly>
                <div class="form-check mt-1">
                    <input class="form-check-input w-override" type="checkbox" value="1" name="warranties[{{ $i }}][override_end]" @checked($g('override_end'))>
                    <label class="form-check-label small">Edit end date manually</label>
                </div>
            </div>
            <div class="col-12 col-md-6 only-product">
                <label class="form-label">Warranty Provider</label>
                <input name="warranties[{{ $i }}][provider]" class="form-control" value="{{ $g('provider') }}" placeholder="e.g. Samsung, ABC Electronics">
            </div>
            <div class="col-12 only-product">
                <label class="form-label">Warranty Terms</label>
                <textarea name="warranties[{{ $i }}][terms]" rows="2" class="form-control" placeholder="Terms and conditions...">{{ $g('terms') }}</textarea>
            </div>
            <div class="col-12">
                <label class="form-label d-block">Remind me before expiry</label>
                @foreach (config('warranty.reminder_options') as $d)
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" name="warranties[{{ $i }}][reminder_days][]" value="{{ $d }}" @checked(in_array($d, $g('reminder_days', [])))>
                        <label class="form-check-label small">{{ $d === 0 ? 'On expiry' : $d.' days' }}</label>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
