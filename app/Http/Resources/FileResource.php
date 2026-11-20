<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/** An uploaded file (invoice, warranty card ...). Files are private: open them with the Bearer token. */
class FileResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'            => $this->id,
            'type'          => $this->type,                    // invoice | warranty_card | receipt | other
            'original_name' => $this->original_name,
            'mime_type'     => $this->mime_type,
            'size'          => (int) $this->size,
            'size_human'    => $this->size_human,
            'is_image'      => $this->is_image,
            'view_url'      => route('api.files.show', $this->id),
            'download_url'  => route('api.files.show', [$this->id, 'download' => 1]),
            'created_at'    => optional($this->created_at)->toIso8601String(),
        ];
    }
}
