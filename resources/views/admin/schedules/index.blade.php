@extends('layouts.app')
@section('title', 'Payment Schedules')
@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h3 class="page-title mb-1">Payment Schedules</h3>
            <p class="text-muted mb-0">How often a customer pays, and the discount for paying for longer periods at once.</p>
        </div>
        <a href="{{ route('admin.schedules.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Add Schedule</a>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>One bill covers</th>
                        <th>Bills per year</th>
                        <th>Discount</th>
                        <th>Customers</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($schedules as $s)
                        <tr>
                            <td>{{ $schedules->firstItem() + $loop->index }}</td>
                            <td class="fw-semibold">{{ $s->name }}</td>
                            <td>{{ $s->months }} {{ $s->months == 1 ? 'month' : 'months' }}</td>
                            <td>{{ 12 % $s->months === 0 ? 12 / $s->months : round(12 / $s->months, 1) }}</td>
                            <td>@if ((float) $s->discount_percent > 0)<span class="badge text-bg-success">{{ (float) $s->discount_percent }}% off</span>@else <span class="text-muted">none</span>@endif</td>
                            <td>{{ $s->accounts_count }}</td>
                            <td><x-status-badge :status="$s->is_active ? 'active' : 'inactive'" /></td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('admin.schedules.edit', $s) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                                <form method="POST" action="{{ route('admin.schedules.destroy', $s) }}" class="d-inline" data-confirm="Delete this payment schedule?">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">No payment schedule yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $schedules->links() }}</div>
    <p class="small text-muted mt-2">A bill is created 15 days before each period starts. Amount = plan monthly price x months - discount.</p>
@endsection
