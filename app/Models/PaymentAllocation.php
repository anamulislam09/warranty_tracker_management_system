<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** "৳500 of this payment was used for that bill". Rebuilt by BillingService::reallocate(). */
class PaymentAllocation extends Model
{
    protected $fillable = ['payment_id', 'invoice_id', 'account_id', 'amount'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function payment() { return $this->belongsTo(Payment::class); }
    public function invoice() { return $this->belongsTo(Invoice::class); }
}
