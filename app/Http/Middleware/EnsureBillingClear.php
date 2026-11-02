<?php

namespace App\Http\Middleware;

use App\Services\BillingService;
use Closure;
use Illuminate\Http\Request;

/**
 * A customer with a bill that is unpaid after its due date can still LOGIN, but can only use the Billing page
 * (to pay) and logout. Everything else is blocked until the bill is paid.
 * (After one more month the scheduler suspends the account: no login at all.)
 */
class EnsureBillingClear
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user || $user->isSuperAdmin() || ! $user->account) {
            return $next($request);
        }
        if ($request->routeIs('billing.*', 'logout')) {
            return $next($request);
        }

        $account = $user->account;
        if (! $account->isPaymentOverdue()) {
            return $next($request);
        }

        $invoice   = $account->invoices()->overdue()->orderBy('due_date')->first();
        $suspendOn = $invoice ? app(BillingService::class)->suspensionDateFor($invoice)->format(BillingService::dateFormat()) : null;
        $message   = 'Your payment is overdue, so your account is restricted. Please pay your bill'
                   . ($suspendOn ? " before {$suspendOn} to avoid suspension." : '.');

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json(['message' => $message], 402);
        }

        if ($user->isOwner()) {
            return redirect()->route('billing.index')->with('error', $message);
        }

        // other users of the customer cannot pay: tell them to contact the owner
        return response()->view('billing.blocked', ['message' => $message], 403);
    }
}
