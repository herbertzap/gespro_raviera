<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Modificar el enum para incluir los nuevos estados
        DB::statement("ALTER TABLE cotizaciones MODIFY COLUMN estado ENUM('borrador', 'enviada', 'aprobada', 'rechazada', 'pendiente_stock', 'procesada', 'cancelada') DEFAULT 'borrador'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revertir a los valores originales
        DB::statement("ALTER TABLE cotizaciones MODIFY COLUMN estado ENUM('borrador', 'enviada', 'aprobada', 'rechazada') DEFAULT 'borrador'");
    }
};
