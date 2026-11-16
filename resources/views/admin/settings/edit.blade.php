@extends('layouts.app')
@section('title', 'Company & Invoice Settings')
@section('content')
    <div class="row">
        <div class="col-11 mx-auto">
            <div class="d-flex justify-content-between align-items-center">
                <h3 class="page-title">Company &amp; Invoice Settings</h3>
                <button type="button" class="btn btn-warning align-self-start align-self-sm-auto"
                    onclick="window.history.back()"><i class="bi bi-arrow-left"></i> Back</button>
            </div>
            <p class="text-muted">These details and the logo are printed on every invoice (the PDF the customer and you download, and the one in the emails).</p>

            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')

                        <h6 class="section-title mb-3">Logo</h6>
                        <div class="row mb-3">
                            <div class="col-lg-6 col-md-6 col-sm-12">
                                <label class="form-label">Company logo</label>
                                <input type="file" name="logo" class="form-control" accept=".jpg,.jpeg,.png,.webp">
                                <div class="form-text">JPG, PNG or WEBP, max 2 MB. A square logo looks best. It is made smaller automatically.</div>
                            </div>
                            <div class="col-lg-6 col-md-6 col-sm-12">
                                @if ($logoUrl)
                                    <img src="{{ $logoUrl }}" alt="Logo" class="rounded border bg-white p-1" style="height:96px">
                                    <div class="form-check mt-1">
                                        <input class="form-check-input" type="checkbox" name="remove_logo" value="1" id="remove_logo">
                                        <label class="form-check-label small" for="remove_logo">Remove the logo</label>
                                    </div>
                                @else
                                    <div class="text-muted small mt-4">No logo yet.</div>
                                @endif
                            </div>
                        </div>

                        <h6 class="section-title mb-3 mt-4">Company</h6>
                        <div class="row mb-3">
                            <div class="col-lg-6 col-md-6 col-sm-12">
                                <label class="form-label">Company name *</label>
                                <input name="company_name" placeholder="Company name" class="form-control" value="{{ old('company_name', $s['company_name']) }}" required>
                            </div>
                            <div class="col-lg-6 col-md-6 col-sm-12">
                                <label class="form-label">Tagline</label>
                                <input name="company_tagline" placeholder="Tagline" class="form-control" value="{{ old('company_tagline', $s['company_tagline']) }}">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-lg-6 col-md-6 col-sm-12">
                                <label class="form-label">Office title</label>
                                <input name="company_office" placeholder="e.g. Billing Office" class="form-control" value="{{ old('company_office', $s['company_office']) }}">
                            </div>
                            <div class="col-lg-6 col-md-6 col-sm-12">
                                <label class="form-label">Phone</label>
                                <input name="company_phone" placeholder="Phone" class="form-control" value="{{ old('company_phone', $s['company_phone']) }}">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-12">
                                <label class="form-label">Address</label>
                                <textarea name="company_address" rows="2" class="form-control" placeholder="One line per row">{{ old('company_address', $s['company_address']) }}</textarea>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-lg-6 col-md-6 col-sm-12">
                                <label class="form-label">Billing email</label>
                                <input type="email" name="company_email" placeholder="billing@example.com" class="form-control" value="{{ old('company_email', $s['company_email']) }}">
                            </div>
                            <div class="col-lg-6 col-md-6 col-sm-12">
                                <label class="form-label">Website</label>
                                <input name="company_website" placeholder="www.example.com" class="form-control" value="{{ old('company_website', $s['company_website']) }}">
                            </div>
                        </div>

                        <h6 class="section-title mb-3 mt-4">Invoice</h6>
                        <div class="row mb-3">
                            <div class="col-lg-6 col-md-6 col-sm-12">
                                <label class="form-label">Invoice subtitle</label>
                                <input name="invoice_subtitle" placeholder="e.g. SAAS SUBSCRIPTION" class="form-control" value="{{ old('invoice_subtitle', $s['invoice_subtitle']) }}">
                            </div>
                            <div class="col-lg-6 col-md-6 col-sm-12">
                                <label class="form-label">VAT / Tax text</label>
                                <input name="tax_text" placeholder="e.g. Not charged" class="form-control" value="{{ old('tax_text', $s['tax_text']) }}">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-lg-6 col-md-6 col-sm-12">
                                <label class="form-label">Product name</label>
                                <input name="product_name" placeholder="Warranty Tracker" class="form-control" value="{{ old('product_name', $s['product_name']) }}">
                            </div>
                            <div class="col-lg-6 col-md-6 col-sm-12">
                                <label class="form-label">Product description</label>
                                <input name="product_description" placeholder="Cloud-based SaaS warranty management" class="form-control" value="{{ old('product_description', $s['product_description']) }}">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-12">
                                <label class="form-label">Billing notes</label>
                                <textarea name="invoice_notes" rows="4" class="form-control" placeholder="One note per row">{{ old('invoice_notes', $s['invoice_notes']) }}</textarea>
                                <div class="form-text">Each row becomes one bullet in the "Subscription &amp; billing notes" box.</div>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-12">
                                <label class="form-label">Footer line</label>
                                <input name="invoice_footer" placeholder="Thank you for choosing ..." class="form-control" value="{{ old('invoice_footer', $s['invoice_footer']) }}">
                            </div>
                        </div>

                        <h6 class="section-title mb-3 mt-4">Payment information</h6>
                        <div class="row mb-3">
                            <div class="col-lg-6 col-md-6 col-sm-12">
                                <label class="form-label">Payment method</label>
                                <input name="payment_method" placeholder="Bank Transfer / Mobile Financial Service" class="form-control" value="{{ old('payment_method', $s['payment_method']) }}">
                            </div>
                            <div class="col-lg-6 col-md-6 col-sm-12">
                                <label class="form-label">Bank</label>
                                <input name="bank_name" placeholder="Bank name" class="form-control" value="{{ old('bank_name', $s['bank_name']) }}">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-lg-4 col-md-4 col-sm-12">
                                <label class="form-label">Account name</label>
                                <input name="account_name" placeholder="Account name" class="form-control" value="{{ old('account_name', $s['account_name']) }}">
                            </div>
                            <div class="col-lg-4 col-md-4 col-sm-12">
                                <label class="form-label">Account no</label>
                                <input name="account_no" placeholder="Account number" class="form-control" value="{{ old('account_no', $s['account_no']) }}">
                            </div>
                            <div class="col-lg-4 col-md-4 col-sm-12">
                                <label class="form-label">Branch</label>
                                <input name="bank_branch" placeholder="Branch" class="form-control" value="{{ old('bank_branch', $s['bank_branch']) }}">
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <button class="btn btn-primary"><i class="bi bi-check-lg"></i> Save Settings</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
