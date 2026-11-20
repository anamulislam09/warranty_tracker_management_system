<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'         => $this->id,
            'type'       => $this->type,                        // expiry | billing ...
            'title'      => $this->title,
            'message'    => $this->message,
            'product_id' => $this->product_id,                  // open this product when it is set
            'is_read'    => $this->read_at !== null,
            'read_at'    => optional($this->read_at)->toIso8601String(),
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
