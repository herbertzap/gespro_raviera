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
        Schema::table('productos', function (Blueprint $table) {
            // Agregar índices para mejorar búsquedas
            $table->index('KOPR', 'idx_productos_kopr');
            $table->index('NOKOPR', 'idx_productos_nokopr');
            $table->index('activo', 'idx_productos_activo');
            
            // Índice compuesto para búsquedas filtradas por activo
            $table->index(['activo', 'KOPR'], 'idx_productos_activo_kopr');
            $table->index(['activo', 'NOKOPR'], 'idx_productos_activo_nokopr');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropIndex('idx_productos_kopr');
            $table->dropIndex('idx_productos_nokopr');
            $table->dropIndex('idx_productos_activo');
            $table->dropIndex('idx_productos_activo_kopr');
            $table->dropIndex('idx_productos_activo_nokopr');
        });
    }
};
