<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\BillingService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function __construct(private BillingService $billing) {}

    private function rules(): array
    {
        return [
            'amount'    => 'required|numeric|min:0.01|max:99999999',
            'paid_on'   => 'required|date|before_or_equal:today',
            'method'    => ['required', Rule::in(array_keys(config('billing.methods')))],
            'reference' => 'nullable|string|max:100',
            'note'      => 'nullable|string|max:500',
        ];
    }

    public function index(Request $request)
    {
        $month = BillingService::monthFromInput($request->month);

        $query = Payment::with(['account', 'invoice', 'receiver'])
            ->when($request->account_id, fn ($q, $id) => $q->where('account_id', $id))
            ->when($request->input('method'), fn ($q, $m) => $q->where('method', $m))
            ->when($month, fn ($q) => $q->whereHas('invoice', fn ($i) => $i->whereDate('billing_month', $month->toDateString())))
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
        $selected = $request->invoice_id ? Invoice::find($request->invoice_id) : null;

        // open bills of every customer: the form fills the bill dropdown from this
        $open = Invoice::open()->orderBy('billing_month')->get()->groupBy('account_id')->map(
            fn ($list) => $list->map(fn ($i) => [
                'id'      => $i->id,
                'label'   => $i->month_label . ' - ' . $i->plan_name . ' - due ' . Invoice::money($i->balance),
                'balance' => round($i->balance, 2),
            ])->values()
        );

        return view('admin.payments.form', [
            'payment'  => new Payment(['paid_on' => today(), 'method' => 'cash']),
            'accounts' => Account::orderBy('name')->get(['id', 'name']),
            'open'     => $open,
            'selected' => $selected,
            'methods'  => config('billing.methods'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['invoice_id' => 'required|exists:invoices,id'] + $this->rules());
        $invoice = Invoice::with('account')->findOrFail($data['invoice_id']);

        if ($invoice->status === 'cancelled') {
            return back()->withInput()->with('error', 'This bill is cancelled.');
        }
        if ((float) $data['amount'] > $invoice->balance + 0.001) {
            return back()->withInput()->with('error', 'Amount is more than the balance of this bill (' . Invoice::money($invoice->balance) . ').');
        }

        $this->billing->recordPayment($invoice, $data, $request->user()->id);

        return redirect()->route('admin.invoices.show', $invoice)->with('success', 'Payment saved.');
    }

    public function edit(Payment $payment)
    {
        return view('admin.payments.form', [
            'payment'  => $payment->load('account', 'invoice'),
            'accounts' => collect(), 'open' => collect(), 'selected' => $payment->invoice,
            'methods'  => config('billing.methods'),
        ]);
    }

    public function update(Request $request, Payment $payment)
    {
        $data    = $request->validate($this->rules());
        $invoice = $payment->invoice;

        // the payment itself is part of what is already paid
        $limit = $invoice->balance + (float) $payment->amount;
        if ((float) $data['amount'] > $limit + 0.001) {
            return back()->withInput()->with('error', 'Amount is more than this bill allows (' . Invoice::money($limit) . ').');
        }

        $this->billing->updatePayment($payment, $data);

        return redirect()->route('admin.invoices.show', $invoice)->with('success', 'Payment updated.');
    }

    public function destroy(Payment $payment)
    {
        $invoice = $payment->invoice;
        $this->billing->deletePayment($payment);

        return back()->with('success', 'Payment deleted.');
    }
}
