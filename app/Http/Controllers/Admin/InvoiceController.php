<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Invoice;
use App\Models\PaymentSchedule;
use App\Models\Plan;
use App\Services\BillingService;
use App\Services\InvoicePdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class InvoiceController extends Controller
{
    public function __construct(private BillingService $billing) {}

    /** What the form shows for every customer: his schedule, the next period, the amount, and whether it may be created now. */
    private function drafts()
    {
        return Account::with(['plan', 'schedule'])->orderBy('name')->get()->mapWithKeys(function ($account) {
            $d = $this->billing->nextInvoiceDraft($account, true);        // by hand: the first bill of a new customer is allowed at any time

            if (! isset($d['quote'])) {
                return [$account->id => ['can' => false, 'message' => $d['message']]];
            }
            return [$account->id => [
                'can' => $d['can'], 'message' => $d['message'], 'first' => $d['first'],
                'free_until' => $d['free_until'] ? $d['free_until']->format(BillingService::dateFormat()) : null,
                'schedule' => $d['schedule']->name . ' (every ' . $d['quote']['months'] . ' ' . ($d['quote']['months'] == 1 ? 'month' : 'months') . ')',
                'discount' => (float) $d['quote']['discount_percent'],
                'plan' => $d['plan']->name, 'period' => $d['period_label'],
                'amount' => $d['quote']['amount'], 'due' => $d['due']->toDateString(),
            ]];
        });
    }

    public function index(Request $request)
    {
        $month = BillingService::monthFromInput($request->month);

        $query = Invoice::with('account')
            ->when($request->account_id, fn ($q, $id) => $q->where('account_id', $id))
            ->when($month, fn ($q) => $q->whereDate('billing_month', $month->toDateString()))
            ->when($request->status, fn ($q, $s) => $s === 'overdue' ? $q->overdue() : $q->where('status', $s))
            ->when($request->from, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))      // bills issued from (dashboard link)
            ->when($request->to, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))        // bills issued until
            ->when($request->q, fn ($q, $t) => $q->where(fn ($q) => $q
                ->where('invoice_no', 'like', "%{$t}%")
                ->orWhereHas('account', fn ($a) => $a->where('name', 'like', "%{$t}%")->orWhere('email', 'like', "%{$t}%"))));

        $totals = (clone $query)->where('status', '!=', 'cancelled')
            ->selectRaw('COALESCE(SUM(amount),0) as billed, COALESCE(SUM(paid_amount),0) as paid')->first();

        $invoices = $query->orderByDesc('billing_month')->orderByDesc('id')->paginate(15)->withQueryString();

        return view('admin.invoices.index', [
            'invoices' => $invoices,
            'totals'   => $totals,
            'accounts' => Account::orderBy('name')->get(['id', 'name']),
            'months'   => BillingService::monthOptions(36, 2),
        ]);
    }

    public function create(Request $request)
    {
        return view('admin.invoices.form', [
            'invoice'  => new Invoice(['account_id' => $request->account_id]),
            'accounts' => Account::orderBy('name')->get(['id', 'name']),
            'drafts'   => $this->drafts(),
            'plans'    => Plan::orderBy('monthly_price')->get(),
            'planPrices' => Plan::pluck('monthly_price', 'id'),
        ]);
    }

    /** The period, plan and schedule are NOT chosen here: they always come from the customer's own payment schedule. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'account_id' => 'required|exists:accounts,id',
            'amount'     => 'nullable|numeric|min:0.01|max:99999999',
            'due_date'   => 'nullable|date',
            'note'       => 'nullable|string|max:500',
        ]);

        $account = Account::with(['plan', 'schedule'])->findOrFail($data['account_id']);
        $draft   = $this->billing->nextInvoiceDraft($account, true);

        if (! $draft['can']) {
            return back()->withInput()->with('error', $draft['message']);
        }

        $invoice = $this->billing->createInvoice(
            $account, $draft, isset($data['amount']) ? (float) $data['amount'] : null,
            ! empty($data['due_date']) ? Carbon::parse($data['due_date']) : null, $data['note'] ?? null, $request->user()->id
        );
        $this->billing->announce($invoice);

        return redirect()->route('admin.invoices.show', $invoice)->with('success', 'Bill created and sent to the customer.');
    }

    public function show(Invoice $invoice)
    {
        $invoice->load('account', 'plan', 'schedule', 'payments.receiver', 'creator');
        return view('admin.invoices.show', compact('invoice'));
    }

    public function download(Invoice $invoice, InvoicePdf $pdf)
    {
        return $pdf->download($invoice);
    }

    public function edit(Invoice $invoice)
    {
        if ($invoice->payments()->exists()) {
            return redirect()->route('admin.invoices.show', $invoice)->with('error', 'A payment was already made for this bill, so it cannot be edited any more.');
        }

        return view('admin.invoices.form', [
            'invoice' => $invoice->load('account'),
            'accounts' => collect(), 'drafts' => collect(),
            'plans' => Plan::orderBy('monthly_price')->get(),
            'planPrices' => Plan::pluck('monthly_price', 'id'),
        ]);
    }

    public function update(Request $request, Invoice $invoice)
    {
        if ($invoice->payments()->exists()) {
            return redirect()->route('admin.invoices.show', $invoice)->with('error', 'A payment was already made for this bill, so it cannot be edited any more.');
        }

        $data = $request->validate([
            'plan_id'  => 'required|exists:plans,id',
            'amount'   => 'required|numeric|min:0.01|max:99999999',
            'due_date' => 'required|date',
            'note'     => 'nullable|string|max:500',
        ]);

        $plan     = Plan::find($data['plan_id']);
        $schedule = $invoice->schedule ?: new PaymentSchedule(['months' => $invoice->months, 'discount_percent' => $invoice->discount_percent]);
        $quote    = BillingService::quote($plan, $schedule);
        $disc     = max(0, round($quote['base'] - (float) $data['amount'], 2));

        $invoice->update([
            'plan_id' => $plan->id, 'plan_name' => $plan->name, 'amount' => $data['amount'],
            'base_amount' => $quote['base'], 'discount_amount' => $disc,
            'discount_percent' => $quote['base'] > 0 ? round($disc / $quote['base'] * 100, 2) : 0,
            'due_date' => $data['due_date'], 'note' => $data['note'] ?? null,
        ]);
        $this->billing->refresh($invoice);          // the status may change (for example paid -> partial)

        return redirect()->route('admin.invoices.show', $invoice)->with('success', 'Bill updated.');
    }


    public function destroy(Invoice $invoice)
    {
        if ($invoice->payments()->exists()) {
            return back()->with('error', 'This bill has payments, so it cannot be deleted. Delete its payments first.');
        }
        $account = $invoice->account;
        $invoice->delete();
        $this->billing->reactivateIfCleared($account);

        return redirect()->route('admin.invoices.index')->with('success', 'Bill deleted.');
    }
}
