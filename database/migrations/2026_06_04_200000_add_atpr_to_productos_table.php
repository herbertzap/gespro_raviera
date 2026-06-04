<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            if (! Schema::hasColumn('productos', 'atpr')) {
                $table->string('atpr', 10)->nullable()->after('TIPR')->comment('ATPR ERP: OCU = oculto, no vender');
            }
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            if (Schema::hasColumn('productos', 'atpr')) {
                $table->dropColumn('atpr');
            }
        });
    }
};
