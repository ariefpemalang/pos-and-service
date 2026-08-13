<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BankAccountController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\ItemCategoryController;
use App\Http\Controllers\Api\V1\ItemController;
use App\Http\Controllers\Api\V1\ItemUnitController;
use App\Http\Controllers\Api\V1\SettingController;
use App\Http\Controllers\Api\V1\StockInController;
use App\Http\Controllers\Api\V1\StockOutController;
use App\Http\Controllers\Api\V1\SupplierController;
use App\Http\Controllers\Api\V1\TechnicianController;
use App\Http\Controllers\Api\V1\WorkshopServiceController;
use Illuminate\Support\Facades\Route;

Route::middleware('api.key')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        // Prefix nama route `api.*` agar tidak menimpa route web (customers.index, dll).
        Route::apiResource('customers', CustomerController::class)->names('api.customers');
        Route::apiResource('suppliers', SupplierController::class)->names('api.suppliers');
        Route::apiResource('technicians', TechnicianController::class)->names('api.technicians');
        Route::post('technicians/{technician}', [TechnicianController::class, 'update'])->name('api.technicians.update.multipart');
        Route::apiResource('bank-accounts', BankAccountController::class)->names('api.bank-accounts');
        Route::apiResource('workshop-services', WorkshopServiceController::class)->names('api.workshop-services');
        Route::apiResource('item-categories', ItemCategoryController::class)->names('api.item-categories');
        Route::apiResource('item-units', ItemUnitController::class)->names('api.item-units');
        Route::apiResource('items', ItemController::class)->names('api.items');
        // Multipart update (PHP sering gagal parse file di PUT).
        Route::post('items/{item}', [ItemController::class, 'update'])->name('api.items.update.multipart');

        Route::get('/dashboard', [DashboardController::class, 'show'])->name('api.dashboard.show');

        Route::get('/settings', [SettingController::class, 'show'])->name('api.settings.show');
        Route::match(['put', 'post'], '/settings', [SettingController::class, 'update'])->name('api.settings.update');

        Route::get('stock-ins', [StockInController::class, 'index'])->name('api.stock-ins.index');
        Route::post('stock-ins', [StockInController::class, 'store'])->name('api.stock-ins.store');
        Route::get('stock-ins/{batchNo}', [StockInController::class, 'show'])->name('api.stock-ins.show');

        Route::get('stock-outs', [StockOutController::class, 'index'])->name('api.stock-outs.index');
        Route::post('stock-outs', [StockOutController::class, 'store'])->name('api.stock-outs.store');
        Route::get('stock-outs/{batchNo}', [StockOutController::class, 'show'])->name('api.stock-outs.show');
    });
});
