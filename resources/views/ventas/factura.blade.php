<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Factura {{ $venta->numero_factura ?? '#'.$venta->id }} — {{ $tienda['nombre'] }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: "Courier New", ui-monospace, monospace;
            background: #f1f5f9; color: #0f172a;
            display: flex; align-items: center; justify-content: center;
            min-height: 100vh; padding: 1.5rem;
        }
        .factura {
            background: #fff; border-radius: 14px; padding: 1.4rem;
            width: 100%; max-width: 430px;
            box-shadow: 0 24px 60px rgba(2, 6, 23, 0.2);
        }
        .head { text-align: center; border-bottom: 2px dashed #94a3b8; padding-bottom: 0.6rem; margin-bottom: 0.7rem; }
        .head h1 { font-size: 1.2rem; letter-spacing: 0.04em; margin-bottom: 0.25rem; }
        .head small { display: block; color: #475569; }
        .meta { display: grid; gap: 0.2rem; font-size: 0.84rem; margin-bottom: 0.7rem; }
        table { width: 100%; border-collapse: collapse; font-size: 0.8rem; margin-bottom: 0.7rem; }
        th { text-align: left; border-bottom: 1px solid #cbd5e1; padding: 0.25rem 0.15rem; }
        td { border-bottom: 1px dotted #e2e8f0; padding: 0.3rem 0.15rem; }
        th:nth-child(3), td:nth-child(3), th:nth-child(4), td:nth-child(4) { text-align: right; }
        .totales { border-top: 2px dashed #94a3b8; padding-top: 0.55rem; display: grid; gap: 0.2rem; font-size: 0.85rem; }
        .totales div { display: flex; justify-content: space-between; }
        .anulada { color: #b91c1c; font-weight: 700; text-align: center; }
        .foot { text-align: center; color: #64748b; margin-top: 0.6rem; font-size: 0.75rem; }
        .acciones { display: flex; gap: 0.5rem; margin-top: 0.9rem; }
        .acciones button { flex: 1; padding: 0.65rem; border: none; border-radius: 10px; font-weight: 700; cursor: pointer; }
        .btn-print { background: #047857; color: #fff; }
        .btn-back { background: #e2e8f0; color: #0f172a; }
        @media print {
            body { background: #fff; padding: 0; }
            .factura { box-shadow: none; max-width: 100%; border-radius: 0; }
            .acciones { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="factura">
        <div class="head">
            <h1>{{ $tienda['nombre'] }}</h1>
            <small>{{ $tienda['direccion'] }}</small>
            <small>Tel: {{ $tienda['telefono'] }} · NIT: {{ $tienda['nit'] }}</small>
        </div>

        <div class="meta">
            <div><strong>Factura:</strong> {{ $venta->numero_factura ?? ('FAC-#'.$venta->id) }}</div>
            <div><strong>Fecha:</strong> {{ $venta->created_at?->format('d/m/Y H:i') }}</div>
            <div><strong>Vendedor:</strong> {{ $venta->vendedor->name ?? $venta->vendedor->email ?? '' }}</div>
            <div><strong>Cliente:</strong> {{ $venta->cliente_nombre ?? ($venta->comprador->name ?? 'Mostrador') }} · <strong>NIT/CI:</strong> {{ $venta->nit_ci ?? '0' }}</div>
            <div><strong>Método de pago:</strong> {{ strtoupper($venta->metodo_pago ?? 'efectivo') }}</div>
        </div>

        <table>
            <thead>
                <tr><th>Cant</th><th>Detalle</th><th>P. Unit</th><th>Subtotal</th></tr>
            </thead>
            <tbody>
                @foreach ($venta->items as $item)
                    <tr>
                        <td>{{ $item->cantidad }}</td>
                        <td>{{ $item->producto->nombre ?? 'Producto' }}</td>
                        <td>Bs. {{ number_format($item->precio_unit_centavos / 100, 2) }}</td>
                        <td>Bs. {{ number_format(($item->cantidad * $item->precio_unit_centavos) / 100, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="totales">
            <div><span>TOTAL PAGADO</span><strong>Bs. {{ number_format($venta->total / 100, 2) }}</strong></div>
            <div><span>Monto recibido</span><span>Bs. {{ number_format(($venta->monto_pagado ?? $venta->total) / 100, 2) }}</span></div>
            <div><span>Cambio</span><span>Bs. {{ number_format(($venta->cambio ?? 0) / 100, 2) }}</span></div>
        </div>

        @if ($venta->estado === 'ANULADA')
            <p class="anulada">⚠ VENTA ANULADA</p>
        @endif

        <small class="foot">¡Gracias por su compra! · Documento generado por {{ $tienda['nombre'] }}</small>

        <div class="acciones">
            <button class="btn-print" onclick="window.print()">🖨️ Imprimir comprobante</button>
            <a class="btn-back" style="display:flex;align-items:center;justify-content:center;text-decoration:none;" href="{{ url()->previous() }}">Volver</a>
        </div>
    </div>
</body>
</html>
