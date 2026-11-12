@extends('layouts.app')

@section('title', $producto->nombre . ' | Detalle de producto')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">CRUD de Productos · Detalle (show)</p>
            <h1>{{ $producto->nombre }}</h1>
            <p>Código SKU: <code>{{ $producto->sku }}</code> · Categoría: <strong>{{ $producto->categoria }}</strong></p>
        </div>
        <div class="actions-row">
            <a class="button secondary" href="{{ auth()->user()->esComprador() ? route('catalogo') : route('productos.index') }}">← Volver</a>
            @can('update', $producto)
                <a class="button" href="{{ route('productos.edit', $producto) }}">Editar producto</a>
                <form method="post" action="{{ route('productos.toggle', $producto) }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="secondary">
                        {{ $producto->activo ? 'Ocultar del catálogo' : 'Activar en catálogo' }}
                    </button>
                </form>
            @endcan
            @can('delete', $producto)
                <form method="post" action="{{ route('productos.destroy', $producto) }}" onsubmit="return confirm('¿Enviar este producto a la papelera?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="danger">Enviar a papelera</button>
                </form>
            @endcan
        </div>
    </div>

    <div class="two-col">
        <section class="card">
            <h2 style="margin-top: 0;">Información del producto</h2>
            <p>{{ $producto->descripcion ?: 'Este producto no tiene descripción registrada.' }}</p>

            <div class="metrics-grid" style="margin-top: 1rem; margin-bottom: 0;">
                <div class="metric-card">
                    <div class="metric-label">Precio unitario</div>
                    <div class="metric-value">${{ number_format($producto->precio_centavos / 100, 2) }}</div>
                    <div class="metric-sub">{{ $producto->precio_centavos }} centavos</div>
                </div>

                <div class="metric-card">
                    <div class="metric-label">Stock en inventario</div>
                    <div class="metric-value">{{ $producto->stock }}</div>
                    <div class="metric-sub">
                        <span class="badge {{ $producto->stock === 0 ? 'danger-badge' : ($producto->stock <= 5 ? 'warning' : '') }}">
                            {{ $producto->stock === 0 ? 'Agotado' : ($producto->stock <= 5 ? 'Stock bajo' : 'Disponible') }}
                        </span>
                    </div>
                </div>
            </div>

            <div style="margin-top: 1rem; color: #475467; font-size: 0.9rem;">
                <p style="margin: 0.25rem 0;">Estado en catálogo: <strong>{{ $producto->activo ? 'Activo (visible)' : 'Inactivo (oculto)' }}</strong></p>
                <p style="margin: 0.25rem 0;">Fecha de alta: {{ optional($producto->created_at)->format('d/m/Y H:i') }}</p>
                <p style="margin: 0.25rem 0;">Última actualización: {{ optional($producto->updated_at)->format('d/m/Y H:i') }}</p>
            </div>
        </section>

        <section class="card">
            @if ($producto->activo && $producto->stock > 0)
                <h2 style="margin-top: 0;">Solicitar pedido en línea</h2>
                <p>Al confirmar, el sistema reserva las unidades en una transacción y genera un pedido pendiente para el vendedor.</p>
                <form method="post" action="{{ route('pedidos.store') }}" class="stack">
                    @csrf
                    <input type="hidden" name="producto_id" value="{{ $producto->id }}">
                    <label>
                        Cantidad a solicitar (máx. {{ $producto->stock }})
                        <input name="cantidad" type="number" min="1" max="{{ $producto->stock }}" value="{{ old('cantidad', 1) }}" required>
                        @error('cantidad')
                            <span class="field-error">{{ $message }}</span>
                        @enderror
                    </label>
                    <label>
                        Nota o indicación (opcional)
                        <input name="nota" value="{{ old('nota') }}" maxlength="500" placeholder="Ej. Pasaré a recogerlo a las 5 pm">
                    </label>
                    <button type="submit" class="success">Confirmar pedido (${{ number_format($producto->precio_centavos / 100, 2) }} c/u)</button>
                </form>
            @else
                <h2 style="margin-top: 0;">Disponibilidad para pedidos</h2>
                <p>Este producto no admite pedidos en línea en este momento porque está agotado o inactivo.</p>
            @endif

            @if (auth()->user()->esAdmin() || auth()->user()->esVendedor())
                <hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 1.25rem 0;">
                <h3 style="margin-top: 0;">Historial de ventas de este producto ({{ $producto->items->count() }})</h3>
                @if ($producto->items->isEmpty())
                    <p>Aún no se han registrado ventas para este producto.</p>
                @else
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Venta</th>
                                    <th>Cantidad</th>
                                    <th>Precio histórico</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($producto->items as $item)
                                    <tr>
                                        <td>#{{ $item->venta_id }}</td>
                                        <td>{{ $item->cantidad }} uds.</td>
                                        <td>${{ number_format($item->precio_unit_centavos / 100, 2) }}</td>
                                        <td>
                                            <span class="badge {{ optional($item->venta)->estado === 'ANULADA' ? 'muted' : '' }}">
                                                {{ optional($item->venta)->estado ?? 'COMPLETADA' }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @endif
        </section>
    </div>
@endsection
