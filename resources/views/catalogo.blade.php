@extends('layouts.app')

@section('title', 'Catálogo | La Tiendita')

@section('content')
    @php
        $iconos = ['Bebidas' => '☕', 'Hogar' => '🏺', 'Papelería' => '📓', 'Alimentos' => '🍫', 'Accesorios' => '🎒'];
        $totalUnidades = $productos->sum('stock');
    @endphp

    <section class="hero-banner">
        <div class="hero-content">
            <span class="hero-kicker">✨ Selección Artesanal · Inventario en Vivo</span>
            <h1>Catálogo de productos</h1>
            <p>Productos disponibles en inventario en tiempo real gestionados desde el motor de Laravel.</p>
            <div class="actions-row" style="margin-top: 1.1rem;">
                @auth
                    @if (auth()->user()->esAdmin())
                        <a class="button success" href="{{ route('productos.index') }}">Gestionar CRUD de Productos</a>
                        <a class="button secondary" href="{{ route('productos.create') }}">+ Nuevo producto</a>
                    @elseif (auth()->user()->esVendedor())
                        <a class="button success" href="{{ route('vendedor.ventas.index') }}">Ir a Punto de venta</a>
                        <a class="button secondary" href="{{ route('productos.index') }}">Ver inventario</a>
                    @else
                        <a class="button success" href="{{ route('panel') }}">Ir a mi Panel</a>
                        <a class="button secondary" href="{{ route('comprador.compras.index') }}">🛍️ Mis compras y pedidos</a>
                    @endif
                @else
                    <a class="button success" href="{{ route('login') }}">🔑 Iniciar sesión</a>
                    <a class="button secondary" href="{{ route('register') }}">Crear cuenta</a>
                @endauth
            </div>
        </div>
        <div class="hero-stats">
            <div class="hero-stat"><strong>{{ $productos->count() }}</strong><span>Productos</span></div>
            <div class="hero-stat"><strong>{{ $categorias->count() }}</strong><span>Categorías</span></div>
            <div class="hero-stat"><strong>{{ $totalUnidades }}</strong><span>Unidades listas</span></div>
        </div>
    </section>

    <section class="card">
        <form method="get" action="{{ route('catalogo') }}" class="form-grid">
            <label>
                🔍 Buscar por nombre, SKU o descripción
                <input name="q" value="{{ $filtros['q'] ?? '' }}" placeholder="Ej. Café, TAZ-001, libreta...">
            </label>
            <label>
                🏷️ Categoría
                <select name="categoria">
                    <option value="">Todas las categorías</option>
                    @foreach ($categorias as $cat)
                        <option value="{{ $cat }}" @selected(($filtros['categoria'] ?? '') === $cat)>{{ $cat }}</option>
                    @endforeach
                </select>
            </label>
            <div class="actions-row">
                <button type="submit">Buscar</button>
                @if (!empty($filtros['q']) || !empty($filtros['categoria']))
                    <a class="button secondary" href="{{ route('catalogo') }}">Limpiar</a>
                @endif
            </div>
        </form>
    </section>

    <div class="grid">
        @forelse ($productos as $producto)
            @php
                $icono = $iconos[$producto->categoria] ?? '📦';
                $pct = min(100, round(($producto->stock / 30) * 100));
                $fillClass = $producto->stock === 0 ? 'empty' : ($producto->stock <= 5 ? 'low' : '');
            @endphp
            <article class="card product-card stack">
                <div>
                    <div class="row" style="margin-bottom: 0.65rem;">
                        <span class="cat-icon">{{ $icono }}</span>
                        <div style="text-align: right;">
                            <span class="badge info">{{ $producto->categoria }}</span>
                            <div class="eyebrow" style="margin-top: 0.25rem;">{{ $producto->sku }}</div>
                        </div>
                    </div>
                    <h2 style="margin: 0.2rem 0 0.4rem; font-size: 1.22rem;">{{ $producto->nombre }}</h2>
                    <p style="color: #475569; margin: 0; font-size: 0.92rem;">{{ $producto->descripcion ?: 'Sin descripción adicional.' }}</p>
                </div>
                <div>
                    <div class="stock-bar"><div class="stock-fill {{ $fillClass }}" style="width: {{ $pct }}%"></div></div>
                    <div class="row" style="margin-top: 0.5rem;">
                        <span class="price" style="margin: 0;">${{ number_format($producto->precio_centavos / 100, 2) }}</span>
                        <span class="badge {{ $producto->stock === 0 ? 'danger-badge' : ($producto->stock <= 5 ? 'warning' : '') }}">
                            {{ $producto->stock }} disponibles
                        </span>
                    </div>
                    <div style="margin-top: 0.85rem;">
                        @auth
                            <a class="button" style="width: 100%;" href="{{ route('productos.show', $producto) }}">Ver detalle</a>
                        @else
                            <a class="button secondary" style="width: 100%;" href="{{ route('login') }}">Inicia sesión para pedir</a>
                        @endauth
                    </div>
                </div>
            </article>
        @empty
            <p>No hay productos disponibles por el momento.</p>
        @endforelse
    </div>
@endsection
