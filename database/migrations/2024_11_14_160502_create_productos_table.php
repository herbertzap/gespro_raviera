<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductosTable extends Migration
{
    public function up()
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->string('TIPR', 10)->nullable();
            $table->string('KOPR', 20)->nullable();
            $table->string('NOKOPR', 255)->nullable();
            $table->string('KOPRRA', 20)->nullable();
            $table->string('NOKOPRRA', 255)->nullable();
            $table->string('KOPRTE', 20)->nullable();
            $table->string('UD01PR', 10)->nullable();
            $table->string('UD02PR', 10)->nullable();
            $table->float('RLUD')->nullable();
            $table->float('POIVPR')->nullable();
            $table->string('RGPR', 10)->nullable();
            $table->string('MRPR', 50)->nullable();
            $table->string('FMPR', 50)->nullable();
            $table->string('PFPR', 50)->nullable();
            $table->string('HFPR', 50)->nullable();
            $table->boolean('DIVISIBLE')->default(0);
            $table->timestamp('FECRPR')->nullable();
            $table->boolean('DIVISIBLE2')->default(0);
            $table->tinyInteger('estado')->default(0); // Estado: 0 = ingresado, 1 = por validar, 2 = validado
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('productos');
    }
}
