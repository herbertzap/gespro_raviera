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
        Schema::create('temporales', function (Blueprint $table) {
            $table->id();

            // Datos de bodega
            $table->foreignId('bodega_id')->constrained('bodegas')->cascadeOnDelete();
            $table->string('empresa', 5)->nullable();
            $table->string('kosu', 10)->nullable();
            $table->string('kobo', 10);
            $table->string('centro_costo', 10)->nullable();

            // Datos del producto
            $table->string('sku', 50);
            $table->string('nombre_producto', 200);
            $table->decimal('rlud', 18, 3)->default(1);
            $table->string('unidad_medida_1', 10)->nullable();
            $table->string('unidad_medida_2', 10)->nullable();

            // Capturas
            $table->decimal('captura_1', 18, 3)->default(0);
            $table->decimal('captura_2', 18, 3)->nullable();
            $table->decimal('stfi1', 18, 3)->nullable();
            $table->decimal('stfi2', 18, 3)->nullable();

            $table->string('funcionario', 20)->nullable();
            $table->string('tido', 3)->nullable();

            $table->timestamps();

            $table->index(['kobo', 'sku']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('temporales');
    }
};
