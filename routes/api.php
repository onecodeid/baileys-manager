<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\WhatsAppSessionController;
use App\Http\Controllers\Api\UserController;

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('users', UserController::class)->only(['index', 'store']);
    Route::apiResource('sessions', WhatsAppSessionController::class);

    // Tambahkan ini
    Route::get('sessions/{session_name}/qr', [WhatsAppSessionController::class, 'getQr']);
});

