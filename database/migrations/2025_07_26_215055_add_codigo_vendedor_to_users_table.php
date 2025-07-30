<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('codigo_vendedor')->nullable()->after('email');
            $table->index('codigo_vendedor');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['codigo_vendedor']);
            $table->dropColumn('codigo_vendedor');
        });
    }
};
