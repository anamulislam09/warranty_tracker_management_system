<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Product;
use App\Services\DocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function store(Request $request, Product $product, DocumentService $service)
    {
        $account = $request->user()->account;

        if (! $account->plan?->document_upload_enabled) {
            return back()->with('error', 'Document upload is not available in your plan.');
        }

        $request->validate([
            'type' => 'required|in:invoice,warranty_card,receipt,other',
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:5120',
        ]);

        if ($account->storageLimitReached($request->file('file')->getSize())) {
            return back()->with('error', 'Storage limit reached. Please upgrade your plan.');
        }

        $service->store($product, $request->file('file'), $request->type);

        return back()->with('success', 'Document uploaded.');
    }

    // Authenticated view / download - no public URL
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
        return back()->with('success', 'Document deleted.');
    }
}
