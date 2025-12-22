<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SincronizarProductoEspecifico extends Command
{
    protected $signature = 'producto:sincronizar-especifico {codigo}';
    protected $description = 'Sincroniza un producto específico desde SQL Server a MySQL';

    public function handle()
    {
        $codigoProducto = $this->argument('codigo');
        
        $this->info("Sincronizando producto: {$codigoProducto}");
        
        $host = env('SQLSRV_EXTERNAL_HOST');
        $port = env('SQLSRV_EXTERNAL_PORT', '1433');
        $database = env('SQLSRV_EXTERNAL_DATABASE');
        $username = env('SQLSRV_EXTERNAL_USERNAME');
        $password = env('SQLSRV_EXTERNAL_PASSWORD');
        
        // Consulta SQL específica para este producto
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
            WHERE MAEPR.KOPR = '{$codigoProducto}' AND MAEPR.ATPR <> 'N' AND MAEPR.ATPR <> 'OCU'
        ";

        $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
        $singleLineQuery = str_replace(["\n", "\r"], ' ', $query);
        file_put_contents($tempFile, $singleLineQuery . "\ngo\nquit");

        $command = "tsql -H {$host} -p {$port} -U {$username} -P {$password} -D {$database} < {$tempFile} 2>&1";
        $output = shell_exec($command);

        unlink($tempFile);

        if (empty($output)) {
            $this->error('No se pudo obtener datos del servidor SQL Server');
            return 1;
        }

        // Buscar la línea de datos
        $lines = explode("\n", trim($output));
        $dataLine = null;
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || 
                strpos($line, 'Setting') === 0 || 
                strpos($line, 'locale') === 0 || 
                strpos($line, 'using') === 0 ||
                strpos($line, 'LINEA') !== false ||
                strpos($line, 'rows affected)') !== false) {
                continue;
            }
            
            if (strpos($line, $codigoProducto) === 0) {
                $dataLine = $line;
                break;
            }
        }

        if (!$dataLine) {
            $this->error('No se encontró el producto en SQL Server');
            return 1;
        }

        $this->info("Línea encontrada: {$dataLine}");
        
        // Parsear los campos
        $fields = explode('|', $dataLine);
        
        if (count($fields) < 10) {
            $this->error('Línea con pocos campos: ' . count($fields));
            return 1;
        }

        $convertToFloat = function($value, $isDiscount = false) {
            $value = trim($value ?? '');
            if (empty($value) || $value === 'NULL') {
                return 0.0;
            }
            $floatValue = (float)$value;
            
            if ($isDiscount && $floatValue > 100) {
                return 0.0;
            }
            
            if ($floatValue > 999999999.99) {
                return 0.0;
            }
            return $floatValue;
        };
        
        $codigo = trim($fields[0]);
        $nombreProducto = trim($fields[1]);
        $tipoProducto = trim($fields[2]);
        $unidadMedida = trim($fields[3]);
        $stockFisico = $convertToFloat($fields[4] ?? '');
        $stockComprometido = $convertToFloat($fields[5] ?? '');
        $stockDisponible = $convertToFloat($fields[6] ?? '');
        
        // Precios 01P
        $precio01p = $convertToFloat($fields[7] ?? '');
        $precio01pUd2 = $convertToFloat($fields[8] ?? '');
        $descuentoMaximo01p = $convertToFloat($fields[9] ?? '', true);
        
        $this->info("Código: {$codigo}");
        $this->info("Nombre: {$nombreProducto}");
        $this->info("Precio 01P (campo 7): '{$fields[7]}' -> {$precio01p}");
        $this->info("Precio 01P UD2 (campo 8): '{$fields[8]}' -> {$precio01pUd2}");
        $this->info("Descuento Máximo (campo 9): '{$fields[9]}' -> {$descuentoMaximo01p}");
        
        // Verificar si el producto existe
        $productoExistente = DB::table('productos')->where('KOPR', $codigo)->first();

        $data = [
            'precio_01p' => $precio01p,
            'precio_01p_ud2' => $precio01pUd2,
            'descuento_maximo_01p' => $descuentoMaximo01p,
            'stock_fisico' => $stockFisico,
            'stock_comprometido' => $stockComprometido,
            'stock_disponible' => $stockDisponible,
            'ultima_sincronizacion' => now(),
        ];

        if ($productoExistente) {
            DB::table('productos')->where('KOPR', $codigo)->update($data);
            $this->info("✅ Producto actualizado exitosamente");
        } else {
            $this->error("❌ El producto no existe en MySQL. Debe sincronizarse primero.");
            return 1;
        }

        return 0;
    }
}


