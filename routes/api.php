<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\BatchController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Granja Avícola ERP
|--------------------------------------------------------------------------
*/

// ── Rutas públicas de autenticación ────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('/login',           [AuthController::class, 'login']);
    Route::post('/forgot-password', [PasswordResetController::class, 'sendLink']);
    Route::post('/reset-password',  [PasswordResetController::class, 'reset']);
});

// ── Rutas protegidas (requieren token Sanctum) ──────────────────────────
Route::middleware('auth:sanctum')->group(function () {
    // Perfil y Sesión
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me',      [AuthController::class, 'me']);
    });

    // Módulo 2: Gestión de Lotes
    Route::apiResource('batches', BatchController::class);
    Route::post('batches/{batch}/mortality', [BatchController::class, 'registerMortality']);
});
