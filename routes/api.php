<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\BatchController;
use App\Http\Controllers\ProductSizeController;
use App\Http\Controllers\Api\ProductionController;
use App\Http\Controllers\Api\BatchCollectionController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\CashBoxController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ReminderController;
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
    Route::get('productions/pending-balance', [ProductionController::class, 'pendingBalance']);
    Route::get('productions/summary', [ProductionController::class, 'summary']);
    Route::apiResource('productions', ProductionController::class);

    // 4.3: Inventario Actual (Stock)
    Route::get('inventory', [InventoryController::class, 'index']);
    Route::post('inventory/adjust', [InventoryController::class, 'adjust']);

    // Módulo 5: Gestión de Clientes y Ventas
    Route::apiResource('customers', CustomerController::class);
    Route::apiResource('sales',     SaleController::class);

    // Módulo 6: Gestión de Caja (Cash Management)
    Route::get('cash/current',         [CashBoxController::class, 'current']);
    Route::post('cash/open',           [CashBoxController::class, 'open']);
    Route::post('cash/close',          [CashBoxController::class, 'close']);
    Route::post('cash/transactions',   [CashBoxController::class, 'storeTransaction']);
    Route::get('cash/history',         [CashBoxController::class, 'index']);
    Route::get('cash/history/{cash_box}', [CashBoxController::class, 'show']);
    Route::patch('cash/history/{cash_box}', [CashBoxController::class, 'update']);

    // Módulo 7: Gestión de Usuarios
    Route::apiResource('users', UserController::class);
    Route::post('auth/change-password', [UserController::class, 'changePassword']);

    // Módulo 8: Recordatorios Compartidos (Multiusuario & Administrador)
    Route::get('reminders', [ReminderController::class, 'index']);
    Route::post('reminders', [ReminderController::class, 'store']);
    Route::get('reminders/history', [ReminderController::class, 'history']);
    Route::post('reminders/{reminder}/done', [ReminderController::class, 'markAsDone']);

    // Módulo 9: Pedidos de Clientes (Independientes)
    Route::get('orders', [\App\Http\Controllers\OrderController::class, 'index']);
    Route::post('orders', [\App\Http\Controllers\OrderController::class, 'store']);
    Route::get('orders/history', [\App\Http\Controllers\OrderController::class, 'history']);
    Route::post('orders/{order}/status', [\App\Http\Controllers\OrderController::class, 'updateStatus']);
    Route::patch('orders/{order}', [\App\Http\Controllers\OrderController::class, 'update']);
});
