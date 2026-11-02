<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Services\BillingService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function __construct(private BillingService $billing) {}

    private function rules(): array
    {
        return [
            'invoice_id' => 'nullable|exists:invoices,id',
            'amount'     => 'required|numeric|min:0.01|max:99999999',
            'paid_on'    => 'required|date|before_or_equal:today',
            'method'     => ['required', Rule::in(array_keys(config('billing.methods')))],
            'reference'  => 'nullable|string|max:100',
            'note'       => 'nullable|string|max:500',
        ];
    }

    /** The bill the payer wants to pay first - it must belong to the same customer. */
    private function target(Account $account, $invoiceId): ?Invoice
    {
        if (! $invoiceId) return null;
        return Invoice::where('account_id', $account->id)->where('status', '!=', 'cancelled')->find($invoiceId);
    }

    public function index(Request $request)
    {
        $month = BillingService::monthFromInput($request->month);

        $query = Payment::with(['account', 'allocations.invoice', 'receiver'])
            ->when($request->account_id, fn ($q, $id) => $q->where('account_id', $id))
            ->when($request->input('method'), fn ($q, $m) => $q->where('method', $m))
            ->when($month, fn ($q) => $q->whereHas('allocations.invoice', fn ($i) => $i->whereDate('billing_month', $month->toDateString())))
            ->when($request->from, fn ($q, $d) => $q->whereDate('paid_on', '>=', $d))
            ->when($request->to, fn ($q, $d) => $q->whereDate('paid_on', '<=', $d));

        $total    = (clone $query)->sum('amount');
        $payments = $query->orderByDesc('paid_on')->orderByDesc('id')->paginate(15)->withQueryString();

        return view('admin.payments.index', [
            'payments' => $payments,
            'total'    => $total,
            'accounts' => Account::orderBy('name')->get(['id', 'name']),
            'months'   => BillingService::monthOptions(36, 2),
        ]);
    }

    public function create(Request $request)
    {
        $accounts = Account::orderBy('name')->get(['id', 'name']);
        $selected = $request->invoice_id ? Invoice::find($request->invoice_id) : null;

        $openByAccount = Invoice::open()->orderBy('billing_month')->get()->groupBy('account_id');
        $received = Payment::selectRaw('account_id, SUM(amount) as total')->groupBy('account_id')->pluck('total', 'account_id');
        $applied  = PaymentAllocation::selectRaw('account_id, SUM(amount) as total')->groupBy('account_id')->pluck('total', 'account_id');

        // what the form needs to know about every customer: dues, advance, and the open bills
        $info = $accounts->mapWithKeys(function ($a) use ($openByAccount, $received, $applied) {
            $open = $openByAccount->get($a->id, collect());
            return [$a->id => [
                'due'      => round($open->sum(fn ($i) => $i->balance), 2),
                'advance'  => max(0, round((float) ($received[$a->id] ?? 0) - (float) ($applied[$a->id] ?? 0), 2)),
                'invoices' => $open->map(fn ($i) => [
                    'id' => $i->id, 'label' => $i->period_label . ' - ' . Invoice::money($i->balance) . ' due', 'balance' => round($i->balance, 2),
                ])->values(),
            ]];
        });

        return view('admin.payments.form', [
            'payment'  => new Payment(['paid_on' => today(), 'method' => 'cash']),
            'accounts' => $accounts, 'info' => $info, 'selected' => $selected, 'targets' => collect(),
            'methods'  => config('billing.methods'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['account_id' => 'required|exists:accounts,id'] + $this->rules());
        $account = Account::findOrFail($data['account_id']);

        $target = $this->target($account, $data['invoice_id'] ?? null);
        if (! empty($data['invoice_id']) && ! $target) {
            return back()->withInput()->with('error', 'That bill does not belong to this customer (or it is cancelled).');
        }

        $this->billing->recordPayment($account, Arr::only($data, ['amount', 'paid_on', 'method', 'reference', 'note']), $request->user()->id, $target);

        $advance = $account->advanceBalance();
        $message = 'Payment saved.' . ($advance > 0 ? ' ' . Invoice::money($advance) . ' is kept as advance for the next bill.' : '');

        return redirect()->route('admin.payments.index')->with('success', $message);
    }

    public function edit(Payment $payment)
    {
        $payment->load('account');

        return view('admin.payments.form', [
            'payment'  => $payment, 'accounts' => collect(), 'info' => collect(), 'selected' => $payment->invoice,
            'targets'  => Invoice::where('account_id', $payment->account_id)->where('status', '!=', 'cancelled')->orderBy('billing_month')->get(),
            'methods'  => config('billing.methods'),
        ]);
    }

    public function update(Request $request, Payment $payment)
    {
        $data   = $request->validate($this->rules());
        $target = $this->target($payment->account, $data['invoice_id'] ?? null);
        if (! empty($data['invoice_id']) && ! $target) {
            return back()->withInput()->with('error', 'That bill does not belong to this customer (or it is cancelled).');
        }

        $this->billing->updatePayment($payment, Arr::only($data, ['amount', 'paid_on', 'method', 'reference', 'note']), $target);

        return redirect()->route('admin.payments.index')->with('success', 'Payment updated.');
    }

    public function destroy(Payment $payment)
    {
        $this->billing->deletePayment($payment);

        return back()->with('success', 'Payment deleted.');
    }
}
