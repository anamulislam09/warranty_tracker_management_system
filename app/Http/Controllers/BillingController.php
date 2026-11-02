<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Payment;
use App\Services\BillingService;
use App\Services\Gateways\GatewayManager;
use Illuminate\Http\Request;

/** The customer owner sees his bills, payments, dues and advance, and can pay online (when the gateway is on). */
class BillingController extends Controller
{
    public function __construct(private BillingService $billing, private GatewayManager $gateways) {}

    public function index(Request $request)
    {
        $account = $request->user()->account->load('plan');

        $open    = Invoice::where('account_id', $account->id)->open()->orderBy('billing_month')->get();
        $overdue = $open->filter(fn ($i) => $i->is_overdue);

        $suspendOn = null;
        if ($overdue->isNotEmpty()) {
            $suspendOn = $this->billing->suspensionDateFor($overdue->sortBy('due_date')->first());
        }

        return view('billing.index', [
            'account'        => $account,
            'quote'          => $account->plan ? BillingService::quote($account->plan, $account->billing_cycle) : null,
            'nextStart'      => $this->billing->nextPeriodStart($account),
            'open'           => $open,
            'dueTotal'       => $open->sum(fn ($i) => $i->balance),
            'overdueTotal'   => $overdue->sum(fn ($i) => $i->balance),
            'suspendOn'      => $suspendOn,
            'advance'        => $account->advanceBalance(),
            'gatewayEnabled' => $this->gateways->enabled(),
            'invoices'       => Invoice::where('account_id', $account->id)->orderByDesc('billing_month')->paginate(10, ['*'], 'bills_page'),
            'payments'       => Payment::with('allocations.invoice')->where('account_id', $account->id)
                                    ->orderByDesc('paid_on')->orderByDesc('id')->paginate(10, ['*'], 'pay_page'),
        ]);
    }

    /** "Pay online" (a bill) or "Add advance" (no bill). */
    public function pay(Request $request)
    {
        $data = $request->validate([
            'invoice_id' => 'nullable|integer',
            'amount'     => 'required|numeric|min:1|max:99999999',
        ]);
        $account = $request->user()->account;

        // the bill must belong to this customer - never trust the id from the browser
        $invoice = null;
        if (! empty($data['invoice_id'])) {
            $invoice = Invoice::where('account_id', $account->id)->open()->find($data['invoice_id']);
            if (! $invoice) return back()->with('error', 'This bill was not found or is already paid.');
        }

        if (! $this->gateways->enabled()) {
            return back()->with('error', 'Online payment is not available yet. Please pay the administrator directly.');
        }

        try {
            return redirect()->away($this->gateways->driver()->initiate($account, $invoice, (float) $data['amount']));
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'The online payment could not be started. Please try again later.');
        }
    }
}
