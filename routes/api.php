<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BankAccountController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\ItemCategoryController;
use App\Http\Controllers\Api\V1\ItemController;
use App\Http\Controllers\Api\V1\ItemUnitController;
use App\Http\Controllers\Api\V1\SettingController;
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

        Route::apiResource('customers', CustomerController::class);
        Route::apiResource('suppliers', SupplierController::class);
        Route::apiResource('technicians', TechnicianController::class);
        Route::apiResource('bank-accounts', BankAccountController::class);
        Route::apiResource('workshop-services', WorkshopServiceController::class);
        Route::apiResource('item-categories', ItemCategoryController::class);
        Route::apiResource('item-units', ItemUnitController::class);
        Route::apiResource('items', ItemController::class);
        // Multipart update (PHP sering gagal parse file di PUT).
        Route::post('items/{item}', [ItemController::class, 'update']);

        Route::get('/settings', [SettingController::class, 'show']);
        Route::match(['put', 'post'], '/settings', [SettingController::class, 'update']);
    });
});
