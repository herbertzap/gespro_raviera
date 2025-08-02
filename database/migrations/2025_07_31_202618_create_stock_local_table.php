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
        Schema::create('stock_local', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_producto', 50)->index();
            $table->string('nombre_producto', 255);
            $table->string('codigo_bodega', 10)->default('01');
            $table->string('nombre_bodega', 100)->nullable();
            $table->decimal('stock_fisico', 15, 2)->default(0);
            $table->decimal('stock_comprometido', 15, 2)->default(0);
            $table->decimal('stock_disponible', 15, 2)->default(0);
            $table->string('unidad_medida', 10)->default('UN');
            $table->decimal('precio_venta', 15, 2)->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamp('ultima_actualizacion')->useCurrent();
            $table->timestamps();
            
            // Índices para optimizar consultas
            $table->index(['codigo_producto', 'codigo_bodega']);
            $table->index('stock_disponible');
            $table->index('activo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_local');
    }
};
