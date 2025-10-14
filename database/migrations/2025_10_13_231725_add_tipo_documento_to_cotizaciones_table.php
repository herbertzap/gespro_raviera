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
            $table->enum('tipo_documento', ['cotizacion', 'nota_venta'])
                ->default('nota_venta')
                ->after('id')
                ->comment('Tipo de documento: cotizacion (solo cotizar) o nota_venta (con aprobaciones)');
        });
        
        // Actualizar registros existentes a 'nota_venta'
        DB::table('cotizaciones')->update(['tipo_documento' => 'nota_venta']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->dropColumn('tipo_documento');
        });
    }
};
