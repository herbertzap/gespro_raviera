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
        Schema::table('temporales', function (Blueprint $table) {
            $table->string('codigo_ubicacion', 50)->nullable()->after('ubicacion_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('temporales', function (Blueprint $table) {
            $table->dropColumn('codigo_ubicacion');
        });
    }
};
