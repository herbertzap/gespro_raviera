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
        Schema::create('cotizacion_historial', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cotizacion_id');
            $table->enum('estado_anterior', [
                'borrador', 'enviada', 'pendiente_stock', 'procesada',
                'pendiente', 'pendiente_picking', 'aprobada_supervisor', 
                'aprobada_compras', 'aprobada_picking', 'rechazada'
            ])->nullable();
            $table->enum('estado_nuevo', [
                'borrador', 'enviada', 'pendiente_stock', 'procesada',
                'pendiente', 'pendiente_picking', 'aprobada_supervisor', 
                'aprobada_compras', 'aprobada_picking', 'rechazada',
                'enviada_sql', 'nvv_generada', 'nvv_facturada', 'despachada'
            ]);
            $table->enum('tipo_accion', [
                'creacion', 'envio', 'aprobacion', 'rechazo', 'separacion',
                'insercion_sql', 'generacion_nvv', 'facturacion', 'despacho'
            ]);
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->string('usuario_nombre')->nullable();
            $table->string('rol_usuario')->nullable();
            $table->text('comentarios')->nullable();
            $table->text('detalles_adicionales')->nullable(); // JSON para datos específicos
            $table->timestamp('fecha_accion');
            $table->integer('tiempo_transcurrido_segundos')->nullable(); // Tiempo desde el estado anterior
            $table->timestamps();

            // Índices
            $table->index(['cotizacion_id', 'fecha_accion']);
            $table->index(['estado_nuevo', 'fecha_accion']);
            $table->index(['tipo_accion', 'fecha_accion']);
            $table->index('usuario_id');

            // Foreign keys
            $table->foreign('cotizacion_id')->references('id')->on('cotizaciones')->onDelete('cascade');
            $table->foreign('usuario_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cotizacion_historial');
    }
};