@extends('layouts.app')

@section('title', 'Nuevo producto | La Tiendita')

@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">CRUD de Productos · Alta (create / store)</p>
            <h1>Registrar producto nuevo</h1>
            <p>Los datos son validados en el backend mediante <code>ProductoRequest</code> antes de guardarse.</p>
        </div>
        <a class="button secondary" href="{{ route('productos.index') }}">← Volver a la lista</a>
    </div>

    <section class="card">
        <form method="post" action="{{ route('productos.store') }}" class="stack">
            @csrf
            <div class="form-grid">
                <label>
                    Código SKU *
                    <input name="sku" value="{{ old('sku') }}" placeholder="Ej. CAF-002" required maxlength="64">
                    @error('sku')
                        <span class="field-error">{{ $message }}</span>
                    @enderror
                </label>

                <label>
                    Nombre del producto *
                    <input name="nombre" value="{{ old('nombre') }}" placeholder="Ej. Café Espresso Molido" required maxlength="160">
                    @error('nombre')
                        <span class="field-error">{{ $message }}</span>
                    @enderror
                </label>

                <label>
                    Categoría *
                    <input name="categoria" value="{{ old('categoria', 'Bebidas') }}" placeholder="Bebidas, Hogar, Papelería..." required maxlength="80">
                    @error('categoria')
                        <span class="field-error">{{ $message }}</span>
                    @enderror
                </label>
            </div>

            <div class="form-grid">
                <label>
                    Precio en centavos * (ej. 18900 = $189.00)
                    <input name="precio_centavos" type="number" min="1" max="100000000" value="{{ old('precio_centavos') }}" placeholder="18900" required>
                    @error('precio_centavos')
                        <span class="field-error">{{ $message }}</span>
                    @enderror
                </label>

                <label>
                    Stock disponible *
                    <input name="stock" type="number" min="0" max="1000000" value="{{ old('stock', 10) }}" required>
                    @error('stock')
                        <span class="field-error">{{ $message }}</span>
                    @enderror
                </label>

                <label>
                    Visible en catálogo público
                    <select name="activo">
                        <option value="1" @selected(old('activo', '1') == '1')>Sí, activo</option>
                        <option value="0" @selected(old('activo') == '0')>No, oculto</option>
                    </select>
                </label>
            </div>

            <label>
                Descripción (opcional)
                <textarea name="descripcion" maxlength="1000" placeholder="Describe las características principales del producto...">{{ old('descripcion') }}</textarea>
                @error('descripcion')
                    <span class="field-error">{{ $message }}</span>
                @enderror
            </label>

            <div class="actions-row">
                <button type="submit">Guardar producto</button>
                <a class="button secondary" href="{{ route('productos.index') }}">Cancelar</a>
            </div>
        </form>
    </section>
@endsection
