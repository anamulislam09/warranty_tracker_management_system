<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Invoice;
use App\Models\Plan;
use App\Services\BillingService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class InvoiceController extends Controller
{
    public function __construct(private BillingService $billing) {}

    private function formData(Invoice $invoice): array
    {
        $accounts = Account::with('plan')->orderBy('name')->get();

        return [
            'invoice'      => $invoice,
            'accounts'     => $accounts,
            'plans'        => Plan::orderBy('monthly_price')->get(),
            'months'       => BillingService::monthOptions(),
            'planPrices'   => Plan::pluck('monthly_price', 'id'),
            'accountPlans' => $accounts->pluck('plan_id', 'id'),
            'dueDay'       => (int) config('billing.due_day'),
        ];
    }

    public function index(Request $request)
    {
        $month = BillingService::monthFromInput($request->month);

        $query = Invoice::with('account')
            ->when($request->account_id, fn ($q, $id) => $q->where('account_id', $id))
            ->when($month, fn ($q) => $q->whereDate('billing_month', $month->toDateString()))
            ->when($request->status, fn ($q, $s) => $s === 'overdue' ? $q->overdue() : $q->where('status', $s))
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
        $invoice = new Invoice(['account_id' => $request->account_id]);
        return view('admin.invoices.form', $this->formData($invoice));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'account_id'    => 'required|exists:accounts,id',
            'billing_month' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'plan_id'       => 'required|exists:plans,id',
            'amount'        => 'required|numeric|min:0.01|max:99999999',
            'due_date'      => 'required|date',
            'note'          => 'nullable|string|max:500',
        ]);

        $month   = BillingService::monthFromInput($data['billing_month']);
        $account = Account::findOrFail($data['account_id']);

        if (Invoice::where('account_id', $account->id)->whereDate('billing_month', $month->toDateString())->exists()) {
            return back()->withInput()->with('error', "A bill for {$month->format('F Y')} already exists for this customer. Edit that bill instead.");
        }

        $invoice = $this->billing->createInvoice(
            $account, $month, Plan::find($data['plan_id']), (float) $data['amount'],
            Carbon::parse($data['due_date']), $data['note'] ?? null, $request->user()->id
        );
        $this->billing->announce($invoice);

        return redirect()->route('admin.invoices.show', $invoice)->with('success', 'Bill created.');
    }

    public function show(Invoice $invoice)
    {
        $invoice->load('account', 'plan', 'payments.receiver', 'creator');
        return view('admin.invoices.show', compact('invoice'));
    }

    public function edit(Invoice $invoice)
    {
        return view('admin.invoices.form', $this->formData($invoice->load('account')));
    }

    public function update(Request $request, Invoice $invoice)
    {
        $data = $request->validate([
            'plan_id'  => 'required|exists:plans,id',
            'amount'   => 'required|numeric|min:0.01|max:99999999',
            'due_date' => 'required|date',
            'note'     => 'nullable|string|max:500',
        ]);

        if ((float) $data['amount'] < (float) $invoice->paid_amount) {
            return back()->withInput()->with('error', 'Amount cannot be less than the already paid amount (' . Invoice::money($invoice->paid_amount) . ').');
        }

        $plan = Plan::find($data['plan_id']);
        $invoice->update([
            'plan_id' => $plan->id, 'plan_name' => $plan->name, 'amount' => $data['amount'],
            'due_date' => $data['due_date'], 'note' => $data['note'] ?? null,
        ]);
        $this->billing->refresh($invoice);          // status may change (e.g. paid -> partial)

        return redirect()->route('admin.invoices.show', $invoice)->with('success', 'Bill updated.');
    }

    public function cancel(Invoice $invoice)
    {
        $invoice->update(['status' => 'cancelled']);
        $this->billing->refresh($invoice);

        return back()->with('success', 'Bill cancelled (waived).');
    }

    public function destroy(Invoice $invoice)
    {
        if ($invoice->payments()->exists()) {
            return back()->with('error', 'This bill has payments. Delete the payments first, or cancel the bill instead.');
        }
        $account = $invoice->account;
        $invoice->delete();
        $this->billing->reactivateIfCleared($account);

        return redirect()->route('admin.invoices.index')->with('success', 'Bill deleted.');
    }
}
