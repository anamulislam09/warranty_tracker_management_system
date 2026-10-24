<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Product;
use Illuminate\Http\UploadedFile;

class DocumentService
{
    /** Files go to the PRIVATE disk (storage/app/private) - never publicly reachable. */
    public function store(Product $product, UploadedFile $file, string $type): Document
    {
        $path = $file->store("accounts/{$product->account_id}/products/{$product->id}", 'local');

        return Document::create([
            'account_id'    => $product->account_id,
            'product_id'    => $product->id,
            'type'          => $type,
            'original_name' => $file->getClientOriginalName(),
            'path'          => $path,
            'mime_type'     => $file->getMimeType(),
            'size'          => $file->getSize(),
        ]);
    }
}
