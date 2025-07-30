<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('cotizacions', function (Blueprint $table) {
            $table->id();
            $table->string('numero_cotizacion')->unique();
            $table->foreignId('user_id')->constrained(); // Vendedor que crea
            $table->string('codigo_cliente');
            $table->string('nombre_cliente');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('impuesto', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->enum('estado', ['borrador', 'enviada', 'aprobada', 'rechazada'])->default('borrador');
            $table->text('observaciones')->nullable();
            $table->date('fecha_vencimiento')->nullable();
            $table->timestamps();
            
            $table->index('codigo_cliente');
            $table->index('estado');
        });
    }

    public function down()
    {
        Schema::dropIfExists('cotizacions');
    }
};
