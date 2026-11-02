<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserNotification extends Model
{
    protected $fillable = ['account_id', 'user_id', 'product_id', 'type', 'title', 'message', 'read_at'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    public function product()
    {
        return $this->belongsTo(Product::class)->withoutGlobalScopes();
    }
}
