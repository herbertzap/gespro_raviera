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
            $table->text('observacion_vendedor')->nullable()->comment('Observación del vendedor (máx 250 caracteres)');
            $table->string('numero_orden_compra', 40)->nullable()->comment('Número de orden de compra del cliente');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->dropColumn(['observacion_vendedor', 'numero_orden_compra']);
        });
    }
};