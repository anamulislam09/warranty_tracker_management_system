<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\BillingService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

/** A payment always belongs to ONE bill and can never be more than the balance of that bill (no advance payments). */
class PaymentController extends Controller
{
    public function __construct(private BillingService $billing) {}

    /** Methods the admin can choose by hand. SSLCommerz is not one of them: those payments only come from the gateway. */
    private function manualMethods(): array
    {
        return Arr::except(config('billing.methods'), ['sslcommerz']);
    }

    private function rules(): array
    {
        return [
            'amount'    => 'required|numeric|min:0.01|max:99999999',
            'paid_on'   => 'required|date|before_or_equal:today',
            'method'    => ['required', Rule::in(array_keys($this->manualMethods()))],
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
        $selected = $request->invoice_id ? Invoice::open()->find($request->invoice_id) : null;

        // only customers that have an unpaid bill, and only those bills
        $open = Invoice::with('account:id,name')->open()->orderBy('billing_month')->get()->groupBy('account_id');

        $accounts = $open->map(fn ($list) => $list->first()->account)->sortBy('name')->values();
        $info = $open->map(fn ($list) => [
            'due' => round($list->sum(fn ($i) => $i->balance), 2),
            'invoices' => $list->map(fn ($i) => [
                'id' => $i->id, 'balance' => round($i->balance, 2),
                'label' => $i->period_label . ' (' . $i->cycle_label . ') - ' . Invoice::money($i->balance) . ' due',
            ])->values(),
        ]);

        return view('admin.payments.form', [
            'payment'  => new Payment(['paid_on' => today(), 'method' => 'cash']),
            'accounts' => $accounts, 'info' => $info, 'selected' => $selected,
            'methods'  => $this->manualMethods(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['invoice_id' => 'required|exists:invoices,id'] + $this->rules());
        $invoice = Invoice::with('account')->findOrFail($data['invoice_id']);

        try {
            $this->billing->recordPayment($invoice, Arr::only($data, ['amount', 'paid_on', 'method', 'reference', 'note']), $request->user()->id);
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.invoices.show', $invoice)->with('success', 'Payment saved. The updated invoice was emailed to the customer.');
    }

    public function edit(Payment $payment)
    {
        if ($payment->isOnline()) {
            return redirect()->route('admin.invoices.show', $payment->invoice_id)->with('error', 'An online payment cannot be edited or deleted.');
        }

        return view('admin.payments.form', [
            'payment' => $payment->load('account', 'invoice'),
            'accounts' => collect(), 'info' => collect(), 'selected' => $payment->invoice,
            'methods' => $this->manualMethods(),
        ]);
    }

    public function update(Request $request, Payment $payment)
    {
        if ($payment->isOnline()) {
            return redirect()->route('admin.invoices.show', $payment->invoice_id)->with('error', 'An online payment cannot be edited or deleted.');
        }

        $data = $request->validate($this->rules());

        try {
            $this->billing->updatePayment($payment, Arr::only($data, ['amount', 'paid_on', 'method', 'reference', 'note']));
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.invoices.show', $payment->invoice_id)->with('success', 'Payment updated.');
    }

    public function destroy(Payment $payment)
    {
        if ($payment->isOnline()) {
            return redirect()->route('admin.invoices.show', $payment->invoice_id)->with('error', 'An online payment cannot be edited or deleted.');
        }

        $this->billing->deletePayment($payment);

        return back()->with('success', 'Payment deleted.');
    }
}
