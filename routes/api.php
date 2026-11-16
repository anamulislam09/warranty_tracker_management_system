<?php

use App\Http\Controllers\Api;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mobile app API  -  https://your-domain/api/v1/...
|--------------------------------------------------------------------------
| Security: Laravel Sanctum tokens. POST /auth/login gives a token, the app sends it in every call:
|     Authorization: Bearer <token>        Accept: application/json
| Every logged-in call passes these checks (in this order):
|     auth:sanctum      valid, not expired token                          -> 401 unauthenticated
|     account.active    the user and the customer are active              -> 403 account_inactive
|     billing.clear     no overdue bill (only Billing may be used)         -> 402 payment_overdue
|     role              owner / member                                    -> 403 forbidden
|     permission:...    the permission of that screen                     -> 403 forbidden
| The super admin has no mobile app: the website is his panel.
| Route NAMES start with "api." - EnsureBillingClear uses them to let a restricted customer still reach billing.
*/
Route::prefix('v1')->name('api.')->group(function () {

    // ---------------- no login ----------------
    Route::get('meta/registration', [Api\MetaController::class, 'registration'])->name('meta.registration');
    Route::post('auth/register', [Api\AuthController::class, 'register'])->middleware('throttle:10,1')->name('auth.register');
    Route::post('auth/login', [Api\AuthController::class, 'login'])->middleware('throttle:6,1')->name('auth.login');

    // ---------------- logged in (owner or member) ----------------
    Route::middleware(['auth:sanctum', 'account.active', 'billing.clear', 'role:owner,member', 'throttle:120,1'])->group(function () {

        // account, profile, devices
        Route::get('me', [Api\AuthController::class, 'me'])->name('me');
        Route::put('me', [Api\AuthController::class, 'updateProfile'])->name('auth.profile');
        Route::put('me/password', [Api\AuthController::class, 'updatePassword'])->name('auth.password');
        Route::get('me/sessions', [Api\AuthController::class, 'sessions'])->name('auth.sessions');
        Route::delete('me/sessions/{id}', [Api\AuthController::class, 'revokeSession'])->name('auth.sessions.revoke');
        Route::post('auth/logout', [Api\AuthController::class, 'logout'])->name('auth.logout');
        Route::post('auth/logout-all', [Api\AuthController::class, 'logoutAll'])->name('auth.logout-all');
        Route::post('devices', [Api\DeviceController::class, 'store'])->name('devices.store');
        Route::delete('devices', [Api\DeviceController::class, 'destroy'])->name('devices.destroy');

        // lists for the forms
        Route::get('meta', [Api\MetaController::class, 'app'])->name('meta.app');
        Route::get('categories', [Api\MetaController::class, 'categories'])->name('meta.categories');

        // dashboard
        Route::get('dashboard', [Api\DashboardController::class, 'show'])->middleware('permission:dashboard.view')->name('dashboard');
        Route::get('usage', [Api\DashboardController::class, 'usage'])->name('usage');

        // products and documents
        Route::get('products', [Api\ProductController::class, 'index'])->middleware('permission:products.view')->name('products.index');
        Route::get('products/{product}', [Api\ProductController::class, 'show'])->middleware('permission:products.view')->name('products.show');
        Route::post('products', [Api\ProductController::class, 'store'])->middleware('permission:products.create')->name('products.store');
        Route::put('products/{product}', [Api\ProductController::class, 'update'])->middleware('permission:products.edit')->name('products.update');
        Route::delete('products/{product}', [Api\ProductController::class, 'destroy'])->middleware('permission:products.delete')->name('products.destroy');
        Route::post('products/{product}/renew', [Api\ProductController::class, 'renew'])->middleware('permission:products.edit')->name('products.renew');

        // files of a product
        Route::post('products/{product}/files', [Api\FileController::class, 'store'])->middleware('permission:documents.manage')->name('files.store');
        Route::get('files/{document}', [Api\FileController::class, 'show'])->middleware('permission:products.view')->name('files.show');
        Route::delete('files/{document}', [Api\FileController::class, 'destroy'])->middleware('permission:documents.manage')->name('files.destroy');

        // notifications
        Route::middleware('permission:notifications.view')->group(function () {
            Route::get('notifications', [Api\NotificationController::class, 'index'])->name('notifications.index');
            Route::get('notifications/unread-count', [Api\NotificationController::class, 'unreadCount'])->name('notifications.unread');
            Route::post('notifications/read-all', [Api\NotificationController::class, 'readAll'])->name('notifications.read-all');
            Route::post('notifications/{id}/read', [Api\NotificationController::class, 'read'])->name('notifications.read');
        });

        // ---------------- the customer OWNER only ----------------
        Route::middleware('role:owner')->group(function () {
            // billing (still works when the account is restricted by an overdue bill)
            Route::get('billing', [Api\BillingController::class, 'summary'])->name('billing.summary');
            Route::get('billing/invoices', [Api\BillingController::class, 'invoices'])->name('billing.invoices');
            Route::get('billing/invoices/{id}', [Api\BillingController::class, 'invoice'])->name('billing.invoices.show');
            Route::get('billing/invoices/{id}/download', [Api\BillingController::class, 'download'])->name('billing.invoices.download');
            Route::post('billing/invoices/{id}/pay', [Api\BillingController::class, 'pay'])->name('billing.invoices.pay');
            Route::get('billing/payments', [Api\BillingController::class, 'payments'])->name('billing.payments');
            Route::get('billing/online-payments', [Api\BillingController::class, 'onlinePayments'])->name('billing.online');
            Route::get('billing/online-payments/{uniqueId}', [Api\BillingController::class, 'onlinePayment'])->name('billing.online.show');
            Route::post('billing/online-payments/{uniqueId}/check', [Api\BillingController::class, 'check'])->name('billing.online.check');

            // the owner's other users
            Route::get('users/options', [Api\UserController::class, 'options'])->name('users.options');
            Route::get('users', [Api\UserController::class, 'index'])->name('users.index');
            Route::post('users', [Api\UserController::class, 'store'])->name('users.store');
            Route::get('users/{id}', [Api\UserController::class, 'show'])->name('users.show');
            Route::put('users/{id}', [Api\UserController::class, 'update'])->name('users.update');
            Route::delete('users/{id}', [Api\UserController::class, 'destroy'])->name('users.destroy');
        });
    });
});
