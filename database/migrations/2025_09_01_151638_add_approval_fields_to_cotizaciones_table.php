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
        Schema::table('cotizaciones', function (Blueprint $table) {
            // Campos para el flujo de aprobaciones
            $table->enum('estado_aprobacion', ['pendiente', 'pendiente_picking', 'aprobada_supervisor', 'aprobada_compras', 'aprobada_picking', 'rechazada'])->default('pendiente')->after('estado');
            $table->unsignedBigInteger('aprobado_por_supervisor')->nullable()->after('estado_aprobacion');
            $table->unsignedBigInteger('aprobado_por_compras')->nullable()->after('aprobado_por_supervisor');
            $table->unsignedBigInteger('aprobado_por_picking')->nullable()->after('aprobado_por_compras');
            $table->timestamp('fecha_aprobacion_supervisor')->nullable()->after('aprobado_por_supervisor');
            $table->timestamp('fecha_aprobacion_compras')->nullable()->after('aprobado_por_compras');
            $table->timestamp('fecha_aprobacion_picking')->nullable()->after('aprobado_por_picking');
            $table->text('comentarios_supervisor')->nullable()->after('fecha_aprobacion_supervisor');
            $table->text('comentarios_compras')->nullable()->after('fecha_aprobacion_compras');
            $table->text('comentarios_picking')->nullable()->after('fecha_aprobacion_picking');
            // $table->text('motivo_rechazo')->nullable()->after('comentarios_picking'); // Ya existe
            
            // Campos para problemas de stock
            $table->boolean('tiene_problemas_stock')->default(false)->after('motivo_rechazo');
            $table->text('detalle_problemas_stock')->nullable()->after('tiene_problemas_stock');
            
            // Campos para problemas de crédito/cliente
            $table->boolean('tiene_problemas_credito')->default(false)->after('detalle_problemas_stock');
            $table->text('detalle_problemas_credito')->nullable()->after('tiene_problemas_credito');
            
            // Campo para indicar si es una nota separada por problemas de stock
            $table->unsignedBigInteger('nota_original_id')->nullable()->after('detalle_problemas_credito');
            $table->text('productos_separados')->nullable()->after('nota_original_id');
            
            // Índices para mejorar performance
            $table->index(['estado_aprobacion', 'estado']);
            $table->index(['aprobado_por_supervisor', 'aprobado_por_compras', 'aprobado_por_picking'], 'idx_aprobaciones');
            $table->index('nota_original_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->dropIndex(['estado_aprobacion', 'estado']);
            $table->dropIndex('idx_aprobaciones');
            $table->dropIndex('nota_original_id');
            
            $table->dropColumn([
                'estado_aprobacion',
                'aprobado_por_supervisor',
                'aprobado_por_compras',
                'aprobado_por_picking',
                'fecha_aprobacion_supervisor',
                'fecha_aprobacion_compras',
                'fecha_aprobacion_picking',
                'comentarios_supervisor',
                'comentarios_compras',
                'comentarios_picking',
                'motivo_rechazo',
                'tiene_problemas_stock',
                'detalle_problemas_stock',
                'tiene_problemas_credito',
                'detalle_problemas_credito',
                'nota_original_id',
                'productos_separados'
            ]);
        });
    }
};
