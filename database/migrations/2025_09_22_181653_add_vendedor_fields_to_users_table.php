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
        Schema::table('users', function (Blueprint $table) {
            // Campos para vendedores
            $table->string('rut', 20)->nullable()->after('email');
            $table->string('email_alternativo')->nullable()->after('rut');
            $table->boolean('es_vendedor')->default(false)->after('codigo_vendedor');
            $table->boolean('primer_login')->default(true)->after('es_vendedor');
            $table->timestamp('fecha_ultimo_cambio_password')->nullable()->after('primer_login');
            
            // Índices
            $table->index('rut');
            $table->index('es_vendedor');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['rut']);
            $table->dropIndex(['es_vendedor']);
            
            $table->dropColumn([
                'rut',
                'email_alternativo',
                'es_vendedor',
                'primer_login',
                'fecha_ultimo_cambio_password'
            ]);
        });
    }
};