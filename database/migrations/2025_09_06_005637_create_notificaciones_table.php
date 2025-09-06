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
        Schema::create('notificaciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('usuario_id')->nullable(); // NULL = notificación global
            $table->string('tipo'); // 'nueva_cotizacion', 'aprobacion_pendiente', 'factura_vencida', 'stock_bajo', etc.
            $table->string('titulo');
            $table->text('mensaje');
            $table->string('prioridad')->default('normal'); // 'baja', 'normal', 'alta', 'urgente'
            $table->string('estado')->default('no_leida'); // 'no_leida', 'leida', 'archivada'
            $table->json('datos_adicionales')->nullable(); // Datos específicos de la notificación
            $table->string('url_accion')->nullable(); // URL para la acción relacionada
            $table->string('icono')->default('info'); // Icono para mostrar
            $table->string('color')->default('primary'); // Color del badge/icono
            $table->timestamp('fecha_vencimiento')->nullable(); // Cuándo expira la notificación
            $table->timestamp('fecha_leida')->nullable();
            $table->timestamps();

            // Índices
            $table->index(['usuario_id', 'estado', 'created_at']);
            $table->index(['tipo', 'created_at']);
            $table->index(['prioridad', 'created_at']);
            $table->index('fecha_vencimiento');

            // Foreign keys
            $table->foreign('usuario_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notificaciones');
    }
};