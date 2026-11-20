<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\Account;
use App\Models\Invoice;
use App\Models\PaymentSchedule;
use App\Models\Plan;
use App\Services\BillingService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Bills. The rules are the website's:
 *   - a bill is always for the NEXT period of the customer's own payment schedule (no other period, no advance bill);
 *   - the first bill of a new customer may be created by hand at any time;
 *   - a bill that already has a payment can no longer be edited or deleted;
 *   - there is no "cancel": a wrong bill is edited (before any payment) or deleted.
 * The PDF is /admin/invoices/{id}/download (see routes/api.php).
 */
class InvoiceController extends ApiController
{
    public function __construct(private BillingService $billing) {}

    private function paymentRow($p): array
    {
        return [
            'id'           => $p->id,
            'amount'       => (float) $p->amount,
            'paid_on'      => $p->paid_on->toDateString(),
            'method'       => $p->method,
            'method_label' => config('billing.methods')[$p->method] ?? $p->method,
            'reference'    => $p->reference,
            'note'         => $p->note,
            'is_online'    => $p->isOnline(),
            'received_by'  => optional($p->receiver)->name,
        ];
    }

    private function row(Invoice $i, bool $withPayments = false): array
    {
        $hasPayments = $i->relationLoaded('payments') ? $i->payments->isNotEmpty() : $i->payments()->exists();

        $row = [
            'id'               => $i->id,
            'invoice_no'       => $i->invoice_no,
            'account'          => $i->account ? ['id' => $i->account->id, 'name' => $i->account->name] : null,
            'status'           => $i->display_status,
            'status_text'      => $i->status_text,
            'period_label'     => $i->period_label,
            'billing_month'    => $i->billing_month->toDateString(),
            'period_end'       => optional($i->period_end)->toDateString(),
            'plan_name'        => $i->plan_name,
            'plan_id'          => $i->plan_id,
            'schedule_name'    => $i->cycle_label,
            'months'           => (int) $i->months,
            'base_amount'      => (float) $i->base_amount,
            'discount_percent' => (float) $i->discount_percent,
            'discount_amount'  => (float) $i->discount_amount,
            'amount'           => (float) $i->amount,
            'paid_amount'      => (float) $i->paid_amount,
            'balance'          => round((float) $i->balance, 2),
            'due_date'         => $i->due_date->toDateString(),
            'is_overdue'       => (bool) $i->is_overdue,
            'note'             => $i->note,
            'currency'         => 'BDT',
            'created_at'       => optional($i->created_at)->toIso8601String(),
            'can_edit'         => ! $hasPayments,          // a bill with a payment is locked
            'can_delete'       => ! $hasPayments,
            'download_url'     => route('api.admin.invoices.download', $i->id),
        ];

        if ($withPayments) {
            $row['created_by'] = optional($i->creator)->name;
            $row['payments']   = $i->payments->map(fn ($p) => $this->paymentRow($p))->values();
        }

        return $row;
    }

    public function index(Request $request)
    {
        $month = BillingService::monthFromInput($request->month);              // YYYY-MM

        $query = Invoice::with('account')
            ->when($request->account_id, fn ($q, $id) => $q->where('account_id', $id))
            ->when($month, fn ($q) => $q->whereDate('billing_month', $month->toDateString()))
            ->when($request->status, fn ($q, $s) => $s === 'overdue' ? $q->overdue() : $q->where('status', $s))
            ->when($request->from, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))     // bills issued from
            ->when($request->to, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))       // bills issued until
            ->when($request->q, fn ($q, $t) => $q->where(fn ($q) => $q
                ->where('invoice_no', 'like', "%{$t}%")
                ->orWhereHas('account', fn ($a) => $a->where('name', 'like', "%{$t}%")->orWhere('email', 'like', "%{$t}%"))));

        $totals = (clone $query)->where('status', '!=', 'cancelled')
            ->selectRaw('COALESCE(SUM(amount),0) as billed, COALESCE(SUM(paid_amount),0) as paid')->first();

        $page = $query->with('payments')->orderByDesc('billing_month')->orderByDesc('id')->paginate($this->perPage($request, 15, 100));

        return $this->ok(
            $page->getCollection()->map(fn ($i) => $this->row($i))->values(),
            'OK',
            $this->pageMeta($page) + ['totals' => ['billed' => round((float) $totals->billed, 2), 'paid' => round((float) $totals->paid, 2)]]
        );
    }

    public function show(int $id)
    {
        $invoice = Invoice::with('account', 'creator', 'payments.receiver')->find($id);

        return $invoice ? $this->ok($this->row($invoice, true)) : $this->fail('Bill not found.', 'not_found');
    }

    /**
     * What the "new bill" screen shows for a customer: his payment schedule, the next period, the amount,
     * and whether the bill may be created now (can). When can is false, "message" says why.
     */
    public function draft(Request $request)
    {
        $request->validate(['account_id' => 'required|exists:accounts,id']);

        $account = Account::with('plan', 'schedule')->findOrFail($request->account_id);
        $d = $this->billing->nextInvoiceDraft($account, true);        // by hand: the first bill of a new customer is allowed at any time

        if (! isset($d['quote'])) {
            return $this->ok(['account_id' => $account->id, 'can' => false, 'message' => $d['message']]);
        }

        return $this->ok([
            'account_id'       => $account->id,
            'can'              => $d['can'],
            'message'          => $d['message'],
            'is_first_bill'    => $d['first'],
            'free_until'       => optional($d['free_until'])->toDateString(),
            'payment_schedule' => ['name' => $d['schedule']->name, 'months' => (int) $d['quote']['months']],
            'plan'             => ['id' => $d['plan']->id, 'name' => $d['plan']->name],
            'period_label'     => $d['period_label'],
            'period_start'     => $d['start']->toDateString(),
            'period_end'       => $d['end']->toDateString(),
            'discount_percent' => (float) $d['quote']['discount_percent'],
            'amount'           => (float) $d['quote']['amount'],
            'due_date'         => $d['due']->toDateString(),
            'creatable_from'   => $d['opens_on']->toDateString(),
        ]);
    }

    /** The period, plan and schedule are NOT sent: they always come from the customer's own payment schedule. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'account_id' => 'required|exists:accounts,id',
            'amount'     => 'nullable|numeric|min:0.01|max:99999999',
            'due_date'   => 'nullable|date',
            'note'       => 'nullable|string|max:500',
        ]);

        $account = Account::with('plan', 'schedule')->findOrFail($data['account_id']);
        $draft   = $this->billing->nextInvoiceDraft($account, true);

        if (! $draft['can']) {
            return $this->fail($draft['message'], 'bill_not_allowed');
        }

        $invoice = $this->billing->createInvoice(
            $account, $draft, isset($data['amount']) ? (float) $data['amount'] : null,
            ! empty($data['due_date']) ? Carbon::parse($data['due_date']) : null, $data['note'] ?? null, $request->user()->id
        );
        $this->billing->announce($invoice);

        return $this->ok($this->row($invoice->load('account', 'creator', 'payments.receiver'), true), 'Bill created successfully and sent to the customer.');
    }

    public function update(Request $request, int $id)
    {
        $invoice = Invoice::find($id);
        if (! $invoice) {
            return $this->fail('Bill not found.', 'not_found');
        }
        if ($invoice->payments()->exists()) {
            return $this->fail('A payment was already made for this bill, so it cannot be edited any more.', 'bill_locked');
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
        $discount = max(0, round($quote['base'] - (float) $data['amount'], 2));

        $invoice->update([
            'plan_id' => $plan->id, 'plan_name' => $plan->name, 'amount' => $data['amount'],
            'base_amount' => $quote['base'], 'discount_amount' => $discount,
            'discount_percent' => $quote['base'] > 0 ? round($discount / $quote['base'] * 100, 2) : 0,
            'due_date' => $data['due_date'], 'note' => $data['note'] ?? null,
        ]);
        $this->billing->refresh($invoice);

        return $this->ok($this->row($invoice->fresh()->load('account', 'creator', 'payments.receiver'), true), 'Bill updated successfully.');
    }

    public function destroy(int $id)
    {
        $invoice = Invoice::find($id);
        if (! $invoice) {
            return $this->fail('Bill not found.', 'not_found');
        }
        if ($invoice->payments()->exists()) {
            return $this->fail('This bill has payments, so it cannot be deleted. Delete its payments first.', 'bill_locked');
        }

        $account = $invoice->account;
        $invoice->delete();
        $this->billing->reactivateIfCleared($account);

        return $this->ok(null, 'Bill deleted successfully.');
    }
}
