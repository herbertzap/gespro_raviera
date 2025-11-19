<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SincronizarProductos extends Command
{
    protected $signature = 'productos:sincronizar {--limit=1000 : Número de productos por lote} {--offset=0 : Desplazamiento inicial (OFFSET)} {--once : Procesar solo un lote y finalizar}';
    protected $description = 'Sincroniza productos desde SQL Server a MySQL';

    public function handle()
    {
        $this->info('Iniciando sincronización de productos...');
        
        $chunkSize = (int) $this->option('limit');
        $offset = (int) $this->option('offset');
        $processOnce = (bool) $this->option('once');

        if ($chunkSize <= 0) {
            $chunkSize = 1000;
        }

        $productosProcesados = 0;
        $productosActualizados = 0;
        $productosCreados = 0;

        $currentOffset = $offset;
        $lote = 1;

        do {
            $productos = $this->obtenerProductosDesdeSqlServer($chunkSize, $currentOffset);

            $cantidadLote = $productos->count();
            
            if ($cantidadLote === 0) {
                if ($productosProcesados === 0) {
                    $this->warn('No se encontraron productos para sincronizar.');
                }
                break;
            }

            $this->info("Procesando lote {$lote} ({$cantidadLote} productos, offset {$currentOffset})");

            foreach ($productos as $producto) {
                $codigoProducto = trim((string)($producto->codigo_producto ?? ''));

                if ($codigoProducto === '') {
                continue;
            }

                $nombreProducto = trim((string)($producto->nombre_producto ?? ''));
                $tipoProducto = trim((string)($producto->tipo_producto ?? ''));
                $unidadMedida = trim((string)($producto->unidad_medida ?? ''));

                $convertToFloat = function ($value, $isDiscount = false) {
                    if ($value === null || $value === '') {
                    return 0.0;
                }

                    $floatValue = (float) $value;
                
                if ($isDiscount && $floatValue > 100) {
                        return 0.0;
                }
                
                if ($floatValue > 999999999.99) {
                        return 0.0;
                }

                return $floatValue;
            };
            
                $stockFisico = $convertToFloat($producto->stock_fisico ?? 0);
                $stockComprometido = $convertToFloat($producto->stock_comprometido ?? 0);
                $stockDisponible = $convertToFloat($producto->stock_disponible ?? 0);
            
                $precio01p = $convertToFloat($producto->precio_01p ?? 0);
                $precio01pUd2 = $convertToFloat($producto->precio_01p_ud2 ?? 0);
                $descuentoMaximo01p = $convertToFloat($producto->descuento_maximo_01p ?? 0, true);

            $productoExistente = DB::table('productos')->where('KOPR', $codigoProducto)->first();

            $data = [
                'TIPR' => $tipoProducto,
                'NOKOPR' => $nombreProducto,
                'KOPRRA' => '',
                'NOKOPRRA' => '',
                'KOPRTE' => '',
                'UD01PR' => $unidadMedida,
                'UD02PR' => '',
                'RLUD' => 1.0,
                'POIVPR' => 0,
                'RGPR' => '',
                'MRPR' => '',
                'FMPR' => '',
                'PFPR' => '',
                'HFPR' => '',
                'DIVISIBLE' => false,
                'DIVISIBLE2' => false,
                'FECRPR' => null,
                'estado' => 1,
                'ultima_sincronizacion' => now(),
                'precio_01p' => $precio01p,
                'precio_01p_ud2' => $precio01pUd2,
                'descuento_maximo_01p' => $descuentoMaximo01p,
                    'precio_02p' => 0.0,
                    'precio_02p_ud2' => 0.0,
                    'descuento_maximo_02p' => 0.0,
                    'precio_03p' => 0.0,
                    'precio_03p_ud2' => 0.0,
                    'descuento_maximo_03p' => 0.0,
                'stock_fisico' => $stockFisico,
                'stock_comprometido' => $stockComprometido,
                'stock_disponible' => $stockDisponible,
                'activo' => true,
                    'updated_at' => now(),
            ];

            if ($productoExistente) {
                DB::table('productos')->where('KOPR', $codigoProducto)->update($data);
                $productosActualizados++;
            } else {
                $data['KOPR'] = $codigoProducto;
                $data['created_at'] = now();
                DB::table('productos')->insert($data);
                $productosCreados++;
            }

            $productosProcesados++;
        }

            $currentOffset += $cantidadLote;
            $lote++;

            $this->info("Total procesado hasta ahora: {$productosProcesados}");

            if ($processOnce) {
                break;
            }

            if ($cantidadLote < $chunkSize) {
                break;
            }

        } while (true);

        $this->info('Sincronización completada:');
        $this->info("- Productos procesados: {$productosProcesados}");
        $this->info("- Productos creados: {$productosCreados}");
        $this->info("- Productos actualizados: {$productosActualizados}");

        return 0;
    }

    private function obtenerProductosDesdeSqlServer(int $limit, int $offset)
    {
        return DB::connection('sqlsrv_external')
            ->table('MAEPR')
            ->leftJoin('MAEST as MAESTOCK', function ($join) {
                $join->on('MAEPR.KOPR', '=', 'MAESTOCK.KOPR')
                    ->where('MAESTOCK.KOBO', '=', '01');
            })
            ->leftJoin('TABPRE as TABPRE01', function ($join) {
                $join->on('MAEPR.KOPR', '=', 'TABPRE01.KOPR')
                    ->where('TABPRE01.KOLT', '=', '01P');
            })
            ->whereNotIn('MAEPR.ATPR', ['N', 'OCU'])
            ->orderBy('MAEPR.NOKOPR')
            ->offset($offset)
            ->limit($limit)
            ->select([
                'MAEPR.KOPR as codigo_producto',
                DB::raw("REPLACE(MAEPR.NOKOPR, '|', ' ') as nombre_producto"),
                'MAEPR.TIPR as tipo_producto',
                'MAEPR.UD01PR as unidad_medida',
                DB::raw('COALESCE(MAESTOCK.STFI1, 0) as stock_fisico'),
                DB::raw('COALESCE(MAESTOCK.STOCNV1, 0) as stock_comprometido'),
                DB::raw('(COALESCE(MAESTOCK.STFI1, 0) - COALESCE(MAESTOCK.STOCNV1, 0)) as stock_disponible'),
                DB::raw('COALESCE(TABPRE01.PP01UD, 0) as precio_01p'),
                DB::raw('COALESCE(TABPRE01.PP02UD, 0) as precio_01p_ud2'),
                DB::raw('COALESCE(TABPRE01.DTMA01UD, 0) as descuento_maximo_01p'),
            ])
            ->get();
    }
}