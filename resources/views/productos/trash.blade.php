@extends('layouts.app')

@section('title', 'Papelera de productos | La Tiendita')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Extra · Papelera con SoftDeletes (deleted_at)</p>
            <h1>Papelera de productos</h1>
            <p>Un producto borrado no se pierde: el administrador puede restaurarlo al catálogo o eliminarlo definitivamente si no tiene ventas.</p>
        </div>
        <a class="button secondary" href="{{ route('productos.index') }}">← Volver a productos activos</a>
    </div>

    <section class="card">
        @if ($productos->isEmpty())
            <p>La papelera está vacía. Todos los productos están activos o en el listado principal.</p>
        @else
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>SKU</th>
                            <th>Nombre</th>
                            <th>Categoría</th>
                            <th>Precio</th>
                            <th>Stock</th>
                            <th>Fecha de borrado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($productos as $producto)
                            <tr>
                                <td><code>{{ $producto->sku }}</code></td>
                                <td>
                                    <strong>{{ $producto->nombre }}</strong>
                                    <div style="font-size: 0.8rem; color: #64748b;">
                                        {{ $producto->items_count }} ventas históricas asociadas
                                    </div>
                                </td>
                                <td>{{ $producto->categoria }}</td>
                                <td>${{ number_format($producto->precio_centavos / 100, 2) }}</td>
                                <td>{{ $producto->stock }} uds.</td>
                                <td>{{ optional($producto->deleted_at)->format('d/m/Y H:i') }}</td>
                                <td>
                                    <div class="actions-row">
                                        <form method="post" action="{{ route('productos.restore', $producto->id) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="success" style="padding: 0.35rem 0.65rem; font-size: 0.84rem;">
                                                Restaurar
                                            </button>
                                        </form>

                                        @if ($producto->items_count === 0)
                                            <form method="post" action="{{ route('productos.forceDestroy', $producto->id) }}" onsubmit="return confirm('¿Eliminar definitivamente «{{ $producto->nombre }}» de la base de datos? Esta acción no se puede deshacer.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="danger" style="padding: 0.35rem 0.65rem; font-size: 0.84rem;">
                                                    Borrar definitivo
                                                </button>
                                            </form>
                                        @else
                                            <span class="badge muted" title="Tiene ventas históricas registradas">Con historial</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
