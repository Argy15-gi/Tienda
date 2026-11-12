<?php

namespace Tests\Feature;

use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Registro;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StoreFlowsTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_only_shows_active_products(): void
    {
        $this->producto(['activo' => true, 'nombre' => 'Visible']);
        $this->producto(['activo' => false, 'nombre' => 'Oculto', 'sku' => 'HIDE-01']);

        $this->get('/')
            ->assertOk()
            ->assertSee('Visible')
            ->assertDontSee('Oculto');
    }

    public function test_user_can_register_with_hashed_password_and_login_and_logout(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Nuevo Cliente',
            'email' => 'cliente@tiendita.test',
            'password' => 'ClaveSegura123!',
            'password_confirmation' => 'ClaveSegura123!',
        ]);

        $response->assertRedirect(route('panel'));
        $this->assertAuthenticated();

        $user = User::query()->where('email', 'cliente@tiendita.test')->firstOrFail();
        $this->assertSame('COMPRADOR', $user->rol);
        $this->assertNotSame('ClaveSegura123!', $user->password);
        $this->assertTrue(Hash::check('ClaveSegura123!', $user->password));

        $this->post(route('logout'))->assertRedirect(route('catalogo'));
        $this->assertGuest();

        $this->post(route('login.store'), [
            'email' => 'cliente@tiendita.test',
            'password' => 'ClaveIncorrecta',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->withHeaders(['Origin' => 'null'])->post(route('login.store'), [
            'email' => 'cliente@tiendita.test',
            'password' => 'ClaveSegura123!',
        ])->assertRedirect(route('panel'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_middleware_redirects_guests_from_protected_routes(): void
    {
        $product = $this->producto();

        $this->get(route('panel'))->assertRedirect(route('login'));
        $this->get(route('productos.index'))->assertRedirect(route('login'));
        $this->get(route('productos.create'))->assertRedirect(route('login'));
        $this->get(route('productos.show', $product))->assertRedirect(route('login'));
        $this->get(route('admin.productos.index'))->assertRedirect(route('login'));
    }

    public function test_decoupled_api_auth_middleware_and_crud(): void
    {
        $this->getJson('/api/productos')->assertUnauthorized();

        $reg = $this->postJson('/api/auth/register', [
            'name' => 'Cliente API',
            'email' => 'api@tiendita.test',
            'password' => 'ClaveSegura123!',
            'password_confirmation' => 'ClaveSegura123!',
        ])->assertCreated();

        $buyerToken = $reg->json('token');
        $this->assertNotEmpty($buyerToken);

        // Un comprador recién registrado NO puede ver ni entrar al CRUD de productos (403 Forbidden)
        $this->withHeader('Authorization', 'Bearer '.$buyerToken)
            ->getJson('/api/productos')
            ->assertForbidden();

        // Un comprador no puede crear productos por API (ProductoRequest / ProductoPolicy)
        $this->withHeader('Authorization', 'Bearer '.$buyerToken)
            ->postJson('/api/productos', [
                'sku' => 'API-01',
                'nombre' => 'Desde API',
                'categoria' => 'Bebidas',
                'precio_centavos' => 5000,
                'stock' => 10,
            ])->assertForbidden();

        // Admin por API (verificando que mass-assignment de 'rol' es ignorado en User::create)
        $admin = User::query()->create([
            'name' => 'Admin API',
            'email' => 'admin-api@tiendita.test',
            'password' => Hash::make('AdminClave123!'),
            'rol' => 'ADMIN',
        ]);
        $this->assertSame('COMPRADOR', $admin->fresh()->rol);
        $admin->forceFill(['rol' => 'ADMIN'])->save();

        $login = $this->postJson('/api/auth/login', [
            'email' => $admin->email,
            'password' => 'AdminClave123!',
        ])->assertOk();

        $adminToken = $login->json('token');

        $created = $this->withHeader('Authorization', 'Bearer '.$adminToken)
            ->postJson('/api/productos', [
                'sku' => ' api-01 ',
                'nombre' => 'Producto API',
                'categoria' => 'Bebidas',
                'precio_centavos' => 12000,
                'stock' => 8,
            ])->assertCreated();

        $prodId = $created->json('producto.id');

        $this->withHeader('Authorization', 'Bearer '.$adminToken)
            ->getJson("/api/productos/{$prodId}")
            ->assertOk()
            ->assertJsonPath('producto.sku', 'API-01');

        $this->withHeader('Authorization', 'Bearer '.$adminToken)
            ->deleteJson("/api/productos/{$prodId}")
            ->assertOk();

        $this->assertSoftDeleted('productos', ['id' => $prodId]);

        $this->withHeader('Authorization', 'Bearer '.$adminToken)
            ->patchJson("/api/productos/{$prodId}/restaurar")
            ->assertOk();

        $this->assertNotSoftDeleted('productos', ['id' => $prodId]);

        // Toggle activo/inactivo y exportación CSV de ventas por API
        $this->withHeader('Authorization', 'Bearer '.$adminToken)
            ->patchJson("/api/productos/{$prodId}/estado")
            ->assertOk()
            ->assertJsonPath('producto.activo', false);

        $this->withHeader('Authorization', 'Bearer '.$adminToken)
            ->get('/api/ventas-exportar')
            ->assertOk();

        // Seguridad 1: Token en query string (?token=...) sin header Authorization es rechazado (401)
        $this->withoutHeader('Authorization')
            ->getJson("/api/productos?token={$adminToken}")
            ->assertUnauthorized();

        // Seguridad 2: Origen CORS no confiable es rechazado (403)
        $this->withHeaders([
            'Origin' => 'https://attacker.example',
            'Authorization' => 'Bearer '.$adminToken,
        ])->getJson('/api/productos')->assertForbidden();
        $this->flushHeaders();

        // Seguridad 3: Escalación de privilegios por mass-assignment en registro es bloqueada
        $forgedReg = $this->postJson('/api/auth/register', [
            'name' => 'Intento Admin',
            'email' => 'forged@tiendita.test',
            'password' => 'ClaveSegura123!',
            'password_confirmation' => 'ClaveSegura123!',
            'rol' => 'ADMIN',
        ])->assertCreated();
        $this->assertSame('COMPRADOR', $forgedReg->json('user.rol'));

        // Seguridad 4: Sanitización contra CSV Formula Injection
        $this->producto(['sku' => 'CSV-01', 'nombre' => '=CMD|\'/C calc\'!A0', 'categoria' => 'Hogar']);
        $csvRes = $this->withHeader('Authorization', 'Bearer '.$adminToken)
            ->get('/api/productos-exportar?q=CSV-01');
        $csvRes->assertOk();
        $this->assertStringContainsString("'=CMD|", $csvRes->streamedContent());

        // Seguridad 5: Escape de comodín '%' en búsqueda (previene DoS por wildcard LIKE)
        $wildcardRes = $this->withHeader('Authorization', 'Bearer '.$adminToken)
            ->getJson('/api/productos?q=%25');
        $wildcardRes->assertOk();
        $this->assertCount(0, $wildcardRes->json('productos'));

        // Seguridad 6: Límite de pedidos pendientes por comprador (previene DoS por acaparamiento de stock)
        $stockProd = $this->producto(['sku' => 'DOS-01', 'stock' => 20, 'precio_centavos' => 15000]);
        for ($i = 0; $i < 3; $i++) {
            $orderRes = $this->withHeader('Authorization', 'Bearer '.$buyerToken)
                ->postJson('/api/pedidos', [
                    'producto_id' => $stockProd->id,
                    'cantidad' => 1,
                    'total' => 1, // Intento de manipular el total desde el cliente
                    'precio_centavos' => 1,
                ])
                ->assertCreated();
            // El servidor ignora el total del cliente y calcula desde la BD (15000)
            $this->assertSame(15000, $orderRes->json('pedido.total'));
        }
        $this->withHeader('Authorization', 'Bearer '.$buyerToken)
            ->postJson('/api/pedidos', ['producto_id' => $stockProd->id, 'cantidad' => 10])
            ->assertStatus(422);
        $this->assertSame(17, $stockProd->fresh()->stock);

        // Seguridad 7: Las variables de entorno (.env) nunca se exponen por HTTP y el servidor limpia HTML del cliente
        $this->get('/.env')->assertNotFound();
        $cleanProd = $this->withHeader('Authorization', 'Bearer '.$adminToken)
            ->postJson('/api/productos', [
                'sku' => 'XSS-01',
                'nombre' => '<script>alert(1)</script>Café Puro',
                'categoria' => '<b>Bebidas</b>',
                'precio_centavos' => 9900,
                'stock' => 5,
            ])->assertCreated();
        $this->assertSame('alert(1)Café Puro', $cleanProd->json('producto.nombre'));
        $this->assertSame('Bebidas', $cleanProd->json('producto.categoria'));
    }

    public function test_full_crud_and_soft_deletes_trash_and_restore_on_productos(): void
    {
        $admin = $this->usuario('ADMIN');

        // 1. Create form
        $this->actingAs($admin)->get(route('productos.create'))->assertOk();

        // 2. Store
        $this->actingAs($admin)->post(route('productos.store'), [
            'sku' => ' crud-01 ',
            'nombre' => 'Producto CRUD',
            'categoria' => 'Bebidas',
            'descripcion' => 'Descripción completa del producto CRUD',
            'precio_centavos' => 15500,
            'stock' => 12,
            'activo' => 1,
        ])->assertRedirect(route('productos.index'));

        $producto = Producto::query()->where('sku', 'CRUD-01')->firstOrFail();

        // 3. Index + Search/Filter
        $this->actingAs($admin)
            ->get(route('productos.index', ['q' => 'CRUD-01', 'categoria' => 'Bebidas']))
            ->assertOk()
            ->assertSee('Producto CRUD');

        // 4. Show (detalle)
        $this->actingAs($admin)
            ->get(route('productos.show', $producto))
            ->assertOk()
            ->assertSee('Producto CRUD')
            ->assertSee('Descripción completa del producto CRUD');

        // 5. Edit form & Update
        $this->actingAs($admin)->get(route('productos.edit', $producto))->assertOk();
        $this->actingAs($admin)->put(route('productos.update', $producto), [
            'sku' => 'CRUD-01',
            'nombre' => 'Producto CRUD Editado',
            'categoria' => 'Bebidas',
            'descripcion' => 'Descripción actualizada',
            'precio_centavos' => 19900,
            'stock' => 20,
            'activo' => 1,
        ])->assertRedirect(route('productos.show', $producto));

        $this->assertSame('Producto CRUD Editado', $producto->fresh()->nombre);
        $this->assertSame(19900, $producto->fresh()->precio_centavos);

        // 6. Destroy -> Papelera (SoftDeletes)
        $this->actingAs($admin)
            ->delete(route('productos.destroy', $producto))
            ->assertRedirect(route('productos.index'));

        $this->assertSoftDeleted('productos', ['id' => $producto->id]);

        // 7. Ver en Papelera y Restaurar
        $this->actingAs($admin)
            ->get(route('productos.trash'))
            ->assertOk()
            ->assertSee('Producto CRUD Editado');

        $this->actingAs($admin)
            ->patch(route('productos.restore', $producto->id))
            ->assertRedirect(route('productos.trash'));

        $this->assertNotSoftDeleted('productos', ['id' => $producto->id]);
        $this->assertTrue($producto->fresh()->activo);
    }

    public function test_csv_export_downloads_filtered_products(): void
    {
        $admin = $this->usuario('ADMIN');
        $this->producto(['sku' => 'EXP-001', 'nombre' => 'Exportable Uno', 'categoria' => 'Hogar']);

        $response = $this->actingAs($admin)->get(route('productos.export', ['categoria' => 'Hogar']));
        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('EXP-001', $response->streamedContent());
    }

    public function test_buyer_can_place_online_order_and_seller_can_complete_it(): void
    {
        $buyer = $this->usuario('COMPRADOR');
        $seller = $this->usuario('VENDEDOR');
        $product = $this->producto(['precio_centavos' => 10000, 'stock' => 5]);

        $this->actingAs($buyer)
            ->post(route('pedidos.store'), [
                'producto_id' => $product->id,
                'cantidad' => 2,
                'nota' => 'Pedido de prueba',
            ])
            ->assertRedirect(route('comprador.compras.index'));

        $this->assertSame(3, $product->fresh()->stock);
        $pedido = Pedido::query()->firstOrFail();
        $this->assertSame('PENDIENTE', $pedido->estado);
        $this->assertSame(20000, $pedido->total);

        $this->actingAs($seller)
            ->patch(route('pedidos.estado', $pedido), ['estado' => 'COMPLETADO'])
            ->assertSessionHasNoErrors();

        $this->assertSame('COMPLETADO', $pedido->fresh()->estado);
        $this->assertNotNull($pedido->fresh()->venta_id);
        $this->assertDatabaseHas('ventas', [
            'id' => $pedido->fresh()->venta_id,
            'comprador_id' => $buyer->id,
            'total' => 20000,
        ]);
    }

    public function test_seller_cannot_create_or_change_products(): void
    {
        $seller = $this->usuario('VENDEDOR');
        $product = $this->producto();
        $payload = [
            'sku' => 'NEW-01',
            'nombre' => 'Nuevo',
            'categoria' => 'General',
            'precio_centavos' => 100,
            'stock' => 2,
        ];

        $this->actingAs($seller)->post(route('admin.productos.store'), $payload)->assertForbidden();
        $this->actingAs($seller)->put(route('admin.productos.update', $product), $payload)->assertForbidden();
        $this->actingAs($seller)->delete(route('admin.productos.destroy', $product))->assertForbidden();
        $this->assertDatabaseCount('productos', 1);
        $this->assertSame(10000, $product->fresh()->precio_centavos);
        $this->assertSame(5, $product->fresh()->stock);
    }

    public function test_unauthenticated_admin_requests_redirect_to_login(): void
    {
        $this->get(route('admin.productos.index'))->assertRedirect(route('login'));
    }

    public function test_duplicate_skus_are_normalized_before_unique_validation(): void
    {
        $admin = $this->usuario('ADMIN');
        $this->producto(['sku' => 'CAF-001']);

        $this->actingAs($admin)
            ->post(route('admin.productos.store'), [
                'sku' => ' caf-001 ',
                'nombre' => 'Duplicado',
                'categoria' => 'Bebidas',
                'precio_centavos' => 100,
                'stock' => 1,
            ])
            ->assertSessionHasErrors('sku');

        $this->assertDatabaseCount('productos', 1);
    }

    public function test_admin_can_create_product_with_normalized_sku_and_empty_description(): void
    {
        $admin = $this->usuario('ADMIN');

        $this->actingAs($admin)
            ->post(route('admin.productos.store'), [
                'sku' => ' new-01 ',
                'nombre' => 'Nuevo producto',
                'categoria' => 'General',
                'descripcion' => '',
                'precio_centavos' => 100,
                'stock' => 2,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('productos', [
            'sku' => 'NEW-01',
            'descripcion' => '',
            'precio_centavos' => 100,
            'stock' => 2,
        ]);
        $this->assertDatabaseHas('registros', [
            'usuario_id' => $admin->id,
            'accion' => 'PRODUCTO_CREADO',
        ]);
    }

    public function test_admin_product_edits_are_audited_and_preserve_sale_price_snapshots(): void
    {
        $admin = $this->usuario('ADMIN');
        $seller = $this->usuario('VENDEDOR');
        $product = $this->producto(['precio_centavos' => 12500]);
        $sale = $this->venta($seller, $this->usuario('COMPRADOR'), $product, 12500);

        $this->actingAs($admin)
            ->put(route('admin.productos.update', $product), [
                'sku' => $product->sku,
                'nombre' => $product->nombre,
                'categoria' => $product->categoria,
                'descripcion' => $product->descripcion,
                'precio_centavos' => 20000,
                'stock' => $product->stock,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(20000, $product->fresh()->precio_centavos);
        $this->assertDatabaseHas('items_venta', [
            'venta_id' => $sale->id,
            'precio_unit_centavos' => 12500,
        ]);
        $registro = Registro::query()->where('accion', 'PRODUCTO_ACTUALIZADO')->firstOrFail();
        $this->assertSame(12500, json_decode($registro->detalle, true, flags: JSON_THROW_ON_ERROR)['precioAnterior']);
        $this->assertSame(20000, json_decode($registro->detalle, true, flags: JSON_THROW_ON_ERROR)['precioNuevo']);
    }

    public function test_sale_uses_database_price_and_ignores_forged_amounts(): void
    {
        $seller = $this->usuario('VENDEDOR');
        $product = $this->producto(['precio_centavos' => 12500, 'stock' => 4]);

        $this->actingAs($seller)
            ->post(route('vendedor.ventas.store'), [
                'items' => [['producto_id' => $product->id, 'cantidad' => 2]],
                'precio' => 1,
                'total' => 1,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('ventas', ['total' => 25000]);
        $this->assertDatabaseHas('items_venta', [
            'producto_id' => $product->id,
            'cantidad' => 2,
            'precio_unit_centavos' => 12500,
        ]);
        $this->assertSame(2, $product->fresh()->stock);
        $this->assertDatabaseHas('registros', ['accion' => 'VENTA_REGISTRADA']);
    }

    public function test_invalid_or_excessive_sale_does_not_change_stock(): void
    {
        $seller = $this->usuario('VENDEDOR');
        $product = $this->producto(['precio_centavos' => 100000000, 'stock' => 22]);

        $this->actingAs($seller)
            ->post(route('vendedor.ventas.store'), [
                'items' => [['producto_id' => $product->id, 'cantidad' => 22]],
                'total' => 10,
            ])
            ->assertSessionHasErrors('items');

        $this->assertSame(22, $product->fresh()->stock);
        $this->assertDatabaseCount('ventas', 0);
    }

    public function test_only_one_sale_can_consume_the_last_unit(): void
    {
        $seller = $this->usuario('VENDEDOR');
        $product = $this->producto(['stock' => 1]);
        $payload = ['items' => [['producto_id' => $product->id, 'cantidad' => 1]]];

        $this->actingAs($seller)
            ->post(route('vendedor.ventas.store'), $payload)
            ->assertSessionHasNoErrors();
        $this->post(route('vendedor.ventas.store'), $payload)
            ->assertSessionHasErrors('items');

        $this->assertSame(0, $product->fresh()->stock);
        $this->assertDatabaseCount('ventas', 1);
    }

    public function test_failure_on_second_product_rolls_back_first_stock_decrement(): void
    {
        $seller = $this->usuario('VENDEDOR');
        $first = $this->producto(['sku' => 'FIRST', 'stock' => 4]);
        $second = $this->producto(['sku' => 'SECOND', 'stock' => 0]);

        $this->actingAs($seller)
            ->post(route('vendedor.ventas.store'), [
                'items' => [
                    ['producto_id' => $first->id, 'cantidad' => 2],
                    ['producto_id' => $second->id, 'cantidad' => 1],
                ],
            ])
            ->assertSessionHasErrors('items');

        $this->assertSame(4, $first->fresh()->stock);
        $this->assertSame(0, $second->fresh()->stock);
        $this->assertDatabaseCount('ventas', 0);
        $this->assertDatabaseCount('registros', 0);
    }

    public function test_admin_cancellation_requires_reason_and_restores_stock_only_once(): void
    {
        $admin = $this->usuario('ADMIN');
        $seller = $this->usuario('VENDEDOR');
        $product = $this->producto(['stock' => 2]);
        $sale = Venta::query()->create([
            'vendedor_id' => $seller->id,
            'total' => 10000,
            'estado' => 'COMPLETADA',
        ]);
        $sale->items()->create([
            'producto_id' => $product->id,
            'cantidad' => 3,
            'precio_unit_centavos' => 10000,
        ]);
        $product->update(['stock' => 2]);

        $this->actingAs($admin)
            ->patch(route('admin.ventas.anular', $sale), ['motivo' => ''])
            ->assertSessionHasErrors('motivo');

        $this->actingAs($admin)
            ->patch(route('admin.ventas.anular', $sale), ['motivo' => 'Devolución aprobada'])
            ->assertSessionHasNoErrors();
        $this->assertSame(5, $product->fresh()->stock);
        $this->assertSame('ANULADA', $sale->fresh()->estado);
        $this->assertDatabaseHas('registros', ['accion' => 'VENTA_ANULADA']);

        $this->actingAs($admin)
            ->patch(route('admin.ventas.anular', $sale), ['motivo' => 'Intento repetido'])
            ->assertStatus(409);
        $this->assertSame(5, $product->fresh()->stock);
    }

    public function test_admin_cannot_remove_their_own_admin_role_or_last_admin(): void
    {
        $admin = $this->usuario('ADMIN');
        $seller = $this->usuario('VENDEDOR');

        $this->actingAs($admin)
            ->patch(route('admin.usuarios.rol', $admin), ['rol' => 'COMPRADOR'])
            ->assertStatus(409);
        $this->actingAs($admin)
            ->patch(route('admin.usuarios.rol', $admin), ['rol' => 'VENDEDOR'])
            ->assertStatus(409);

        $this->assertSame('ADMIN', $admin->fresh()->rol);
        $this->assertSame('VENDEDOR', $seller->fresh()->rol);
        $this->assertDatabaseCount('registros', 0);
    }

    public function test_admin_can_change_another_users_role_and_audit_the_change(): void
    {
        $admin = $this->usuario('ADMIN');
        $seller = $this->usuario('VENDEDOR');

        $this->actingAs($admin)
            ->patch(route('admin.usuarios.rol', $seller), ['rol' => 'COMPRADOR'])
            ->assertSessionHasNoErrors();

        $this->assertSame('COMPRADOR', $seller->fresh()->rol);
        $this->assertDatabaseHas('registros', [
            'usuario_id' => $admin->id,
            'accion' => 'ROL_ACTUALIZADO',
        ]);
    }

    public function test_seed_is_repeatable_and_does_not_reset_stock(): void
    {
        Artisan::call('db:seed');
        $product = Producto::query()->where('sku', 'CAF-001')->firstOrFail();
        $product->update(['stock' => 7]);

        Artisan::call('db:seed');

        $this->assertDatabaseCount('productos', 3);
        $this->assertSame(7, $product->fresh()->stock);
        $this->assertSame(18900, $product->fresh()->precio_centavos);
    }

    public function test_google_oauth_creates_a_new_buyer_and_authenticates_the_session(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'test-token']),
            'www.googleapis.com/oauth2/v3/userinfo' => Http::response([
                'sub' => 'google-user-001',
                'name' => 'Google Buyer',
                'email' => 'google-buyer@example.test',
                'email_verified' => true,
            ]),
        ]);

        $state = str_repeat('x', 48);
        $this->withSession(['google_oauth_state' => $state])
            ->get(route('google.callback', ['code' => 'test-code', 'state' => $state]))
            ->assertRedirect(route('catalogo'));

        $this->assertDatabaseHas('users', [
            'google_id' => 'google-user-001',
            'email' => 'google-buyer@example.test',
            'rol' => 'COMPRADOR',
        ]);
        $this->assertAuthenticated();
    }

    public function test_roles_only_see_their_own_sales(): void
    {
        $seller = $this->usuario('VENDEDOR');
        $otherSeller = $this->usuario('VENDEDOR');
        $buyer = $this->usuario('COMPRADOR');
        $product = $this->producto();
        $ownSale = $this->venta($seller, $buyer, $product, 111);
        $otherSale = $this->venta($otherSeller, $buyer, $product, 222);

        $this->actingAs($seller)->get(route('vendedor.ventas.index'))
            ->assertOk()->assertSee('Venta #'.$ownSale->id)->assertDontSee('Venta #'.$otherSale->id);
        $this->actingAs($buyer)->get(route('comprador.compras.index'))
            ->assertOk()->assertSee('Venta #'.$ownSale->id)->assertSee('Venta #'.$otherSale->id);
        $this->actingAs($this->usuario('ADMIN'))->get(route('admin.ventas.index'))
            ->assertOk()->assertSee('Venta #'.$ownSale->id)->assertSee('Venta #'.$otherSale->id);
    }

    public function test_admin_management_pages_render(): void
    {
        $admin = $this->usuario('ADMIN');

        $this->actingAs($admin)->get(route('panel'))->assertOk();
        $this->actingAs($admin)->get(route('productos.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.productos.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.usuarios.index'))->assertOk();
    }

    public function test_a_buyer_cannot_record_a_sale_and_a_seller_cannot_cancel(): void
    {
        $buyer = $this->usuario('COMPRADOR');
        $seller = $this->usuario('VENDEDOR');
        $product = $this->producto();
        $sale = $this->venta($seller, $buyer, $product, 100);

        $this->actingAs($buyer)->post(route('vendedor.ventas.store'), [
            'items' => [['producto_id' => $product->id, 'cantidad' => 1]],
        ])->assertForbidden();
        $this->actingAs($seller)->patch(route('admin.ventas.anular', $sale), [
            'motivo' => 'No autorizado',
        ])->assertForbidden();

        $this->assertSame('COMPLETADA', $sale->fresh()->estado);
        $this->assertSame(5, $product->fresh()->stock);
    }

    private function usuario(string $rol): User
    {
        $user = new User([
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
        ]);
        $user->forceFill([
            'google_id' => (string) fake()->unique()->numberBetween(1000, 99999999),
            'rol' => $rol,
            'email_verified_at' => now(),
        ])->save();

        return $user;
    }

    private function producto(array $overrides = []): Producto
    {
        static $counter = 0;
        $counter++;

        return Producto::query()->create(array_merge([
            'sku' => 'TEST-'.$counter,
            'nombre' => 'Producto de prueba '.$counter,
            'categoria' => 'General',
            'descripcion' => 'Prueba',
            'precio_centavos' => 10000,
            'stock' => 5,
            'activo' => true,
        ], $overrides));
    }

    private function venta(User $seller, User $buyer, Producto $product, int $total): Venta
    {
        $sale = Venta::query()->create([
            'vendedor_id' => $seller->id,
            'comprador_id' => $buyer->id,
            'total' => $total,
            'estado' => 'COMPLETADA',
        ]);
        $sale->items()->create([
            'producto_id' => $product->id,
            'cantidad' => 1,
            'precio_unit_centavos' => $total,
        ]);

        return $sale;
    }
}
