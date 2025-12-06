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
        Schema::create('bodegas', function (Blueprint $table) {
            $table->id();
            $table->string('empresa', 5)->nullable();
            $table->string('kosu', 10)->nullable();
            $table->string('kobo', 10);
            $table->string('nombre_bodega', 150);
            $table->string('centro_costo', 10)->nullable();
            $table->timestamps();

            $table->unique(['empresa', 'kosu', 'kobo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bodegas');
    }
};
