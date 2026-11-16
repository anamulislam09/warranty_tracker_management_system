<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\OnlinePaymentService;
use Illuminate\Http\Request;

class PaymentAPIController extends Controller
{
    /**
     * Route: POST create-payment   (name: Payment.create_payment)
     * "Pay now": the payment is created first, then it goes to SSLCommerz (only once), then the customer is redirected there.
     * The customer always pays the whole balance of the bill, the amount is not taken from the browser.
     */
    public function create_payment(Request $request, OnlinePaymentService $payments)
    {
        $data = $request->validate(['invoice_id' => 'required|integer']);

        // the bill must belong to this customer - never trust the id from the browser
        $invoice = Invoice::where('account_id', $request->user()->account_id)->open()->find($data['invoice_id']);
        if (! $invoice) {
            return back()->with('error', 'This bill was not found or is already paid.');
        }

        try {
            $url = $payments->createPayment($invoice, $request->user(), $request->ip());
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->away($url);
    }
}
