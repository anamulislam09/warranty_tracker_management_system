<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\FileResource;
use App\Models\Document;
use App\Models\Product;
use App\Services\DocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Files of a product (invoice, warranty card ...). Private: they are only served to the logged-in owner of the account. */
class FileController extends ApiController
{
    public function store(Request $request, Product $product, DocumentService $service)
    {
        $account = $request->user()->account;

        if (! $account->plan?->document_upload_enabled) {
            return $this->fail('Document upload is not available in your plan.', 'plan_feature_missing');
        }

        $request->validate([
            'type' => 'required|in:invoice,warranty_card,receipt,other',
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:5120',
        ]);

        if ($account->storageLimitReached($request->file('file')->getSize())) {
            return $this->fail('Storage limit reached. Please upgrade your plan.', 'storage_limit_reached');
        }

        $document = $service->store($product, $request->file('file'), $request->type);

        return $this->created($this->one(FileResource::class, $document), 'File uploaded.');
    }

    /** View (inline) or download (?download=1). The app must send the Bearer token. */
    public function show(Request $request, Document $document)
    {
        abort_unless(Storage::disk('local')->exists($document->path), 404);

        return $request->boolean('download')
            ? Storage::disk('local')->download($document->path, $document->original_name)
            : Storage::disk('local')->response($document->path, $document->original_name);
    }

    public function destroy(Document $document)
    {
        Storage::disk('local')->delete($document->path);
        $document->delete();

        return $this->ok(null, 'File deleted.');
    }
}
