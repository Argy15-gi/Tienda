<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductoRequest;
use App\Models\Producto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductoController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Producto::class);

        $filtros = $request->only(['q', 'categoria', 'estado']);

        return view('productos.index', [
            'productos' => Producto::query()->filtrar($filtros)->orderBy('nombre')->get(),
            'categorias' => Producto::query()->select('categoria')->distinct()->orderBy('categoria')->pluck('categoria'),
            'filtros' => $filtros,
            'enPapeleraCount' => Producto::onlyTrashed()->count(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Producto::class);

        return view('productos.create');
    }

    public function store(ProductoRequest $request): RedirectResponse
    {
        Gate::authorize('create', Producto::class);

        $data = $request->validated();
        $data['descripcion'] = $data['descripcion'] ?? '';
        $data['categoria'] = $data['categoria'] ?? 'General';
        $data['activo'] = $request->boolean('activo', true);

        $producto = DB::transaction(function () use ($data, $request): Producto {
            $nuevo = Producto::query()->create($data);
            $this->registrar($request, 'PRODUCTO_CREADO', [
                'productoId' => $nuevo->id,
                'sku' => $nuevo->sku,
                'nombre' => $nuevo->nombre,
            ]);

            return $nuevo;
        });

        return redirect()
            ->route('productos.index')
            ->with('status', "Producto «{$producto->nombre}» ({$producto->sku}) creado correctamente.");
    }

    public function show(Producto $producto): View
    {
        Gate::authorize('view', $producto);
        $producto->load(['items.venta.vendedor:id,name,email', 'items.venta.comprador:id,name,email']);

        return view('productos.show', ['producto' => $producto]);
    }

    public function edit(Producto $producto): View
    {
        Gate::authorize('update', $producto);

        return view('productos.edit', ['producto' => $producto]);
    }

    public function update(ProductoRequest $request, Producto $producto): RedirectResponse
    {
        Gate::authorize('update', $producto);

        $data = $request->validated();
        $data['descripcion'] = $data['descripcion'] ?? '';
        $data['categoria'] = $data['categoria'] ?? $producto->categoria ?? 'General';
        if ($request->has('activo')) {
            $data['activo'] = $request->boolean('activo');
        }

        DB::transaction(function () use ($data, $producto, $request): void {
            $precioAnterior = $producto->precio_centavos;
            $producto->update($data);
            $this->registrar($request, 'PRODUCTO_ACTUALIZADO', [
                'productoId' => $producto->id,
                'sku' => $producto->sku,
                'precioAnterior' => $precioAnterior,
                'precioNuevo' => $producto->precio_centavos,
            ]);
        });

        return redirect()
            ->route('productos.show', $producto)
            ->with('status', 'Producto actualizado correctamente.');
    }

    public function destroy(Request $request, Producto $producto): RedirectResponse
    {
        Gate::authorize('delete', $producto);

        DB::transaction(function () use ($producto, $request): void {
            $producto->update(['activo' => false]);
            $producto->delete();
            $this->registrar($request, 'PRODUCTO_EN_PAPELERA', [
                'productoId' => $producto->id,
                'sku' => $producto->sku,
                'nombre' => $producto->nombre,
            ]);
        });

        return redirect()
            ->route('productos.index')
            ->with('status', "El producto «{$producto->nombre}» se envió a la papelera.");
    }

    public function trash(): View
    {
        Gate::authorize('create', Producto::class);

        return view('productos.trash', [
            'productos' => Producto::onlyTrashed()->withCount('items')->orderByDesc('deleted_at')->get(),
        ]);
    }

    public function restore(Request $request, int $id): RedirectResponse
    {
        $producto = Producto::onlyTrashed()->findOrFail($id);
        Gate::authorize('restore', $producto);

        DB::transaction(function () use ($producto, $request): void {
            $producto->restore();
            $producto->update(['activo' => true]);
            $this->registrar($request, 'PRODUCTO_RESTAURADO', [
                'productoId' => $producto->id,
                'sku' => $producto->sku,
                'nombre' => $producto->nombre,
            ]);
        });

        return redirect()
            ->route('productos.trash')
            ->with('status', "Producto «{$producto->nombre}» restaurado y activado.");
    }

    public function forceDestroy(Request $request, int $id): RedirectResponse
    {
        $producto = Producto::withTrashed()->withCount(['items', 'itemsPedido'])->findOrFail($id);
        Gate::authorize('forceDelete', $producto);

        if ($producto->items_count > 0 || $producto->items_pedido_count > 0) {
            return back()->withErrors([
                'papelera' => "No se puede eliminar definitivamente «{$producto->nombre}» porque tiene ventas o pedidos históricos asociados.",
            ]);
        }

        $nombre = $producto->nombre;
        $sku = $producto->sku;

        DB::transaction(function () use ($producto, $request, $sku, $nombre): void {
            $idEliminado = $producto->id;
            $producto->forceDelete();
            $this->registrar($request, 'PRODUCTO_ELIMINADO_DEFINITIVO', [
                'productoId' => $idEliminado,
                'sku' => $sku,
                'nombre' => $nombre,
            ]);
        });

        return redirect()
            ->route('productos.trash')
            ->with('status', "Producto «{$nombre}» eliminado definitivamente.");
    }

    public function toggleActivo(Request $request, Producto $producto): RedirectResponse
    {
        Gate::authorize('update', $producto);

        $nuevoEstado = ! $producto->activo;
        DB::transaction(function () use ($producto, $nuevoEstado, $request): void {
            $producto->update(['activo' => $nuevoEstado]);
            $this->registrar($request, $nuevoEstado ? 'PRODUCTO_ACTIVADO' : 'PRODUCTO_DESACTIVADO', [
                'productoId' => $producto->id,
                'sku' => $producto->sku,
            ]);
        });

        return back()->with('status', $nuevoEstado ? 'Producto activado en catálogo.' : 'Producto ocultado del catálogo.');
    }

    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('viewAny', Producto::class);

        $productos = Producto::query()->filtrar($request->only(['q', 'categoria', 'estado']))->orderBy('sku')->get();
        $rows = $productos->map(fn (Producto $p) => [
            $p->id,
            $p->sku,
            $p->nombre,
            $p->categoria,
            number_format($p->precio_centavos / 100, 2, '.', ''),
            $p->precio_centavos,
            $p->stock,
            $p->activo ? 'Activo' : 'Inactivo',
            optional($p->created_at)->format('Y-m-d H:i'),
        ]);

        return $this->streamCsv(
            'productos-tiendita-'.now()->format('Y-m-d').'.csv',
            ['ID', 'SKU', 'Nombre', 'Categoría', 'Precio ($)', 'Precio (centavos)', 'Stock', 'Estado', 'Fecha Alta'],
            $rows,
        );
    }
}
