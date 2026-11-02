<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Invoice;
use App\Models\Plan;
use App\Services\BillingService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class InvoiceController extends Controller
{
    public function __construct(private BillingService $billing) {}

    private function formData(Invoice $invoice): array
    {
        $accounts = Account::with('plan')->orderBy('name')->get();

        return [
            'invoice'       => $invoice,
            'accounts'      => $accounts,
            'plans'         => Plan::orderBy('monthly_price')->get(),
            'months'        => BillingService::monthOptions(),
            'cycles'        => config('billing.cycles'),
            'planPrices'    => Plan::pluck('monthly_price', 'id'),
            'accountPlans'  => $accounts->pluck('plan_id', 'id'),
            'accountCycles' => $accounts->pluck('billing_cycle', 'id'),
            'dueDay'        => (int) config('billing.due_day'),
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
        return view('admin.invoices.form', $this->formData(new Invoice(['account_id' => $request->account_id])));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'account_id'    => 'required|exists:accounts,id',
            'billing_month' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'cycle'         => ['required', Rule::in(array_keys(config('billing.cycles')))],
            'plan_id'       => 'required|exists:plans,id',
            'amount'        => 'required|numeric|min:0.01|max:99999999',
            'due_date'      => 'required|date',
            'note'          => 'nullable|string|max:500',
        ]);

        $start   = BillingService::monthFromInput($data['billing_month']);
        $months  = (int) config("billing.cycles.{$data['cycle']}.months");
        $end     = $start->copy()->addMonthsNoOverflow($months)->subDay();
        $account = Account::findOrFail($data['account_id']);

        // a period must not overlap another bill of the same customer
        $overlap = Invoice::where('account_id', $account->id)
            ->where('billing_month', '<=', $end->toDateString())
            ->where('period_end', '>=', $start->toDateString())->exists();
        if ($overlap) {
            return back()->withInput()->with('error', 'This customer already has a bill that covers some of these months. Edit that bill or choose another period.');
        }

        $invoice = $this->billing->createInvoice(
            $account, $start, Plan::find($data['plan_id']), $data['cycle'], (float) $data['amount'],
            Carbon::parse($data['due_date']), $data['note'] ?? null, $request->user()->id
        );
        $this->billing->reallocate($account);          // an advance balance pays the new bill automatically
        $this->billing->announce($invoice);

        return redirect()->route('admin.invoices.show', $invoice)->with('success', 'Bill created.');
    }

    public function show(Invoice $invoice)
    {
        $invoice->load('account', 'plan', 'allocations.payment.receiver', 'creator');
        return view('admin.invoices.show', [
            'invoice' => $invoice,
            'advance' => $invoice->account->advanceBalance(),
        ]);
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

        $plan  = Plan::find($data['plan_id']);
        $quote = BillingService::quote($plan, $invoice->cycle);
        $disc  = max(0, round($quote['base'] - (float) $data['amount'], 2));

        $invoice->update([
            'plan_id' => $plan->id, 'plan_name' => $plan->name, 'amount' => $data['amount'],
            'base_amount' => $quote['base'], 'discount_amount' => $disc,
            'discount_percent' => $quote['base'] > 0 ? round($disc / $quote['base'] * 100, 2) : 0,
            'due_date' => $data['due_date'], 'note' => $data['note'] ?? null,
        ]);
        $this->billing->refresh($invoice);          // payments are re-applied, the status may change

        return redirect()->route('admin.invoices.show', $invoice)->with('success', 'Bill updated.');
    }

    public function cancel(Invoice $invoice)
    {
        $invoice->update(['status' => 'cancelled']);
        $this->billing->refresh($invoice);          // money paid for it moves to the next bills / advance

        return back()->with('success', 'Bill cancelled (waived).');
    }

    public function destroy(Invoice $invoice)
    {
        if ($invoice->allocations()->exists()) {
            return back()->with('error', 'Payments are applied to this bill. Delete those payments first, or cancel the bill instead.');
        }
        $account = $invoice->account;
        $invoice->delete();
        $this->billing->reallocate($account);

        return redirect()->route('admin.invoices.index')->with('success', 'Bill deleted.');
    }
}
