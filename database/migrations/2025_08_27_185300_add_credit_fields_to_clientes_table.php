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
        Schema::table('clientes', function (Blueprint $table) {
            // Campos de crédito
            $table->string('condicion_pago', 50)->nullable()->comment('CREDITO A 30 DIAS, TRANSFERENCIA ANTICIPADA, etc.');
            $table->integer('dias_credito')->default(0)->comment('Días máximos de pago desde DIPRVE');
            $table->decimal('credito_total', 15, 2)->default(0)->comment('Crédito total del cliente');
            $table->decimal('credito_utilizado', 15, 2)->default(0)->comment('Crédito utilizado actualmente');
            $table->decimal('credito_disponible', 15, 2)->default(0)->comment('Crédito disponible');
            
            // Comentarios administrativos
            $table->text('comentario_administracion')->nullable()->comment('Campo OBEN - comentarios de administración');
            
            // Campos de validación
            $table->boolean('requiere_autorizacion_credito')->default(false);
            $table->boolean('requiere_autorizacion_retraso')->default(false);
            $table->integer('dias_retraso_facturas')->default(0)->comment('Días de retraso en facturas pendientes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn([
                'condicion_pago',
                'dias_credito', 
                'credito_total',
                'credito_utilizado',
                'credito_disponible',
                'comentario_administracion',
                'requiere_autorizacion_credito',
                'requiere_autorizacion_retraso',
                'dias_retraso_facturas'
            ]);
        });
    }
};
