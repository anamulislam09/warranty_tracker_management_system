@extends('layouts.app')
@section('title', $product->exists ? 'Edit' : 'Add New')
@section('content')
    @php
        $cfg = config('warranty');
        $type = old('item_type', $product->item_type ?: 'product');
        $mode = old('warranty_mode', $product->warranty_mode ?: 'single');
        $rows = old(
            'warranties',
            $product->exists
                ? $product->warranties
                    ->map(
                        fn($w) => [
                            'id' => $w->id,
                            'component' => $w->component,
                            'warranty_type' => $w->warranty_type,
                            'start_date' => $w->start_date->toDateString(),
                            'duration' => $w->duration,
                            'duration_unit' => $w->duration_unit,
                            'end_date' => $w->end_date->toDateString(),
                            'override_end' => $w->end_date_source === 'manual',
                            'provider' => $w->provider,
                            'terms' => $w->terms,
                            'reminder_days' => $w->reminder_days ?? [],
                        ],
                    )
                    ->all()
                : [
                    [
                        'component' => 'Whole Product',
                        'warranty_type' => 'manufacturer',
                        'duration' => 12,
                        'duration_unit' => 'month',
                        'start_date' => today()->toDateString(),
                        'reminder_days' => $cfg['default_reminders'],
                    ],
                ],
        );
        $blank = [
            'component' => '',
            'warranty_type' => 'manufacturer',
            'duration' => 12,
            'duration_unit' => 'month',
            'start_date' => today()->toDateString(),
            'reminder_days' => $cfg['default_reminders'],
        ];
        $val = fn($k) => old($k, $product->$k);
        $subMap = $categories->mapWithKeys(
            fn($c) => [$c->id => $c->subcategories->map(fn($s) => ['id' => $s->id, 'name' => $s->name])->values()],
        );

        // after a server-side validation error, open the step that contains the first error
        $startPanel = null;
        foreach ($errors->keys() as $k) {
            if (str_starts_with($k, 'warranties') || $k === 'warranty_mode' || $k === 'notes') {
                $startPanel = 'panel-warranty';
                break;
            }
            if (
                in_array($k, [
                    'purchase_date',
                    'purchase_price',
                    'currency',
                    'seller_name',
                    'seller_phone',
                    'invoice_number',
                    'purchase_location',
                ])
            ) {
                $startPanel = $startPanel ?: 'panel-purchase';
            }
        }
    @endphp

    <style>
        #panel-warranty[data-kind="document"] .only-product,
        #panel-warranty[data-kind="document"] .only-product-inline,
        #panel-warranty[data-kind="product"] .only-document,
        #panel-warranty[data-kind="product"] .only-document-inline {
            display: none !important;
        }

        #panel-warranty[data-mode="single"] .only-multiple {
            display: none !important;
        }

        .type-card {
            cursor: pointer;
            border: 2px solid transparent;
        }

        .btn-check:checked+.type-card {
            border-color: var(--wt-blue);
            background: #eef4ff;
        }

        .step-pill {
            border: 0;
            background: #e9eef8;
            color: #51628a;
            border-radius: 2rem;
            padding: .45rem 1rem;
            font-size: .9rem;
        }

        .step-pill .num {
            display: inline-flex;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background: #fff;
            align-items: center;
            justify-content: center;
            font-size: .75rem;
            margin-right: .4rem;
            font-weight: 600;
        }

        .step-pill.active {
            background: var(--wt-blue);
            color: #fff;
        }

        .step-pill.done {
            background: #d8f3e6;
            color: #137a4d;
        }

        .is-invalid-msg {
            color: #dc3545;
            font-size: .8rem;
        }
    </style>

    <div class="d-flex justify-content-between align-items-center mb-3 gap-3">
        <div class="">
            <h3 class="page-title mb-1">
                {{ $product->exists ? 'Edit ' . ($product->isDocument() ? 'Document' : 'Product') : 'Add Product / Document' }}
            </h3>
            <p class="text-muted">Fill each step and click Next. Save on the last step.</p>
        </div>
        <button type="button" class="btn btn-warning align-self-start align-self-sm-auto" onclick="window.history.back()">
            <i class="bi bi-arrow-left"></i> Back
        </button>
    </div>

    <form method="POST" id="item-form" data-subs="{{ json_encode($subMap) }}" data-start="{{ $startPanel }}"
        action="{{ $product->exists ? route('products.update', $product) : route('products.store') }}">
        @csrf @if ($product->exists)
            @method('PUT')
        @endif

        {{-- what are we adding? --}}
        @if ($product->exists)
            <input type="hidden" name="item_type" value="{{ $product->item_type }}">
        @else
            <div class="row g-3 mb-3" style="max-width:560px">
                @foreach (['product' => ['bi-box-seam', 'Product', 'Phone, fridge, laptop... with warranty'], 'document' => ['bi-file-earmark-text', 'Document', 'License, passport... with expiry & renewal']] as $k => [$ic, $t, $d])
                    <div class="col-6">
                        <input type="radio" class="btn-check" name="item_type" id="type_{{ $k }}"
                            value="{{ $k }}" @checked($type === $k)>
                        <label for="type_{{ $k }}" class="card type-card h-100 w-100 p-3 text-center">
                            <i class="bi {{ $ic }} fs-2 text-primary"></i>
                            <div class="fw-semibold">{{ $t }}</div><small
                                class="text-muted">{{ $d }}</small>
                        </label>
                    </div>
                @endforeach
            </div>
        @endif

        <div id="stepper" class="d-flex flex-wrap gap-2 mb-3"></div>

        {{-- STEP: product information --}}
        <div class="card mb-3 step-panel" id="panel-info-product" data-type="product" data-title="Product Information">
            <div class="card-body">
                <h5 class="section-title mb-3"><i class="bi bi-box-seam text-primary"></i> Product Information</h5>
                <div class="row g-3">
                    <div class="col-12 col-md-6"><label class="form-label">Product Name <span
                                class="text-danger">*</span></label>
                        <input name="name" class="form-control" data-required value="{{ $val('name') }}"
                            placeholder="e.g. Samsung Split AC">
                    </div>
                    <div class="col-12 col-md-3"><label class="form-label">Category <span
                                class="text-danger">*</span></label>
                        <select name="category_id" id="category_id" class="form-select" data-required>
                            <option value="">Select a category</option>
                            @foreach ($categories as $c)
                                <option value="{{ $c->id }}" @selected($val('category_id') == $c->id)>{{ $c->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-3"><label class="form-label">Subcategory <span
                                class="text-danger">*</span></label>
                        <select name="subcategory_id" id="subcategory_id" class="form-select"
                            data-selected="{{ $val('subcategory_id') }}"></select>
                    </div>
                    <div class="col-12 col-md-4"><label class="form-label">Brand</label><input name="brand"
                            class="form-control" value="{{ $val('brand') }}" placeholder="e.g. Samsung"></div>
                    <div class="col-12 col-md-4"><label class="form-label">Model</label><input name="model"
                            class="form-control" value="{{ $val('model') }}" placeholder="e.g. AR12TYHQASIN"></div>
                    <div class="col-12 col-md-4"><label class="form-label">Serial Number</label><input name="serial_number"
                            class="form-control" value="{{ $val('serial_number') }}" placeholder="e.g. SN1234567890"></div>
                    <div class="col-12"><label class="form-label">Product Description</label>
                        <textarea name="description" rows="2" maxlength="500" class="form-control"
                            placeholder="Add any additional details about your product...">{{ $val('description') }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        {{-- STEP: document information --}}
        <div class="card mb-3 step-panel" id="panel-info-document" data-type="document" data-title="Document Information">
            <div class="card-body">
                <h5 class="section-title mb-3"><i class="bi bi-file-earmark-text text-primary"></i> Document Information
                </h5>
                <div class="row g-3">
                    <div class="col-12 col-md-6"><label class="form-label">Document Type <span
                                class="text-danger">*</span></label>
                        <select name="document_type" class="form-select" data-required>
                            <option value="">Select a type</option>
                            @foreach ($cfg['document_types'] as $dt)
                                <option @selected($val('document_type') === $dt)>{{ $dt }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-6"><label class="form-label">Title / Name <span
                                class="text-danger">*</span></label>
                        <input name="name" class="form-control" data-required value="{{ $val('name') }}"
                            placeholder="e.g. My Driving License">
                    </div>
                    <div class="col-12 col-md-6"><label class="form-label">Document Number</label>
                        <input name="serial_number" class="form-control" value="{{ $val('serial_number') }}"
                            placeholder="e.g. DL-1234567">
                    </div>
                    <div class="col-12 col-md-6"><label class="form-label">Issued By</label>
                        <input name="issued_by" class="form-control" value="{{ $val('issued_by') }}"
                            placeholder="e.g. BRTA, Passport Office">
                    </div>
                    <div class="col-12"><label class="form-label">Description</label>
                        <textarea name="description" rows="2" maxlength="500" class="form-control">{{ $val('description') }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        {{-- STEP: purchase details (products only) --}}
        <div class="card mb-3 step-panel" id="panel-purchase" data-type="product" data-title="Purchase Details">
            <div class="card-body">
                <h5 class="section-title mb-3"><i class="bi bi-cart text-success"></i> Purchase Details</h5>
                <div class="row g-3">
                    <div class="col-12 col-md-4"><label class="form-label">Purchase Date <span
                                class="text-danger">*</span></label>
                        <input type="date" name="purchase_date" class="form-control" data-required
                            value="{{ old('purchase_date', optional($product->purchase_date)->toDateString()) }}">
                    </div>
                    <div class="col-8 col-md-4"><label class="form-label">Purchase Price</label>
                        <input type="number" step="0.01" min="0" name="purchase_price" class="form-control"
                            value="{{ $val('purchase_price') }}" placeholder="72000">
                    </div>
                    <div class="col-4 col-md-4"><label class="form-label">Currency</label>
                        <select name="currency" class="form-select">
                            @foreach ($cfg['currencies'] as $cur)
                                <option @selected(($val('currency') ?: 'BDT') === $cur)>{{ $cur }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-4"><label class="form-label">Seller / Store Name</label><input
                            name="seller_name" class="form-control" value="{{ $val('seller_name') }}"
                            placeholder="ABC Electronics"></div>
                    <div class="col-12 col-md-4"><label class="form-label">Seller Phone</label><input name="seller_phone"
                            class="form-control" value="{{ $val('seller_phone') }}" placeholder="+880 1712 345678">
                    </div>
                    <div class="col-12 col-md-4"><label class="form-label">Invoice Number</label><input
                            name="invoice_number" class="form-control" value="{{ $val('invoice_number') }}"
                            placeholder="INV-45872"></div>
                    <div class="col-12"><label class="form-label">Purchase Location</label><input
                            name="purchase_location" class="form-control" value="{{ $val('purchase_location') }}"
                            placeholder="e.g. Dhaka, Bangladesh or Online Store"></div>
                </div>
            </div>
        </div>

        {{-- STEP: warranty information (products) / validity (documents) --}}
        <div class="step-panel" id="panel-warranty" data-type="both" data-title-product="Warranty Information"
            data-title-document="Validity & Reminders" data-kind="{{ $type }}" data-mode="{{ $mode }}">
            <div class="card mb-3">
                <div class="card-body">
                    <h5 class="section-title mb-3"><i class="bi bi-shield-check text-primary"></i>
                        <span class="only-product-inline">Warranty Information</span><span
                            class="only-document-inline">Validity &amp; Reminders</span>
                    </h5>

                    <div class="only-product mb-3" style="max-width:520px">
                        <label class="form-label">How many warranties does this product have? <span
                                class="text-danger">*</span></label>
                        <select name="warranty_mode" id="warranty_mode" class="form-select">
                            <option value="single" @selected($mode === 'single')>Single warranty (for the whole product)
                            </option>
                            <option value="multiple" @selected($mode === 'multiple')>Multiple warranties (separate warranty
                                for each component)</option>
                        </select>
                        <div class="form-text only-multiple">Example: Refrigerator &rarr; Compressor 10 years, Motor 5
                            years. The product itself has no warranty.</div>
                    </div>
                    <div class="only-document text-muted small mb-3">Enter when this document is valid. Reminders tell you
                        before it expires, and you can renew it later.</div>

                    <div class="d-flex justify-content-end only-multiple mb-2">
                        <button type="button" id="add-warranty" class="btn btn-outline-primary btn-sm"><i
                                class="bi bi-plus-lg"></i> Add component warranty</button>
                    </div>
                    <div id="warranty-list">
                        @foreach ($rows as $i => $row)
                            @include('products._warranty_row', ['i' => $i, 'row' => $row])
                        @endforeach
                    </div>
                    <template id="warranty-template">@include('products._warranty_row', ['i' => '__i__', 'row' => $blank])</template>

                    <label class="form-label mt-2">Notes</label>
                    <textarea name="notes" rows="2" maxlength="1000" class="form-control"
                        placeholder="Additional notes (optional)">{{ $val('notes') }}</textarea>
                    <div class="form-text">You can upload invoice / scanned files on the next page, after saving.</div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between gap-2 mb-4">
            <div class="d-flex gap-2">
                <button type="button" id="btn-prev" class="btn btn-outline-secondary d-none"><i
                        class="bi bi-arrow-left"></i> Previous</button>
            </div>
            <div>
                <button type="button" id="btn-next" class="btn btn-primary">Next <i
                        class="bi bi-arrow-right"></i></button>
                <button type="submit" id="btn-submit" class="btn btn-success btn-lg d-none"><i
                        class="bi bi-check-circle"></i> {{ $product->exists ? 'Update' : 'Save' }}</button>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        (function() {
            const form = document.getElementById('item-form');
            const panels = [...form.querySelectorAll('.step-panel')];
            const stepper = document.getElementById('stepper');
            const wPanel = document.getElementById('panel-warranty');
            const list = document.getElementById('warranty-list');
            const tpl = document.getElementById('warranty-template').innerHTML;
            const modeSel = document.getElementById('warranty_mode');
            const cat = document.getElementById('category_id');
            const sub = document.getElementById('subcategory_id');
            const subs = JSON.parse(form.dataset.subs);
            const btnPrev = document.getElementById('btn-prev'),
                btnNext = document.getElementById('btn-next'),
                btnSubmit = document.getElementById('btn-submit');
            let steps = [],
                cur = 0,
                counter = list.querySelectorAll('.warranty-row').length + 100;
            const pad = n => String(n).padStart(2, '0');

            const getType = () => {
                const checked = form.querySelector('input[name=item_type]:checked');
                return checked ? checked.value : form.querySelector('input[name=item_type]').value;
            };
            const rows = () => [...list.querySelectorAll('.warranty-row')];

            // ---------- subcategory depends on category ----------
            function fillSub(selected) {
                const items = subs[cat.value] || [];
                sub.innerHTML = '<option value="">' + (cat.value ? (items.length ? 'Select subcategory' : '- none -') :
                    'Select a category first') + '</option>';
                items.forEach(s => {
                    const o = document.createElement('option');
                    o.value = s.id;
                    o.textContent = s.name;
                    if (String(s.id) === String(selected)) o.selected = true;
                    sub.appendChild(o);
                });
                sub.toggleAttribute('data-required', items.length > 0);
                if (window.jQuery) jQuery(sub).trigger('change.select2'); // refresh Select2 display
            }
            cat.addEventListener('change', () => fillSub(''));
            fillSub(sub.dataset.selected);

            // ---------- end date calculation ----------
            function calc(row) {
                if (row.querySelector('.w-override').checked) return;
                const s = row.querySelector('.w-start').value;
                const d = parseInt(row.querySelector('.w-duration').value, 10);
                const u = row.querySelector('.w-unit').value;
                if (!s || !d) return;
                const dt = new Date(s + 'T00:00:00');
                if (u === 'day') {
                    dt.setDate(dt.getDate() + d);
                } else {
                    const months = u === 'year' ? d * 12 : d;
                    const day = dt.getDate();
                    dt.setDate(1);
                    dt.setMonth(dt.getMonth() + months);
                    const last = new Date(dt.getFullYear(), dt.getMonth() + 1, 0).getDate();
                    dt.setDate(Math.min(day, last));
                }
                dt.setDate(dt.getDate() - 1);
                setDateValue(row.querySelector('.w-end'), dt.getFullYear() + '-' + pad(dt.getMonth() + 1) + '-' + pad(dt
                    .getDate()));
            }
            list.addEventListener('input', e => {
                const r = e.target.closest('.warranty-row');
                if (r) calc(r);
            });
            list.addEventListener('change', e => {
                const r = e.target.closest('.warranty-row');
                if (!r) return;
                if (e.target.classList.contains('w-override')) setDateEditable(r.querySelector('.w-end'), e
                    .target.checked);
                calc(r);
            });
            list.addEventListener('click', e => {
                const btn = e.target.closest('.remove-warranty');
                if (!btn) return;
                if (rows().length > 1) {
                    btn.closest('.warranty-row').remove();
                    applyMode();
                } else toastr.warning('At least one warranty is required.');
            });
            document.getElementById('add-warranty').addEventListener('click', () => {
                list.insertAdjacentHTML('beforeend', tpl.replaceAll('__i__', counter++));
                if (window.initSelect2) initSelect2(list.lastElementChild); // Select2 for the new row
                if (window.initDatePickers) initDatePickers(list
                    .lastElementChild); // date picker for the new row
                calc(list.lastElementChild);
                applyMode();
            });
            rows().forEach(r => {
                setDateEditable(r.querySelector('.w-end'), r.querySelector('.w-override').checked);
                calc(r);
            });

            // ---------- single / multiple warranty ----------
            function applyMode() {
                const type = getType();
                const mode = type === 'document' ? 'single' : modeSel.value;
                wPanel.dataset.kind = type;
                wPanel.dataset.mode = mode;
                rows().forEach((r, i) => r.classList.toggle('d-none', mode === 'single' && i > 0));
            }
            modeSel.addEventListener('change', () => {
                applyMode();
                // when going to "multiple", give the first row an empty component name to fill in
                const first = rows()[0];
                if (modeSel.value === 'multiple' && first) {
                    const c = first.querySelector('input[name$="[component]"]');
                    if (c && c.value === 'Whole Product') c.value = '';
                }
            });

            // ---------- wizard ----------
            function build(keepPanel) {
                const type = getType();
                steps = panels.filter(p => p.dataset.type === 'both' || p.dataset.type === type);
                // controls of panels that do not belong to this type are disabled -> they are not submitted
                panels.forEach(p => p.querySelectorAll('input,select,textarea').forEach(el => el.disabled = !steps
                    .includes(p)));
                fillSub(sub.value || sub.dataset.selected);
                sub.disabled = !steps.includes(document.getElementById('panel-info-product'));
                applyMode();
                stepper.innerHTML = '';
                steps.forEach((p, i) => {
                    const title = p.dataset['title' + (type === 'product' ? 'Product' : 'Document')] || p
                        .dataset.title;
                    p.dataset.stepTitle = title;
                    const b = document.createElement('button');
                    b.type = 'button';
                    b.className = 'step-pill';
                    b.innerHTML = '<span class="num">' + (i + 1) + '</span>' + title;
                    b.addEventListener('click', () => {
                        if (i < cur) show(i);
                    });
                    stepper.appendChild(b);
                });
                show(keepPanel ? Math.max(0, steps.findIndex(p => p.id === keepPanel)) : 0);
            }

            function show(i) {
                cur = i;
                panels.forEach(p => p.classList.add('d-none'));
                steps[i].classList.remove('d-none');
                [...stepper.children].forEach((b, k) => {
                    b.classList.toggle('active', k === i);
                    b.classList.toggle('done', k < i);
                });
                btnPrev.classList.toggle('d-none', i === 0);
                const last = i === steps.length - 1;
                btnNext.classList.toggle('d-none', last);
                btnSubmit.classList.toggle('d-none', !last);
                if (!last) btnNext.innerHTML = 'Next: ' + steps[i + 1].dataset.stepTitle +
                    ' <i class="bi bi-arrow-right"></i>';
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            }

            function validate(panel) {
                let ok = true,
                    first = null;
                panel.querySelectorAll('[data-required]').forEach(el => {
                    const vis = el._flatpickr ? el._flatpickr.altInput :
                        el; // date pickers show an alternative input
                    if (el.disabled || vis.offsetParent === null) return; // hidden fields are skipped
                    const bad = !String(el.value).trim() || (el.type !== 'hidden' && el.checkValidity && !el
                        .checkValidity());
                    vis.classList.toggle('is-invalid', bad);
                    if (bad) {
                        ok = false;
                        first = first || vis;
                    }
                });
                if (first) first.focus();
                return ok;
            }
            const clearInvalid = e => {
                e.target.classList.remove('is-invalid');
                if (e.target._flatpickr) e.target._flatpickr.altInput.classList.remove('is-invalid');
            };
            form.addEventListener('input', clearInvalid);
            form.addEventListener('change', clearInvalid);

            btnNext.addEventListener('click', () => {
                if (validate(steps[cur])) show(cur + 1);
            });
            btnPrev.addEventListener('click', () => show(cur - 1));
            form.addEventListener('submit', e => {
                // validate every step before saving
                for (let i = 0; i < steps.length; i++) {
                    if (!validate(steps[i])) {
                        e.preventDefault();
                        show(i);
                        validate(steps[i]);
                        return;
                    }
                }
            });
            form.addEventListener('keydown', e => {
                if (e.key === 'Enter' && e.target.tagName === 'INPUT') {
                    e.preventDefault();
                    if (cur < steps.length - 1) btnNext.click();
                }
            });
            form.querySelectorAll('input[name=item_type]').forEach(r => r.addEventListener('change', () => build(
                null)));

            build(form.dataset.start || null);
        })();
    </script>
@endpush
