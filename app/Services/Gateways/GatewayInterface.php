<?php

namespace App\Services\Gateways;

use App\Models\Account;
use App\Models\Invoice;
use Illuminate\Http\Request;

/**
 * Every online payment gateway (SSLCommerz, bKash, Stripe ...) implements this.
 * The rest of the system only talks to this interface.
 */
interface GatewayInterface
{
    /** key used in the URL, in config and in payments.method, e.g. "sslcommerz" */
    public function name(): string;

    /** true when the gateway is fully set up (keys present and the class is implemented) */
    public function isConfigured(): bool;

    /** Start a payment. Return the URL the customer must be redirected to. */
    public function initiate(Account $account, ?Invoice $invoice, float $amount): string;

    /**
     * Called when the gateway sends the customer back (success / fail / cancel) or calls our IPN URL.
     * MUST verify the payment with the gateway server (never trust the browser).
     *
     * @return array{success:bool, account_id?:int, invoice_id?:int|null, amount?:float, reference?:string, message?:string}
     */
    public function handleCallback(Request $request): array;
}
