@extends('layouts.app')

@section('title', 'Administrar productos | La Tiendita')

@section('content')
    <h1>Administrar productos</h1>
    <section class="card">
        <h2>Crear producto</h2>
        <form method="post" action="{{ route('admin.productos.store') }}" class="form-grid">
            @csrf
            <label>SKU<input name="sku" value="{{ old('sku') }}" required maxlength="64"></label>
            <label>Nombre<input name="nombre" value="{{ old('nombre') }}" required maxlength="160"></label>
            <label>Precio (centavos)<input name="precio_centavos" type="number" min="0" max="100000000" required></label>
            <label>Stock<input name="stock" type="number" min="0" max="1000000" required></label>
            <label class="wide">Descripción<textarea name="descripcion" maxlength="1000">{{ old('descripcion') }}</textarea></label>
            <button type="submit">Crear producto</button>
        </form>
    </section>

    <h2>Inventario</h2>
    <div class="stack">
        @forelse ($productos as $producto)
            <article class="card">
                <div class="row"><strong>{{ $producto->sku }}</strong><span>{{ $producto->activo ? 'Activo' : 'Desactivado' }}</span></div>
                <form method="post" action="{{ route('admin.productos.update', $producto) }}" class="form-grid">
                    @csrf
                    @method('PUT')
                    <label>SKU<input name="sku" value="{{ $producto->sku }}" required maxlength="64"></label>
                    <label>Nombre<input name="nombre" value="{{ $producto->nombre }}" required maxlength="160"></label>
                    <label>Precio (centavos)<input name="precio_centavos" type="number" min="0" max="100000000" value="{{ $producto->precio_centavos }}" required></label>
                    <label>Stock<input name="stock" type="number" min="0" max="1000000" value="{{ $producto->stock }}" required></label>
                    <label class="wide">Descripción<textarea name="descripcion" maxlength="1000">{{ $producto->descripcion }}</textarea></label>
                    <button type="submit">Guardar cambios</button>
                </form>
                @if ($producto->activo)
                    <form method="post" action="{{ route('admin.productos.destroy', $producto) }}" class="inline-form">
                        @csrf
                        @method('DELETE')
                        <button class="danger" type="submit">Desactivar</button>
                    </form>
                @endif
            </article>
        @empty
            <p>Aún no hay productos.</p>
        @endforelse
    </div>
@endsection
