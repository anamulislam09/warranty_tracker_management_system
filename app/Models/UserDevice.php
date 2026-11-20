<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A phone of a user that can receive push notifications. */
class UserDevice extends Model
{
    protected $fillable = ['user_id', 'token', 'platform', 'device_name', 'last_used_at'];

    protected function casts(): array
    {
        return ['last_used_at' => 'datetime'];
    }

    public function user() { return $this->belongsTo(User::class); }
}
