<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(){
    Schema::create('logs', function (Blueprint $table) {
        $table->id();
        $table->string('user_id')->nullable(); // ID del usuario que realizó la acción
        $table->string('action_type');        // Tipo de acción (carga, modificación, eliminación, etc.)
        $table->string('table_name');        // Nombre de la tabla afectada
        $table->text('data');                // Datos insertados/modificados
        $table->text('errors')->nullable();  // Mensajes de error, si los hubo
        $table->timestamp('created_at')->useCurrent();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('logs');
    }
};
