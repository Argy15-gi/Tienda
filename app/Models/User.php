<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

// Defensa contra Mass-Assignment Privilege Escalation:
// 'rol', 'api_token', 'google_id' y 'email_verified_at' NO son asignables en masa.
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token', 'api_token', 'google_id'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function esAdmin(): bool
    {
        return $this->rol === 'ADMIN';
    }

    public function esVendedor(): bool
    {
        return $this->rol === 'VENDEDOR';
    }

    public function esComprador(): bool
    {
        return $this->rol === 'COMPRADOR';
    }

    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class, 'vendedor_id');
    }

    public function compras(): HasMany
    {
        return $this->hasMany(Venta::class, 'comprador_id');
    }

    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class, 'comprador_id');
    }

    public function registros(): HasMany
    {
        return $this->hasMany(Registro::class, 'usuario_id');
    }
}
