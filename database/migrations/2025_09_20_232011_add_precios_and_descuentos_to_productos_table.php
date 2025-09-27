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
        Schema::table('productos', function (Blueprint $table) {
            // Campos de precios para diferentes listas de precios
            $table->decimal('precio_01p', 10, 2)->nullable()->after('ultima_sincronizacion');
            $table->decimal('precio_01p_ud2', 10, 2)->nullable()->after('precio_01p');
            $table->decimal('descuento_maximo_01p', 5, 2)->nullable()->after('precio_01p_ud2');
            
            $table->decimal('precio_02p', 10, 2)->nullable()->after('descuento_maximo_01p');
            $table->decimal('precio_02p_ud2', 10, 2)->nullable()->after('precio_02p');
            $table->decimal('descuento_maximo_02p', 5, 2)->nullable()->after('precio_02p_ud2');
            
            $table->decimal('precio_03p', 10, 2)->nullable()->after('descuento_maximo_02p');
            $table->decimal('precio_03p_ud2', 10, 2)->nullable()->after('precio_03p');
            $table->decimal('descuento_maximo_03p', 5, 2)->nullable()->after('precio_03p_ud2');
            
            // Campos de stock
            $table->decimal('stock_fisico', 10, 2)->nullable()->after('descuento_maximo_03p');
            $table->decimal('stock_comprometido', 10, 2)->nullable()->after('stock_fisico');
            $table->decimal('stock_disponible', 10, 2)->nullable()->after('stock_comprometido');
            
            // Campo para indicar si el producto está activo
            $table->boolean('activo')->default(true)->after('stock_disponible');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn([
                'precio_01p', 'precio_01p_ud2', 'descuento_maximo_01p',
                'precio_02p', 'precio_02p_ud2', 'descuento_maximo_02p',
                'precio_03p', 'precio_03p_ud2', 'descuento_maximo_03p',
                'stock_fisico', 'stock_comprometido', 'stock_disponible',
                'activo'
            ]);
        });
    }
};