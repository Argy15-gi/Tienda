@extends('layouts.app')

@section('title', 'Mis compras y pedidos | La Tiendita')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Área del comprador</p>
            <h1>Mis compras y pedidos</h1>
            <p>Consulta el estado de tus pedidos en línea y el historial de tus compras completadas.</p>
        </div>
        <div class="actions-row">
            <a class="button" href="{{ route('catalogo') }}">Explorar catálogo</a>
            <a class="button secondary" href="{{ route('ventas.export') }}">Exportar mis compras (CSV)</a>
        </div>
    </div>

    @if (isset($pedidos) && $pedidos->isNotEmpty())
        <h2>Mis pedidos en línea</h2>
        <div class="stack">
            @foreach ($pedidos as $pedido)
                <article class="card">
                    <div class="row">
                        <strong>Pedido #{{ $pedido->id }}</strong>
                        <span class="badge {{ $pedido->estado === 'PENDIENTE' ? 'warning' : ($pedido->estado === 'COMPLETADO' ? '' : 'muted') }}">
                            {{ $pedido->estado }}
                        </span>
                    </div>
                    <p>Fecha: {{ $pedido->created_at->format('d/m/Y H:i') }} · Total: Bs. {{ number_format($pedido->total / 100, 2) }}</p>
                    @if ($pedido->nota)
                        <p><small>Nota: {{ $pedido->nota }}</small></p>
                    @endif
                    <ul>
                        @foreach ($pedido->items as $item)
                            <li>{{ $item->cantidad }} × {{ $item->producto->nombre ?? 'Producto' }} (Bs. {{ number_format($item->precio_unit_centavos / 100, 2) }} c/u)</li>
                        @endforeach
                    </ul>
                </article>
            @endforeach
        </div>
    @endif

    <h2 style="margin-top: 1.5rem;">Historial de compras</h2>
    @include('partials.ventas-list')
@endsection
