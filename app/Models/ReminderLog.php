<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReminderLog extends Model
{
    protected $fillable = ['warranty_id', 'end_date', 'reminder_days', 'channel'];

    protected function casts(): array
    {
        return ['end_date' => 'date'];
    }
}
