<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\PaymentAttempt;
use App\Services\OnlinePaymentService;
use Illuminate\Http\Request;

/** Every online payment try, and the "Verify with gateway" button for payments the site did not get to know about. */
class OnlinePaymentController extends Controller
{
    public function __construct(private OnlinePaymentService $online) {}

    public function index(Request $request)
    {
        $query = PaymentAttempt::with(['account', 'invoice'])
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->account_id, fn($q, $id) => $q->where('account_id', $id))
            ->when($request->gateway, fn($q, $g) => $q->where('gateway', $g))
            ->when($request->q, fn($q, $t) => $q->where(fn($q) => $q->where('tran_id', 'like', "%{$t}%")->orWhere('gateway_reference', 'like', "%{$t}%")))
            ->when($request->from, fn($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->to, fn($q, $d) => $q->whereDate('created_at', '<=', $d));

        return view('admin.online-payments.index', [
            'attempts' => $query->latest()->paginate(15)->withQueryString(),
            'counts'   => PaymentAttempt::selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status'),
            'accounts' => Account::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(PaymentAttempt $attempt)
    {
        return view('admin.online-payments.show', ['attempt' => $attempt->load('account', 'invoice', 'payment', 'user', 'verifier')]);
    }

    /** Manual verify: ask the gateway. If it says success, the payment is recorded and the bill is updated. */
    public function verify(Request $request, PaymentAttempt $attempt)
    {
        try {
            $attempt = $this->online->verify($attempt, 'manual', $request->user()->id);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        $last = collect($attempt->validation_payload)->last();

        if ($attempt->isSuccess()) {
            return back()->with('success', 'The gateway confirmed the payment. It is now recorded on the bill.');
        }
        return back()->with('error', ($last['message'] ?? 'The gateway did not confirm the payment.') . ' Status: ' . ucfirst(str_replace('_', ' ', $attempt->status)) . '.');
    }
}
