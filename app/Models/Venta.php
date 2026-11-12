<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Venta extends Model
{
    protected $table = 'ventas';

    protected $fillable = [
        'vendedor_id',
        'comprador_id',
        'total',
        'estado',
        'motivo',
        'numero_factura',
        'metodo_pago',
        'monto_pagado',
        'cambio',
        'cliente_nombre',
        'nit_ci',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'integer',
            'monto_pagado' => 'integer',
            'cambio' => 'integer',
        ];
    }

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendedor_id');
    }

    public function comprador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'comprador_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ItemVenta::class, 'venta_id');
    }
}
