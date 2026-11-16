<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Services\BillingService;
use App\Services\InvoicePdf;
use App\Services\OnlinePaymentService;
use Illuminate\Http\Request;

/**
 * The customer owner sees his payment schedule, bills, payments and dues, downloads invoices and pays online.
 * ("Pay now" itself is the route create-payment: App\Http\Controllers\PaymentAPIController)
 */
class BillingController extends Controller
{
    public function __construct(
        private BillingService $billing,
        private OnlinePaymentService $online,
    ) {}

    public function index(Request $request)
    {
        $account = $request->user()->account->load(['plan', 'schedule']);

        // the customer is back from SSLCommerz (route ssl_process sends him here): show how the payment ended, then clean the URL
        if ($uid = $request->query('payment')) {
            $attempt = PaymentAttempt::where('unique_id', $uid)->where('account_id', $account->id)->first();
            $redirect = redirect()->route('billing.index');

            return $attempt
                ? $redirect->with($attempt->isSuccess() ? 'success' : 'error', $this->online->messageFor($attempt))
                : $redirect;
        }

        $open    = Invoice::where('account_id', $account->id)->open()->orderBy('billing_month')->get();
        $overdue = $open->filter(fn ($i) => $i->is_overdue);

        $suspendOn = $overdue->isNotEmpty()
            ? $this->billing->suspensionDateFor($overdue->sortBy('due_date')->first())
            : null;

        return view('billing.index', [
            'account'        => $account,
            'current'        => Invoice::where('account_id', $account->id)->where('status', '!=', 'cancelled')->orderByDesc('billing_month')->first(),
            'draft'          => $this->billing->nextInvoiceDraft($account),
            'open'           => $open,
            'dueTotal'       => $open->sum(fn ($i) => $i->balance),
            'paidTotal'      => (float) Payment::where('account_id', $account->id)->sum('amount'),
            'overdueTotal'   => $overdue->sum(fn ($i) => $i->balance),
            'suspendOn'      => $suspendOn,
            'gatewayEnabled' => $this->online->enabled(),
            'attempts'       => PaymentAttempt::with('invoice')->where('account_id', $account->id)->latest()->limit(5)->get(),
            'invoices'       => Invoice::with('payments')->where('account_id', $account->id)->orderByDesc('billing_month')
                                    ->paginate(5, ['*'], 'bills_page')->withQueryString(),
            'payments'       => Payment::with('invoice')->where('account_id', $account->id)
                                    ->orderByDesc('paid_on')->orderByDesc('id')->paginate(5, ['*'], 'pay_page')->withQueryString(),
        ]);
    }

    public function download(Request $request, Invoice $invoice, InvoicePdf $pdf)
    {
        // a customer can only download his own invoices
        abort_unless($invoice->account_id === $request->user()->account_id, 404);

        return $pdf->download($invoice);
    }

    /** "Check status": the customer asks SSLCommerz about his own waiting payment. */
    public function check(Request $request, PaymentAttempt $attempt)
    {
        abort_unless($attempt->account_id === $request->user()->account_id, 404);

        try {
            $attempt = $this->online->verify($attempt, 'auto', $request->user()->id);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with($attempt->isSuccess() ? 'success' : 'error', $this->online->messageFor($attempt));
    }
}
