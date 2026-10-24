<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureAccountActive
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && ! $user->isSuperAdmin()
            && ($user->status !== 'active' || optional($user->account)->status !== 'active')) {

            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'Your account is not active.'], 403);
            }

            $message = ($user->status === 'active' && $user->account)
                ? $user->account->inactiveMessage()
                : 'Your user account is inactive.';

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => $message]);
        }

        return $next($request);
    }
}
