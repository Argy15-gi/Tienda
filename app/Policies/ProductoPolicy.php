<?php

namespace App\Policies;

use App\Models\Producto;
use App\Models\User;

class ProductoPolicy
{
    /**
     * Solo ADMIN y VENDEDOR pueden acceder al listado de gestión/inventario (CRUD).
     * Los compradores (usuarios registrados) solo consultan el catálogo público.
     */
    public function viewAny(User $user): bool
    {
        return $user->esAdmin() || $user->esVendedor();
    }

    /**
     * Un comprador solo puede ver la ficha de un producto si está activo.
     */
    public function view(User $user, Producto $producto): bool
    {
        return $producto->activo || $user->esAdmin() || $user->esVendedor();
    }

    /**
     * Exclusivamente el ADMIN puede crear, editar, activar/ocultar, enviar a la papelera,
     * restaurar o borrar definitivamente productos.
     */
    public function create(User $user): bool
    {
        return $user->esAdmin();
    }

    public function update(User $user, Producto $producto): bool
    {
        return $user->esAdmin();
    }

    public function delete(User $user, Producto $producto): bool
    {
        return $user->esAdmin();
    }

    public function restore(User $user, Producto $producto): bool
    {
        return $user->esAdmin();
    }

    public function forceDelete(User $user, Producto $producto): bool
    {
        return $user->esAdmin();
    }
}
