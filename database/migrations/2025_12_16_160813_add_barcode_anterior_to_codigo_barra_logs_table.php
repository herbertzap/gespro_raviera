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
        Schema::table('codigo_barra_logs', function (Blueprint $table) {
            $table->string('barcode_anterior', 60)->nullable()->after('barcode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('codigo_barra_logs', function (Blueprint $table) {
            $table->dropColumn('barcode_anterior');
        });
    }
};
