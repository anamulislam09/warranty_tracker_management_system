<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role'           => \App\Http\Middleware\CheckRole::class,
            'permission'     => \App\Http\Middleware\CheckPermission::class,
            'account.active' => \App\Http\Middleware\EnsureAccountActive::class,
            'billing.clear'  => \App\Http\Middleware\EnsureBillingClear::class,
        ]);

        // online payment gateways send the customer / their server back with a POST
        $middleware->validateCsrfTokens(except: ['billing/gateway/*']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Everything under /api answers in JSON, in ONE format, whatever header the app sends:
        //   { "success": false, "code": "...", "message": "...", "errors": { field: [..] } }
        // and with ONE failure code: HTTP 422 (success is always HTTP 200). The app reads "success" and "code".
        $exceptions->shouldRenderJsonWhen(fn (Request $request, Throwable $e) => $request->is('api/*') || $request->expectsJson());

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false, 'code' => 'validation_error',
                    'message' => $e->validator->errors()->first() ?: 'The given data was invalid.',
                    'errors'  => $e->errors(),
                ], 422);
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['success' => false, 'code' => 'unauthenticated', 'message' => 'Your login is not valid or has expired. Please login again.'], 422);
            }
        });

        // no permission, not found (also a wrong id), wrong method, too many requests ... : the real kind is in "code", the http code is 422
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if (! $request->is('api/*')) return;

            $status = $e->getStatusCode();
            $codes  = [401 => 'unauthenticated', 402 => 'payment_required', 403 => 'forbidden', 404 => 'not_found',
                       405 => 'method_not_allowed', 419 => 'page_expired', 429 => 'too_many_requests'];
            $text   = [403 => 'You do not have permission to do this.', 404 => 'Not found.', 405 => 'Method not allowed.',
                       429 => 'Too many requests. Please wait a moment.'];

            $message = in_array($status, [404, 405], true) ? $text[$status] : ($e->getMessage() ?: ($text[$status] ?? 'Request failed.'));

            return response()->json(['success' => false, 'code' => $codes[$status] ?? 'http_error', 'message' => $message], 422, $e->getHeaders());
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*')) {
                report($e);
                return response()->json([
                    'success' => false, 'code' => 'server_error',
                    'message' => config('app.debug') ? $e->getMessage() : 'Something went wrong on the server. Please try again later.',
                ], 422);
            }
        });
    })->create();
