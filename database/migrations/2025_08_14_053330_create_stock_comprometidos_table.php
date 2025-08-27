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
        Schema::create('stock_comprometidos', function (Blueprint $table) {
            $table->id();
            $table->string('producto_codigo', 50)->index();
            $table->string('producto_nombre', 200);
            $table->string('bodega_codigo', 10)->default('01');
            $table->string('bodega_nombre', 100)->default('Bodega Principal');
            $table->decimal('cantidad_comprometida', 15, 3);
            $table->decimal('stock_disponible_original', 15, 3);
            $table->decimal('stock_disponible_actual', 15, 3);
            $table->string('unidad_medida', 10)->default('UN');
            
            // Referencias a la cotización/nota de venta
            $table->unsignedBigInteger('cotizacion_id')->nullable();
            $table->string('cotizacion_estado', 50)->default('pendiente');
            $table->unsignedBigInteger('nota_venta_pendiente_id')->nullable();
            
            // Información del vendedor
            $table->unsignedBigInteger('vendedor_id');
            $table->string('vendedor_nombre', 200);
            
            // Información del cliente
            $table->string('cliente_codigo', 50);
            $table->string('cliente_nombre', 200);
            
            // Fechas
            $table->timestamp('fecha_compromiso');
            $table->timestamp('fecha_liberacion')->nullable();
            
            // Estado del compromiso
            $table->enum('estado', ['activo', 'liberado', 'procesado'])->default('activo');
            
            // Observaciones
            $table->text('observaciones')->nullable();
            
            // Timestamps
            $table->timestamps();
            
            // Índices
            $table->index(['producto_codigo', 'estado']);
            $table->index(['cotizacion_id', 'estado']);
            $table->index(['nota_venta_pendiente_id', 'estado']);
            $table->index('fecha_compromiso');
            
            // Foreign keys
            $table->foreign('cotizacion_id')->references('id')->on('cotizaciones')->onDelete('cascade');
            $table->foreign('nota_venta_pendiente_id')->references('id')->on('nota_venta_pendientes')->onDelete('cascade');
            $table->foreign('vendedor_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_comprometidos');
    }
};
