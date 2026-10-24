<?php

use App\Http\Controllers\Api;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('auth/login', [Api\AuthController::class, 'login']);

    Route::middleware(['auth:sanctum', 'account.active', 'role:owner,member'])->group(function () {
        Route::post('auth/logout', [Api\AuthController::class, 'logout']);

        Route::get('products', [Api\ProductController::class, 'index'])->middleware('permission:products.view');
        Route::get('products/{product}', [Api\ProductController::class, 'show'])->middleware('permission:products.view');
        Route::post('products', [Api\ProductController::class, 'store'])->middleware('permission:products.create');
        Route::put('products/{product}', [Api\ProductController::class, 'update'])->middleware('permission:products.edit');
        Route::delete('products/{product}', [Api\ProductController::class, 'destroy'])->middleware('permission:products.delete');
    });
});
