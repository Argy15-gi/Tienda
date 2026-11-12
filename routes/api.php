<?php

use App\Http\Controllers\Api\TiendaApiController;
use Illuminate\Support\Facades\Route;

// Catálogo público y Autenticación API (con límite de intentos contra fuerza bruta y DoS)
Route::get('/catalogo', [TiendaApiController::class, 'catalogo'])->middleware('throttle:60,1');
Route::post('/auth/register', [TiendaApiController::class, 'register'])->middleware('throttle:15,1');
Route::post('/auth/login', [TiendaApiController::class, 'login'])->middleware('throttle:15,1');
Route::post('/auth/demo', [TiendaApiController::class, 'demoLogin'])->middleware('throttle:15,1');

// 4. Middleware auth.api + throttle: rutas protegidas solo para quien inició sesión
Route::middleware(['auth.api', 'throttle:60,1'])->group(function (): void {
    Route::get('/auth/me', [TiendaApiController::class, 'me']);
    Route::post('/auth/logout', [TiendaApiController::class, 'logout']);

    // Extra: Detalles de la tienda (datos que se imprimen en la factura)
    Route::get('/tienda', [TiendaApiController::class, 'tienda']);

    // Extra: Panel de inicio con contadores y avisos
    Route::get('/panel', [TiendaApiController::class, 'panel']);

    // 2. CRUD completo de Productos (index, store, show, update, destroy)
    Route::get('/productos', [TiendaApiController::class, 'productosIndex']);
    Route::post('/productos', [TiendaApiController::class, 'productosStore']);
    Route::get('/productos/{producto}', [TiendaApiController::class, 'productosShow']);
    Route::put('/productos/{producto}', [TiendaApiController::class, 'productosUpdate']);
    Route::delete('/productos/{producto}', [TiendaApiController::class, 'productosDestroy']);

    // Factura imprimible de una venta (vendedor dueño, comprador o ADMIN)
    Route::get('/ventas/{venta}/factura', [TiendaApiController::class, 'ventasFactura']);

    // Extras de Productos: Papelera (SoftDeletes), Restaurar, Borrado definitivo, Estado y Exportar CSV
    Route::get('/productos-papelera', [TiendaApiController::class, 'productosTrash']);
    Route::patch('/productos/{id}/restaurar', [TiendaApiController::class, 'productosRestore']);
    Route::delete('/productos/{id}/forzar', [TiendaApiController::class, 'productosForceDestroy']);
    Route::patch('/productos/{producto}/estado', [TiendaApiController::class, 'productosToggleActivo']);
    Route::get('/productos-exportar', [TiendaApiController::class, 'productosExport']);

    // Ventas y Pedidos en línea
    Route::get('/ventas', [TiendaApiController::class, 'ventasIndex']);
    Route::get('/ventas-exportar', [TiendaApiController::class, 'ventasExport']);
    Route::post('/ventas', [TiendaApiController::class, 'ventasStore'])->middleware('role:ADMIN,VENDEDOR');
    Route::patch('/ventas/{venta}/anular', [TiendaApiController::class, 'ventasCancel'])->middleware('role:ADMIN');

    Route::get('/pedidos', [TiendaApiController::class, 'pedidosIndex']);
    Route::post('/pedidos', [TiendaApiController::class, 'pedidosStore']);
    Route::patch('/pedidos/{pedido}/estado', [TiendaApiController::class, 'pedidosEstado'])->middleware('role:ADMIN,VENDEDOR');

    // Usuarios y Roles (ADMIN)
    Route::get('/usuarios', [TiendaApiController::class, 'usuariosIndex'])->middleware('role:ADMIN');
    Route::patch('/usuarios/{user}/rol', [TiendaApiController::class, 'usuariosUpdateRole'])->middleware('role:ADMIN');
});
