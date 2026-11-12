<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\TiendaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminUsuarioController extends Controller
{
    public function index(): View
    {
        return view('admin.usuarios', [
            'usuarios' => User::query()->orderBy('name')->get(),
        ]);
    }

    public function updateRole(Request $request, User $user, TiendaService $tienda): RedirectResponse
    {
        $data = $request->validate([
            'rol' => ['required', 'string', Rule::in(['COMPRADOR', 'VENDEDOR', 'ADMIN'])],
        ]);

        $tienda->actualizarRolUsuario($request->user(), $user, $data['rol']);

        return back()->with('status', 'Rol actualizado.');
    }
}
