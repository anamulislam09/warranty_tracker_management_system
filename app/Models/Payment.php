<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Money received for ONE bill. A bill can have many payments, but never more than its amount. */
class Payment extends Model
{
    protected $fillable = ['invoice_id', 'account_id', 'amount', 'paid_on', 'method', 'reference', 'note', 'received_by'];

    protected function casts(): array
    {
        return ['paid_on' => 'date', 'amount' => 'decimal:2'];
    }

    /** Paid through the online gateway. The gateway's money can not be edited or deleted by hand. */
    public function isOnline(): bool
    {
        return $this->method === 'sslcommerz';
    }

    public function invoice() { return $this->belongsTo(Invoice::class); }
    public function account() { return $this->belongsTo(Account::class); }
    public function receiver() { return $this->belongsTo(User::class, 'received_by'); }
}
