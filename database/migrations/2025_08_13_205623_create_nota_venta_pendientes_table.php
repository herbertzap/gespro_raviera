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
        Schema::create('nota_venta_pendientes', function (Blueprint $table) {
            $table->id();
            
            // Información de la cotización original
            $table->unsignedBigInteger('cotizacion_id');
            $table->string('cotizacion_numero')->nullable();
            
            // Información del cliente
            $table->string('cliente_codigo');
            $table->string('cliente_nombre');
            $table->text('cliente_direccion')->nullable();
            $table->string('cliente_telefono')->nullable();
            $table->string('cliente_lista_precios')->nullable();
            
            // Información del vendedor
            $table->unsignedBigInteger('vendedor_id');
            $table->string('vendedor_nombre');
            $table->string('vendedor_codigo')->nullable();
            
            // Información de la nota de venta
            $table->string('numero_nota_venta')->nullable();
            $table->date('fecha_nota_venta');
            $table->decimal('subtotal', 15, 2);
            $table->decimal('descuento_global', 15, 2)->default(0);
            $table->decimal('total', 15, 2);
            $table->text('observaciones')->nullable();
            
            // Estado y aprobación
            $table->enum('estado', ['pendiente', 'aprobada', 'rechazada', 'cancelada'])->default('pendiente');
            $table->unsignedBigInteger('aprobado_por')->nullable();
            $table->timestamp('fecha_aprobacion')->nullable();
            $table->text('motivo_rechazo')->nullable();
            $table->text('comentarios_supervisor')->nullable();
            
            // Información de stock
            $table->boolean('tiene_problemas_stock')->default(false);
            $table->text('detalle_problemas_stock')->nullable();
            
            // Timestamps
            $table->timestamps();
            
            // Índices
            $table->foreign('cotizacion_id')->references('id')->on('cotizaciones')->onDelete('cascade');
            $table->foreign('vendedor_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('aprobado_por')->references('id')->on('users')->onDelete('set null');
            
            $table->index(['estado', 'fecha_nota_venta']);
            $table->index(['cliente_codigo', 'estado']);
            $table->index(['vendedor_id', 'estado']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nota_venta_pendientes');
    }
};
