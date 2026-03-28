<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\BatchController;
use App\Http\Controllers\ProductSizeController;
use App\Http\Controllers\Api\ProductionController;
use App\Http\Controllers\Api\BatchCollectionController;
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

    // Módulo 3: Configuración de Productos (Tamaños y Precios)
    Route::apiResource('product-sizes', ProductSizeController::class);

    // Módulo 4: Producción y Recolección Diaria
    
    // 4.1: Recolecta por Lote (Punto de Vista Pura Postura)
    Route::get('daily-collections/total', [BatchCollectionController::class, 'dailyTotal']);
    Route::get('daily-collections/summary', [BatchCollectionController::class, 'summary']);
    Route::apiResource('daily-collections', BatchCollectionController::class);

    // 4.2: Clasificación de Inventario (Punto de Vista Venta/Cartones)
    Route::get('productions/summary', [ProductionController::class, 'summary']);
    Route::apiResource('productions', ProductionController::class);
});
