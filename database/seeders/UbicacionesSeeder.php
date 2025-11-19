<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UbicacionesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (!Schema::hasTable('bodegas') || !Schema::hasTable('ubicaciones')) {
            return;
        }

        $bodegaMap = DB::table('bodegas')->pluck('id', 'kobo');

        $ubicaciones = [
            ['kobo' => 'PSI', 'codigo' => '1Z1E01C01'],
            ['kobo' => 'PSI', 'codigo' => '1Z1E01C02'],
            ['kobo' => 'PSI', 'codigo' => '1Z1E01C03'],
            ['kobo' => 'PSI', 'codigo' => '1Z1E01C04'],
            ['kobo' => 'PSI', 'codigo' => '1Z1E01C05'],
            ['kobo' => 'PSI', 'codigo' => '1Z1E01C06'],
            ['kobo' => 'PSI', 'codigo' => '1Z1E01C07'],
        ];

        $now = now();

        $rows = [];
        foreach ($ubicaciones as $ubicacion) {
            $kobo = $ubicacion['kobo'];
            if (!isset($bodegaMap[$kobo])) {
                continue;
            }

            $rows[] = [
                'bodega_id' => $bodegaMap[$kobo],
                'kobo' => $kobo,
                'codigo' => $ubicacion['codigo'],
                'descripcion' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (!empty($rows)) {
            DB::table('ubicaciones')->upsert(
                $rows,
                ['bodega_id', 'codigo'],
                ['descripcion', 'updated_at']
            );
        }
    }
}
