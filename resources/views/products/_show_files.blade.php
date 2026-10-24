@php $user = auth()->user(); @endphp
<div id="files-card" class="card {{ $fill ? 'h-100' : '' }}" style="scroll-margin-top:80px"><div class="card-body">
    <div class="d-flex align-items-center gap-2 mb-3">
        <span class="ic ic-orange"><i class="bi bi-file-earmark-text"></i></span>
        <h6 class="mb-0 fw-semibold" style="color:#e07b00">Documents / Files ({{ $product->documents->count() }})</h6>
    </div>
    @forelse ($product->documents as $d)
        <div class="d-flex align-items-center justify-content-between gap-2 py-2 border-bottom">
            <div class="text-truncate"><i class="bi {{ $d->is_image ? 'bi-file-image text-primary' : 'bi-file-earmark-pdf text-danger' }} fs-5 me-1"></i>
                <span class="fw-semibold">{{ $d->original_name }}</span><br><small class="text-muted">{{ ucwords(str_replace('_', ' ', $d->type)) }} &middot; {{ $d->size_human }} &middot; {{ $d->created_at->format(config('warranty.date_format')) }}</small></div>
            <div class="text-nowrap">
                <a href="{{ route('documents.show', $d) }}" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                <a href="{{ route('documents.show', [$d, 'download' => 1]) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-download"></i></a>
                @if ($user->hasPermission('documents.manage'))
                    <form method="POST" action="{{ route('documents.destroy', $d) }}" class="d-inline" data-confirm="Delete this file?">@csrf @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                @endif
            </div>
        </div>
    @empty
        <p class="text-muted small">No files uploaded.</p>
    @endforelse

    @if ($user->hasPermission('documents.manage'))
        <form method="POST" action="{{ route('documents.store', $product) }}" enctype="multipart/form-data" class="row g-2 mt-2">
            @csrf
            <div class="col-12 col-sm-4">
                <select name="type" class="form-select form-select-sm">
                    @foreach (['invoice' => 'Invoice', 'warranty_card' => 'Warranty Card', 'receipt' => 'Receipt', 'other' => 'Other'] as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach
                </select>
            </div>
            <div class="col-12 col-sm-5"><input type="file" name="file" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png,.webp" required></div>
            <div class="col-12 col-sm-3"><button class="btn btn-primary btn-sm w-100"><i class="bi bi-upload"></i> Upload</button></div>
            <div class="col-12"><small class="text-muted">PDF, JPG, JPEG, PNG or WEBP (max 5 MB). Stored privately.</small></div>
        </form>
    @endif
</div></div>
