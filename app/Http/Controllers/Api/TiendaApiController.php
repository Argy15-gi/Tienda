<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\PedidoRequest;
use App\Http\Requests\ProductoRequest;
use App\Http\Requests\RegistroRequest;
use App\Http\Requests\VentaRequest;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use App\Services\TiendaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TiendaApiController extends Controller
{
    public function __construct(private readonly TiendaService $tienda) {}

    public function register(RegistroRequest $request): JsonResponse
    {
        $data = $request->validated();
        $plainToken = Str::random(60);

        $user = DB::transaction(function () use ($data, $plainToken): User {
            $nuevo = new User([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);
            $nuevo->forceFill([
                'rol' => 'COMPRADOR',
                'api_token' => hash('sha256', $plainToken),
                'email_verified_at' => now(),
            ])->save();

            $this->tienda->auditar($nuevo, 'USUARIO_REGISTRADO', [
                'email' => $nuevo->email,
                'rol' => $nuevo->rol,
            ]);

            return $nuevo;
        });

        return response()->json([
            'message' => 'Cuenta registrada correctamente.',
            'token' => $plainToken,
            'user' => $user,
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();
        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user || ! $user->password || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['El correo o la contraseña no coinciden con nuestros registros.'],
            ]);
        }

        $plainToken = Str::random(60);
        $user->forceFill(['api_token' => hash('sha256', $plainToken)])->save();

        return response()->json([
            'message' => 'Sesión iniciada correctamente.',
            'token' => $plainToken,
            'user' => $user,
        ]);
    }

    public function demoLogin(Request $request): JsonResponse
    {
        abort_unless((bool) config('services.demo.enabled', false), 403, 'El acceso rápido de demostración está deshabilitado.');

        $data = $request->validate([
            'rol' => ['required', 'string', Rule::in(['ADMIN', 'VENDEDOR', 'COMPRADOR'])],
        ]);

        $emailMap = [
            'ADMIN' => 'admin@tiendita.test',
            'VENDEDOR' => 'vendedor@tiendita.test',
            'COMPRADOR' => 'comprador@tiendita.test',
        ];

        $user = User::query()->where('email', $emailMap[$data['rol']])->firstOrFail();
        $plainToken = Str::random(60);
        $user->forceFill(['api_token' => hash('sha256', $plainToken)])->save();

        return response()->json([
            'message' => "Sesión de demostración ({$user->rol}) iniciada desde el servidor.",
            'token' => $plainToken,
            'user' => $user,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $request->user()]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->forceFill(['api_token' => null])->save();

        return response()->json(['message' => 'Has cerrado sesión correctamente.']);
    }

    public function catalogo(Request $request): JsonResponse
    {
        $filtros = $request->only(['q', 'categoria']);

        return response()->json([
            'productos' => Producto::query()->where('activo', true)->filtrar($filtros)->orderBy('nombre')->get(),
            'categorias' => Producto::query()->where('activo', true)->select('categoria')->distinct()->orderBy('categoria')->pluck('categoria'),
        ]);
    }

    public function panel(Request $request): JsonResponse
    {
        return response()->json($this->tienda->obtenerDatosPanel($request->user()));
    }

    public function productosIndex(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Producto::class);
        $filtros = $request->only(['q', 'categoria', 'estado']);

        return response()->json([
            'productos' => Producto::query()->filtrar($filtros)->orderBy('nombre')->get(),
            'categorias' => Producto::query()->select('categoria')->distinct()->orderBy('categoria')->pluck('categoria'),
            'enPapeleraCount' => Producto::onlyTrashed()->count(),
        ]);
    }

    public function productosStore(ProductoRequest $request): JsonResponse
    {
        Gate::authorize('create', Producto::class);
        $data = $request->validated();
        $data['descripcion'] = $data['descripcion'] ?? '';
        $data['categoria'] = $data['categoria'] ?? 'General';
        $data['activo'] = $request->boolean('activo', true);

        $producto = DB::transaction(function () use ($data, $request): Producto {
            $nuevo = Producto::query()->create($data);
            $this->registrar($request, 'PRODUCTO_CREADO', ['productoId' => $nuevo->id, 'sku' => $nuevo->sku, 'nombre' => $nuevo->nombre]);

            return $nuevo;
        });

        return response()->json([
            'message' => "Producto «{$producto->nombre}» ({$producto->sku}) creado correctamente.",
            'producto' => $producto,
        ], 201);
    }

    public function productosShow(Producto $producto): JsonResponse
    {
        Gate::authorize('view', $producto);
        $producto->load(['items.venta.vendedor:id,name,email', 'items.venta.comprador:id,name,email']);

        return response()->json(['producto' => $producto]);
    }

    public function productosUpdate(ProductoRequest $request, Producto $producto): JsonResponse
    {
        Gate::authorize('update', $producto);
        $data = $request->validated();
        $data['descripcion'] = $data['descripcion'] ?? '';
        $data['categoria'] = $data['categoria'] ?? $producto->categoria ?? 'General';
        if ($request->has('activo')) {
            $data['activo'] = $request->boolean('activo');
        }

        DB::transaction(function () use ($data, $producto, $request): void {
            $precioAnterior = $producto->precio_centavos;
            $producto->update($data);
            $this->registrar($request, 'PRODUCTO_ACTUALIZADO', [
                'productoId' => $producto->id,
                'sku' => $producto->sku,
                'precioAnterior' => $precioAnterior,
                'precioNuevo' => $producto->precio_centavos,
            ]);
        });

        return response()->json([
            'message' => 'Producto actualizado correctamente.',
            'producto' => $producto->fresh(),
        ]);
    }

    public function productosDestroy(Request $request, Producto $producto): JsonResponse
    {
        Gate::authorize('delete', $producto);

        DB::transaction(function () use ($producto, $request): void {
            $producto->update(['activo' => false]);
            $producto->delete();
            $this->registrar($request, 'PRODUCTO_EN_PAPELERA', ['productoId' => $producto->id, 'sku' => $producto->sku]);
        });

        return response()->json(['message' => "El producto «{$producto->nombre}» se envió a la papelera."]);
    }

    public function productosTrash(): JsonResponse
    {
        Gate::authorize('create', Producto::class);

        return response()->json([
            'productos' => Producto::onlyTrashed()->withCount('items')->orderByDesc('deleted_at')->get(),
        ]);
    }

    public function productosRestore(Request $request, int $id): JsonResponse
    {
        $producto = Producto::onlyTrashed()->findOrFail($id);
        Gate::authorize('restore', $producto);

        DB::transaction(function () use ($producto, $request): void {
            $producto->restore();
            $producto->update(['activo' => true]);
            $this->registrar($request, 'PRODUCTO_RESTAURADO', ['productoId' => $producto->id, 'sku' => $producto->sku]);
        });

        return response()->json([
            'message' => "Producto «{$producto->nombre}» restaurado y activado.",
            'producto' => $producto->fresh(),
        ]);
    }

    public function productosForceDestroy(Request $request, int $id): JsonResponse
    {
        $producto = Producto::withTrashed()->withCount(['items', 'itemsPedido'])->findOrFail($id);
        Gate::authorize('forceDelete', $producto);

        if ($producto->items_count > 0 || $producto->items_pedido_count > 0) {
            return response()->json([
                'message' => "No se puede eliminar definitivamente «{$producto->nombre}» porque tiene ventas o pedidos históricos.",
            ], 409);
        }

        $nombre = $producto->nombre;
        $sku = $producto->sku;

        DB::transaction(function () use ($producto, $request, $sku, $nombre): void {
            $idEliminado = $producto->id;
            $producto->forceDelete();
            $this->registrar($request, 'PRODUCTO_ELIMINADO_DEFINITIVO', ['productoId' => $idEliminado, 'sku' => $sku]);
        });

        return response()->json(['message' => "Producto «{$nombre}» eliminado definitivamente."]);
    }

    public function productosToggleActivo(Request $request, Producto $producto): JsonResponse
    {
        Gate::authorize('update', $producto);

        DB::transaction(function () use ($producto, $request): void {
            $producto->update(['activo' => ! $producto->activo]);
            $this->registrar($request, 'PRODUCTO_ESTADO_CAMBIADO', [
                'productoId' => $producto->id,
                'sku' => $producto->sku,
                'activo' => $producto->activo,
            ]);
        });

        $estadoTexto = $producto->activo ? 'activado' : 'ocultado del catálogo';

        return response()->json([
            'message' => "Producto «{$producto->nombre}» {$estadoTexto}.",
            'producto' => $producto->fresh(),
        ]);
    }

    public function productosExport(Request $request): StreamedResponse
    {
        Gate::authorize('viewAny', Producto::class);
        $productos = Producto::query()->filtrar($request->only(['q', 'categoria', 'estado']))->orderBy('sku')->get();
        $rows = $productos->map(fn (Producto $p) => [
            $p->id,
            $p->sku,
            $p->nombre,
            $p->categoria,
            number_format($p->precio_centavos / 100, 2, '.', ''),
            $p->precio_centavos,
            $p->stock,
            $p->activo ? 'Activo' : 'Inactivo',
            optional($p->created_at)->format('Y-m-d H:i'),
        ]);

        return $this->streamCsv(
            'productos-tiendita-'.now()->format('Y-m-d').'.csv',
            ['ID', 'SKU', 'Nombre', 'Categoría', 'Precio (Bs.)', 'Precio (centavos)', 'Stock', 'Estado', 'Fecha Alta'],
            $rows,
        );
    }

    public function ventasIndex(Request $request): JsonResponse
    {
        $user = $request->user();
        $ventas = Venta::query()
            ->when($user->esVendedor(), fn ($q) => $q->where('vendedor_id', $user->id))
            ->when($user->esComprador(), fn ($q) => $q->where('comprador_id', $user->id))
            ->with(['vendedor:id,name,email', 'comprador:id,name,email', 'items.producto'])
            ->latest()
            ->get();

        $hoy = now()->startOfDay();
        $completadas = $ventas->where('estado', 'COMPLETADA');
        $completadasHoy = $completadas->filter(fn (Venta $v) => $v->created_at && $v->created_at->gte($hoy));

        $arqueoPorVendedor = $user->esAdmin()
            ? $completadas->groupBy('vendedor_id')->map(function ($grupo) {
                $vend = $grupo->first()->vendedor;

                return [
                    'vendedor' => $vend->name ?? $vend->email ?? 'Vendedor',
                    'ventas_count' => $grupo->count(),
                    'total_centavos' => (int) $grupo->sum('total'),
                ];
            })->values()
            : [];

        return response()->json([
            'ventas' => $ventas,
            'productos' => Producto::query()->where('activo', true)->orderBy('nombre')->get(),
            'compradores' => User::query()->where('rol', 'COMPRADOR')->orderBy('name')->get(['id', 'name', 'email']),
            'arqueo' => [
                'ventas_hoy_count' => $completadasHoy->count(),
                'recaudado_hoy_centavos' => (int) $completadasHoy->sum('total'),
                'ventas_activas_count' => $completadas->count(),
                'recaudado_activo_centavos' => (int) $completadas->sum('total'),
                'anuladas_count' => $ventas->where('estado', 'ANULADA')->count(),
                'por_vendedor' => $arqueoPorVendedor,
            ],
        ]);
    }

    public function ventasExport(Request $request): StreamedResponse
    {
        $user = $request->user();
        $ventas = Venta::query()
            ->when($user->esVendedor(), fn ($q) => $q->where('vendedor_id', $user->id))
            ->when($user->esComprador(), fn ($q) => $q->where('comprador_id', $user->id))
            ->with(['vendedor:id,name,email', 'comprador:id,name,email', 'items.producto'])
            ->latest()
            ->get();

        $rows = $ventas->map(fn (Venta $v) => [
            $v->id,
            optional($v->created_at)->format('Y-m-d H:i'),
            $v->estado,
            $v->vendedor->name ?? $v->vendedor->email ?? 'N/A',
            $v->comprador->name ?? $v->comprador->email ?? 'Mostrador',
            number_format($v->total / 100, 2, '.', ''),
            $v->items->map(fn ($i) => "{$i->cantidad}x ".($i->producto->sku ?? 'PROD'))->implode(' | '),
            $v->numero_factura ?? '',
            strtoupper($v->metodo_pago ?? 'EFECTIVO'),
            number_format(($v->monto_pagado ?? $v->total) / 100, 2, '.', ''),
            number_format(($v->cambio ?? 0) / 100, 2, '.', ''),
        ]);

        return $this->streamCsv(
            'ventas-tiendita-'.now()->format('Y-m-d').'.csv',
            ['Folio', 'Fecha', 'Estado', 'Vendedor', 'Comprador', 'Total (Bs.)', 'Artículos', 'Factura', 'Método de Pago', 'Monto Recibido (Bs.)', 'Cambio (Bs.)'],
            $rows,
        );
    }

    public function ventasStore(VentaRequest $request): JsonResponse
    {
        $venta = $this->tienda->registrarVenta($request->user(), $request->validated());

        return response()->json([
            'message' => "Venta #{$venta->id} registrada correctamente.",
            'venta' => $venta,
        ], 201);
    }

    public function ventasCancel(Request $request, Venta $venta): JsonResponse
    {
        $data = $request->validate([
            'motivo' => ['required', 'string', 'min:3', 'max:500'],
        ], [
            'motivo.required' => 'El motivo de anulación es obligatorio.',
            'motivo.min' => 'El motivo debe tener al menos 3 caracteres.',
        ]);

        $this->tienda->anularVenta($request->user(), $venta, $data['motivo']);

        return response()->json(['message' => "Venta #{$venta->id} anulada y stock devuelto al inventario."]);
    }

    /**
     * Detalles de la tienda (encabezado de la factura / comprobante).
     */
    public function tienda(): JsonResponse
    {
        return response()->json([
            'tienda' => config('services.tienda'),
        ]);
    }

    /**
     * Datos completos de la factura de una venta.
     * Visible para ADMIN, el vendedor que la registró o el comprador que la pagó.
     */
    public function ventasFactura(Request $request, Venta $venta): JsonResponse
    {
        $user = $request->user();

        $autorizado = $user->esAdmin()
            || $venta->vendedor_id === $user->id
            || ($venta->comprador_id !== null && $venta->comprador_id === $user->id);

        abort_unless($autorizado, 403, 'No tienes permiso para ver esta factura.');

        $venta->load(['vendedor:id,name,email', 'comprador:id,name,email', 'items.producto']);

        return response()->json([
            'venta' => $venta,
            'tienda' => config('services.tienda'),
        ]);
    }

    public function pedidosIndex(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'pedidos' => Pedido::query()
                ->when($user->esComprador(), fn ($q) => $q->where('comprador_id', $user->id))
                ->with(['comprador:id,name,email', 'atendidoPor:id,name,email', 'items.producto'])
                ->latest()
                ->get(),
        ]);
    }

    public function pedidosStore(PedidoRequest $request): JsonResponse
    {
        $pedido = $this->tienda->crearPedido($request->user(), $request->validated());

        return response()->json([
            'message' => "Pedido #{$pedido->id} creado y stock reservado.",
            'pedido' => $pedido,
        ], 201);
    }

    public function pedidosEstado(Request $request, Pedido $pedido): JsonResponse
    {
        $data = $request->validate([
            'estado' => ['required', 'string', Rule::in(['COMPLETADO', 'CANCELADO'])],
        ]);

        $this->tienda->procesarEstadoPedido($request->user(), $pedido, $data['estado']);

        return response()->json([
            'message' => $data['estado'] === 'COMPLETADO'
                ? "Pedido #{$pedido->id} completado y registrado como venta."
                : "Pedido #{$pedido->id} cancelado y stock devuelto.",
        ]);
    }

    public function usuariosIndex(): JsonResponse
    {
        return response()->json(['usuarios' => User::query()->orderBy('name')->get()]);
    }

    public function usuariosUpdateRole(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'rol' => ['required', 'string', Rule::in(['COMPRADOR', 'VENDEDOR', 'ADMIN'])],
        ]);

        $actualizado = $this->tienda->actualizarRolUsuario($request->user(), $user, $data['rol']);

        return response()->json([
            'message' => 'Rol actualizado correctamente.',
            'user' => $actualizado,
        ]);
    }
}
