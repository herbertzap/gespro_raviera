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
        Schema::create('nota_venta_pendiente_productos', function (Blueprint $table) {
            $table->id();
            
            // Referencia a la nota de venta pendiente
            $table->unsignedBigInteger('nota_venta_pendiente_id');
            
            // Información del producto
            $table->string('codigo_producto');
            $table->string('nombre_producto');
            $table->decimal('cantidad', 10, 2);
            $table->decimal('precio_unitario', 15, 2);
            $table->decimal('subtotal', 15, 2);
            $table->string('unidad_medida')->default('UN');
            
            // Información de stock
            $table->decimal('stock_disponible', 10, 2)->default(0);
            $table->decimal('stock_comprometido', 10, 2)->default(0);
            $table->boolean('stock_suficiente')->default(true);
            $table->text('problemas_stock')->nullable();
            
            // Timestamps
            $table->timestamps();
            
            // Índices
            $table->foreign('nota_venta_pendiente_id')->references('id')->on('nota_venta_pendientes')->onDelete('cascade');
            $table->index(['codigo_producto', 'stock_suficiente'], 'nvp_producto_stock_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nota_venta_pendiente_productos');
    }
};
