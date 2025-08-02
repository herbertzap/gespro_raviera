<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            // Campos de estado (solo los que faltan)
            $table->datetime('fecha_aprobacion')->nullable()->after('estado');
            $table->foreignId('aprobado_por')->nullable()->constrained('users')->after('fecha_aprobacion');
            
            // Campos de descuentos
            $table->decimal('total_sin_descuento', 15, 2)->default(0)->after('total');
            $table->decimal('descuento_global', 15, 2)->default(0)->after('total_sin_descuento');
            $table->decimal('porcentaje_descuento', 5, 2)->default(0)->after('descuento_global');
            
            // Campos adicionales para el flujo
            $table->text('motivo_rechazo')->nullable()->after('porcentaje_descuento');
            $table->boolean('requiere_aprobacion')->default(false)->after('motivo_rechazo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->dropForeign(['aprobado_por']);
            $table->dropColumn([
                'fecha_aprobacion',
                'aprobado_por',
                'total_sin_descuento',
                'descuento_global',
                'porcentaje_descuento',
                'motivo_rechazo',
                'requiere_aprobacion'
            ]);
        });
    }
};
