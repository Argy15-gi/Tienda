<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemPedido extends Model
{
    public $timestamps = false;

    protected $table = 'items_pedido';

    protected $fillable = [
        'pedido_id',
        'producto_id',
        'cantidad',
        'precio_unit_centavos',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'integer',
            'precio_unit_centavos' => 'integer',
        ];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'pedido_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id')->withTrashed();
    }
}
