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
            // Agregar columnas faltantes si no existen
            if (!Schema::hasColumn('cotizaciones', 'cliente_telefono')) {
                $table->string('cliente_telefono', 50)->nullable()->after('cliente_direccion');
            }
            
            if (!Schema::hasColumn('cotizaciones', 'cliente_lista_precios')) {
                $table->string('cliente_lista_precios', 20)->nullable()->after('cliente_telefono');
            }
            
            if (!Schema::hasColumn('cotizaciones', 'motivo_rechazo')) {
                $table->text('motivo_rechazo')->nullable()->after('observaciones');
            }
            
            if (!Schema::hasColumn('cotizaciones', 'nota_venta_id')) {
                $table->string('nota_venta_id')->nullable()->after('requiere_aprobacion');
            }
            
            if (!Schema::hasColumn('cotizaciones', 'fecha_aprobacion')) {
                $table->datetime('fecha_aprobacion')->nullable()->after('nota_venta_id');
            }
            
            if (!Schema::hasColumn('cotizaciones', 'aprobado_por')) {
                $table->foreignId('aprobado_por')->nullable()->constrained('users')->after('fecha_aprobacion');
            }
            
            if (!Schema::hasColumn('cotizaciones', 'fecha_cancelacion')) {
                $table->datetime('fecha_cancelacion')->nullable()->after('aprobado_por');
            }
            
            if (!Schema::hasColumn('cotizaciones', 'cancelado_por')) {
                $table->foreignId('cancelado_por')->nullable()->constrained('users')->after('fecha_cancelacion');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->dropForeign(['aprobado_por']);
            $table->dropForeign(['cancelado_por']);
            $table->dropColumn([
                'cliente_telefono',
                'cliente_lista_precios',
                'motivo_rechazo',
                'nota_venta_id',
                'fecha_aprobacion',
                'aprobado_por',
                'fecha_cancelacion',
                'cancelado_por'
            ]);
        });
    }
};
