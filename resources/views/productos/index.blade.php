@extends('layouts.app')

@section('title', 'Productos (CRUD) | La Tiendita')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">Recurso principal · CRUD completo</p>
            <h1>Gestión de productos</h1>
            <p>Consulta, busca, filtra, exporta y administra el inventario de productos.</p>
        </div>
        <div class="actions-row">
            <a class="button secondary" href="{{ route('productos.export', $filtros) }}">
                Exportar hoja de cálculo (CSV)
            </a>
            @can('create', \App\Models\Producto::class)
                <a class="button secondary" href="{{ route('productos.trash') }}">
                    Papelera ({{ $enPapeleraCount }})
                </a>
                <a class="button" href="{{ route('productos.create') }}">
                    + Nuevo producto
                </a>
            @endcan
        </div>
    </div>

    <section class="card">
        <form method="get" action="{{ route('productos.index') }}" class="form-grid">
            <label>
                Buscar (nombre, SKU o descripción)
                <input name="q" value="{{ $filtros['q'] ?? '' }}" placeholder="Ej. Café, CAF-001...">
            </label>

            <label>
                Categoría
                <select name="categoria">
                    <option value="">Todas las categorías</option>
                    @foreach ($categorias as $cat)
                        <option value="{{ $cat }}" @selected(($filtros['categoria'] ?? '') === $cat)>{{ $cat }}</option>
                    @endforeach
                </select>
            </label>

            <label>
                Estado / Stock
                <select name="estado">
                    <option value="">Todos</option>
                    <option value="activos" @selected(($filtros['estado'] ?? '') === 'activos')>Solo activos</option>
                    <option value="inactivos" @selected(($filtros['estado'] ?? '') === 'inactivos')>Ocultos / inactivos</option>
                    <option value="bajo_stock" @selected(($filtros['estado'] ?? '') === 'bajo_stock')>Stock bajo (1 a 5)</option>
                    <option value="agotados" @selected(($filtros['estado'] ?? '') === 'agotados')>Agotados (0)</option>
                </select>
            </label>

            <div class="actions-row">
                <button type="submit">Filtrar</button>
                @if (!empty($filtros['q']) || !empty($filtros['categoria']) || !empty($filtros['estado']))
                    <a class="button secondary" href="{{ route('productos.index') }}">Limpiar filtros</a>
                @endif
            </div>
        </form>
    </section>

    <section class="card">
        <div class="row" style="margin-bottom: 0.8rem;">
            <strong>{{ $productos->count() }} {{ $productos->count() === 1 ? 'producto encontrado' : 'productos encontrados' }}</strong>
            <small>Haz clic en «Ver» para abrir la ficha detallada de cualquier producto.</small>
        </div>

        @if ($productos->isEmpty())
            <p>No se encontraron productos con los criterios seleccionados.</p>
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
                            <th>Estado</th>
                            <th>Acciones del CRUD</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($productos as $producto)
                            <tr>
                                <td><code>{{ $producto->sku }}</code></td>
                                <td>
                                    <strong>{{ $producto->nombre }}</strong>
                                    @if ($producto->descripcion)
                                        <div style="font-size: 0.83rem; color: #64748b;">{{ \Illuminate\Support\Str::limit($producto->descripcion, 60) }}</div>
                                    @endif
                                </td>
                                <td><span class="badge info">{{ $producto->categoria }}</span></td>
                                <td><strong>${{ number_format($producto->precio_centavos / 100, 2) }}</strong></td>
                                <td>
                                    <span class="badge {{ $producto->stock === 0 ? 'danger-badge' : ($producto->stock <= 5 ? 'warning' : '') }}">
                                        {{ $producto->stock }} uds.
                                    </span>
                                </td>
                                <td>
                                    <span class="badge {{ $producto->activo ? '' : 'muted' }}">
                                        {{ $producto->activo ? 'Activo' : 'Inactivo' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="actions-row">
                                        <a class="button secondary" style="padding: 0.35rem 0.65rem; font-size: 0.84rem;" href="{{ route('productos.show', $producto) }}">
                                            Ver detalle
                                        </a>
                                        @can('update', $producto)
                                            <a class="button secondary" style="padding: 0.35rem 0.65rem; font-size: 0.84rem;" href="{{ route('productos.edit', $producto) }}">
                                                Editar
                                            </a>
                                        @endcan
                                        @can('delete', $producto)
                                            <form method="post" action="{{ route('productos.destroy', $producto) }}" onsubmit="return confirm('¿Enviar «{{ $producto->nombre }}» a la papelera?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="danger" style="padding: 0.35rem 0.65rem; font-size: 0.84rem;">
                                                    Borrar
                                                </button>
                                            </form>
                                        @endcan
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
