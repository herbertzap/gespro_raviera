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
            // Campos para la guía de picking
            $table->string('guia_picking_bodega', 100)->nullable()->after('observaciones_picking');
            $table->string('guia_picking_separado_por', 150)->nullable()->after('guia_picking_bodega');
            $table->string('guia_picking_revisado_por', 150)->nullable()->after('guia_picking_separado_por');
            $table->string('guia_picking_numero_bultos', 50)->nullable()->after('guia_picking_revisado_por');
            $table->string('guia_picking_firma', 150)->nullable()->after('guia_picking_numero_bultos');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->dropColumn([
                'guia_picking_bodega',
                'guia_picking_separado_por',
                'guia_picking_revisado_por',
                'guia_picking_numero_bultos',
                'guia_picking_firma'
            ]);
        });
    }
};
