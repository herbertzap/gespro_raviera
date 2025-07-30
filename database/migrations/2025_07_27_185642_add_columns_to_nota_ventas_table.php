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
        Schema::table('nota_ventas', function (Blueprint $table) {
            $table->string('numero_nvv')->unique();
            $table->date('fecha_nvv');
            $table->string('codigo_cliente');
            $table->string('codigo_vendedor');
            $table->decimal('total_nvv', 15, 2);
            $table->decimal('saldo_pendiente', 15, 2)->default(0);
            $table->date('fecha_vencimiento');
            $table->enum('estado', ['borrador', 'por_aprobar', 'aprobada', 'rechazada', 'facturada'])->default('borrador');
            $table->text('observaciones')->nullable();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('nota_ventas', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn([
                'numero_nvv',
                'fecha_nvv',
                'codigo_cliente',
                'codigo_vendedor',
                'total_nvv',
                'saldo_pendiente',
                'fecha_vencimiento',
                'estado',
                'observaciones',
                'user_id'
            ]);
        });
    }
};
