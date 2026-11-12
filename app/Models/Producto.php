<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Producto extends Model
{
    use SoftDeletes;

    protected $table = 'productos';

    protected $fillable = [
        'sku',
        'nombre',
        'categoria',
        'descripcion',
        'precio_centavos',
        'stock',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'precio_centavos' => 'integer',
            'stock' => 'integer',
            'activo' => 'boolean',
        ];
    }

    /**
     * Scope de búsqueda y filtrado con escape de comodines LIKE para prevenir DoS por comodines.
     */
    public function scopeFiltrar(Builder $query, array $filtros): Builder
    {
        $buscar = mb_substr(trim((string) ($filtros['q'] ?? '')), 0, 100);
        $categoria = mb_substr(trim((string) ($filtros['categoria'] ?? '')), 0, 80);
        $estado = trim((string) ($filtros['estado'] ?? ''));

        $query->when($buscar !== '', function (Builder $subQuery) use ($buscar): void {
            $escapado = addcslashes($buscar, '%_\\');
            $termino = '%'.$escapado.'%';
            $subQuery->where(function (Builder $inner) use ($termino): void {
                $inner->whereRaw("nombre LIKE ? ESCAPE '\\'", [$termino])
                    ->orWhereRaw("sku LIKE ? ESCAPE '\\'", [$termino])
                    ->orWhereRaw("categoria LIKE ? ESCAPE '\\'", [$termino])
                    ->orWhereRaw("descripcion LIKE ? ESCAPE '\\'", [$termino]);
            });
        });

        $query->when($categoria !== '', function (Builder $subQuery) use ($categoria): void {
            $subQuery->where('categoria', $categoria);
        });

        $query->when($estado !== '', function (Builder $subQuery) use ($estado): void {
            match ($estado) {
                'activos' => $subQuery->where('activo', true),
                'inactivos' => $subQuery->where('activo', false),
                'bajo_stock' => $subQuery->where('activo', true)->whereBetween('stock', [1, 5]),
                'agotados' => $subQuery->where('stock', 0),
                default => null,
            };
        });

        return $query;
    }

    public function getPrecioFormateadoAttribute(): string
    {
        return '$'.number_format($this->precio_centavos / 100, 2);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ItemVenta::class, 'producto_id');
    }

    public function itemsPedido(): HasMany
    {
        return $this->hasMany(ItemPedido::class, 'producto_id');
    }
}
