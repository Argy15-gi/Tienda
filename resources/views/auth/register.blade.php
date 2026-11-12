@extends('layouts.app')

@section('title', 'Crear cuenta | La Tiendita')

@section('content')
    <section class="card narrow">
        <p class="eyebrow">Registro de usuario</p>
        <h1 style="margin-top: 0;">Crear cuenta</h1>
        <p>Tu contraseña se almacena hasheada con <code>bcrypt</code> en la tabla <code>users</code>. Las cuentas nuevas inician con el rol <strong>COMPRADOR</strong>.</p>

        <form method="post" action="{{ route('register.store') }}" class="stack">
            @csrf
            <label>
                Nombre completo
                <input
                    name="name"
                    type="text"
                    value="{{ old('name') }}"
                    placeholder="Ana Pérez"
                    required
                    minlength="2"
                    maxlength="100"
                    autofocus
                >
                @error('name')
                    <span class="field-error">{{ $message }}</span>
                @enderror
            </label>

            <label>
                Correo electrónico
                <input
                    name="email"
                    type="email"
                    value="{{ old('email') }}"
                    placeholder="ana@correo.com"
                    required
                    maxlength="160"
                >
                @error('email')
                    <span class="field-error">{{ $message }}</span>
                @enderror
            </label>

            <label>
                Contraseña (mínimo 8 caracteres)
                <input
                    name="password"
                    type="password"
                    placeholder="Mínimo 8 caracteres"
                    required
                    minlength="8"
                    maxlength="100"
                >
                @error('password')
                    <span class="field-error">{{ $message }}</span>
                @enderror
            </label>

            <label>
                Confirmar contraseña
                <input
                    name="password_confirmation"
                    type="password"
                    placeholder="Repite tu contraseña"
                    required
                    minlength="8"
                    maxlength="100"
                >
            </label>

            <button type="submit">Registrarme y entrar</button>
        </form>

        <hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 1.25rem 0;">

        <p style="margin: 0;">
            ¿Ya tienes una cuenta? <a href="{{ route('login') }}"><strong>Inicia sesión aquí</strong></a>
        </p>
    </section>
@endsection
