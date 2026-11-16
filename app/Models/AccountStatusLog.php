<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One row every time a customer changes status (pending -> active -> inactive ...). */
class AccountStatusLog extends Model
{
    protected $fillable = ['account_id', 'from_status', 'to_status', 'reason'];

    public function account() { return $this->belongsTo(Account::class); }
}
