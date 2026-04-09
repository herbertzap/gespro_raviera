<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cliente_sucursales', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_cliente', 20)->index();
            $table->string('suen', 20)->default('');
            $table->string('direccion', 300)->nullable();
            $table->string('telefono', 50)->nullable();
            $table->string('region', 100)->nullable();
            $table->string('comuna', 100)->nullable();
            $table->timestamps();
            $table->unique(['codigo_cliente', 'suen'], 'cliente_sucursales_codigo_suen_unique');
        });

        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->string('cliente_suen', 20)->nullable()->after('cliente_codigo');
        });
    }

    public function down(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->dropColumn('cliente_suen');
        });
        Schema::dropIfExists('cliente_sucursales');
    }
};
