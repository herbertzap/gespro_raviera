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
            $table->boolean('facturada')->default(false)->after('numero_nvv');
            $table->string('numero_factura', 20)->nullable()->after('facturada');
            $table->dateTime('fecha_facturacion')->nullable()->after('numero_factura');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->dropColumn(['facturada', 'numero_factura', 'fecha_facturacion']);
        });
    }
};
