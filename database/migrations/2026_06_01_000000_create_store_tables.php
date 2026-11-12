<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->string('nombre');
            $table->string('categoria')->default('General');
            $table->string('descripcion', 1000)->default('');
            $table->unsignedInteger('precio_centavos');
            $table->unsignedInteger('stock')->default(0);
            $table->boolean('activo')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('ventas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendedor_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('comprador_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('total');
            $table->string('estado')->default('COMPLETADA');
            $table->string('motivo')->nullable();
            $table->timestamps();
        });

        Schema::create('items_venta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->constrained('ventas')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $table->unsignedInteger('cantidad');
            $table->unsignedInteger('precio_unit_centavos');
            $table->unique(['venta_id', 'producto_id']);
        });

        Schema::create('pedidos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comprador_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('atendido_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('venta_id')->nullable()->constrained('ventas')->nullOnDelete();
            $table->unsignedInteger('total');
            $table->string('estado')->default('PENDIENTE'); // PENDIENTE, COMPLETADO, CANCELADO
            $table->string('nota', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('items_pedido', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained('pedidos')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $table->unsignedInteger('cantidad');
            $table->unsignedInteger('precio_unit_centavos');
            $table->unique(['pedido_id', 'producto_id']);
        });

        Schema::create('registros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('users')->restrictOnDelete();
            $table->string('accion');
            $table->text('detalle');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registros');
        Schema::dropIfExists('items_pedido');
        Schema::dropIfExists('pedidos');
        Schema::dropIfExists('items_venta');
        Schema::dropIfExists('ventas');
        Schema::dropIfExists('productos');
    }
};
