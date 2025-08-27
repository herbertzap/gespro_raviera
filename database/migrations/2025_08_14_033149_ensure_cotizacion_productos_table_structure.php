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
        if (!Schema::hasTable('cotizacion_productos')) {
            Schema::create('cotizacion_productos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('cotizacion_id')->constrained()->onDelete('cascade');
                $table->string('codigo_producto', 50);
                $table->string('nombre_producto', 200);
                $table->decimal('precio_unitario', 15, 2);
                $table->integer('cantidad');
                $table->decimal('subtotal', 15, 2);
                $table->integer('stock_disponible')->default(0);
                $table->boolean('stock_suficiente')->default(true);
                $table->timestamps();
                
                $table->index('cotizacion_id');
                $table->index('codigo_producto');
            });
        } else {
            // Si la tabla existe, agregar columnas faltantes
            Schema::table('cotizacion_productos', function (Blueprint $table) {
                if (!Schema::hasColumn('cotizacion_productos', 'stock_disponible')) {
                    $table->integer('stock_disponible')->default(0)->after('subtotal');
                }
                
                if (!Schema::hasColumn('cotizacion_productos', 'stock_suficiente')) {
                    $table->boolean('stock_suficiente')->default(true)->after('stock_disponible');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cotizacion_productos');
    }
};
