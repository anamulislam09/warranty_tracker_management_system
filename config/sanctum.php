<?php

use Laravel\Sanctum\Sanctum;

return [
    // only used by a browser SPA with cookies - the mobile app uses Bearer tokens
    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
        '%s%s',
        'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1',
        Sanctum::currentApplicationUrlWithPort()
    ))),

    'guard' => ['web'],

    // A mobile token lives this many MINUTES (default 90 days). After that the app gets 401 and the user logs in again.
    // null = never expires. Expired tokens are deleted every day by `sanctum:prune-expired`.
    'expiration' => env('SANCTUM_TOKEN_EXPIRATION_MINUTES', 60 * 24 * 90),

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' => Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token' => Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ],
];
