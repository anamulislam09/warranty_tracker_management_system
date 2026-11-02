<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    use BelongsToAccount;

    protected $fillable = ['account_id', 'product_id', 'type', 'original_name', 'path', 'mime_type', 'size'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function getSizeHumanAttribute(): string
    {
        return $this->size >= 1048576
            ? round($this->size / 1048576, 1) . ' MB'
            : max(1, round($this->size / 1024)) . ' KB';
    }

    public function getIsImageAttribute(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }
}
