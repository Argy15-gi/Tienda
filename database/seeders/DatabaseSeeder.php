<?php

namespace Database\Seeders;

use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Registro;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $passwordHash = Hash::make((string) config('services.demo.password', 'TiendaDev2026!'));

        $admin = User::query()->firstOrNew(['email' => 'admin@tiendita.test']);
        $admin->forceFill([
            'name' => 'Ana Administradora',
            'rol' => 'ADMIN',
            'password' => $passwordHash,
            'email_verified_at' => now(),
        ])->save();

        $vendedor = User::query()->firstOrNew(['email' => 'vendedor@tiendita.test']);
        $vendedor->forceFill([
            'name' => 'Luis Vendedor',
            'rol' => 'VENDEDOR',
            'password' => $passwordHash,
            'email_verified_at' => now(),
        ])->save();

        $comprador = User::query()->firstOrNew(['email' => 'comprador@tiendita.test']);
        $comprador->forceFill([
            'name' => 'Carla Compradora',
            'rol' => 'COMPRADOR',
            'password' => $passwordHash,
            'email_verified_at' => now(),
        ])->save();

        $productos = [
            [
                'sku' => 'CAF-001',
                'nombre' => 'Café de altura',
                'categoria' => 'Bebidas',
                'descripcion' => 'Bolsa de café tostado artesanal de 500 g.',
                'precio_centavos' => 18900,
                'stock' => 24,
            ],
            [
                'sku' => 'TAZ-001',
                'nombre' => 'Taza de cerámica',
                'categoria' => 'Hogar',
                'descripcion' => 'Taza artesanal con capacidad de 350 ml.',
                'precio_centavos' => 12500,
                'stock' => 18,
            ],
            [
                'sku' => 'LIB-001',
                'nombre' => 'Libreta de notas',
                'categoria' => 'Papelería',
                'descripcion' => 'Libreta de pasta dura con 120 páginas.',
                'precio_centavos' => 9900,
                'stock' => 3, // Stock bajo para demostrar alertas y filtros
            ],
        ];

        foreach ($productos as $producto) {
            $registro = Producto::withTrashed()->firstOrNew(['sku' => $producto['sku']]);
            $esNuevo = ! $registro->exists;
            $registro->fill(array_diff_key($producto, ['stock' => true]));
            if ($esNuevo) {
                $registro->stock = $producto['stock'];
            }
            $registro->save();
        }

        if (! app()->environment('testing')) {
            $extraProductos = [
                [
                    'sku' => 'GAL-001',
                    'nombre' => 'Galletas de avena y miel',
                    'categoria' => 'Alimentos',
                    'descripcion' => 'Paquete de 8 galletas horneadas con miel orgánica.',
                    'precio_centavos' => 6500,
                    'stock' => 15,
                ],
                [
                    'sku' => 'TER-001',
                    'nombre' => 'Termo de acero inoxidable',
                    'categoria' => 'Accesorios',
                    'descripcion' => 'Mantiene bebidas calientes por 12 horas (500 ml).',
                    'precio_centavos' => 24500,
                    'stock' => 4,
                ],
            ];

            foreach ($extraProductos as $prod) {
                $registro = Producto::withTrashed()->firstOrNew(['sku' => $prod['sku']]);
                if (! $registro->exists) {
                    $registro->fill($prod);
                    $registro->save();
                }
            }

            // Producto de ejemplo en la papelera para lucir la función de restaurar en la defensa
            $enPapelera = Producto::withTrashed()->firstOrNew(['sku' => 'EDI-001']);
            if (! $enPapelera->exists) {
                $enPapelera->fill([
                    'nombre' => ' Prensa francesa edición limitada',
                    'categoria' => 'Hogar',
                    'descripcion' => 'Producto de temporada enviado a la papelera para demostración.',
                    'precio_centavos' => 32000,
                    'stock' => 6,
                    'activo' => false,
                ]);
                $enPapelera->save();
                $enPapelera->delete();
            }

            $cafe = Producto::query()->where('sku', 'CAF-001')->first();
            if ($cafe && Venta::query()->count() === 0) {
                $venta = Venta::query()->create([
                    'vendedor_id' => $vendedor->id,
                    'comprador_id' => $comprador->id,
                    'total' => $cafe->precio_centavos * 2,
                    'estado' => 'COMPLETADA',
                ]);
                $venta->items()->create([
                    'producto_id' => $cafe->id,
                    'cantidad' => 2,
                    'precio_unit_centavos' => $cafe->precio_centavos,
                ]);

                Registro::query()->create([
                    'usuario_id' => $vendedor->id,
                    'accion' => 'VENTA_REGISTRADA',
                    'detalle' => json_encode(['ventaId' => $venta->id, 'total' => $venta->total], JSON_UNESCAPED_UNICODE),
                ]);
            }

            $taza = Producto::query()->where('sku', 'TAZ-001')->first();
            if ($taza && Pedido::query()->count() === 0) {
                $pedido = Pedido::query()->create([
                    'comprador_id' => $comprador->id,
                    'total' => $taza->precio_centavos,
                    'estado' => 'PENDIENTE',
                    'nota' => 'Paso por mi pedido hoy por la tarde.',
                ]);
                $pedido->items()->create([
                    'producto_id' => $taza->id,
                    'cantidad' => 1,
                    'precio_unit_centavos' => $taza->precio_centavos,
                ]);

                Registro::query()->create([
                    'usuario_id' => $comprador->id,
                    'accion' => 'PEDIDO_CREADO',
                    'detalle' => json_encode(['pedidoId' => $pedido->id, 'producto' => $taza->nombre], JSON_UNESCAPED_UNICODE),
                ]);
            }
        }
    }
}
