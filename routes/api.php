<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CustomerController;
use Illuminate\Support\Facades\Route;

Route::middleware('api.key')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        Route::apiResource('customers', CustomerController::class);
    });
});
