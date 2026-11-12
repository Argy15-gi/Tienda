<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'La Tiendita')</title>
    <link rel="stylesheet" href="{{ asset('app.css') }}">
</head>
<body>
    <header class="site-header">
        <div class="brand-group">
            <a class="brand" href="{{ route('catalogo') }}">
                <span class="brand-logo">🛍️</span>
                La Tiendita
            </a>
            @auth
                <span class="badge info">{{ auth()->user()->rol }}</span>
            @endauth
        </div>
        <nav>
            <a href="{{ route('catalogo') }}" class="{{ request()->routeIs('catalogo') ? 'active' : '' }}">🏪 Catálogo</a>
            @auth
                <a href="{{ route('panel') }}" class="{{ request()->routeIs('panel') ? 'active' : '' }}">📊 Panel</a>

                @if (auth()->user()->rol === 'ADMIN')
                    <a href="{{ route('productos.index') }}" class="{{ request()->routeIs('productos.*') ? 'active' : '' }}">📦 Productos (CRUD)</a>
                    <a href="{{ route('vendedor.ventas.index') }}" class="{{ request()->routeIs('vendedor.*') ? 'active' : '' }}">💳 Punto de venta</a>
                    <a href="{{ route('pedidos.index') }}" class="{{ request()->routeIs('pedidos.*') ? 'active' : '' }}">🛍️ Pedidos</a>
                    <a href="{{ route('admin.ventas.index') }}" class="{{ request()->routeIs('admin.ventas.*') ? 'active' : '' }}">🧾 Ventas</a>
                    <a href="{{ route('admin.usuarios.index') }}" class="{{ request()->routeIs('admin.usuarios.*') ? 'active' : '' }}">👥 Usuarios</a>
                @elseif (auth()->user()->rol === 'VENDEDOR')
                    <a href="{{ route('productos.index') }}" class="{{ request()->routeIs('productos.*') ? 'active' : '' }}">📦 Inventario</a>
                    <a href="{{ route('vendedor.ventas.index') }}" class="{{ request()->routeIs('vendedor.*') ? 'active' : '' }}">💳 Punto de venta</a>
                    <a href="{{ route('pedidos.index') }}" class="{{ request()->routeIs('pedidos.*') ? 'active' : '' }}">🛍️ Pedidos</a>
                @else
                    <a href="{{ route('comprador.compras.index') }}" class="{{ request()->routeIs('comprador.*') ? 'active' : '' }}">🛍️ Mis compras</a>
                @endif

                <span class="user-pill">
                    👤 <strong>{{ auth()->user()->name ?? auth()->user()->email }}</strong>
                </span>
                <form method="post" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="link-button">Salir</button>
                </form>
            @else
                <a href="{{ route('login') }}" class="{{ request()->routeIs('login') ? 'active' : '' }}">🔑 Iniciar sesión</a>
                <a href="{{ route('register') }}" class="button" style="color: #fff; padding: 0.42rem 0.85rem;">✨ Crear cuenta</a>
            @endauth
        </nav>
    </header>
    <main class="container">
        @if (session('status'))
            <p class="notice success">{{ session('status') }}</p>
        @endif
        @if ($errors->any())
            <div class="notice error">
                <strong>Revisa estos datos antes de continuar:</strong>
                <ul style="margin: 0.4rem 0 0; padding-left: 1.25rem;">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        @yield('content')
    </main>
    <footer class="site-footer">
        <strong>La Tiendita</strong> · Backend en Laravel 13 (MVC + REST API) · Seguridad auditada
    </footer>
</body>
</html>
