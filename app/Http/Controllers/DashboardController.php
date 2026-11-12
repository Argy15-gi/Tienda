<?php

namespace App\Http\Controllers;

use App\Services\TiendaService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request, TiendaService $tienda): View
    {
        return view('panel', $tienda->obtenerDatosPanel($request->user()));
    }
}
