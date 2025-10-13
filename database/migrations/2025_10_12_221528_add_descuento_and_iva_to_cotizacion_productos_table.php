<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cotizacion_productos', function (Blueprint $table) {
            $table->decimal('descuento_porcentaje', 5, 2)->after('subtotal')->default(0)->comment('Descuento aplicado en porcentaje');
            $table->decimal('descuento_valor', 15, 2)->after('descuento_porcentaje')->default(0)->comment('Valor del descuento en pesos');
            $table->decimal('subtotal_con_descuento', 15, 2)->after('descuento_valor')->default(0)->comment('Subtotal después de aplicar descuento');
            $table->decimal('iva_porcentaje', 5, 2)->after('subtotal_con_descuento')->default(19)->comment('Porcentaje de IVA aplicado');
            $table->decimal('iva_valor', 15, 2)->after('iva_porcentaje')->default(0)->comment('Valor del IVA en pesos');
            $table->decimal('total_producto', 15, 2)->after('iva_valor')->default(0)->comment('Total del producto con IVA');
        });
    }

    public function down(): void
    {
        Schema::table('cotizacion_productos', function (Blueprint $table) {
            $table->dropColumn([
                'descuento_porcentaje',
                'descuento_valor', 
                'subtotal_con_descuento',
                'iva_porcentaje',
                'iva_valor',
                'total_producto'
            ]);
        });
    }
};