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
        Schema::table('stock_local', function (Blueprint $table) {
            $table->string('codigo_bodega', 20)->change();
            $table->string('nombre_bodega', 255)->change();
            $table->string('unidad_medida', 20)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_local', function (Blueprint $table) {
            $table->string('codigo_bodega', 10)->change();
            $table->string('nombre_bodega', 100)->change();
            $table->string('unidad_medida', 10)->change();
        });
    }
};