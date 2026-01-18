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
        
        // Consulta SQL con paginación por OFFSET/FETCH - SOLO lista 01P (principal)
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
                CAST(ISNULL(TABPRE01.DTMA01UD, 0) AS VARCHAR(30)) AS LINEA
            FROM MAEPR 
            LEFT JOIN MAEST ON MAEPR.KOPR = MAEST.KOPR AND MAEST.KOBO = '01'
            LEFT JOIN TABPRE AS TABPRE01 ON MAEPR.KOPR = TABPRE01.KOPR AND TABPRE01.KOLT = '01P'
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
        
        // Asegurar que la salida esté en UTF-8 válido
        if (!mb_check_encoding($output, 'UTF-8')) {
            // Intentar convertir desde diferentes codificaciones comunes
            $output = mb_convert_encoding($output, 'UTF-8', 'ISO-8859-1');
        }
        
        // Limpiar caracteres inválidos de UTF-8
        $output = mb_convert_encoding($output, 'UTF-8', 'UTF-8');

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
            // Filtrar líneas vacías o de configuración
            if (empty($line) || 
                strpos($line, 'Setting') === 0 || 
                strpos($line, 'locale') === 0 || 
                strpos($line, 'using') === 0 ||
                strpos($line, 'CODIGO_PRODUCTO') !== false ||
                strpos($line, 'LINEA') !== false ||
                strpos($line, 'KOPR') === 0 && strpos($line, '|') === false ||
                strpos($line, 'rows affected)') !== false ||
                preg_match('/^\d+>$/', $line) || // Líneas que son solo números seguidos de >
                preg_match('/^Msg \d+/', $line)) { // Mensajes de error
                continue;
            }
            
            // Solo incluir líneas que tienen el delimitador | y contienen datos válidos
            if (strpos($line, '|') !== false && strlen($line) > 10) {
                $dataLines[] = $line;
                if ($this->getOutput()->isVerbose()) {
                    $this->line('Línea encontrada: ' . substr($line, 0, 100) . '...');
                }
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
            // Asegurar que la línea esté en UTF-8 válido antes de procesar
            if (!mb_check_encoding($line, 'UTF-8')) {
                $line = mb_convert_encoding($line, 'UTF-8', 'ISO-8859-1');
            }
            
            // Ahora la salida viene delimitada por '|'
            $fields = explode('|', $line);
            
            if (count($fields) < 10) {
                $this->warn('Línea con pocos campos: ' . count($fields) . ' - ' . substr($line, 0, 50));
                continue;
            }

            $codigoProducto = trim($fields[0]);
            if (empty($codigoProducto)) {
                continue;
            }

            // Limpiar y asegurar UTF-8 en el nombre del producto
            $nombreProductoRaw = trim($fields[1]);
            if (!mb_check_encoding($nombreProductoRaw, 'UTF-8')) {
                $nombreProductoRaw = mb_convert_encoding($nombreProductoRaw, 'UTF-8', 'ISO-8859-1');
            }
            $nombreProducto = $this->limpiarNombreProducto($nombreProductoRaw);
            $tipoProducto = trim($fields[2]);
            $unidadMedida = trim($fields[3]);
            // Función helper para convertir valores vacíos a 0 y manejar valores muy grandes
            $convertToFloat = function($value, $isDiscount = false) {
                $value = trim($value ?? '');
                // Manejar valores NULL o vacíos
                if (empty($value) || $value === 'NULL' || strtoupper($value) === 'NULL') {
                    return 0.0;
                }
                // Convertir a float
                $floatValue = (float)$value;
                
                // Si la conversión falla (NaN o infinity), retornar 0
                if (!is_finite($floatValue)) {
                    return 0.0;
                }
                
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
            
            // Precios 01P - verificar que los campos existan antes de procesarlos
            $precio01p = isset($fields[7]) ? $convertToFloat($fields[7]) : 0.0;
            $precio01pUd2 = isset($fields[8]) ? $convertToFloat($fields[8]) : 0.0;
            $descuentoMaximo01p = isset($fields[9]) ? $convertToFloat($fields[9], true) : 0.0;
            
            // Listas 02P y 03P no se usan (se sincronizan con 0 por ahora)
            $precio02p = 0.0;
            $precio02pUd2 = 0.0;
            $descuentoMaximo02p = 0.0;
            $precio03p = 0.0;
            $precio03pUd2 = 0.0;
            $descuentoMaximo03p = 0.0;

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
                // Actualizar producto existente - SOLO actualizar campos que vienen de la sincronización
                // Mantener campos existentes que no se están sincronizando (como KOPRRA, KOPRTE, etc.)
                $updateData = [
                    'NOKOPR' => $nombreProducto,
                    'TIPR' => $tipoProducto,
                    'UD01PR' => $unidadMedida,
                    'precio_01p' => $precio01p,
                    'precio_01p_ud2' => $precio01pUd2,
                    'descuento_maximo_01p' => $descuentoMaximo01p,
                    'precio_02p' => $precio02p,
                    'precio_02p_ud2' => $precio02pUd2,
                    'descuento_maximo_02p' => $descuentoMaximo02p,
                    'precio_03p' => $precio03p,
                    'precio_03p_ud2' => $precio03pUd2,
                    'descuento_maximo_03p' => $descuentoMaximo03p,
                    'stock_fisico' => $stockFisico,
                    'stock_comprometido' => $stockComprometido,
                    'stock_disponible' => $stockDisponible,
                    'ultima_sincronizacion' => now(),
                    'updated_at' => now(),
                    'activo' => true,
                ];
                
                DB::table('productos')->where('KOPR', $codigoProducto)->update($updateData);
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
    
    /**
     * Limpiar nombre del producto removiendo información adicional como "Múltiplo: X"
     */
    private function limpiarNombreProducto($nombreProducto)
    {
        if (empty($nombreProducto)) {
            return $nombreProducto;
        }
        
        $nombreLimpio = $nombreProducto;
        
        // Remover patrones como "xxxxxxxmultiplo: X" o "xxxxxmultiplo: X" al final (case insensitive)
        $nombreLimpio = preg_replace('/\s*xxxxxxx?multiplo:\s*\d+.*$/i', '', $nombreLimpio);
        // Remover "Múltiplo: X" o "multiplo: X" al final (con o sin acento, case insensitive)
        $nombreLimpio = preg_replace('/\s*[Mm][úu]ltiplo:\s*\d+.*$/i', '', $nombreLimpio);
        // Remover "MULTIPLO: X" al final
        $nombreLimpio = preg_replace('/\s*MULTIPLO:\s*\d+.*$/i', '', $nombreLimpio);
        // Remover "UN.Múltiplo: X" o "UN.MULTIPLO: X" al final
        $nombreLimpio = preg_replace('/\s*UN\.\s*[Mm][úu]?ltiplo:\s*\d+.*$/i', '', $nombreLimpio);
        // Remover la palabra "adicional" si aparece
        $nombreLimpio = preg_replace('/\s*adicional\s*/i', ' ', $nombreLimpio);
        // Limpiar espacios múltiples y recortar
        $nombreLimpio = preg_replace('/\s+/', ' ', trim($nombreLimpio));
        
        return $nombreLimpio;
    }
}