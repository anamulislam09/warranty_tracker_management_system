<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\PaymentAttempt;
use App\Models\User;
use App\Utils\Sslcommerz\Sslcommerzgateway;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The online payment flow (SSLCommerz):
 *
 *   1. createPayment()  - route "create-payment": the PAYMENT (a payment attempt) is created first,
 *                         then it is sent to the gateway - ONCE - and the customer is redirected.
 *   2. processReturn()  - route "ssl-process/{unique_id}": the customer comes back (success, fail and cancel all use it).
 *                         The answer is stored and processed ONCE (request_step 1 -> 2), and never trusted:
 *                         SSLCommerz is asked (verify) before the payment is recorded on the bill.
 *   3. verify()         - the same check, used by processReturn(), by the scheduler (a payment that stays "pending")
 *                         and by the admin's "Verify with gateway" button - so a payment that was really paid at the
 *                         gateway but never reached our site is still found and recorded.
 *
 *   request_step : 0 = created, not sent to the gateway | 1 = sent, waiting for the customer | 2 = answer processed
 *   status       : initiated -> pending -> success | failed | cancelled | expired | needs_review
 */
class OnlinePaymentService
{
    public function __construct(private Sslcommerzgateway $gateway, private BillingService $billing) {}

    /** Is "Pay online" available for the customers right now? */
    public function enabled(): bool
    {
        return (bool) config('billing.online.enabled') && $this->gateway->isConfigured();
    }

    // ------------------------------------------------------------------ 1. create the payment, go to the gateway
    /**
     * @return string the gateway page the customer must be sent to
     * @throws \DomainException
     */
    public function createPayment(Invoice $invoice, ?User $user = null, ?string $ip = null, string $source = 'web'): string
    {
        return $this->startPayment($invoice, $user, $ip, $source)->gateway_page_url;
    }

    /**
     * Same as createPayment() but gives back the payment (attempt): the mobile app needs its unique_id to follow the payment.
     * $source = web | app  (decides where the customer is sent after the gateway)
     * @throws \DomainException
     */
    public function startPayment(Invoice $invoice, ?User $user = null, ?string $ip = null, string $source = 'web'): PaymentAttempt
    {
        if (! $this->enabled()) {
            throw new \DomainException('Online payment is not available yet. Please pay the administrator directly.');
        }

        // the customer pays the whole balance of the bill: the amount is not chosen by the customer
        $attempt = DB::transaction(function () use ($invoice, $user, $ip, $source) {
            $invoice = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();      // two clicks at the same time -> one payment

            if (! in_array($invoice->status, ['unpaid', 'partial'], true)) {
                throw new \DomainException('This bill is already paid.');
            }
            $amount = round($invoice->balance, 2);
            if ($amount < 1) {
                throw new \DomainException('There is nothing to pay on this bill.');
            }

            // the same payment that is still waiting at the gateway is used again: it is NOT sent a second time
            $open = PaymentAttempt::where('invoice_id', $invoice->id)->where('status', 'pending')->where('request_step', 1)
                ->where('source', $source)->where('amount', $amount)->where('created_at', '>=', now()->subMinutes(30))->latest()->first();
            if ($open && $open->gateway_page_url) {
                return $open;
            }

            return PaymentAttempt::create([
                'account_id' => $invoice->account_id, 'invoice_id' => $invoice->id, 'user_id' => optional($user)->id,
                'gateway' => 'sslcommerz', 'source' => $source, 'tran_id' => $this->newTranId($invoice), 'unique_id' => str_replace('-', '', (string) Str::uuid()),
                'amount' => $amount, 'currency' => 'BDT', 'status' => 'initiated', 'request_step' => 0, 'ip_address' => $ip,
            ]);
        });

        $this->sendToGateway($attempt);

        return $attempt->refresh();
    }

    private function newTranId(Invoice $invoice): string
    {
        return sprintf('WT-%d-%s-%s', $invoice->id, now()->format('ymdHis'), Str::upper(Str::random(4)));
    }

    /** A payment goes to the gateway only ONCE (request_step 0 -> 1). Asked again, it gives the same page back. */
    public function sendToGateway(PaymentAttempt $attempt): string
    {
        if ($attempt->request_step >= 1) {
            if ($attempt->gateway_page_url) return $attempt->gateway_page_url;
            throw new \DomainException('Your payment is being prepared. Please wait a moment and try again.');
        }

        // claim the "send": only one request can change 0 -> 1
        $claimed = PaymentAttempt::where('id', $attempt->id)->where('request_step', 0)->update(['request_step' => 1]);
        if (! $claimed) {
            throw new \DomainException('Your payment is being prepared. Please wait a moment and try again.');
        }

        $result = $this->gateway->generate($attempt->refresh());

        if (! $result['ok']) {
            $attempt->update([
                'status' => 'failed', 'failure_reason' => 'Could not start the payment: ' . ($result['error'] ?? 'unknown error'),
                'request_payload' => ['sent' => $result['request'] ?? null, 'gateway_response' => $result['response'] ?? null],
            ]);
            throw new \DomainException('The online payment could not be started. Please try again later.');
        }

        $attempt->update([
            'status' => 'pending',
            'gateway_session_key' => $result['session_key'],
            'gateway_page_url' => $result['url'],
            'request_payload' => ['sent' => $result['request'], 'gateway_response' => $result['response']],
        ]);

        return $result['url'];
    }

    // ------------------------------------------------------------------ 2. the customer comes back
    /**
     * @param array $response everything that came with the request (status, tran_id, val_id ...)
     * @return array{attempt: ?PaymentAttempt, success: bool, message: string}
     */
    public function processReturn(?string $uniqueId, array $response): array
    {
        $attempt = $uniqueId ? PaymentAttempt::where('unique_id', $uniqueId)->first() : null;
        if (! $attempt) {
            return ['attempt' => null, 'success' => false, 'message' => 'Transaction not found or already processed.'];
        }

        // store what came back (every time)
        $log   = $attempt->callback_payload ?? [];
        $log[] = ['at' => now()->toDateTimeString(), 'data' => $response];
        $attempt->update([
            'callback_payload' => array_slice($log, -10), 'callback_count' => $attempt->callback_count + 1,
            'last_callback_at' => now(), 'gateway_status' => $response['status'] ?? $attempt->gateway_status,
        ]);

        // the answer is processed only ONCE: only one request can change step 1 -> 2
        $claimed = PaymentAttempt::where('id', $attempt->id)->where('request_step', 1)->update(['request_step' => 2]);
        $attempt->refresh();

        if (! $claimed) {                                           // already processed, or never sent: just show where it stands
            return ['attempt' => $attempt, 'success' => $attempt->isSuccess(), 'message' => $this->messageFor($attempt)];
        }

        $status = strtoupper((string) ($response['status'] ?? ''));

        if ($status === 'VALID') {
            try {
                $attempt = $this->verify($attempt, 'auto');         // never trust the browser: ask SSLCommerz
            } catch (\DomainException $e) {
                // the check will be repeated by the scheduler
            }
        } elseif ($status === 'CANCELLED') {
            $attempt->update(['status' => 'cancelled', 'failure_reason' => 'The customer cancelled the payment.']);
        } else {
            $attempt->update(['status' => 'failed', 'failure_reason' => 'SSLCommerz returned: ' . ($status ?: 'no status') . '.']);
        }

        $attempt->refresh();

        return ['attempt' => $attempt, 'success' => $attempt->isSuccess(), 'message' => $this->messageFor($attempt)];
    }

    public function messageFor(PaymentAttempt $attempt): string
    {
        return match ($attempt->status) {
            'success'      => 'Payment received. Thank you! The updated invoice was sent to your email.',
            'cancelled'    => 'The payment was cancelled.',
            'failed'       => 'The payment failed. No money was taken.',
            'needs_review' => 'The gateway confirmed your payment but we need to check it. Our team will update your bill soon.',
            'expired'      => 'This payment was never finished.',
            default        => 'We are waiting for the gateway to confirm your payment. Press "Check status" in a moment.',
        };
    }

    // ------------------------------------------------------------------ 3. verify (return, scheduler, manual button)
    /**
     * Asks SSLCommerz about the payment. If SSLCommerz says SUCCESS (and amount + transaction id match),
     * the payment is recorded on the bill. $by = auto | manual
     *
     * @throws \DomainException when SSLCommerz cannot be asked
     */
    public function verify(PaymentAttempt $attempt, string $by = 'auto', ?int $userId = null): PaymentAttempt
    {
        if ($attempt->isSuccess()) return $attempt;

        $result = $this->gateway->sslPaymentValidation($attempt);

        // keep the answer of every check
        $checks   = $attempt->validation_payload ?? [];
        $checks[] = ['at' => now()->toDateTimeString(), 'by' => $by, 'state' => $result['state'], 'message' => $result['message'], 'raw' => $result['payment_info']];
        $attempt->update([
            'validation_payload' => array_slice($checks, -10),
            'gateway_status'     => $result['payment_info']['status'] ?? $attempt->gateway_status,
        ]);

        if ($result['state'] === 'unknown' && empty($result['payment_info'])) {
            // SSLCommerz could not be asked at all
            throw new \DomainException($result['message'] ?: 'SSLCommerz could not be reached.');
        }

        switch ($result['state']) {
            case 'success':
                return $this->markSuccess($attempt, $result['payment_info'], $by, $userId);
            case 'mismatch':
                $attempt->update(['status' => 'needs_review', 'failure_reason' => $result['message'] . ' Please check it by hand.']);
                break;
            case 'failed':
                $attempt->update(['status' => 'failed', 'failure_reason' => $result['message']]);
                break;
            case 'cancelled':
                $attempt->update(['status' => 'cancelled', 'failure_reason' => $result['message']]);
                break;
            default:                                                       // pending / unknown: stays as it is
                $attempt->update(['failure_reason' => $result['message']]);
        }

        return $attempt->refresh();
    }

    /** SSLCommerz says SUCCESS: record the payment on the bill (once, even if two requests come together). */
    private function markSuccess(PaymentAttempt $attempt, array $info, string $by, ?int $userId): PaymentAttempt
    {
        DB::transaction(function () use ($attempt, $info, $by, $userId) {
            $locked = PaymentAttempt::whereKey($attempt->id)->lockForUpdate()->first();
            if ($locked->isSuccess()) return;

            $invoice = Invoice::with('account')->findOrFail($locked->invoice_id);
            $who     = $by === 'manual' ? ', verified manually by ' . optional(User::find($userId))->name : '';

            try {
                $payment = $this->billing->recordPayment($invoice, [
                    'amount' => $locked->amount, 'paid_on' => today(), 'method' => 'sslcommerz',
                    'reference' => $info['bank_tran_id'] ?? $info['val_id'] ?? $locked->tran_id,
                    'note' => 'Paid online (' . $locked->tran_id . ')' . $who,
                ], null);
            } catch (\DomainException $e) {
                // the money is at SSLCommerz, but the bill does not need it any more (for example paid by the admin meanwhile)
                $locked->update(['status' => 'needs_review',
                    'failure_reason' => $e->getMessage() . ' SSLCommerz reported this payment as successful: please check it.']);
                return;
            }

            $locked->update([
                'status' => 'success', 'request_step' => 2, 'payment_id' => $payment->id,
                'gateway_reference' => $info['val_id'] ?? $info['bank_tran_id'] ?? $locked->gateway_reference,
                'card_type' => $info['card_type'] ?? null, 'card_no' => $info['card_no'] ?? null,
                'verified_at' => now(), 'verified_by' => $by, 'verified_user_id' => $userId, 'failure_reason' => null,
            ]);
        });

        return $attempt->refresh();
    }

    // ------------------------------------------------------------------ scheduler
    /**
     * Run every few minutes:
     *  - "pending" payments that stay unconfirmed are asked at SSLCommerz (the customer may have paid, but did not come back)
     *  - tries that never finished are closed
     * @return array{verified: int, success: int, expired: int}
     */
    public function reconcile(): array
    {
        $verified = $success = 0;

        PaymentAttempt::where('status', 'pending')
            ->where('created_at', '<=', now()->subMinutes((int) config('billing.online.verify_after_minutes')))
            ->where('created_at', '>=', now()->subDays(3))
            ->each(function (PaymentAttempt $attempt) use (&$verified, &$success) {
                try {
                    $verified++;
                    if ($this->verify($attempt, 'auto')->isSuccess()) $success++;
                } catch (\Throwable $e) {
                    report($e);
                }
            });

        $expired = PaymentAttempt::whereIn('status', ['initiated', 'pending'])
            ->where('created_at', '<=', now()->subHours((int) config('billing.online.expire_after_hours')))
            ->update(['status' => 'expired']);

        return ['verified' => $verified, 'success' => $success, 'expired' => $expired];
    }
}
