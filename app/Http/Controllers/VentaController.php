<?php

namespace App\Http\Controllers;

use App\Http\Requests\VentaRequest;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use App\Services\TiendaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VentaController extends Controller
{
    public function adminIndex(): View
    {
        return view('admin.ventas', [
            'ventas' => Venta::query()
                ->with(['vendedor:id,name,email', 'comprador:id,name,email', 'items.producto'])
                ->latest()
                ->get(),
        ]);
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $ventas = Venta::query()
            ->when($user->esVendedor(), fn ($q) => $q->where('vendedor_id', $user->id))
            ->when($user->esComprador(), fn ($q) => $q->where('comprador_id', $user->id))
            ->with(['vendedor:id,name,email', 'comprador:id,name,email', 'items.producto'])
            ->latest()
            ->get();

        $pedidos = Pedido::query()
            ->when($user->esComprador(), fn ($q) => $q->where('comprador_id', $user->id))
            ->with(['comprador:id,name,email', 'atendidoPor:id,name,email', 'items.producto'])
            ->latest()
            ->get();

        return view($user->esComprador() ? 'comprador.compras' : 'vendedor.ventas', [
            'ventas' => $ventas,
            'pedidos' => $pedidos,
            'productos' => Producto::query()->where('activo', true)->orderBy('nombre')->get(),
            'compradores' => User::query()->where('rol', 'COMPRADOR')->orderBy('name')->get(),
        ]);
    }

    public function store(VentaRequest $request, TiendaService $tienda): RedirectResponse
    {
        $tienda->registrarVenta($request->user(), $request->validated());

        return back()->with('status', 'Venta registrada.');
    }

    public function cancel(Request $request, Venta $venta, TiendaService $tienda): RedirectResponse
    {
        $data = $request->validate([
            'motivo' => ['required', 'string', 'min:3', 'max:500'],
        ], [
            'motivo.required' => 'Debes indicar el motivo de la anulación.',
            'motivo.min' => 'El motivo debe tener al menos 3 caracteres.',
        ]);

        $tienda->anularVenta($request->user(), $venta, $data['motivo']);

        return back()->with('status', 'Venta anulada y stock devuelto.');
    }

    /**
     * Factura imprimible de una venta (web/Blade).
     * Visible para ADMIN, el vendedor que la registró o el comprador que la pagó.
     */
    public function factura(Request $request, Venta $venta)
    {
        $user = $request->user();

        $autorizado = $user->esAdmin()
            || $venta->vendedor_id === $user->id
            || ($venta->comprador_id !== null && $venta->comprador_id === $user->id);

        abort_unless($autorizado, 403, 'No tienes permiso para ver esta factura.');

        return view('ventas.factura', [
            'venta' => $venta->load(['vendedor:id,name,email', 'comprador:id,name,email', 'items.producto']),
            'tienda' => config('services.tienda'),
        ]);
    }

    public function export(Request $request): StreamedResponse
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
            $v->vendedor->name ?? $v->vendedor->email ?? '',
            $v->comprador->name ?? $v->comprador->email ?? 'Mostrador',
            number_format($v->total / 100, 2, '.', ''),
            $v->items->map(fn ($i) => "{$i->cantidad}x ".($i->producto->nombre ?? 'Producto'))->implode('; '),
            $v->numero_factura ?? '',
            strtoupper($v->metodo_pago ?? 'EFECTIVO'),
            number_format(($v->monto_pagado ?? $v->total) / 100, 2, '.', ''),
            number_format(($v->cambio ?? 0) / 100, 2, '.', ''),
            $v->motivo ?? '',
        ]);

        return $this->streamCsv(
            'ventas-tiendita-'.now()->format('Y-m-d').'.csv',
            ['ID Venta', 'Fecha', 'Estado', 'Vendedor', 'Comprador', 'Total (Bs.)', 'Artículos', 'Factura', 'Método de Pago', 'Monto Recibido (Bs.)', 'Cambio (Bs.)', 'Motivo Anulación'],
            $rows,
        );
    }
}
