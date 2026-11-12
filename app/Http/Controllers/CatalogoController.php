<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CatalogoController extends Controller
{
    public function index(Request $request): View
    {
        $filtros = $request->only(['q', 'categoria']);
        $productos = Producto::query()
            ->where('activo', true)
            ->filtrar($filtros)
            ->orderBy('nombre')
            ->get();

        $categorias = Producto::query()
            ->where('activo', true)
            ->select('categoria')
            ->distinct()
            ->orderBy('categoria')
            ->pluck('categoria');

        return view('catalogo', [
            'productos' => $productos,
            'categorias' => $categorias,
            'filtros' => $filtros,
        ]);
    }
}
