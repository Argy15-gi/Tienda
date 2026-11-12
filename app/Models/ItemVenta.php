<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemVenta extends Model
{
    public $timestamps = false;

    protected $table = 'items_venta';

    protected $fillable = [
        'venta_id',
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

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id')->withTrashed();
    }
}
