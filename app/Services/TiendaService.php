<?php

namespace App\Services;

use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Registro;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TiendaService
{
    /**
     * Single Source of Truth (SoT) para las métricas y avisos del Panel de Inicio.
     */
    public function obtenerDatosPanel(User $user): array
    {
        $inicioMes = now()->startOfMonth();
        $filtroRolVentas = fn ($q) => $q
            ->when($user->esVendedor(), fn ($sq) => $sq->where('vendedor_id', $user->id))
            ->when($user->esComprador(), fn ($sq) => $sq->where('comprador_id', $user->id));

        $esStaff = $user->esAdmin() || $user->esVendedor();

        return [
            'metricas' => [
                'productos_activos' => Producto::query()->where('activo', true)->count(),
                'productos_nuevos_mes' => Producto::query()->where('created_at', '>=', $inicioMes)->count(),
                'productos_bajo_stock' => $esStaff
                    ? Producto::query()->where('activo', true)->whereBetween('stock', [1, 5])->count()
                    : 0,
                'productos_en_papelera' => $user->esAdmin() ? Producto::onlyTrashed()->count() : 0,
                'ventas_mes_count' => Venta::query()
                    ->where('estado', 'COMPLETADA')
                    ->where('created_at', '>=', $inicioMes)
                    ->tap($filtroRolVentas)
                    ->count(),
                'ingresos_mes_centavos' => (int) Venta::query()
                    ->where('estado', 'COMPLETADA')
                    ->where('created_at', '>=', $inicioMes)
                    ->tap($filtroRolVentas)
                    ->sum('total'),
                'pedidos_pendientes' => Pedido::query()
                    ->where('estado', 'PENDIENTE')
                    ->when($user->esComprador(), fn ($q) => $q->where('comprador_id', $user->id))
                    ->count(),
                'usuarios_totales' => $user->esAdmin() ? User::query()->count() : 1,
            ],
            'productosAlerta' => $esStaff
                ? Producto::query()
                    ->where('activo', true)
                    ->where('stock', '<=', 5)
                    ->orderBy('stock')
                    ->take(5)
                    ->get()
                : collect(),
            'avisos' => Registro::query()
                ->with('usuario:id,name,email,rol')
                ->when(! $user->esAdmin(), fn ($q) => $q->where('usuario_id', $user->id))
                ->latest()
                ->take(8)
                ->get(),
            'ultimasVentas' => Venta::query()
                ->with(['vendedor:id,name,email', 'comprador:id,name,email', 'items.producto'])
                ->tap($filtroRolVentas)
                ->latest()
                ->take(5)
                ->get(),
        ];
    }

    /**
     * Registra una venta descontando stock atómicamente en una transacción.
     */
    public function registrarVenta(User $vendedor, array $data): Venta
    {
        return DB::transaction(function () use ($vendedor, $data): Venta {
            $compradorId = $data['comprador_id'] ?? null;
            $total = 0;
            $lineas = [];

            foreach ($data['items'] as $item) {
                $productoId = (int) $item['producto_id'];
                $cantidad = (int) $item['cantidad'];

                $actualizadas = DB::table('productos')
                    ->where('id', $productoId)
                    ->whereNull('deleted_at')
                    ->where('activo', true)
                    ->where('stock', '>=', $cantidad)
                    ->decrement('stock', $cantidad);

                if ($actualizadas !== 1) {
                    $producto = Producto::query()->find($productoId);
                    abort_unless($producto && $producto->activo, 404, 'El producto no existe o está inactivo.');
                    throw ValidationException::withMessages([
                        'items' => ["Stock insuficiente para {$producto->nombre}."],
                    ]);
                }

                $producto = Producto::query()->findOrFail($productoId);
                $subtotal = $producto->precio_centavos * $cantidad;
                if ($subtotal > 2_147_483_647 || $total > 2_147_483_647 - $subtotal) {
                    throw ValidationException::withMessages([
                        'items' => ['El total de la venta excede el límite permitido.'],
                    ]);
                }

                $total += $subtotal;
                $lineas[] = [
                    'producto_id' => $producto->id,
                    'cantidad' => $cantidad,
                    'precio_unit_centavos' => $producto->precio_centavos,
                ];
            }

            if ($compradorId !== null) {
                $esComprador = User::query()->whereKey($compradorId)->where('rol', 'COMPRADOR')->exists();
                if (! $esComprador) {
                    throw ValidationException::withMessages([
                        'comprador_id' => ['El comprador seleccionado no existe o no tiene ese rol.'],
                    ]);
                }
            }

            $venta = $vendedor->ventas()->create([
                'comprador_id' => $compradorId,
                'total' => $total,
                'estado' => 'COMPLETADA',
            ]);
            $venta->items()->createMany($lineas);

            // Facturación: número secuencial, cobro con cambio y datos del cliente
            $metodoPago = $data['metodo_pago'] ?? 'efectivo';
            $montoPagado = $this->montoPagado($total, $metodoPago, $data['monto_pagado'] ?? null);

            $venta->forceFill([
                'numero_factura' => sprintf('FAC-%s-%06d', now()->format('Y'), $venta->id),
                'metodo_pago' => $metodoPago,
                'monto_pagado' => $montoPagado,
                'cambio' => max(0, $montoPagado - $total),
                'cliente_nombre' => $this->nombreCliente($venta, $data['cliente_nombre'] ?? null),
                'nit_ci' => trim((string) ($data['nit_ci'] ?? '')) !== '' ? trim((string) $data['nit_ci']) : '0',
            ])->save();

            $this->auditar($vendedor, 'VENTA_REGISTRADA', [
                'ventaId' => $venta->id,
                'total' => $total,
                'factura' => $venta->numero_factura,
                'metodo_pago' => $venta->metodo_pago,
                'monto_pagado' => $venta->monto_pagado,
                'cambio' => $venta->cambio,
            ]);

            return $venta->fresh(['vendedor:id,name,email', 'comprador:id,name,email', 'items.producto']);
        }, 3);
    }

    /**
     * Valida el monto recibido en efectivo contra el total y lo devuelve en centavos.
     */
    private function montoPagado(int $totalCentavos, string $metodoPago, mixed $montoPagado): int
    {
        if ($metodoPago !== 'efectivo') {
            // QR y transferencia se cobran por el total exacto: no requieren cambio
            return $totalCentavos;
        }

        if ($montoPagado === null || trim((string) $montoPagado) === '') {
            return $totalCentavos; // Se asume pago exacto
        }

        $montoRecibido = (int) round(((float) $montoPagado) * 100);

        if ($montoRecibido < 0) {
            throw ValidationException::withMessages([
                'monto_pagado' => ['El monto recibido no puede ser negativo.'],
            ]);
        }

        if ($montoRecibido < $totalCentavos) {
            throw ValidationException::withMessages([
                'monto_pagado' => ['El monto recibido (Bs '.number_format($montoRecibido / 100, 2).') es menor al total a pagar (Bs '.number_format($totalCentavos / 100, 2).').'],
            ]);
        }

        return $montoRecibido;
    }

    /**
     * Resuelve el nombre que se imprime en la factura.
     */
    private function nombreCliente(Venta $venta, ?string $sugerido): string
    {
        if ($venta->comprador !== null) {
            return $venta->comprador->name ?? $venta->comprador->email ?? 'Cliente';
        }

        $nombre = trim((string) $sugerido);

        return $nombre !== '' ? $nombre : 'Cliente de mostrador';
    }

    /**
     * Anula una venta completada y devuelve el stock al inventario.
     */
    public function anularVenta(User $admin, Venta $venta, string $motivo): void
    {
        DB::transaction(function () use ($admin, $venta, $motivo): void {
            $cambiadas = Venta::query()
                ->whereKey($venta->id)
                ->where('estado', 'COMPLETADA')
                ->update(['estado' => 'ANULADA', 'motivo' => $motivo, 'updated_at' => now()]);

            abort_if($cambiadas !== 1, 409, 'La venta no existe o ya fue anulada.');

            $ventaConItems = Venta::query()->with('items')->findOrFail($venta->id);
            foreach ($ventaConItems->items as $item) {
                Producto::withTrashed()->whereKey($item->producto_id)->increment('stock', $item->cantidad);
            }

            $this->auditar($admin, 'VENTA_ANULADA', [
                'ventaId' => $venta->id,
                'motivo' => $motivo,
            ]);
        }, 3);
    }

    /**
     * Crea un pedido en línea reservando el stock atómicamente y previniendo acaparamiento (DoS de inventario).
     */
    public function crearPedido(User $comprador, array $data): Pedido
    {
        return DB::transaction(function () use ($comprador, $data): Pedido {
            $pendientesActivos = Pedido::query()
                ->where('comprador_id', $comprador->id)
                ->where('estado', 'PENDIENTE')
                ->lockForUpdate()
                ->count();

            if ($pendientesActivos >= 3) {
                throw ValidationException::withMessages([
                    'cantidad' => ['Ya tienes 3 pedidos pendientes por atender. Espera a que se completen o cancelen antes de reservar más stock.'],
                ]);
            }

            $productoId = (int) $data['producto_id'];
            $cantidad = (int) $data['cantidad'];

            $actualizadas = DB::table('productos')
                ->where('id', $productoId)
                ->whereNull('deleted_at')
                ->where('activo', true)
                ->where('stock', '>=', $cantidad)
                ->decrement('stock', $cantidad);

            if ($actualizadas !== 1) {
                $producto = Producto::query()->find($productoId);
                abort_unless($producto && $producto->activo, 404, 'El producto no está disponible.');
                throw ValidationException::withMessages([
                    'cantidad' => ["Stock insuficiente para «{$producto->nombre}» (disponibles: {$producto->stock})."],
                ]);
            }

            $producto = Producto::query()->findOrFail($productoId);
            $total = $producto->precio_centavos * $cantidad;
            if ($total > 2_147_483_647) {
                throw ValidationException::withMessages([
                    'cantidad' => ['El monto del pedido excede el límite permitido.'],
                ]);
            }

            $pedido = Pedido::query()->create([
                'comprador_id' => $comprador->id,
                'total' => $total,
                'estado' => 'PENDIENTE',
                'nota' => $data['nota'] ?? null,
            ]);

            $pedido->items()->create([
                'producto_id' => $producto->id,
                'cantidad' => $cantidad,
                'precio_unit_centavos' => $producto->precio_centavos,
            ]);

            $this->auditar($comprador, 'PEDIDO_CREADO', [
                'pedidoId' => $pedido->id,
                'producto' => $producto->nombre,
                'cantidad' => $cantidad,
                'total' => $total,
            ]);

            return $pedido->load('items.producto');
        }, 3);
    }

    /**
     * Completa un pedido convirtiéndolo en venta o lo cancela devolviendo stock.
     */
    public function procesarEstadoPedido(User $actor, Pedido $pedido, string $estado): void
    {
        DB::transaction(function () use ($actor, $pedido, $estado): void {
            $bloqueado = Pedido::query()
                ->with('items')
                ->whereKey($pedido->id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_if($bloqueado->estado !== 'PENDIENTE', 409, 'Este pedido ya fue procesado anteriormente.');

            if ($estado === 'COMPLETADO') {
                $venta = $actor->ventas()->create([
                    'comprador_id' => $bloqueado->comprador_id,
                    'total' => $bloqueado->total,
                    'estado' => 'COMPLETADA',
                ]);

                foreach ($bloqueado->items as $item) {
                    $venta->items()->create([
                        'producto_id' => $item->producto_id,
                        'cantidad' => $item->cantidad,
                        'precio_unit_centavos' => $item->precio_unit_centavos,
                    ]);
                }

                // Factura de la venta generada desde el pedido
                $venta->forceFill([
                    'numero_factura' => sprintf('FAC-%s-%06d', now()->format('Y'), $venta->id),
                    'metodo_pago' => 'efectivo',
                    'monto_pagado' => $bloqueado->total,
                    'cambio' => 0,
                    'cliente_nombre' => $bloqueado->comprador?->name ?? $bloqueado->comprador?->email ?? 'Cliente de mostrador',
                    'nit_ci' => '0',
                ])->save();

                $bloqueado->update([
                    'estado' => 'COMPLETADO',
                    'atendido_por_id' => $actor->id,
                    'venta_id' => $venta->id,
                ]);

                $this->auditar($actor, 'PEDIDO_COMPLETADO', [
                    'pedidoId' => $bloqueado->id,
                    'ventaId' => $venta->id,
                    'total' => $bloqueado->total,
                ]);
            } else {
                foreach ($bloqueado->items as $item) {
                    Producto::withTrashed()->whereKey($item->producto_id)->increment('stock', $item->cantidad);
                }

                $bloqueado->update([
                    'estado' => 'CANCELADO',
                    'atendido_por_id' => $actor->id,
                ]);

                $this->auditar($actor, 'PEDIDO_CANCELADO', [
                    'pedidoId' => $bloqueado->id,
                ]);
            }
        }, 3);
    }

    /**
     * Actualiza el rol de otro usuario protegiendo al último administrador y revocando su token API.
     */
    public function actualizarRolUsuario(User $admin, User $objetivo, string $nuevoRol): User
    {
        abort_if($admin->is($objetivo), 409, 'No puedes cambiar tu propio rol.');

        DB::transaction(function () use ($admin, $objetivo, $nuevoRol): void {
            $user = User::query()->whereKey($objetivo->id)->lockForUpdate()->firstOrFail();
            $rolAnterior = $user->rol;

            if ($rolAnterior === 'ADMIN' && $nuevoRol !== 'ADMIN') {
                $cambiado = DB::table('users')
                    ->where('id', $user->id)
                    ->where('rol', 'ADMIN')
                    ->whereRaw("(SELECT COUNT(*) FROM users WHERE rol = 'ADMIN') > 1")
                    ->update(['rol' => $nuevoRol, 'api_token' => null, 'updated_at' => now()]);
                abort_if($cambiado !== 1, 409, 'Debe permanecer al menos un administrador.');
            } else {
                $user->forceFill(['rol' => $nuevoRol, 'api_token' => null])->save();
            }

            $this->auditar($admin, 'ROL_ACTUALIZADO', [
                'usuarioId' => $user->id,
                'rolAnterior' => $rolAnterior,
                'rolNuevo' => $nuevoRol,
            ]);
        }, 3);

        return $objetivo->fresh();
    }

    public function auditar(User $usuario, string $accion, array $detalle): void
    {
        $usuario->registros()->create([
            'accion' => $accion,
            'detalle' => json_encode($detalle, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        ]);
    }
}
