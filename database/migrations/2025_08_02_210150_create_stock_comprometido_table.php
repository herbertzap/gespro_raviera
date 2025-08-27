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
        Schema::create('stock_comprometido', function (Blueprint $table) {
            $table->id();
            $table->string('producto_codigo', 50);
            $table->string('producto_nombre', 200);
            $table->string('bodega_codigo', 20)->default('001');
            $table->string('bodega_nombre', 100)->nullable();
            $table->decimal('cantidad_comprometida', 15, 2);
            $table->decimal('stock_disponible_original', 15, 2);
            $table->decimal('stock_disponible_actual', 15, 2);
            $table->string('unidad_medida', 10)->default('UN');
            $table->unsignedBigInteger('cotizacion_id');
            $table->string('cotizacion_estado', 20)->default('pendiente'); // pendiente, aprobada, cancelada, procesada
            $table->unsignedBigInteger('vendedor_id');
            $table->string('vendedor_nombre', 100);
            $table->string('cliente_codigo', 50);
            $table->string('cliente_nombre', 200);
            $table->timestamp('fecha_compromiso');
            $table->timestamp('fecha_liberacion')->nullable();
            $table->string('motivo_liberacion', 200)->nullable();
            $table->unsignedBigInteger('liberado_por')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();
            
            // Índices
            $table->index('producto_codigo');
            $table->index('bodega_codigo');
            $table->index('cotizacion_id');
            $table->index('vendedor_id');
            $table->index('cliente_codigo');
            $table->index('cotizacion_estado');
            $table->index('fecha_compromiso');
            
            // Clave foránea
            $table->foreign('cotizacion_id')->references('id')->on('cotizaciones')->onDelete('cascade');
            $table->foreign('vendedor_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('liberado_por')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_comprometido');
    }
};
