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
        Schema::create('cotizaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Vendedor que crea
            $table->string('cliente_codigo', 20);
            $table->string('cliente_nombre', 200);
            $table->string('cliente_direccion', 300)->nullable();
            $table->string('cliente_telefono', 50)->nullable();
            $table->string('cliente_lista_precios', 20)->nullable();
            $table->datetime('fecha');
            $table->enum('estado', ['borrador', 'enviada', 'aprobada', 'rechazada', 'procesada', 'cancelada'])->default('borrador');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('descuento_global', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->text('observaciones')->nullable();
            $table->text('motivo_rechazo')->nullable();
            $table->boolean('requiere_aprobacion')->default(false);
            $table->string('nota_venta_id')->nullable(); // ID de la nota de venta en SQL Server
            $table->datetime('fecha_aprobacion')->nullable();
            $table->foreignId('aprobado_por')->nullable()->constrained('users');
            $table->datetime('fecha_cancelacion')->nullable();
            $table->foreignId('cancelado_por')->nullable()->constrained('users');
            $table->timestamps();
            
            // Índices
            $table->index('cliente_codigo');
            $table->index('estado');
            $table->index('fecha');
            $table->index('user_id');
            $table->index(['user_id', 'estado']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cotizaciones');
    }
};
