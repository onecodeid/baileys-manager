<?php

use App\Http\Controllers\Api\WhatsAppSessionController;
use App\Http\Controllers\Api\UserController;

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('users', UserController::class)->only(['index', 'store']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('sessions', WhatsAppSessionController::class);
});
