<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\InvoiceResource;
use App\Http\Resources\OnlinePaymentResource;
use App\Http\Resources\PaymentResource;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Services\BillingService;
use App\Services\InvoicePdf;
use App\Services\OnlinePaymentService;
use Illuminate\Http\Request;

/**
 * Billing for the customer OWNER: payment schedule, bills, payments, invoice PDF and online payment (SSLCommerz).
 * These routes still work when the account is restricted because of an overdue bill (that is what they are for).
 */
class BillingController extends ApiController
{
    public function __construct(private BillingService $billing, private OnlinePaymentService $online) {}

    private function invoiceOf(Request $request, int $id): ?Invoice
    {
        return Invoice::where('account_id', $request->user()->account_id)->find($id);
    }

    public function summary(Request $request)
    {
        $account = $request->user()->account->load('plan', 'schedule');

        $open    = Invoice::where('account_id', $account->id)->open()->orderBy('billing_month')->get();
        $overdue = $open->filter(fn ($i) => $i->is_overdue);
        $current = Invoice::with('payments')->where('account_id', $account->id)->where('status', '!=', 'cancelled')->orderByDesc('billing_month')->first();
        $draft   = $this->billing->nextInvoiceDraft($account);

        return $this->ok([
            'payment_schedule' => $account->schedule ? [
                'name' => $account->schedule->name, 'months' => (int) $account->schedule->months,
                'discount_percent' => (float) $account->schedule->discount_percent,
            ] : null,
            'plan' => $account->plan ? ['name' => $account->plan->name, 'monthly_price' => (float) $account->plan->monthly_price] : null,
            'current_invoice' => $current ? $this->one(InvoiceResource::class, $current) : null,
            'totals' => [
                'paid_total'    => round((float) Payment::where('account_id', $account->id)->sum('amount'), 2),
                'due_total'     => round($open->sum(fn ($i) => $i->balance), 2),
                'overdue_total' => round($overdue->sum(fn ($i) => $i->balance), 2),
                'open_invoices' => $open->count(),
            ],
            'restricted' => $account->isPaymentOverdue(),
            'suspend_on' => $overdue->isNotEmpty() ? $this->billing->suspensionDateFor($overdue->sortBy('due_date')->first())->toDateString() : null,
            'next_bill' => isset($draft['quote']) ? [
                'period_label' => $draft['period_label'],
                'amount'       => (float) $draft['quote']['amount'],
                'creates_on'   => $draft['opens_on']->toDateString(),      // the bill is created on this day (15 days before the period)
                'free_until'   => optional($draft['free_until'])->toDateString(),   // a new customer uses the system free until this day
            ] : null,
            'online_payment_enabled' => $this->online->enabled(),
        ]);
    }

    public function invoices(Request $request)
    {
        $page = Invoice::with('payments')->where('account_id', $request->user()->account_id)
            ->when($request->status === 'open', fn ($q) => $q->open())
            ->when($request->status === 'overdue', fn ($q) => $q->overdue())
            ->when($request->status === 'paid', fn ($q) => $q->where('status', 'paid'))
            ->orderByDesc('billing_month')->paginate($this->perPage($request, 20, 50));

        return $this->paginated($page, InvoiceResource::class);
    }

    public function invoice(Request $request, int $id)
    {
        $invoice = $this->invoiceOf($request, $id);

        return $invoice
            ? $this->ok($this->one(InvoiceResource::class, $invoice->load('payments')))
            : $this->fail('Not found.', 'not_found');
    }

    /** The invoice as a PDF (watermark DUE or PAID). The app must send the Bearer token. */
    public function download(Request $request, int $id, InvoicePdf $pdf)
    {
        $invoice = $this->invoiceOf($request, $id);

        return $invoice ? $pdf->download($invoice) : $this->fail('Not found.', 'not_found');
    }

    public function payments(Request $request)
    {
        $page = Payment::with('invoice')->where('account_id', $request->user()->account_id)
            ->orderByDesc('paid_on')->orderByDesc('id')->paginate($this->perPage($request, 20, 50));

        return $this->paginated($page, PaymentResource::class);
    }

    /**
     * "Pay now": the payment is created first, then it goes to SSLCommerz (once).
     * The app opens payment_url in a WebView / Custom Tab. The customer always pays the whole balance of the bill.
     */
    public function pay(Request $request, int $id)
    {
        $invoice = $this->invoiceOf($request, $id);
        if (! $invoice || ! in_array($invoice->status, ['unpaid', 'partial'], true)) {
            return $this->fail('This bill was not found or is already paid.', 'not_payable');
        }

        try {
            $attempt = $this->online->startPayment($invoice, $request->user(), $request->ip(), 'app');
        } catch (\DomainException $e) {
            return $this->fail($e->getMessage(), 'payment_unavailable');
        }

        return $this->created([
            'payment_url' => $attempt->gateway_page_url,
            'payment'     => $this->one(OnlinePaymentResource::class, $attempt),
        ], 'Payment created. Open payment_url to pay.');
    }

    public function onlinePayments(Request $request)
    {
        $page = PaymentAttempt::where('account_id', $request->user()->account_id)->latest()->paginate($this->perPage($request, 20, 50));

        return $this->paginated($page, OnlinePaymentResource::class);
    }

    private function attemptOf(Request $request, string $uniqueId): ?PaymentAttempt
    {
        return PaymentAttempt::where('account_id', $request->user()->account_id)->where('unique_id', $uniqueId)->first();
    }

    /** The app asks this after the payment page closes (and while the status is "pending"). */
    public function onlinePayment(Request $request, string $uniqueId)
    {
        $attempt = $this->attemptOf($request, $uniqueId);

        return $attempt ? $this->ok($this->one(OnlinePaymentResource::class, $attempt)) : $this->fail('Not found.', 'not_found');
    }

    /** "Check status": the server asks SSLCommerz now. If it was paid, the bill is updated. */
    public function check(Request $request, string $uniqueId)
    {
        $attempt = $this->attemptOf($request, $uniqueId);
        if (! $attempt) {
            return $this->fail('Not found.', 'not_found');
        }

        try {
            $attempt = $this->online->verify($attempt, 'auto', $request->user()->id);
        } catch (\DomainException $e) {
            return $this->fail($e->getMessage(), 'gateway_unreachable');
        }

        return $this->ok($this->one(OnlinePaymentResource::class, $attempt), $this->online->messageFor($attempt));
    }
}
