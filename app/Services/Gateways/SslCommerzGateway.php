<?php

namespace App\Services\Gateways;

use App\Models\Account;
use App\Models\Invoice;
use Illuminate\Http\Request;

/**
 * SSLCommerz - NOT IMPLEMENTED YET (placeholder).
 *
 * To finish it later:
 *  1. initiate():       call the SSLCommerz "session" API with store_id / store_password, the amount,
 *                       a unique tran_id, and success / fail / cancel / ipn URLs =
 *                       route('billing.gateway.callback', ['gateway' => 'sslcommerz']);
 *                       remember tran_id -> account_id / invoice_id (e.g. in the cache); return the GatewayPageURL.
 *  2. handleCallback(): call the SSLCommerz "validation" API with val_id, check status + amount,
 *                       then return ['success' => true, 'account_id' => .., 'invoice_id' => .., 'amount' => .., 'reference' => tran_id].
 *  3. isConfigured():   return true when the keys are present.
 *  4. .env:             SSLCOMMERZ_STORE_ID, SSLCOMMERZ_STORE_PASSWORD, SSLCOMMERZ_SANDBOX, BILLING_ONLINE_ENABLED=true
 *
 * Nothing else has to change: GatewayCallbackController records the payment with BillingService::recordPayment().
 */
class SslCommerzGateway implements GatewayInterface
{
    public function name(): string
    {
        return 'sslcommerz';
    }

    public function isConfigured(): bool
    {
        return false;   // switch to a real check when the methods below are implemented
    }

    public function initiate(Account $account, ?Invoice $invoice, float $amount): string
    {
        throw new \RuntimeException('SSLCommerz is not implemented yet.');
    }

    public function handleCallback(Request $request): array
    {
        return ['success' => false, 'message' => 'SSLCommerz is not implemented yet.'];
    }
}
