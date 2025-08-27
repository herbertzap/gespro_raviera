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
            $table->string('codigo_cliente', 20)->unique();
            $table->string('nombre_cliente', 200);
            $table->string('direccion', 300)->nullable();
            $table->string('telefono', 50)->nullable();
            $table->string('email', 100)->nullable();
            $table->string('codigo_vendedor', 20); // Vendedor asignado
            $table->string('region', 100)->nullable();
            $table->string('comuna', 100)->nullable();
            $table->string('lista_precios_codigo', 20)->nullable();
            $table->string('lista_precios_nombre', 100)->nullable();
            $table->boolean('bloqueado')->default(false);
            $table->boolean('activo')->default(true);
            $table->timestamp('ultima_sincronizacion')->nullable();
            $table->timestamps();
            
            // Índices para optimizar consultas
            $table->index('codigo_vendedor');
            $table->index('activo');
            $table->index('bloqueado');
            $table->index('ultima_sincronizacion');
            $table->index(['codigo_vendedor', 'activo']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('clientes');
    }
};
