<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = ['invoice_id', 'account_id', 'amount', 'paid_on', 'method', 'reference', 'note', 'received_by'];

    protected function casts(): array
    {
        return ['paid_on' => 'date', 'amount' => 'decimal:2'];
    }

    public function invoice() { return $this->belongsTo(Invoice::class); }
    public function account() { return $this->belongsTo(Account::class); }
    public function receiver() { return $this->belongsTo(User::class, 'received_by'); }
}
