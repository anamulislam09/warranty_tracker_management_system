<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\PaymentAttempt;
use App\Services\OnlinePaymentService;
use Illuminate\Http\Request;

/**
 * Every online (SSLCommerz) payment try, and the manual "Verify with gateway":
 * the server asks SSLCommerz itself; if SSLCommerz says the payment is successful, it is recorded on the bill.
 */
class OnlinePaymentController extends ApiController
{
    public function __construct(private OnlinePaymentService $online) {}

    private function row(PaymentAttempt $a, bool $withPayloads = false): array
    {
        $row = [
            'id'                => $a->id,
            'unique_id'         => $a->unique_id,
            'tran_id'           => $a->tran_id,
            'account'           => $a->account ? ['id' => $a->account->id, 'name' => $a->account->name] : null,
            'invoice'           => $a->invoice ? ['id' => $a->invoice->id, 'invoice_no' => $a->invoice->invoice_no, 'period_label' => $a->invoice->period_label] : null,
            'amount'            => (float) $a->amount,
            'currency'          => $a->currency,
            'gateway'           => $a->gateway,
            'source'            => $a->source,                      // web | app
            // initiated | pending | success | failed | cancelled | expired | needs_review
            'status'            => $a->status,
            'request_step'      => (int) $a->request_step,          // 0 created, 1 sent to the gateway, 2 answer processed
            'gateway_reference' => $a->gateway_reference,
            'gateway_status'    => $a->gateway_status,
            'card_type'         => $a->card_type,
            'card_no'           => $a->card_no,
            'failure_reason'    => $a->failure_reason,
            'callback_count'    => (int) $a->callback_count,
            'last_callback_at'  => optional($a->last_callback_at)->toIso8601String(),
            'verified_by'       => $a->verified_by,                 // auto | manual
            'verified_at'       => optional($a->verified_at)->toIso8601String(),
            'payment_id'        => $a->payment_id,                  // the payment that was recorded on the bill
            'can_verify'        => $a->canVerify(),
            'created_at'        => optional($a->created_at)->toIso8601String(),
        ];

        if ($withPayloads) {
            $row['request_payload']    = $a->request_payload;       // what we sent to the gateway
            $row['callback_payload']   = $a->callback_payload;      // what came back from the gateway
            $row['validation_payload'] = $a->validation_payload;    // our checks with the gateway
        }

        return $row;
    }

    public function index(Request $request)
    {
        $query = PaymentAttempt::with(['account', 'invoice'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->account_id, fn ($q, $id) => $q->where('account_id', $id))
            ->when($request->q, fn ($q, $t) => $q->where(fn ($q) => $q->where('tran_id', 'like', "%{$t}%")->orWhere('gateway_reference', 'like', "%{$t}%")))
            ->when($request->from, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->to, fn ($q, $d) => $q->whereDate('created_at', '<=', $d));

        $page = $query->latest()->paginate($this->perPage($request, 15, 100));

        return $this->ok(
            $page->getCollection()->map(fn ($a) => $this->row($a))->values(), 'OK',
            $this->pageMeta($page) + ['counts_by_status' => PaymentAttempt::selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status')]
        );
    }

    public function show(int $id)
    {
        $attempt = PaymentAttempt::with('account', 'invoice')->find($id);

        return $attempt ? $this->ok($this->row($attempt, true)) : $this->fail('Online payment not found.', 'not_found');
    }

    /** Manual verify: ask the gateway. If it says success, the payment is recorded and the bill is updated. */
    public function verify(Request $request, int $id)
    {
        $attempt = PaymentAttempt::with('account', 'invoice')->find($id);
        if (! $attempt) {
            return $this->fail('Online payment not found.', 'not_found');
        }

        try {
            $attempt = $this->online->verify($attempt, 'manual', $request->user()->id);
        } catch (\DomainException $e) {
            return $this->fail($e->getMessage(), 'gateway_unreachable');
        }

        if (! $attempt->isSuccess()) {
            $last = collect($attempt->validation_payload)->last();

            return $this->fail(($last['message'] ?? 'The gateway did not confirm the payment.') . ' Status: ' . $attempt->status . '.', 'not_confirmed');
        }

        return $this->ok($this->row($attempt->load('account', 'invoice'), true), 'The gateway confirmed the payment. It is now recorded on the bill.');
    }
}
