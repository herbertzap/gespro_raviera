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
            $table->string('region', 100)->nullable()->after('codigo_vendedor');
            $table->string('comuna', 100)->nullable()->after('region');
            $table->string('lista_precios_codigo', 20)->nullable()->after('comuna');
            $table->string('lista_precios_nombre', 100)->nullable()->after('lista_precios_codigo');
            $table->boolean('bloqueado')->default(false)->after('lista_precios_nombre');
            $table->timestamp('ultima_sincronizacion')->nullable()->after('bloqueado');
            
            // Índices
            $table->index('activo');
            $table->index('bloqueado');
            $table->index('ultima_sincronizacion');
            $table->index(['codigo_vendedor', 'activo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropIndex(['activo']);
            $table->dropIndex(['bloqueado']);
            $table->dropIndex(['ultima_sincronizacion']);
            $table->dropIndex(['codigo_vendedor', 'activo']);
            
            $table->dropColumn([
                'region',
                'comuna',
                'lista_precios_codigo',
                'lista_precios_nombre',
                'bloqueado',
                'ultima_sincronizacion'
            ]);
        });
    }
};
