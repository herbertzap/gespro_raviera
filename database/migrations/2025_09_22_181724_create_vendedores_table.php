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
        Schema::create('vendedores', function (Blueprint $table) {
            $table->id();
            $table->string('KOFU', 10)->unique(); // Código del vendedor
            $table->string('NOKOFU', 100); // Nombre del vendedor
            $table->string('EMAIL', 100)->nullable(); // Email del sistema
            $table->string('RTFU', 20)->nullable(); // RUT del vendedor
            $table->string('DIRECCION', 200)->nullable(); // Dirección
            $table->string('TELEFONO', 20)->nullable(); // Teléfono
            $table->boolean('activo')->default(true); // Si está activo
            $table->boolean('tiene_usuario')->default(false); // Si ya tiene usuario creado
            $table->unsignedBigInteger('user_id')->nullable(); // ID del usuario asociado
            $table->timestamps();
            
            // Índices
            $table->index('KOFU');
            $table->index('RTFU');
            $table->index('tiene_usuario');
            $table->index('activo');
            
            // Foreign key
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendedores');
    }
};