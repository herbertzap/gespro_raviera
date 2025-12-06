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
        Schema::create('ubicaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bodega_id')->constrained('bodegas')->cascadeOnDelete();
            $table->string('kobo', 10);
            $table->string('codigo', 30);
            $table->string('descripcion', 150)->nullable();
            $table->timestamps();

            $table->unique(['bodega_id', 'codigo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ubicaciones');
    }
};
