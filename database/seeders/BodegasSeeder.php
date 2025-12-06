<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BodegasSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (!Schema::hasTable('bodegas')) {
            return;
        }

        $bodegas = [
            [
                'empresa' => '02',
                'kosu' => '10J',
                'kobo' => '010',
                'nombre_bodega' => '10 DE JULIO',
                'centro_costo' => '005',
            ],
            [
                'empresa' => '02',
                'kosu' => 'CMM',
                'kobo' => 'PSI',
                'nombre_bodega' => 'GRAN AVENIDA',
                'centro_costo' => '001',
            ],
            [
                'empresa' => '02',
                'kosu' => 'MER',
                'kobo' => 'MSA',
                'nombre_bodega' => 'MERSAN',
                'centro_costo' => '004',
            ],
            [
                'empresa' => '02',
                'kosu' => 'VE2',
                'kobo' => 'VE2',
                'nombre_bodega' => 'VENTISQUERO 1111',
                'centro_costo' => '004',
            ],
            [
                'empresa' => '02',
                'kosu' => 'VEN',
                'kobo' => 'VEN',
                'nombre_bodega' => 'VENTISQUERO 1204',
                'centro_costo' => '004',
            ],
        ];

        foreach ($bodegas as $bodega) {
            DB::table('bodegas')->updateOrInsert(
                [
                    'empresa' => $bodega['empresa'],
                    'kosu' => $bodega['kosu'],
                    'kobo' => $bodega['kobo'],
                ],
                array_merge($bodega, [
                    'updated_at' => now(),
                    'created_at' => now(),
                ])
            );
        }
    }
}
