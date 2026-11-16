<?php

use App\Http\Controllers\AccountUserController;
use App\Http\Controllers\Admin;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\PaymentAPIController;
use App\Http\Controllers\SslcommerzPaymentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login']);
    Route::get('register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('register', [AuthController::class, 'register']);
});

Route::post('logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

// SSLCommerz sends the customer back here after success, fail and cancel (no login needed).
// No session / cookies / CSRF on this route: the customer comes from another site, and a new session cookie would log him out.
// After a payment started from the mobile app: a small result page that opens the app (deep link). No session, like ssl-process.
Route::get('payment-result/{unique_id}', [\App\Http\Controllers\PaymentResultController::class, 'show'])->name('payment.result')
    ->withoutMiddleware([
        \Illuminate\Cookie\Middleware\EncryptCookies::class,
        \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ]);

Route::any('ssl-process/{unique_id}', [SslcommerzPaymentController::class, 'ssl_process'])->name('ssl_process')
    ->withoutMiddleware([
        \Illuminate\Cookie\Middleware\EncryptCookies::class,
        \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ]);

Route::middleware(['auth', 'account.active', 'billing.clear'])->group(function () {

    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/password', [ProfileController::class, 'password'])->name('profile.password');

    // ---------- Customer area (owner + members) ----------
    Route::middleware('role:owner,member')->group(function () {
        Route::get('products', [ProductController::class, 'index'])->name('products.index')->middleware('permission:products.view');
        Route::get('products/create', [ProductController::class, 'create'])->name('products.create')->middleware('permission:products.create');
        Route::post('products', [ProductController::class, 'store'])->name('products.store')->middleware('permission:products.create');
        Route::get('products/{product}', [ProductController::class, 'show'])->name('products.show')->middleware('permission:products.view');
        Route::get('products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit')->middleware('permission:products.edit');
        Route::put('products/{product}', [ProductController::class, 'update'])->name('products.update')->middleware('permission:products.edit');
        Route::delete('products/{product}', [ProductController::class, 'destroy'])->name('products.destroy')->middleware('permission:products.delete');
        Route::get('products/{product}/renew', [ProductController::class, 'renewForm'])->name('products.renew')->middleware('permission:products.edit');
        Route::post('products/{product}/renew', [ProductController::class, 'renew'])->name('products.renew.store')->middleware('permission:products.edit');

        Route::post('products/{product}/documents', [DocumentController::class, 'store'])->name('documents.store')->middleware('permission:documents.manage');
        Route::get('documents/{document}', [DocumentController::class, 'show'])->name('documents.show')->middleware('permission:products.view');
        Route::delete('documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy')->middleware('permission:documents.manage');

        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index')->middleware('permission:notifications.view');
        Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.readAll')->middleware('permission:notifications.view');
        Route::get('notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read')->middleware('permission:notifications.view');
    });

    // ---------- Customer owner only ----------
    Route::middleware('role:owner')->group(function () {
        Route::resource('users', AccountUserController::class)->except('show');

        // billing: payment schedule, bills, payment history, invoice download, pay online
        Route::get('billing', [BillingController::class, 'index'])->name('billing.index');
        Route::get('billing/invoices/{invoice}/download', [BillingController::class, 'download'])->name('billing.invoice.download');
        // "Pay now": create the payment, then go to SSLCommerz (once)
        Route::post('create-payment', [PaymentAPIController::class, 'create_payment'])->name('Payment.create_payment');
        Route::post('billing/online/{attempt}/check', [BillingController::class, 'check'])->name('billing.online.check');
    });

    // ---------- Super admin ----------
    Route::middleware('role:super_admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('dashboard', Admin\DashboardController::class)->name('dashboard');
        Route::patch('accounts/{account}/status', [Admin\AccountController::class, 'status'])->name('accounts.status');
        Route::resource('accounts', Admin\AccountController::class);
        Route::resource('plans', Admin\PlanController::class)->except('show');
        Route::resource('categories', Admin\CategoryController::class)->except('show');
        Route::resource('subcategories', Admin\SubcategoryController::class)->except('show');
        // billing
        Route::resource('payment-schedules', Admin\PaymentScheduleController::class)->except('show')->names('schedules')->parameters(['payment-schedules' => 'schedule']);
        Route::get('invoices/{invoice}/download', [Admin\InvoiceController::class, 'download'])->name('invoices.download');
        Route::resource('invoices', Admin\InvoiceController::class);
        Route::resource('payments', Admin\PaymentController::class)->except('show');

        // online payments: every try, and "Verify with gateway"
        Route::get('online-payments', [Admin\OnlinePaymentController::class, 'index'])->name('online-payments.index');
        Route::get('online-payments/{attempt}', [Admin\OnlinePaymentController::class, 'show'])->name('online-payments.show');
        Route::post('online-payments/{attempt}/verify', [Admin\OnlinePaymentController::class, 'verify'])->name('online-payments.verify');

        // company details + logo for the invoice
        Route::get('settings', [Admin\SettingController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [Admin\SettingController::class, 'update'])->name('settings.update');
        Route::get('reports/billing', [Admin\BillingReportController::class, 'index'])->name('reports.billing');
        Route::get('reports/billing/export', [Admin\BillingReportController::class, 'export'])->name('reports.billing.export');

        Route::resource('roles', Admin\RoleController::class)->except('show');
        Route::resource('permissions', Admin\PermissionController::class)->except('show');
    });
});
