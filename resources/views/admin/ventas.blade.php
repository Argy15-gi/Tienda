@extends('layouts.app')

@section('title', 'Todas las ventas | La Tiendita')

@section('content')
    @php
        $hoy = now()->toDateString();
        $activas = $ventas->where('estado', 'ACTIVA');
        $ventasHoy = $activas->filter(fn($v) => optional($v->created_at)->toDateString() === $hoy);
        $porVendedor = $activas->groupBy('vendedor_id');
    @endphp
    <div class="page-header">
        <div>
            <p class="eyebrow">Administración · Auditoría Anti-Malversación</p>
            <h1>Todas las ventas y arqueo de caja</h1>
            <p>Supervisa en tiempo real cada cobro registrado desde móviles en tienda física, audita el efectivo por vendedor y anula operaciones con devolución automática de stock.</p>
        </div>
        <div class="actions-row">
            <a class="button secondary" href="{{ route('ventas.export') }}">Exportar hoja de cálculo (CSV)</a>
            <a class="button" href="{{ route('vendedor.ventas.index') }}">Ir al Punto de Venta</a>
        </div>
    </div>

    <div class="metrics-grid" style="margin-bottom: 1.25rem;">
        <article class="card metric-card">
            <span class="eyebrow">Recaudado hoy (Tienda)</span>
            <strong style="font-size: 1.5rem; color: var(--brand);">Bs. {{ number_format($ventasHoy->sum('total') / 100, 2) }}</strong>
            <small class="muted">{{ $ventasHoy->count() }} venta(s) activas hoy</small>
        </article>
        <article class="card metric-card">
            <span class="eyebrow">Recaudación activa total</span>
            <strong style="font-size: 1.5rem;">Bs. {{ number_format($activas->sum('total') / 100, 2) }}</strong>
            <small class="muted">{{ $activas->count() }} venta(s) activas</small>
        </article>
        <article class="card metric-card">
            <span class="eyebrow">Ventas anuladas (Auditadas)</span>
            <strong style="font-size: 1.5rem;">{{ $ventas->where('estado', 'ANULADA')->count() }}</strong>
            <small class="muted">Solo el ADMIN puede anular con motivo</small>
        </article>
    </div>

    @if ($porVendedor->isNotEmpty())
        <section class="card" style="margin-bottom: 1.25rem;">
            <h2 style="margin-top: 0;">Arqueo de caja en tiempo real por vendedor</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Vendedor</th>
                            <th>Ventas hoy</th>
                            <th>Efectivo a entregar hoy</th>
                            <th>Ventas activas históricas</th>
                            <th>Total histórico cobrado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($porVendedor as $vendedorId => $grupo)
                            @php
                                $vend = optional($grupo->first())->vendedor;
                                $hoyGrupo = $grupo->filter(fn($v) => optional($v->created_at)->toDateString() === $hoy);
                            @endphp
                            <tr>
                                <td><strong>{{ $vend->name ?? 'Vendedor #'.$vendedorId }}</strong><br><small class="muted">{{ $vend->email ?? '' }}</small></td>
                                <td>{{ $hoyGrupo->count() }}</td>
                                <td><strong style="color: var(--brand);">Bs. {{ number_format($hoyGrupo->sum('total') / 100, 2) }}</strong></td>
                                <td>{{ $grupo->count() }}</td>
                                <td>Bs. {{ number_format($grupo->sum('total') / 100, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    @include('partials.ventas-list')
@endsection
