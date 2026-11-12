@extends('layouts.app')

@section('title', 'Panel de inicio | La Tiendita')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Panel general · Rol {{ auth()->user()->rol }}</p>
            <h1>Hola, {{ auth()->user()->name ?? auth()->user()->email }}</h1>
            <p>Resumen en tiempo real de productos, ventas, pedidos y avisos del sistema.</p>
        </div>
        <div class="actions-row">
            @if (auth()->user()->esAdmin())
                <a class="button" href="{{ route('productos.create') }}">+ Nuevo producto</a>
                <a class="button secondary" href="{{ route('productos.export') }}">Exportar CSV</a>
                <a class="button secondary" href="{{ route('productos.trash') }}">Papelera ({{ $metricas['productos_en_papelera'] }})</a>
            @elseif (auth()->user()->esVendedor())
                <a class="button" href="{{ route('vendedor.ventas.index') }}">Registrar venta</a>
                <a class="button secondary" href="{{ route('pedidos.index') }}">Atender pedidos ({{ $metricas['pedidos_pendientes'] }})</a>
            @else
                <a class="button" href="{{ route('catalogo') }}">Ver catálogo</a>
                <a class="button secondary" href="{{ route('comprador.compras.index') }}">Mis compras y pedidos</a>
            @endif
        </div>
    </div>

    <section class="metrics-grid">
        <div class="metric-card">
            <div class="metric-label">Productos activos</div>
            <div class="metric-value">{{ $metricas['productos_activos'] }}</div>
            <div class="metric-sub">{{ $metricas['productos_nuevos_mes'] }} dados de alta este mes</div>
        </div>

        @if (!auth()->user()->esComprador())
            <div class="metric-card">
                <div class="metric-label">Alertas de stock</div>
                <div class="metric-value">{{ $metricas['productos_bajo_stock'] }}</div>
                <div class="metric-sub">
                    <a href="{{ route('productos.index', ['estado' => 'bajo_stock']) }}">Ver productos con 5 unidades o menos</a>
                </div>
            </div>
        @endif

        <div class="metric-card">
            <div class="metric-label">{{ auth()->user()->esComprador() ? 'Mis compras del mes' : 'Ventas del mes' }}</div>
            <div class="metric-value">{{ $metricas['ventas_mes_count'] }}</div>
            <div class="metric-sub">Total: ${{ number_format($metricas['ingresos_mes_centavos'] / 100, 2) }}</div>
        </div>

        <div class="metric-card">
            <div class="metric-label">Pedidos pendientes</div>
            <div class="metric-value">{{ $metricas['pedidos_pendientes'] }}</div>
            <div class="metric-sub">
                @if (auth()->user()->esAdmin())
                    {{ $metricas['productos_en_papelera'] }} productos en papelera · {{ $metricas['usuarios_totales'] }} usuarios
                @else
                    Reservas en línea listas para atender
                @endif
            </div>
        </div>
    </section>

    <div class="two-col">
        <section class="card">
            @if (!auth()->user()->esComprador())
                <div class="row" style="margin-bottom: 0.75rem;">
                    <h2 style="margin: 0;">Alertas de inventario (stock bajo)</h2>
                    <a href="{{ route('productos.index') }}">Ver todos</a>
                </div>
                @if ($productosAlerta->isEmpty())
                    <p>Todos los productos activos tienen buen nivel de stock.</p>
                @else
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>SKU</th>
                                    <th>Producto</th>
                                    <th>Stock</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($productosAlerta as $prod)
                                    <tr>
                                        <td><code>{{ $prod->sku }}</code></td>
                                        <td>{{ $prod->nombre }}</td>
                                        <td>
                                            <span class="badge {{ $prod->stock === 0 ? 'danger-badge' : 'warning' }}">
                                                {{ $prod->stock }} uds.
                                            </span>
                                        </td>
                                        <td>
                                            <a href="{{ route('productos.show', $prod) }}">Ver detalle</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @endif

            <h2 style="margin: {{ auth()->user()->esComprador() ? '0' : '1.5rem' }} 0 0.75rem;">{{ auth()->user()->esComprador() ? 'Mis últimas compras' : 'Últimas ventas' }}</h2>
            @if ($ultimasVentas->isEmpty())
                <p>Todavía no hay ventas registradas.</p>
            @else
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Folio</th>
                                <th>Fecha</th>
                                <th>Total</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($ultimasVentas as $venta)
                                <tr>
                                    <td><strong>#{{ $venta->id }}</strong></td>
                                    <td>{{ $venta->created_at->format('d/m/Y H:i') }}</td>
                                    <td>${{ number_format($venta->total / 100, 2) }}</td>
                                    <td>
                                        <span class="badge {{ $venta->estado === 'ANULADA' ? 'muted' : '' }}">
                                            {{ $venta->estado }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="card">
            <div class="row" style="margin-bottom: 0.75rem;">
                <h2 style="margin: 0;">Avisos recientes del sistema</h2>
                <small>Bitácora de auditoría</small>
            </div>
            @if ($avisos->isEmpty())
                <p>No hay avisos recientes registrados.</p>
            @else
                <div class="stack">
                    @foreach ($avisos as $aviso)
                        <div style="border-bottom: 1px solid #e2e8f0; padding-bottom: 0.65rem;">
                            <div class="row">
                                <span class="badge info">{{ $aviso->accion }}</span>
                                <small>{{ $aviso->created_at->format('d/m/Y H:i') }}</small>
                            </div>
                            <div style="margin-top: 0.3rem; font-size: 0.9rem;">
                                <strong>{{ $aviso->usuario->name ?? $aviso->usuario->email ?? 'Sistema' }}</strong>
                                <span style="color: #475467;">— {{ $aviso->detalle }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
@endsection
