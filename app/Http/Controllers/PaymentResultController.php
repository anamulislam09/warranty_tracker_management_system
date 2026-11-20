<?php

namespace App\Http\Controllers;

use App\Models\PaymentAttempt;
use App\Services\OnlinePaymentService;

/**
 * Where the customer lands after paying with the mobile app (the app opened the gateway in a WebView / browser tab).
 * It shows how the payment ended and opens the app with a deep link, so the app can close the browser and ask the API.
 * No login and no session here: the customer comes from another site.
 */
class PaymentResultController extends Controller
{
    public function show(OnlinePaymentService $payments, string $unique_id)
    {
        $attempt = PaymentAttempt::with('invoice')->where('unique_id', $unique_id)->firstOrFail();

        $deepLink = config('billing.online.app_deep_link') . '?' . http_build_query(['status' => $attempt->status, 'unique_id' => $unique_id]);

        return view('billing.payment-result', [
            'attempt' => $attempt, 'message' => $payments->messageFor($attempt), 'deepLink' => $deepLink,
        ]);
    }
}
