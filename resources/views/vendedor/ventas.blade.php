@extends('layouts.app')

@section('title', 'Punto de venta | La Tiendita')

@section('content')
    @php
        $hoy = now()->toDateString();
        $activas = $ventas->where('estado', 'ACTIVA');
        $ventasHoy = $activas->filter(fn($v) => optional($v->created_at)->toDateString() === $hoy);
        $recaudadoHoy = $ventasHoy->sum('total');
        $recaudadoTotal = $activas->sum('total');
    @endphp
    <div class="page-header">
        <div>
            <p class="eyebrow">Terminal Móvil · Tienda Física en Tiempo Real</p>
            <h1>Punto de venta</h1>
            <p>Registra ventas presenciales desde tu móvil o mostrador. El precio en <strong>Bs.</strong> está bloqueado en el servidor y cada cobro queda auditado en tiempo real contra malversación de fondos.</p>
        </div>
        <div class="actions-row">
            <a class="button secondary" href="{{ route('vendedor.ventas.index') }}">Sincronizar stock</a>
            <a class="button secondary" href="{{ route('pedidos.index') }}">Ver pedidos en línea</a>
            <a class="button secondary" href="{{ route('ventas.export') }}">Exportar ventas (CSV)</a>
        </div>
    </div>

    <div class="metrics-grid" style="margin-bottom: 1.25rem;">
        <article class="card metric-card">
            <span class="eyebrow">Efectivo a rendir hoy</span>
            <strong style="font-size: 1.5rem; color: var(--brand);">Bs. {{ number_format($recaudadoHoy / 100, 2) }}</strong>
            <small class="muted">{{ $ventasHoy->count() }} venta(s) hoy · {{ auth()->user()->name }}</small>
        </article>
        <article class="card metric-card">
            <span class="eyebrow">Total histórico activo</span>
            <strong style="font-size: 1.5rem;">Bs. {{ number_format($recaudadoTotal / 100, 2) }}</strong>
            <small class="muted">{{ $activas->count() }} venta(s) activas registradas</small>
        </article>
        <article class="card metric-card">
            <span class="eyebrow">Control Anti-Malversación</span>
            <strong style="font-size: 1.1rem; color: #166534;">Precios y Caja Bloqueados</strong>
            <small class="muted">Sin edición de precios ni borrado por vendedor</small>
        </article>
    </div>

    <section class="card" style="margin-bottom: 1.25rem;">
        <div class="row" style="margin-bottom: 0.75rem;">
            <h2 style="margin: 0;">Toque rápido desde móvil (Tienda Física)</h2>
            <span class="badge">Stock en vivo</span>
        </div>
        <p class="muted" style="margin-top: 0;">Toca un producto en tu celular para agregarlo automáticamente al ticket de venta inferior:</p>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(175px, 1fr)); gap: 0.65rem; margin-bottom: 1rem;">
            @foreach ($productos as $producto)
                <button type="button"
                    class="button secondary"
                    style="text-align: left; display: flex; flex-direction: column; align-items: flex-start; gap: 0.25rem; padding: 0.75rem; height: auto;"
                    @if($producto->stock <= 0) disabled @endif
                    onclick="agregarDesdeMovil({{ $producto->id }})">
                    <span class="eyebrow" style="font-size: 0.7rem;">{{ $producto->sku }} · Stock: {{ $producto->stock }}</span>
                    <strong style="font-size: 0.92rem;">{{ $producto->nombre }}</strong>
                    <span style="color: var(--brand); font-weight: 700;">Bs. {{ number_format($producto->precio_centavos / 100, 2) }}</span>
                </button>
            @endforeach
        </div>

        <h2 style="margin-top: 0.5rem;">Registrar venta</h2>
        <form method="post" action="{{ route('vendedor.ventas.store') }}" class="stack" onsubmit="const button = this.querySelector('button[type=submit]'); if (button.disabled) return false; button.disabled = true; button.textContent = 'Registrando...';">
            @csrf
            <label>Comprador (opcional)
                <select name="comprador_id">
                    <option value="">Venta en mostrador (Tienda Física sin cuenta)</option>
                    @foreach ($compradores as $comprador)
                        <option value="{{ $comprador->id }}">{{ $comprador->name ?? $comprador->email }} ({{ $comprador->email }})</option>
                    @endforeach
                </select>
            </label>
            <div class="form-grid">
                <label>Cliente de mostrador (nombre para la factura)
                    <input name="cliente_nombre" maxlength="100" placeholder="Ej.: Juan Pérez — opcional">
                </label>
                <label>NIT / CI del cliente
                    <input name="nit_ci" maxlength="20" placeholder="0 (consumidor final)">
                </label>
            </div>
            <div class="form-grid">
                <label>Método de pago
                    <select name="metodo_pago" onchange="document.getElementById('campo-monto').style.display = this.value === 'efectivo' ? '' : 'none';">
                        <option value="efectivo">Efectivo (con cambio)</option>
                        <option value="qr">QR (pago exacto)</option>
                        <option value="transferencia">Transferencia (pago exacto)</option>
                    </select>
                </label>
                <label id="campo-monto">Efectivo recibido (Bs)
                    <input name="monto_pagado" type="number" min="0" step="0.01" placeholder="Vacío = pago exacto del total">
                </label>
            </div>
            @for ($i = 0; $i < 5; $i++)
                <div class="form-grid sale-line">
                    <label>Producto #{{ $i + 1 }}
                        <select name="items[{{ $i }}][producto_id]" class="pos-select-line">
                            <option value="">Selecciona un producto</option>
                            @foreach ($productos as $producto)
                                <option value="{{ $producto->id }}">{{ $producto->sku }} — {{ $producto->nombre }} (Bs. {{ number_format($producto->precio_centavos / 100, 2) }}; stock {{ $producto->stock }})</option>
                            @endforeach
                        </select>
                    </label>
                    <label>Cantidad<input name="items[{{ $i }}][cantidad]" class="pos-qty-line" type="number" min="1" max="1000000" value="1"></label>
                </div>
            @endfor
            <div>
                <button type="submit">Registrar venta</button>
            </div>
        </form>
    </section>

    <script>
        function agregarDesdeMovil(productoId) {
            const selects = document.querySelectorAll('.pos-select-line');
            const qtys = document.querySelectorAll('.pos-qty-line');
            const idStr = String(productoId);
            for (let i = 0; i < selects.length; i++) {
                if (selects[i].value === idStr) {
                    qtys[i].value = parseInt(qtys[i].value || '1', 10) + 1;
                    qtys[i].focus();
                    return;
                }
            }
            for (let i = 0; i < selects.length; i++) {
                if (!selects[i].value) {
                    selects[i].value = idStr;
                    qtys[i].value = 1;
                    return;
                }
            }
        }
    </script>

    <h2>Mis ventas (Folio Inalterable)</h2>
    @include('partials.ventas-list')
@endsection
