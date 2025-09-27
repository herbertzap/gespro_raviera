<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SincronizarProductos extends Command
{
    protected $signature = 'productos:sincronizar {--limit=1000 : Límite de productos a sincronizar} {--offset=0 : Desplazamiento inicial (OFFSET)}';
    protected $description = 'Sincroniza productos desde SQL Server a MySQL';

    public function handle()
    {
        $this->info('Iniciando sincronización de productos...');
        
        $host = env('SQLSRV_EXTERNAL_HOST');
        $port = env('SQLSRV_EXTERNAL_PORT', '1433');
        $database = env('SQLSRV_EXTERNAL_DATABASE');
        $username = env('SQLSRV_EXTERNAL_USERNAME');
        $password = env('SQLSRV_EXTERNAL_PASSWORD');
        
        $limit = (int) $this->option('limit');
        $offset = (int) $this->option('offset');
        
        // Consulta SQL con paginación por OFFSET/FETCH - incluye todas las listas de precios
        $query = "
            SELECT 
                CAST(MAEPR.KOPR AS VARCHAR(30)) + '|' +
                CAST(REPLACE(MAEPR.NOKOPR, '|', ' ') AS VARCHAR(200)) + '|' +
                CAST(MAEPR.TIPR AS VARCHAR(10)) + '|' +
                CAST(MAEPR.UD01PR AS VARCHAR(10)) + '|' +
                CAST(ISNULL(MAEST.STFI1, 0) AS VARCHAR(30)) + '|' +
                CAST(ISNULL(MAEST.STOCNV1, 0) AS VARCHAR(30)) + '|' +
                CAST((ISNULL(MAEST.STFI1, 0) - ISNULL(MAEST.STOCNV1, 0)) AS VARCHAR(30)) + '|' +
                CAST(ISNULL(TABPRE01.PP01UD, 0) AS VARCHAR(30)) + '|' +
                CAST(ISNULL(TABPRE01.PP02UD, 0) AS VARCHAR(30)) + '|' +
                CAST(ISNULL(TABPRE01.DTMA01UD, 0) AS VARCHAR(30)) + '|' +
                CAST(ISNULL(TABPRE02.PP01UD, 0) AS VARCHAR(30)) + '|' +
                CAST(ISNULL(TABPRE02.PP02UD, 0) AS VARCHAR(30)) + '|' +
                CAST(ISNULL(TABPRE02.DTMA01UD, 0) AS VARCHAR(30)) + '|' +
                CAST(ISNULL(TABPRE03.PP01UD, 0) AS VARCHAR(30)) + '|' +
                CAST(ISNULL(TABPRE03.PP02UD, 0) AS VARCHAR(30)) + '|' +
                CAST(ISNULL(TABPRE03.DTMA01UD, 0) AS VARCHAR(30)) AS LINEA
            FROM MAEPR 
            LEFT JOIN MAEST ON MAEPR.KOPR = MAEST.KOPR AND MAEST.KOBO = '01'
            LEFT JOIN TABPRE AS TABPRE01 ON MAEPR.KOPR = TABPRE01.KOPR AND TABPRE01.KOLT = '01P'
            LEFT JOIN TABPRE AS TABPRE02 ON MAEPR.KOPR = TABPRE02.KOPR AND TABPRE02.KOLT = '02P'
            LEFT JOIN TABPRE AS TABPRE03 ON MAEPR.KOPR = TABPRE03.KOPR AND TABPRE03.KOLT = '03P'
            WHERE MAEPR.ATPR <> 'N' AND MAEPR.ATPR <> 'OCU'
            ORDER BY MAEPR.NOKOPR
            OFFSET {$offset} ROWS FETCH NEXT {$limit} ROWS ONLY
        ";

        $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
        $singleLineQuery = str_replace(["\n", "\r"], ' ', $query);
        file_put_contents($tempFile, $singleLineQuery . "\ngo\nquit");

        $command = "tsql -H {$host} -p {$port} -U {$username} -P {$password} -D {$database} < {$tempFile} 2>&1";
        $output = shell_exec($command);

        unlink($tempFile);

        $this->info('Output completo:');
        $this->line($output);

        if (empty($output)) {
            $this->error('No se pudo obtener datos del servidor SQL Server');
            return 1;
        }

        $lines = explode("\n", trim($output));
        $dataLines = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || 
                strpos($line, 'Setting') === 0 || 
                strpos($line, 'locale') === 0 || 
                strpos($line, 'using') === 0 ||
                strpos($line, 'CODIGO_PRODUCTO') !== false ||
                strpos($line, 'rows affected)') !== false) {
                continue;
            }

            if (!empty($line)) {
                $dataLines[] = $line;
                $this->info('Línea encontrada: ' . substr($line, 0, 100) . '...');
            }
        }

        $this->info('Total de líneas de datos encontradas: ' . count($dataLines));

        if (empty($dataLines)) {
            $this->error('No se encontraron datos de productos');
            return 1;
        }

        $productosProcesados = 0;
        $productosActualizados = 0;
        $productosCreados = 0;

        foreach ($dataLines as $line) {
            // Ahora la salida viene delimitada por '|'
            $fields = explode('|', $line);
            
            if (count($fields) < 16) {
                $this->warn('Línea con pocos campos: ' . count($fields) . ' - ' . substr($line, 0, 50));
                continue;
            }

            $codigoProducto = trim($fields[0]);
            if (empty($codigoProducto)) {
                continue;
            }

            $nombreProducto = trim($fields[1]);
            $tipoProducto = trim($fields[2]);
            $unidadMedida = trim($fields[3]);
            // Función helper para convertir valores vacíos a 0 y manejar valores muy grandes
            $convertToFloat = function($value, $isDiscount = false) {
                $value = trim($value ?? '');
                if (empty($value)) {
                    return 0.0;
                }
                $floatValue = (float)$value;
                
                // Para descuentos, limitar a 100 (porcentaje máximo)
                if ($isDiscount && $floatValue > 100) {
                    return 0.0; // Si el descuento es mayor a 100%, usar 0
                }
                
                // Limitar valores muy grandes que pueden causar errores en MySQL
                if ($floatValue > 999999999.99) {
                    return 0.0; // Si es muy grande, usar 0
                }
                return $floatValue;
            };
            
            $stockFisico = $convertToFloat($fields[4] ?? '');
            $stockComprometido = $convertToFloat($fields[5] ?? '');
            $stockDisponible = $convertToFloat($fields[6] ?? '');
            
            // Precios 01P
            $precio01p = $convertToFloat($fields[7] ?? '');
            $precio01pUd2 = $convertToFloat($fields[8] ?? '');
            $descuentoMaximo01p = $convertToFloat($fields[9] ?? '', true);
            
            // Precios 02P
            $precio02p = $convertToFloat($fields[10] ?? '');
            $precio02pUd2 = $convertToFloat($fields[11] ?? '');
            $descuentoMaximo02p = $convertToFloat($fields[12] ?? '', true);
            
            // Precios 03P
            $precio03p = $convertToFloat($fields[13] ?? '');
            $precio03pUd2 = $convertToFloat($fields[14] ?? '');
            $descuentoMaximo03p = $convertToFloat($fields[15] ?? '', true);

            // Verificar si el producto ya existe
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
                
                // Precios de todas las listas
                'precio_01p' => $precio01p,
                'precio_01p_ud2' => $precio01pUd2,
                'descuento_maximo_01p' => $descuentoMaximo01p,
                'precio_02p' => $precio02p,
                'precio_02p_ud2' => $precio02pUd2,
                'descuento_maximo_02p' => $descuentoMaximo02p,
                'precio_03p' => $precio03p,
                'precio_03p_ud2' => $precio03pUd2,
                'descuento_maximo_03p' => $descuentoMaximo03p,
                
                // Stock
                'stock_fisico' => $stockFisico,
                'stock_comprometido' => $stockComprometido,
                'stock_disponible' => $stockDisponible,
                
                'activo' => true,
            ];

            if ($productoExistente) {
                // Actualizar producto existente
                DB::table('productos')->where('KOPR', $codigoProducto)->update($data);
                $productosActualizados++;
            } else {
                // Crear nuevo producto
                $data['KOPR'] = $codigoProducto;
                $data['created_at'] = now();
                $data['updated_at'] = now();
                DB::table('productos')->insert($data);
                $productosCreados++;
            }

            $productosProcesados++;
        }

        $this->info("Sincronización completada:");
        $this->info("- Productos procesados: {$productosProcesados}");
        $this->info("- Productos creados: {$productosCreados}");
        $this->info("- Productos actualizados: {$productosActualizados}");

        return 0;
    }
}