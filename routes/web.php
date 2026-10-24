<?php

use App\Http\Controllers\AccountUserController;
use App\Http\Controllers\Admin;
use App\Http\Controllers\AuthController;
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

Route::middleware(['auth', 'account.active'])->group(function () {

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

        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.readAll');
        Route::get('notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
    });

    // ---------- Customer owner only ----------
    Route::middleware('role:owner')->group(function () {
        Route::resource('users', AccountUserController::class)->except('show');
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
        Route::patch('invoices/{invoice}/cancel', [Admin\InvoiceController::class, 'cancel'])->name('invoices.cancel');
        Route::resource('invoices', Admin\InvoiceController::class);
        Route::resource('payments', Admin\PaymentController::class)->except('show');
        Route::get('reports/billing', [Admin\BillingReportController::class, 'index'])->name('reports.billing');
        Route::get('reports/billing/export', [Admin\BillingReportController::class, 'export'])->name('reports.billing.export');

        Route::resource('roles', Admin\RoleController::class)->except('show');
        Route::resource('permissions', Admin\PermissionController::class)->except('show');
    });
});
