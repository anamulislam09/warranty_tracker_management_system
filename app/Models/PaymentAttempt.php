<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One online payment try. It is created BEFORE the customer goes to the gateway, and keeps
 * everything that happens afterwards: the gateway's answers and our own verification.
 */
class PaymentAttempt extends Model
{
    protected $fillable = [
        'account_id', 'invoice_id', 'user_id', 'gateway', 'source', 'tran_id', 'unique_id', 'amount', 'currency', 'status', 'request_step',
        'gateway_session_key', 'gateway_page_url', 'gateway_reference', 'card_type', 'card_no', 'gateway_status',
        'request_payload', 'callback_payload', 'validation_payload',
        'verified_at', 'verified_by', 'verified_user_id', 'payment_id', 'failure_reason',
        'ip_address', 'callback_count', 'last_callback_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2', 'verified_at' => 'datetime', 'last_callback_at' => 'datetime',
            'request_payload' => 'array', 'callback_payload' => 'array', 'validation_payload' => 'array',
        ];
    }

    public function account() { return $this->belongsTo(Account::class); }
    public function invoice() { return $this->belongsTo(Invoice::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function payment() { return $this->belongsTo(Payment::class); }
    public function verifier() { return $this->belongsTo(User::class, 'verified_user_id'); }

    /** A try that is not finished: the gateway may still have taken the money. */
    public function isOpen(): bool
    {
        return in_array($this->status, ['initiated', 'pending', 'failed', 'cancelled', 'expired', 'needs_review'], true);
    }

    public function isSuccess(): bool
    {
        return $this->status === 'success';
    }

    /** Can the admin ask the gateway about it? (everything that is not already success) */
    public function canVerify(): bool
    {
        return ! $this->isSuccess();
    }
}
