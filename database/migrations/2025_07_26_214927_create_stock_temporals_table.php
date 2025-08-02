<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('stock_temporals', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_producto');
            $table->string('nombre_producto');
            $table->integer('cantidad_reservada');
            $table->foreignId('cotizacion_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('nota_venta_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained(); // Usuario que reserva
            $table->enum('tipo', ['cotizacion', 'nota_venta'])->default('cotizacion');
            $table->enum('estado', ['activa', 'liberada', 'confirmada'])->default('activa');
            $table->timestamp('fecha_expiracion')->nullable();
            $table->timestamps();
            
            $table->index('codigo_producto');
            $table->index('estado');
            $table->index('fecha_expiracion');
        });
    }

    public function down()
    {
        Schema::dropIfExists('stock_temporals');
    }
};
