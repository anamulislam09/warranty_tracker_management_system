<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'description' => $this->description,
            'image_url'   => $this->image_url,
            'subcategories' => $this->subcategories->where('is_active', true)->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->values(),
        ];
    }
}
