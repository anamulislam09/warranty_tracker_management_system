<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Invoice;
use App\Services\BillingService;
use App\Services\Gateways\GatewayManager;
use Illuminate\Http\Request;

/**
 * The online gateway sends the customer (and its own server) back here.
 * Works for every gateway: the gateway class verifies the payment, we only record it.
 */
class GatewayCallbackController extends Controller
{
    public function handle(Request $request, string $gateway, GatewayManager $gateways, BillingService $billing)
    {
        $driver = $gateways->driver($gateway);
        abort_unless($driver && $driver->isConfigured(), 404);

        $result = $driver->handleCallback($request);

        if (! empty($result['success'])) {
            $account = Account::findOrFail($result['account_id']);
            $invoice = ! empty($result['invoice_id'])
                ? Invoice::where('account_id', $account->id)->find($result['invoice_id'])
                : null;

            $billing->recordPayment($account, [
                'amount'    => $result['amount'],
                'paid_on'   => today(),
                'method'    => $gateway,
                'reference' => $result['reference'] ?? null,
                'note'      => 'Paid online',
            ], null, $invoice);

            return redirect()->route('billing.index')->with('success', 'Payment received. Thank you!');
        }

        return redirect()->route('billing.index')->with('error', $result['message'] ?? 'The payment failed or was cancelled.');
    }
}
