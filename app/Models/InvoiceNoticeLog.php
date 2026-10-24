<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceNoticeLog extends Model
{
    protected $fillable = ['invoice_id', 'type'];
}
