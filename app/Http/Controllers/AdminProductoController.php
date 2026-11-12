<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductoRequest;
use App\Models\Producto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminProductoController extends ProductoController
{
    public function store(ProductoRequest $request): RedirectResponse
    {
        parent::store($request);

        return back()->with('status', 'Producto creado.');
    }

    public function update(ProductoRequest $request, Producto $producto): RedirectResponse
    {
        parent::update($request, $producto);

        return back()->with('status', 'Producto actualizado.');
    }

    public function destroy(Request $request, Producto $producto): RedirectResponse
    {
        abort_unless($producto->activo, 409, 'El producto ya está desactivado.');

        DB::transaction(function () use ($producto, $request): void {
            $producto->update(['activo' => false]);
            $this->registrar($request, 'PRODUCTO_DESACTIVADO', [
                'productoId' => $producto->id,
                'sku' => $producto->sku,
            ]);
        });

        return back()->with('status', 'Producto desactivado.');
    }
}
