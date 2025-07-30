<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('cotizacion_productos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cotizacion_id')->constrained()->onDelete('cascade');
            $table->string('codigo_producto');
            $table->string('nombre_producto');
            $table->decimal('precio_unitario', 15, 2);
            $table->integer('cantidad');
            $table->decimal('subtotal', 15, 2);
            $table->integer('stock_disponible')->default(0);
            $table->boolean('stock_suficiente')->default(true);
            $table->timestamps();
            
            $table->index('codigo_producto');
        });
    }

    public function down()
    {
        Schema::dropIfExists('cotizacion_productos');
    }
};
