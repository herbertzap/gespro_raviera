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
        Schema::create('cheques_protestados', function (Blueprint $table) {
            $table->id();
            $table->string('tipo_documento', 10); // TIDP
            $table->string('numero_documento', 20); // NUDP
            $table->string('codigo_cliente', 20); // ENDP
            $table->string('nombre_cliente', 100); // NOKOEN
            $table->date('fecha_vencimiento')->nullable(); // FEVEDP
            $table->date('fecha_emision')->nullable(); // FEEMDP
            $table->string('moneda', 10); // MODP
            $table->decimal('valor', 15, 2); // VALOR calculado
            $table->string('sucursal', 10); // SUREDP
            $table->string('nombre_sucursal', 100); // NOKOSU
            $table->string('empresa', 10); // EMDP
            $table->string('sucursal_empresa', 10); // SUEMDP
            $table->string('cuenta', 20); // CUDP
            $table->string('numero_cuenta', 20); // NUCUDP
            $table->string('cuenta_contable', 50); // CTA
            $table->string('codigo_vendedor', 10); // KOFUEN
            $table->string('nombre_vendedor', 100); // NOKOFU
            $table->timestamps();
            
            // Índices para optimizar consultas
            $table->index('codigo_cliente');
            $table->index('codigo_vendedor');
            $table->index(['codigo_cliente', 'tipo_documento', 'numero_documento'], 'idx_cheques_cliente_doc');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cheques_protestados');
    }
};