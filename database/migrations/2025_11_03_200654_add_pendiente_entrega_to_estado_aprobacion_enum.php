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
        // Para MySQL, necesitamos modificar el enum usando ALTER TABLE directamente
        DB::statement("ALTER TABLE cotizaciones MODIFY COLUMN estado_aprobacion ENUM('pendiente', 'pendiente_picking', 'aprobada_supervisor', 'aprobada_compras', 'aprobada_picking', 'pendiente_entrega', 'rechazada') DEFAULT 'pendiente'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revertir al enum original sin 'pendiente_entrega'
        DB::statement("ALTER TABLE cotizaciones MODIFY COLUMN estado_aprobacion ENUM('pendiente', 'pendiente_picking', 'aprobada_supervisor', 'aprobada_compras', 'aprobada_picking', 'rechazada') DEFAULT 'pendiente'");
    }
};
