<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->timestamp('ultima_sincronizacion')->nullable();
            
            // Índices para optimizar consultas de búsqueda
            $table->index('KOPR');
            $table->index('NOKOPR');
            $table->index('ultima_sincronizacion');
            $table->index(['KOPR', 'NOKOPR']);
        });
    }

    public function down()
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropIndex(['KOPR']);
            $table->dropIndex(['NOKOPR']);
            $table->dropIndex(['ultima_sincronizacion']);
            $table->dropIndex(['KOPR', 'NOKOPR']);
            $table->dropColumn('ultima_sincronizacion');
        });
    }
};
