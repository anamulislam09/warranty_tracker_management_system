@extends('layouts.app')
@section('title', $schedule->exists ? 'Edit Payment Schedule' : 'Add Payment Schedule')
@section('content')
    <div class="row">
        <div class="col-11 mx-auto">
            <div class="d-flex justify-content-between align-items-center">
                <h3 class="page-title">{{ $schedule->exists ? 'Edit Payment Schedule' : 'Add Payment Schedule' }}</h3>
                <button type="button" class="btn btn-warning align-self-start align-self-sm-auto"
                    onclick="window.history.back()"><i class="bi bi-arrow-left"></i> Back</button>
            </div>

            @if ($schedule->exists && $schedule->accounts_count > 0)
                <div class="alert alert-info py-2">{{ $schedule->accounts_count }} customer(s) use this schedule. A new number of months or a new discount applies to the bills created from now on; bills that already exist do not change.</div>
            @endif

            <div class="card">
                <div class="card-body">
                    <form method="POST"
                        action="{{ $schedule->exists ? route('admin.schedules.update', $schedule) : route('admin.schedules.store') }}">
                        @csrf
                        @if ($schedule->exists)
                            @method('PUT')
                        @endif

                        <div class="row mb-3">
                            <div class="col-lg-6 col-md-6 col-sm-12">
                                <label class="form-label">Name *</label>
                                <input name="name" placeholder="e.g. Yearly" class="form-control"
                                    value="{{ old('name', $schedule->name) }}" required>
                            </div>
                            <div class="col-lg-6 col-md-6 col-sm-12">
                                <label class="form-label">One bill covers (months) *</label>
                                <input type="number" min="1" max="36" name="months" placeholder="1, 3, 6, 12" class="form-control"
                                    value="{{ old('months', $schedule->months) }}" required>
                                <div class="form-text">1 = monthly, 3 = quarterly, 6 = half-yearly, 12 = yearly (one bill a year).</div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-lg-6 col-md-6 col-sm-12">
                                <label class="form-label">Discount (%) *</label>
                                <input type="number" step="0.01" min="0" max="50" name="discount_percent" placeholder="0"
                                    class="form-control" value="{{ old('discount_percent', $schedule->discount_percent) }}" required>
                                <div class="form-text">Taken off the price of the bill. Use 0 for no discount.</div>
                            </div>
                            <div class="col-lg-6 col-md-6 col-sm-12">
                                <label class="form-label">Sort order</label>
                                <input type="number" min="0" name="sort_order" placeholder="Sort order" class="form-control"
                                    value="{{ old('sort_order', $schedule->sort_order ?? 0) }}">
                            </div>
                        </div>

                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1"
                                id="is_active" @checked(old('is_active', $schedule->is_active))>
                            <label class="form-check-label" for="is_active">Active <small class="text-muted">(customers can choose it)</small></label>
                        </div>

                        <div class="d-flex gap-2">
                            <button class="btn btn-primary"><i class="bi bi-check-lg"></i> Save Schedule</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
