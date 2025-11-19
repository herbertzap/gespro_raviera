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
        Schema::create('barcode_logs', function (Blueprint $table) {
            $table->id();
            $table->string('barcode', 60);
            $table->string('sku', 50);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('bodega_id')->nullable();
            $table->timestamps();

            $table->index('barcode');
            $table->index('sku');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('barcode_logs');
    }
};
