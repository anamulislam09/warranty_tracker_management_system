<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\BillingService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

/**
 * Payments received by the admin (cash, bank, bKash ...).
 * A payment always belongs to ONE bill and can never be more than the balance of that bill (no advance payments).
 * Online (SSLCommerz) payments come from the gateway: they cannot be added, edited or deleted here.
 */
class PaymentController extends ApiController
{
    public function __construct(private BillingService $billing) {}

    /** The methods the admin can choose by hand (SSLCommerz is not one of them). */
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

    private function row(Payment $p): array
    {
        return [
            'id'           => $p->id,
            'invoice'      => $p->invoice ? ['id' => $p->invoice->id, 'invoice_no' => $p->invoice->invoice_no, 'period_label' => $p->invoice->period_label, 'schedule_name' => $p->invoice->cycle_label] : null,
            'account'      => $p->account ? ['id' => $p->account->id, 'name' => $p->account->name] : null,
            'amount'       => (float) $p->amount,
            'paid_on'      => $p->paid_on->toDateString(),
            'method'       => $p->method,
            'method_label' => config('billing.methods')[$p->method] ?? $p->method,
            'reference'    => $p->reference,
            'note'         => $p->note,
            'is_online'    => $p->isOnline(),
            'can_edit'     => ! $p->isOnline(),
            'received_by'  => optional($p->receiver)->name,
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

        $total = (clone $query)->sum('amount');
        $page  = $query->orderByDesc('paid_on')->orderByDesc('id')->paginate($this->perPage($request, 15, 100));

        return $this->ok(
            $page->getCollection()->map(fn ($p) => $this->row($p))->values(), 'OK',
            $this->pageMeta($page) + ['total_amount' => round((float) $total, 2)]
        );
    }

    /** For the "receive payment" screen: the customers that have an unpaid bill, with those bills. */
    public function openInvoices()
    {
        $groups = Invoice::with('account:id,name')->open()->orderBy('billing_month')->get()->groupBy('account_id');

        $list = $groups->map(function ($bills) {
            return [
                'account_id'   => $bills->first()->account_id,
                'account_name' => optional($bills->first()->account)->name,
                'due_total'    => round($bills->sum(fn ($i) => $i->balance), 2),
                'invoices'     => $bills->map(fn ($i) => [
                    'id' => $i->id, 'invoice_no' => $i->invoice_no, 'period_label' => $i->period_label,
                    'schedule_name' => $i->cycle_label, 'balance' => round($i->balance, 2), 'due_date' => $i->due_date->toDateString(),
                ])->values(),
            ];
        })->sortBy('account_name')->values();

        return $this->ok($list, 'OK', ['methods' => collect($this->manualMethods())->map(fn ($name, $code) => ['code' => $code, 'name' => $name])->values()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['invoice_id' => 'required|exists:invoices,id'] + $this->rules());
        $invoice = Invoice::with('account')->findOrFail($data['invoice_id']);

        try {
            $payment = $this->billing->recordPayment($invoice, Arr::only($data, ['amount', 'paid_on', 'method', 'reference', 'note']), $request->user()->id);
        } catch (\DomainException $e) {
            return $this->fail($e->getMessage(), 'payment_not_allowed');
        }

        return $this->ok($this->row($payment->load('account', 'invoice', 'receiver')), 'Payment saved successfully. The updated invoice was emailed to the customer.');
    }

    public function update(Request $request, int $id)
    {
        $payment = Payment::find($id);
        if (! $payment) {
            return $this->fail('Payment not found.', 'not_found');
        }
        if ($payment->isOnline()) {
            return $this->fail('An online payment cannot be edited or deleted.', 'online_payment_locked');
        }
        $data = $request->validate($this->rules());

        try {
            $this->billing->updatePayment($payment, Arr::only($data, ['amount', 'paid_on', 'method', 'reference', 'note']));
        } catch (\DomainException $e) {
            return $this->fail($e->getMessage(), 'payment_not_allowed');
        }

        return $this->ok($this->row($payment->fresh()->load('account', 'invoice', 'receiver')), 'Payment updated successfully.');
    }

    public function destroy(int $id)
    {
        $payment = Payment::find($id);
        if (! $payment) {
            return $this->fail('Payment not found.', 'not_found');
        }
        if ($payment->isOnline()) {
            return $this->fail('An online payment cannot be edited or deleted.', 'online_payment_locked');
        }

        $this->billing->deletePayment($payment);

        return $this->ok(null, 'Payment deleted successfully.');
    }
}
