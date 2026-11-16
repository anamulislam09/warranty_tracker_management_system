<?php

namespace App\Http\Controllers;

use App\Models\PaymentAttempt;
use App\Services\OnlinePaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SslcommerzPaymentController extends Controller
{
    /**
     * Route: ANY ssl-process/{unique_id}   (name: ssl_process)
     * SSLCommerz sends the customer back here after success, fail and cancel.
     *
     * This route runs WITHOUT the session / cookies (see routes/web.php): the customer comes from another site, and a new
     * session cookie would log him out. The result is shown on the Billing page, which finds the payment by its unique id.
     */
    public function ssl_process(Request $request, OnlinePaymentService $payments, $unique_id = null)
    {
        // where the customer goes afterwards: the website -> Billing page, the mobile app -> a small result page that opens the app
        $after = fn () => PaymentAttempt::where('unique_id', $unique_id)->value('source') === 'app'
            ? redirect()->route('payment.result', $unique_id)
            : redirect()->route('billing.index', ['payment' => $unique_id]);

        try {
            $result = $payments->processReturn($unique_id, $request->all());
        } catch (\Throwable $e) {
            Log::error('SSLCommerz process failed', ['unique_id' => $unique_id, 'error' => $e->getMessage()]);
            return $after();
        }

        if (! $result['attempt']) {
            return redirect('/');                                   // transaction not found
        }

        return $after();
    }
}
