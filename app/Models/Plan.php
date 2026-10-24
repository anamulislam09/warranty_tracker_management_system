<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $fillable = [
        'name', 'description', 'monthly_price', 'annual_price', 'max_products', 'storage_limit_mb',
        'max_users', 'reminder_enabled', 'document_upload_enabled', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'reminder_enabled' => 'boolean',
            'document_upload_enabled' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function accounts() { return $this->hasMany(Account::class); }
}
