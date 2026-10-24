<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permission)
    {
        abort_unless($request->user() && $request->user()->hasPermission($permission), 403, 'You do not have permission.');
        return $next($request);
    }
}
