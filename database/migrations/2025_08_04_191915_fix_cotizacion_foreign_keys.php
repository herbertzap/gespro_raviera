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
        // Eliminar foreign keys existentes que apuntan a la tabla incorrecta
        Schema::table('cotizacion_detalles', function (Blueprint $table) {
            $table->dropForeign(['cotizacion_id']);
        });

        Schema::table('cotizacion_productos', function (Blueprint $table) {
            $table->dropForeign(['cotizacion_id']);
        });

        // Agregar foreign keys correctas que apunten a la tabla 'cotizaciones'
        Schema::table('cotizacion_detalles', function (Blueprint $table) {
            $table->foreign('cotizacion_id')->references('id')->on('cotizaciones')->onDelete('cascade');
        });

        Schema::table('cotizacion_productos', function (Blueprint $table) {
            $table->foreign('cotizacion_id')->references('id')->on('cotizaciones')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Eliminar foreign keys correctas
        Schema::table('cotizacion_detalles', function (Blueprint $table) {
            $table->dropForeign(['cotizacion_id']);
        });

        Schema::table('cotizacion_productos', function (Blueprint $table) {
            $table->dropForeign(['cotizacion_id']);
        });

        // Restaurar foreign keys originales que apuntan a 'cotizacions'
        Schema::table('cotizacion_detalles', function (Blueprint $table) {
            $table->foreign('cotizacion_id')->references('id')->on('cotizacions')->onDelete('cascade');
        });

        Schema::table('cotizacion_productos', function (Blueprint $table) {
            $table->foreign('cotizacion_id')->references('id')->on('cotizacions')->onDelete('cascade');
        });
    }
};
