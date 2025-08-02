<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\StockLocal;

class StockLocalSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $productos = [
            [
                'codigo_producto' => '0000013100000',
                'nombre_producto' => 'TUBO ENSAYO 18 CC UN',
                'codigo_bodega' => '01',
                'nombre_bodega' => 'BODEGA PRINCIPAL',
                'stock_fisico' => 150.00,
                'stock_comprometido' => 25.00,
                'stock_disponible' => 125.00,
                'unidad_medida' => 'UN',
                'precio_venta' => 1250.00,
                'activo' => true
            ],
            [
                'codigo_producto' => '0000013100001',
                'nombre_producto' => 'CLAVO 2" GALVANIZADO KG',
                'codigo_bodega' => '01',
                'nombre_bodega' => 'BODEGA PRINCIPAL',
                'stock_fisico' => 500.00,
                'stock_comprometido' => 50.00,
                'stock_disponible' => 450.00,
                'unidad_medida' => 'KG',
                'precio_venta' => 850.00,
                'activo' => true
            ],
            [
                'codigo_producto' => '0000013100002',
                'nombre_producto' => 'TAPA PVC 4" UN',
                'codigo_bodega' => '01',
                'nombre_bodega' => 'BODEGA PRINCIPAL',
                'stock_fisico' => 75.00,
                'stock_comprometido' => 10.00,
                'stock_disponible' => 65.00,
                'unidad_medida' => 'UN',
                'precio_venta' => 3200.00,
                'activo' => true
            ],
            [
                'codigo_producto' => '0000013100003',
                'nombre_producto' => 'ANTICORROSIVO ROJO LT',
                'codigo_bodega' => '01',
                'nombre_bodega' => 'BODEGA PRINCIPAL',
                'stock_fisico' => 200.00,
                'stock_comprometido' => 30.00,
                'stock_disponible' => 170.00,
                'unidad_medida' => 'LT',
                'precio_venta' => 4500.00,
                'activo' => true
            ],
            [
                'codigo_producto' => '0000013100004',
                'nombre_producto' => 'CEMENTO PORTLAND SACO 25KG',
                'codigo_bodega' => '01',
                'nombre_bodega' => 'BODEGA PRINCIPAL',
                'stock_fisico' => 100.00,
                'stock_comprometido' => 15.00,
                'stock_disponible' => 85.00,
                'unidad_medida' => 'SACO',
                'precio_venta' => 8500.00,
                'activo' => true
            ],
            [
                'codigo_producto' => '0000013100005',
                'nombre_producto' => 'ARENA GRUESA M3',
                'codigo_bodega' => '01',
                'nombre_bodega' => 'BODEGA PRINCIPAL',
                'stock_fisico' => 50.00,
                'stock_comprometido' => 5.00,
                'stock_disponible' => 45.00,
                'unidad_medida' => 'M3',
                'precio_venta' => 25000.00,
                'activo' => true
            ],
            [
                'codigo_producto' => '0000013100006',
                'nombre_producto' => 'LADRILLO FISCAL UN',
                'codigo_bodega' => '01',
                'nombre_bodega' => 'BODEGA PRINCIPAL',
                'stock_fisico' => 10000.00,
                'stock_comprometido' => 500.00,
                'stock_disponible' => 9500.00,
                'unidad_medida' => 'UN',
                'precio_venta' => 120.00,
                'activo' => true
            ],
            [
                'codigo_producto' => '0000013100007',
                'nombre_producto' => 'PINTURA BLANCA LT',
                'codigo_bodega' => '01',
                'nombre_bodega' => 'BODEGA PRINCIPAL',
                'stock_fisico' => 0.00,
                'stock_comprometido' => 0.00,
                'stock_disponible' => 0.00,
                'unidad_medida' => 'LT',
                'precio_venta' => 3800.00,
                'activo' => true
            ],
            [
                'codigo_producto' => '0000013100008',
                'nombre_producto' => 'ALAMBRE GALVANIZADO KG',
                'codigo_bodega' => '01',
                'nombre_bodega' => 'BODEGA PRINCIPAL',
                'stock_fisico' => 300.00,
                'stock_comprometido' => 100.00,
                'stock_disponible' => 200.00,
                'unidad_medida' => 'KG',
                'precio_venta' => 1200.00,
                'activo' => true
            ],
            [
                'codigo_producto' => '0000013100009',
                'nombre_producto' => 'TUBO PVC 4" 6M UN',
                'codigo_bodega' => '01',
                'nombre_bodega' => 'BODEGA PRINCIPAL',
                'stock_fisico' => 25.00,
                'stock_comprometido' => 5.00,
                'stock_disponible' => 20.00,
                'unidad_medida' => 'UN',
                'precio_venta' => 8500.00,
                'activo' => true
            ]
        ];

        foreach ($productos as $producto) {
            StockLocal::updateOrCreate(
                ['codigo_producto' => $producto['codigo_producto'], 'codigo_bodega' => $producto['codigo_bodega']],
                $producto
            );
        }

        $this->command->info('✅ Stock local poblado con ' . count($productos) . ' productos de prueba');
    }
}
