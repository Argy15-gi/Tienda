@extends('layouts.app')

@section('title', 'Editar ' . $producto->nombre . ' | La Tiendita')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">CRUD de Productos · Edición (edit / update)</p>
            <h1>Editar producto: {{ $producto->nombre }}</h1>
            <p>Los cambios de precio quedan auditados y no alteran el precio histórico de ventas anteriores.</p>
        </div>
        <div class="actions-row">
            <a class="button secondary" href="{{ route('productos.show', $producto) }}">← Ver detalle</a>
            <a class="button secondary" href="{{ route('productos.index') }}">Volver a la lista</a>
        </div>
    </div>

    <section class="card">
        <form method="post" action="{{ route('productos.update', $producto) }}" class="stack">
            @csrf
            @method('PUT')

            <div class="form-grid">
                <label>
                    Código SKU *
                    <input name="sku" value="{{ old('sku', $producto->sku) }}" required maxlength="64">
                    @error('sku')
                        <span class="field-error">{{ $message }}</span>
                    @enderror
                </label>

                <label>
                    Nombre del producto *
                    <input name="nombre" value="{{ old('nombre', $producto->nombre) }}" required maxlength="160">
                    @error('nombre')
                        <span class="field-error">{{ $message }}</span>
                    @enderror
                </label>

                <label>
                    Categoría *
                    <input name="categoria" value="{{ old('categoria', $producto->categoria) }}" required maxlength="80">
                    @error('categoria')
                        <span class="field-error">{{ $message }}</span>
                    @enderror
                </label>
            </div>

            <div class="form-grid">
                <label>
                    Precio en centavos * (actual: ${{ number_format($producto->precio_centavos / 100, 2) }})
                    <input name="precio_centavos" type="number" min="1" max="100000000" value="{{ old('precio_centavos', $producto->precio_centavos) }}" required>
                    @error('precio_centavos')
                        <span class="field-error">{{ $message }}</span>
                    @enderror
                </label>

                <label>
                    Stock disponible *
                    <input name="stock" type="number" min="0" max="1000000" value="{{ old('stock', $producto->stock) }}" required>
                    @error('stock')
                        <span class="field-error">{{ $message }}</span>
                    @enderror
                </label>

                <label>
                    Visible en catálogo público
                    <select name="activo">
                        <option value="1" @selected(old('activo', $producto->activo ? '1' : '0') == '1')>Sí, activo</option>
                        <option value="0" @selected(old('activo', $producto->activo ? '1' : '0') == '0')>No, oculto</option>
                    </select>
                </label>
            </div>

            <label>
                Descripción
                <textarea name="descripcion" maxlength="1000">{{ old('descripcion', $producto->descripcion) }}</textarea>
                @error('descripcion')
                    <span class="field-error">{{ $message }}</span>
                @enderror
            </label>

            <div class="actions-row">
                <button type="submit">Guardar cambios</button>
                <a class="button secondary" href="{{ route('productos.show', $producto) }}">Cancelar</a>
            </div>
        </form>
    </section>
@endsection
