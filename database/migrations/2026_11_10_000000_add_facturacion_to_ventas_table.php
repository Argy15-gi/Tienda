<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Facturación y cobro en caja: número de factura secuencial,
     * método de pago (efectivo/QR/transferencia), monto recibido y cambio,
     * y datos del cliente (nombre y NIT/CI) para el comprobante.
     */
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->string('numero_factura')->nullable()->unique()->after('id');
            $table->string('metodo_pago')->default('efectivo')->after('total'); // efectivo, qr, transferencia
            $table->unsignedInteger('monto_pagado')->nullable()->after('metodo_pago'); // en centavos
            $table->unsignedInteger('cambio')->nullable()->after('monto_pagado'); // en centavos
            $table->string('cliente_nombre', 100)->nullable()->after('comprador_id');
            $table->string('nit_ci', 20)->nullable()->after('cliente_nombre');
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn([
                'numero_factura',
                'metodo_pago',
                'monto_pagado',
                'cambio',
                'cliente_nombre',
                'nit_ci',
            ]);
        });
    }
};
