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
            $table->bigInteger('numero_nvv')->nullable()->after('id')->comment('Número correlativo de NVV en SQL Server');
            $table->index('numero_nvv', 'idx_cotizaciones_numero_nvv');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->dropIndex('idx_cotizaciones_numero_nvv');
            $table->dropColumn('numero_nvv');
        });
    }
};
