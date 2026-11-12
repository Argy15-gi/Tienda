@extends('layouts.app')

@section('title', 'Pedidos en línea | La Tiendita')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Atención a clientes</p>
            <h1>Pedidos en línea</h1>
            <p>Los pedidos pendientes ya reservaron stock. Al completarlos se convierten en una venta; al cancelarlos devuelven el stock al inventario.</p>
        </div>
        <a class="button secondary" href="{{ route('vendedor.ventas.index') }}">Ir al Punto de Venta</a>
    </div>

    <div class="stack">
        @forelse ($pedidos as $pedido)
            <article class="card">
                <div class="row">
                    <div>
                        <strong>Pedido #{{ $pedido->id }}</strong>
                        <span class="badge {{ $pedido->estado === 'PENDIENTE' ? 'warning' : ($pedido->estado === 'COMPLETADO' ? '' : 'muted') }}">
                            {{ $pedido->estado }}
                        </span>
                    </div>
                    <small>{{ $pedido->created_at->format('d/m/Y H:i') }}</small>
                </div>

                <p style="margin: 0.45rem 0;">
                    Comprador: <strong>{{ $pedido->comprador->name ?? $pedido->comprador->email }}</strong>
                    · Total: <strong>Bs. {{ number_format($pedido->total / 100, 2) }}</strong>
                    @if ($pedido->venta_id)
                        · Venta generada: <strong>#{{ $pedido->venta_id }}</strong>
                    @endif
                </p>

                @if ($pedido->nota)
                    <p style="margin: 0.25rem 0; color: #475467;">Nota del cliente: «{{ $pedido->nota }}»</p>
                @endif

                <ul>
                    @foreach ($pedido->items as $item)
                        <li>
                            {{ $item->cantidad }} × {{ $item->producto->nombre ?? 'Producto' }}
                            (Bs. {{ number_format($item->precio_unit_centavos / 100, 2) }} c/u)
                        </li>
                    @endforeach
                </ul>

                @if ($pedido->estado === 'PENDIENTE' && (auth()->user()->esAdmin() || auth()->user()->esVendedor()))
                    <div class="actions-row" style="margin-top: 0.75rem;">
                        <form method="post" action="{{ route('pedidos.estado', $pedido) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="estado" value="COMPLETADO">
                            <button type="submit" class="success">Completar y registrar venta</button>
                        </form>

                        <form method="post" action="{{ route('pedidos.estado', $pedido) }}" onsubmit="return confirm('¿Cancelar este pedido y devolver el stock al inventario?');">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="estado" value="CANCELADO">
                            <button type="submit" class="danger">Cancelar y devolver stock</button>
                        </form>
                    </div>
                @endif
            </article>
        @empty
            <section class="card">
                <p>No hay pedidos registrados por el momento.</p>
            </section>
        @endforelse
    </div>
@endsection
