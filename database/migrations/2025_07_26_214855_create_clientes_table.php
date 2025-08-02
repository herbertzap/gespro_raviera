<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_cliente')->unique();
            $table->string('nombre_cliente');
            $table->string('direccion')->nullable();
            $table->string('telefono')->nullable();
            $table->string('email')->nullable();
            $table->string('codigo_vendedor'); // Vendedor asignado
            $table->boolean('activo')->default(true);
            $table->timestamps();
            
            $table->index('codigo_vendedor');
        });
    }

    public function down()
    {
        Schema::dropIfExists('clientes');
    }
};
