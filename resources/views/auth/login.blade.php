@extends('layouts.app')

@section('title', 'Iniciar sesión | La Tiendita')

@section('content')
    <section class="card narrow">
        <p class="eyebrow">🔑 Autenticación Segura</p>
        <h1 style="margin-top: 0;">Iniciar sesión</h1>
        <p>Ingresa con tu correo electrónico y contraseña validada en el servidor con <code>bcrypt</code>.</p>

        <form method="post" action="{{ route('login.store') }}" class="stack" id="login-form">
            @csrf
            <label>
                Correo electrónico
                <input
                    id="login-email"
                    name="email"
                    type="email"
                    value="{{ old('email') }}"
                    placeholder="ejemplo@tiendita.test"
                    required
                    autofocus
                >
                @error('email')
                    <span class="field-error">{{ $message }}</span>
                @enderror
            </label>

            <label>
                Contraseña
                <input
                    id="login-password"
                    name="password"
                    type="password"
                    placeholder="••••••••"
                    required
                >
                @error('password')
                    <span class="field-error">{{ $message }}</span>
                @enderror
            </label>

            <label style="display: flex; align-items: center; gap: 0.5rem; font-weight: 500;">
                <input type="checkbox" name="remember" value="1" style="width: auto;">
                Mantener mi sesión iniciada
            </label>

            <button type="submit">Entrar a la tienda →</button>
        </form>

        @if (config('services.demo.enabled'))
            <div class="demo-box">
                <strong>⚡ Perfiles de demostración (autenticación en servidor sin exponer claves en el cliente):</strong>
                <div class="demo-buttons">
                    <form method="post" action="{{ route('login.demo') }}">
                        @csrf
                        <input type="hidden" name="rol" value="ADMIN">
                        <button type="submit" class="secondary">👑 Admin</button>
                    </form>
                    <form method="post" action="{{ route('login.demo') }}">
                        @csrf
                        <input type="hidden" name="rol" value="VENDEDOR">
                        <button type="submit" class="secondary">💼 Vendedor</button>
                    </form>
                    <form method="post" action="{{ route('login.demo') }}">
                        @csrf
                        <input type="hidden" name="rol" value="COMPRADOR">
                        <button type="submit" class="secondary">🛍️ Comprador</button>
                    </form>
                </div>
            </div>
        @endif

        <hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 1.25rem 0;">

        <div class="row">
            <span>¿Aún no tienes cuenta? <a href="{{ route('register') }}"><strong>Regístrate aquí</strong></a></span>
            <a class="button secondary" href="{{ route('google.login') }}">Continuar con Google</a>
        </div>
    </section>
@endsection
