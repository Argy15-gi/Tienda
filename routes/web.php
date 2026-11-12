<?php

use App\Http\Controllers\AdminProductoController;
use App\Http\Controllers\AdminUsuarioController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogoController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PedidoController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\VentaController;
use Illuminate\Support\Facades\Route;

// Catálogo público con búsqueda y filtro por categoría
Route::get('/', [CatalogoController::class, 'index'])->name('catalogo');

// 3. Autenticación: Registro y Login (correo + clave hasheada, o Google OAuth)
Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:15,1')->name('login.store');
    Route::post('/login/demo', [AuthController::class, 'demoLogin'])->middleware('throttle:15,1')->name('login.demo');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:15,1')->name('register.store');
});

Route::get('/auth/google', [AuthController::class, 'redirectToGoogle'])->name('google.login');
Route::get('/auth/google/callback', [AuthController::class, 'googleCallback'])->name('google.callback');

// 4. Middleware auth: todas las rutas de gestión requieren sesión iniciada
Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Extra: Panel de inicio con contadores y avisos
    Route::get('/panel', [DashboardController::class, 'index'])->name('panel');

    // 2. CRUD completo de Productos (7 métodos: index, create, store, show, edit, update, destroy)
    Route::resource('productos', ProductoController::class);

    // Extras de Productos: Papelera (SoftDeletes), Restaurar, Borrado definitivo y Exportar CSV
    Route::get('/productos-papelera', [ProductoController::class, 'trash'])->name('productos.trash');
    Route::patch('/productos/{id}/restaurar', [ProductoController::class, 'restore'])->name('productos.restore');
    Route::delete('/productos/{id}/forzar', [ProductoController::class, 'forceDestroy'])->name('productos.forceDestroy');
    Route::patch('/productos/{producto}/estado', [ProductoController::class, 'toggleActivo'])->name('productos.toggle');
    Route::get('/productos-exportar', [ProductoController::class, 'export'])->name('productos.export');

    // Factura imprimible de una venta (vendedor dueño, comprador o ADMIN)
    Route::get('/ventas/{venta}/factura', [VentaController::class, 'factura'])->name('ventas.factura');

    // Pedidos en línea y exportación de ventas
    Route::get('/pedidos', [PedidoController::class, 'index'])->name('pedidos.index');
    Route::post('/pedidos', [PedidoController::class, 'store'])->name('pedidos.store');
    Route::patch('/pedidos/{pedido}/estado', [PedidoController::class, 'updateEstado'])
        ->middleware('role:ADMIN,VENDEDOR')
        ->name('pedidos.estado');
    Route::get('/ventas-exportar', [VentaController::class, 'export'])->name('ventas.export');

    // Rutas de administración por rol (ADMIN)
    Route::middleware('role:ADMIN')->prefix('admin')->name('admin.')->group(function (): void {
        Route::get('/productos', [AdminProductoController::class, 'index'])->name('productos.index');
        Route::post('/productos', [AdminProductoController::class, 'store'])->name('productos.store');
        Route::put('/productos/{producto}', [AdminProductoController::class, 'update'])->name('productos.update');
        Route::delete('/productos/{producto}', [AdminProductoController::class, 'destroy'])->name('productos.destroy');
        Route::get('/usuarios', [AdminUsuarioController::class, 'index'])->name('usuarios.index');
        Route::patch('/usuarios/{user}/rol', [AdminUsuarioController::class, 'updateRole'])->name('usuarios.rol');
        Route::get('/ventas', [VentaController::class, 'adminIndex'])->name('ventas.index');
        Route::patch('/ventas/{venta}/anular', [VentaController::class, 'cancel'])->name('ventas.anular');
    });

    // Punto de venta (VENDEDOR y ADMIN)
    Route::middleware('role:VENDEDOR,ADMIN')->prefix('vendedor')->name('vendedor.')->group(function (): void {
        Route::get('/ventas', [VentaController::class, 'index'])->name('ventas.index');
        Route::post('/ventas', [VentaController::class, 'store'])->name('ventas.store');
    });

    // Historial de compras y pedidos (COMPRADOR)
    Route::middleware('role:COMPRADOR,ADMIN')->prefix('comprador')->name('comprador.')->group(function (): void {
        Route::get('/compras', [VentaController::class, 'index'])->name('compras.index');
    });
});
