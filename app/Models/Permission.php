<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    protected $fillable = ['slug', 'label', 'group', 'is_active', 'is_system'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_system' => 'boolean'];
    }

    public function roles() { return $this->belongsToMany(Role::class); }
}
