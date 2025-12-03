<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Tabla TINVENTARIO para Aplicación de Barrido
     * Similar a temporales pero específica para inventario
     */
    public function up(): void
    {
        Schema::create('tinventario', function (Blueprint $table) {
            $table->id();

            // Usuario que realizó el barrido
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            
            // Datos de bodega
            $table->foreignId('bodega_id')->constrained('bodegas')->cascadeOnDelete();
            $table->foreignId('ubicacion_id')->nullable()->constrained('ubicaciones')->nullOnDelete();
            $table->string('codigo_ubicacion', 50)->nullable();
            $table->string('empresa', 5)->nullable();
            $table->string('kosu', 10)->nullable();
            $table->string('kobo', 10);
            $table->string('centro_costo', 10)->nullable();

            // Datos del producto
            $table->string('sku', 50);
            $table->string('nombre_producto', 200);
            $table->string('codigo_barras', 60)->nullable(); // Código de barras escaneado
            $table->decimal('rlud', 18, 3)->default(1);
            $table->string('unidad_medida_1', 10)->nullable();
            $table->string('unidad_medida_2', 10)->nullable();

            // Cantidad escaneada/contada
            $table->decimal('cantidad', 18, 3)->default(1);
            $table->decimal('cantidad_ud2', 18, 3)->nullable(); // Cantidad en unidad 2

            // Funcionario (código del vendedor/empleado)
            $table->string('funcionario', 20)->nullable();
            
            // Fecha del barrido (separada de created_at para permitir edición)
            $table->date('fecha_barrido')->nullable();

            $table->timestamps();

            // Índices para búsquedas y reportes
            $table->index(['kobo', 'sku']);
            $table->index(['user_id', 'fecha_barrido']);
            $table->index(['bodega_id', 'fecha_barrido']);
            $table->index('fecha_barrido');
            $table->index('funcionario');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tinventario');
    }
};


