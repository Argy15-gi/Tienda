@extends('layouts.app')

@section('title', 'Administrar usuarios | La Tiendita')

@section('content')
    <h1>Administrar usuarios</h1>
    <div class="stack">
        @forelse ($usuarios as $usuario)
            <article class="card row">
                <div>
                    <strong>{{ $usuario->name ?? 'Sin nombre' }}</strong>
                    <div>{{ $usuario->email }}</div>
                    <small>Rol actual: {{ $usuario->rol }}</small>
                </div>
                @if (!auth()->user()->is($usuario))
                    <form method="post" action="{{ route('admin.usuarios.rol', $usuario) }}" class="inline-form">
                        @csrf
                        @method('PATCH')
                        <select name="rol" aria-label="Rol para {{ $usuario->email }}">
                            @foreach (['COMPRADOR', 'VENDEDOR', 'ADMIN'] as $rol)
                                <option value="{{ $rol }}" @selected($usuario->rol === $rol)>{{ $rol }}</option>
                            @endforeach
                        </select>
                        <button type="submit">Cambiar rol</button>
                    </form>
                @else
                    <span>Tu rol no se puede cambiar desde esta cuenta.</span>
                @endif
            </article>
        @empty
            <p>No hay usuarios registrados.</p>
        @endforelse
    </div>
@endsection
