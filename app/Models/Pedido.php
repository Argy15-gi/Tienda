<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pedido extends Model
{
    protected $table = 'pedidos';

    protected $fillable = [
        'comprador_id',
        'atendido_por_id',
        'venta_id',
        'total',
        'estado',
        'nota',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'integer',
        ];
    }

    public function comprador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'comprador_id');
    }

    public function atendidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'atendido_por_id');
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ItemPedido::class, 'pedido_id');
    }
}
