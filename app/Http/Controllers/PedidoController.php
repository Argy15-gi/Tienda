<?php

namespace App\Http\Controllers;

use App\Http\Requests\PedidoRequest;
use App\Models\Pedido;
use App\Services\TiendaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PedidoController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $pedidos = Pedido::query()
            ->when($user->esComprador(), fn ($q) => $q->where('comprador_id', $user->id))
            ->with(['comprador:id,name,email', 'atendidoPor:id,name,email', 'items.producto'])
            ->latest()
            ->get();

        return view('pedidos.index', ['pedidos' => $pedidos]);
    }

    public function store(PedidoRequest $request, TiendaService $tienda): RedirectResponse
    {
        $pedido = $tienda->crearPedido($request->user(), $request->validated());

        return redirect()
            ->route('comprador.compras.index')
            ->with('status', "Pedido #{$pedido->id} registrado y stock reservado. Un vendedor lo atenderá pronto.");
    }

    public function updateEstado(Request $request, Pedido $pedido, TiendaService $tienda): RedirectResponse
    {
        $data = $request->validate([
            'estado' => ['required', 'string', Rule::in(['COMPLETADO', 'CANCELADO'])],
        ]);

        $tienda->procesarEstadoPedido($request->user(), $pedido, $data['estado']);

        $mensaje = $data['estado'] === 'COMPLETADO'
            ? "Pedido #{$pedido->id} completado y convertido en venta."
            : "Pedido #{$pedido->id} cancelado y stock devuelto al inventario.";

        return back()->with('status', $mensaje);
    }
}
