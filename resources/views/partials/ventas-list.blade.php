<div class="stack">
    @forelse ($ventas as $venta)
        <article class="card">
            <div class="row">
                <strong>Venta #{{ $venta->id }}</strong>
                <span class="badge {{ $venta->estado === 'ANULADA' ? 'muted' : '' }}">{{ $venta->estado }}</span>
            </div>
            <p>Fecha: {{ $venta->created_at->format('d/m/Y H:i') }} · Total: <strong>Bs. {{ number_format($venta->total / 100, 2) }}</strong></p>
            @if ($venta->numero_factura)
                <p>
                    Factura: <strong>{{ $venta->numero_factura }}</strong>
                    · Pago: <strong>{{ strtoupper($venta->metodo_pago) }}</strong>
                    @if ($venta->cambio > 0) · Cambio: <strong>Bs. {{ number_format($venta->cambio / 100, 2) }}</strong> @endif
                    · Cliente: {{ $venta->cliente_nombre ?? ($venta->comprador->name ?? 'Mostrador') }} (NIT/CI: {{ $venta->nit_ci ?? '0' }})
                </p>
            @endif
            @if ($venta->estado === 'COMPLETADA')
                <a class="button secondary" href="{{ route('ventas.factura', $venta) }}" target="_blank">🧾 Ver factura imprimible</a>
            @endif
            @if (auth()->user()->rol === 'ADMIN')
                <p>Vendedor: {{ $venta->vendedor->name ?? $venta->vendedor->email }} · Comprador: {{ $venta->comprador->name ?? $venta->comprador->email ?? 'Sin asignar' }}</p>
            @endif
            <ul>
                @foreach ($venta->items as $item)
                    <li>{{ $item->cantidad }} × {{ $item->producto->nombre ?? 'Producto' }} (Bs. {{ number_format($item->precio_unit_centavos / 100, 2) }} c/u)</li>
                @endforeach
            </ul>
            @if ($venta->estado === 'ANULADA')
                <p>Motivo: {{ $venta->motivo }}</p>
            @elseif (auth()->user()->rol === 'ADMIN')
                <form method="post" action="{{ route('admin.ventas.anular', $venta) }}" class="inline-form">
                    @csrf
                    @method('PATCH')
                    <label>Motivo de anulación<input name="motivo" required minlength="3" maxlength="500" placeholder="Ej. Devolución solicitada por el cliente"></label>
                    <button class="danger" type="submit">Anular y devolver stock</button>
                </form>
            @endif
        </article>
    @empty
        <p>No hay ventas para mostrar.</p>
    @endforelse
</div>
