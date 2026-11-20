<?php

use App\Http\Controllers\Admin as WebAdmin;
use App\Http\Controllers\Api;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mobile app API  -  https://your-domain/api/v1/...
|--------------------------------------------------------------------------
| ONE rule for every answer:   success = HTTP 200   |   anything that did not work = HTTP 422
| The body always has "success" (true / false), "message", and "code" (what went wrong) on a failure.
|
| Security: Laravel Sanctum tokens. POST /auth/login gives a token, the app sends it in every call:
|     Authorization: Bearer <token>        Accept: application/json
| Every logged-in call passes these checks (in this order):
|     auth:sanctum      valid, not expired token                          -> code unauthenticated
|     account.active    the user and the customer are active              -> code account_inactive
|     billing.clear     no overdue bill (customers only)                  -> code payment_overdue
|     role              super_admin / owner / member                      -> code forbidden
|     permission:...    the permission of that screen (members only)      -> code forbidden
|
| Three kinds of users use the app, and every web screen has its API:
|     super_admin  -> /admin/...     (customers, plans, bills, payments, reports, settings ...)
|     owner        -> the customer's own data, billing and users
|     member       -> the customer's own data, by role and permissions
| Route NAMES start with "api." - EnsureBillingClear uses them to let a restricted customer still reach billing.
*/
Route::prefix('v1')->name('api.')->group(function () {

    // ---------------- no login ----------------
    Route::get('meta/registration', [Api\MetaController::class, 'registration'])->name('meta.registration');
    Route::post('auth/register', [Api\AuthController::class, 'register'])->middleware('throttle:10,1')->name('auth.register');
    Route::post('auth/login', [Api\AuthController::class, 'login'])->middleware('throttle:6,1')->name('auth.login');

    // ---------------- logged in: super admin, owner and member ----------------
    Route::middleware(['auth:sanctum', 'account.active', 'billing.clear', 'throttle:120,1'])->group(function () {

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

        // ======================================================================
        //  CUSTOMER users (owner and member)
        // ======================================================================
        Route::middleware('role:owner,member')->group(function () {

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

        // ======================================================================
        //  SUPER ADMIN  -  /api/v1/admin/...
        //  A request with a picture or a logo is multipart/form-data: PHP does not read a multipart PUT,
        //  so those updates are POST (a plain JSON update may also use PUT).
        // ======================================================================
        Route::middleware('role:super_admin')->prefix('admin')->name('admin.')->group(function () {

            Route::get('dashboard', [Api\Admin\DashboardController::class, 'show'])->name('dashboard');

            // customers
            Route::get('customers', [Api\Admin\CustomerController::class, 'index'])->name('customers.index');
            Route::post('customers', [Api\Admin\CustomerController::class, 'store'])->name('customers.store');
            Route::get('customers/{id}', [Api\Admin\CustomerController::class, 'show'])->name('customers.show');
            Route::put('customers/{id}', [Api\Admin\CustomerController::class, 'update'])->name('customers.update');
            Route::patch('customers/{id}/status', [Api\Admin\CustomerController::class, 'status'])->name('customers.status');
            Route::delete('customers/{id}', [Api\Admin\CustomerController::class, 'destroy'])->name('customers.destroy');

            // subscription plans (monthly price only)
            Route::get('plans', [Api\Admin\PlanController::class, 'index'])->name('plans.index');
            Route::post('plans', [Api\Admin\PlanController::class, 'store'])->name('plans.store');
            Route::get('plans/{id}', [Api\Admin\PlanController::class, 'show'])->name('plans.show');
            Route::put('plans/{id}', [Api\Admin\PlanController::class, 'update'])->name('plans.update');
            Route::delete('plans/{id}', [Api\Admin\PlanController::class, 'destroy'])->name('plans.destroy');

            // payment schedules
            Route::get('payment-schedules', [Api\Admin\PaymentScheduleController::class, 'index'])->name('schedules.index');
            Route::post('payment-schedules', [Api\Admin\PaymentScheduleController::class, 'store'])->name('schedules.store');
            Route::put('payment-schedules/{id}', [Api\Admin\PaymentScheduleController::class, 'update'])->name('schedules.update');
            Route::delete('payment-schedules/{id}', [Api\Admin\PaymentScheduleController::class, 'destroy'])->name('schedules.destroy');

            // categories (with a picture) and subcategories
            Route::get('categories', [Api\Admin\CategoryController::class, 'index'])->name('categories.index');
            Route::post('categories', [Api\Admin\CategoryController::class, 'store'])->name('categories.store');
            Route::match(['put', 'post'], 'categories/{id}', [Api\Admin\CategoryController::class, 'update'])->name('categories.update');
            Route::delete('categories/{id}', [Api\Admin\CategoryController::class, 'destroy'])->name('categories.destroy');
            Route::get('subcategories', [Api\Admin\SubcategoryController::class, 'index'])->name('subcategories.index');
            Route::post('subcategories', [Api\Admin\SubcategoryController::class, 'store'])->name('subcategories.store');
            Route::put('subcategories/{id}', [Api\Admin\SubcategoryController::class, 'update'])->name('subcategories.update');
            Route::delete('subcategories/{id}', [Api\Admin\SubcategoryController::class, 'destroy'])->name('subcategories.destroy');

            // roles and permissions
            Route::get('roles', [Api\Admin\RoleController::class, 'index'])->name('roles.index');
            Route::post('roles', [Api\Admin\RoleController::class, 'store'])->name('roles.store');
            Route::put('roles/{id}', [Api\Admin\RoleController::class, 'update'])->name('roles.update');
            Route::delete('roles/{id}', [Api\Admin\RoleController::class, 'destroy'])->name('roles.destroy');
            Route::get('permissions', [Api\Admin\PermissionController::class, 'index'])->name('permissions.index');
            Route::post('permissions', [Api\Admin\PermissionController::class, 'store'])->name('permissions.store');
            Route::put('permissions/{id}', [Api\Admin\PermissionController::class, 'update'])->name('permissions.update');
            Route::delete('permissions/{id}', [Api\Admin\PermissionController::class, 'destroy'])->name('permissions.destroy');

            // bills ("draft" must stay above {id})
            Route::get('invoices/draft', [Api\Admin\InvoiceController::class, 'draft'])->name('invoices.draft');
            Route::get('invoices', [Api\Admin\InvoiceController::class, 'index'])->name('invoices.index');
            Route::post('invoices', [Api\Admin\InvoiceController::class, 'store'])->name('invoices.store');
            Route::get('invoices/{id}', [Api\Admin\InvoiceController::class, 'show'])->name('invoices.show');
            Route::put('invoices/{id}', [Api\Admin\InvoiceController::class, 'update'])->name('invoices.update');
            Route::delete('invoices/{id}', [Api\Admin\InvoiceController::class, 'destroy'])->name('invoices.destroy');
            Route::get('invoices/{invoice}/download', [WebAdmin\InvoiceController::class, 'download'])->name('invoices.download');   // the PDF

            // payments ("open-invoices" must stay above {id})
            Route::get('payments/open-invoices', [Api\Admin\PaymentController::class, 'openInvoices'])->name('payments.open');
            Route::get('payments', [Api\Admin\PaymentController::class, 'index'])->name('payments.index');
            Route::post('payments', [Api\Admin\PaymentController::class, 'store'])->name('payments.store');
            Route::put('payments/{id}', [Api\Admin\PaymentController::class, 'update'])->name('payments.update');
            Route::delete('payments/{id}', [Api\Admin\PaymentController::class, 'destroy'])->name('payments.destroy');

            // online payments (SSLCommerz) and the manual "Verify with gateway"
            Route::get('online-payments', [Api\Admin\OnlinePaymentController::class, 'index'])->name('online-payments.index');
            Route::get('online-payments/{id}', [Api\Admin\OnlinePaymentController::class, 'show'])->name('online-payments.show');
            Route::post('online-payments/{id}/verify', [Api\Admin\OnlinePaymentController::class, 'verify'])->name('online-payments.verify');

            // billing report (and its two CSV files)
            Route::get('reports/billing', [Api\Admin\ReportController::class, 'billing'])->name('reports.billing');
            Route::get('reports/billing/export', [WebAdmin\BillingReportController::class, 'export'])->name('reports.billing.export');

            // company details + logo for the invoice
            Route::get('settings', [Api\Admin\SettingController::class, 'show'])->name('settings.show');
            Route::match(['put', 'post'], 'settings', [Api\Admin\SettingController::class, 'update'])->name('settings.update');
        });
    });
});
