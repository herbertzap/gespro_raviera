<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Producto;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SincronizarProductosDiario extends Command
{
    protected $signature = 'productos:sincronizar-diario';
    protected $description = 'Sincronizar productos desde SQL Server diariamente';

    public function handle()
    {
        $this->info("🔄 Iniciando sincronización diaria de productos...");
        
        try {
            $host = env('SQLSRV_EXTERNAL_HOST');
            $port = env('SQLSRV_EXTERNAL_PORT', '1433');
            $database = env('SQLSRV_EXTERNAL_DATABASE');
            $username = env('SQLSRV_EXTERNAL_USERNAME');
            $password = env('SQLSRV_EXTERNAL_PASSWORD');
            
            // Query para obtener productos
            $query = "
                SELECT 
                    CAST(MAEPR.KOPR AS VARCHAR(20)) AS KOPR,
                    CAST(MAEPR.NOKOPR AS VARCHAR(255)) AS NOKOPR,
                    CAST(MAEPR.KOPRRA AS VARCHAR(20)) AS KOPRRA,
                    CAST(MAEPR.NOKOPRRA AS VARCHAR(255)) AS NOKOPRRA,
                    CAST(MAEPR.KOPRTE AS VARCHAR(20)) AS KOPRTE,
                    CAST(MAEPR.UD01PR AS VARCHAR(10)) AS UD01PR,
                    CAST(MAEPR.UD02PR AS VARCHAR(10)) AS UD02PR,
                    CAST(MAEPR.RLUD AS FLOAT) AS RLUD,
                    CAST(MAEPR.POIVPR AS FLOAT) AS POIVPR,
                    CAST(MAEPR.RGPR AS VARCHAR(10)) AS RGPR,
                    CAST(MAEPR.MRPR AS VARCHAR(50)) AS MRPR,
                    CAST(MAEPR.FMPR AS VARCHAR(50)) AS FMPR,
                    CAST(MAEPR.PFPR AS VARCHAR(50)) AS PFPR,
                    CAST(MAEPR.HFPR AS VARCHAR(50)) AS HFPR,
                    CAST(MAEPR.DIVISIBLE AS BIT) AS DIVISIBLE,
                    CAST(MAEPR.FECRPR AS DATETIME) AS FECRPR,
                    CAST(MAEPR.DIVISIBLE2 AS BIT) AS DIVISIBLE2
                FROM dbo.MAEPR 
                WHERE MAEPR.KOPR != '' 
                AND MAEPR.KOPR IS NOT NULL
                AND MAEPR.ATPR != 'OCU'
                ORDER BY MAEPR.NOKOPR
            ";
            
            // Crear archivo temporal con la consulta
            $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
            file_put_contents($tempFile, $query . "\ngo\nquit");
            
            // Ejecutar consulta usando tsql
            $command = "tsql -H {$host} -p {$port} -U {$username} -P {$password} -D {$database} < {$tempFile} 2>&1";
            $output = shell_exec($command);
            
            // Limpiar archivo temporal
            unlink($tempFile);
            
            if (!$output || str_contains($output, 'error')) {
                throw new \Exception('Error ejecutando consulta tsql: ' . $output);
            }
            
            $this->info("📋 Procesando datos...");
            
            // Procesar la salida línea por línea
            $lines = explode("\n", $output);
            $productosExternos = [];
            $lineaNumero = 0;
            
            // Asegurar que la salida esté en UTF-8
            $output = mb_convert_encoding($output, 'UTF-8', 'UTF-8');
            
            foreach ($lines as $line) {
                $line = trim($line);
                $lineaNumero++;
                
                // Saltar líneas vacías o de configuración
                if (empty($line) || 
                    strpos($line, 'locale') !== false || 
                    strpos($line, 'Setting') !== false || 
                    strpos($line, 'Msg ') !== false || 
                    strpos($line, 'Warning:') !== false ||
                    preg_match('/^\d+>$/', $line) ||
                    preg_match('/^\d+>\s+\d+>\s+\d+>/', $line) ||
                    strpos($line, 'rows affected') !== false ||
                    strpos($line, 'KOPR') !== false) {
                    continue;
                }
                
                // Buscar líneas que empiecen con códigos de producto
                if (preg_match('/^([A-Z0-9]{1,20})/', $line)) {
                    $producto = $this->extraerProductoDeLinea($line);
                    if ($producto) {
                        $productosExternos[] = $producto;
                    }
                }
            }

            $this->info("📊 Total de productos encontrados: " . count($productosExternos));
            
            if (count($productosExternos) == 0) {
                $this->warn("⚠️ No se encontraron productos para sincronizar");
                return;
            }

            $this->info("💾 Guardando productos en base de datos local...");
            
            $contador = 0;
            $actualizados = 0;
            $creados = 0;

            foreach ($productosExternos as $productoExterno) {
                $contador++;
                
                if ($contador % 1000 == 0) {
                    $this->info("Procesando producto {$contador} de " . count($productosExternos));
                }

                // Buscar si el producto ya existe
                $productoLocal = Producto::where('KOPR', $productoExterno['KOPR'])->first();

                $datosProducto = [
                    'TIPR' => $productoExterno['KOPR'] ? substr($productoExterno['KOPR'], 0, 2) : null,
                    'KOPR' => $productoExterno['KOPR'],
                    'NOKOPR' => $productoExterno['NOKOPR'],
                    'KOPRRA' => $productoExterno['KOPRRA'],
                    'NOKOPRRA' => $productoExterno['NOKOPRRA'],
                    'KOPRTE' => $productoExterno['KOPRTE'],
                    'UD01PR' => $productoExterno['UD01PR'],
                    'UD02PR' => $productoExterno['UD02PR'],
                    'RLUD' => $productoExterno['RLUD'],
                    'POIVPR' => $productoExterno['POIVPR'],
                    'RGPR' => $productoExterno['RGPR'],
                    'MRPR' => $productoExterno['MRPR'],
                    'FMPR' => $productoExterno['FMPR'],
                    'PFPR' => $productoExterno['PFPR'],
                    'HFPR' => $productoExterno['HFPR'],
                    'DIVISIBLE' => $productoExterno['DIVISIBLE'],
                    'FECRPR' => $productoExterno['FECRPR'],
                    'DIVISIBLE2' => $productoExterno['DIVISIBLE2'],
                    'ultima_sincronizacion' => now(),
                ];

                if ($productoLocal) {
                    // Actualizar producto existente
                    $productoLocal->update($datosProducto);
                    $actualizados++;
                } else {
                    // Crear nuevo producto
                    Producto::create($datosProducto);
                    $creados++;
                }
            }

            $this->info("✅ Sincronización diaria completada exitosamente!");
            $this->info("📈 Productos creados: {$creados}");
            $this->info("🔄 Productos actualizados: {$actualizados}");
            $this->info("📊 Total procesados: " . ($creados + $actualizados));

            // Registrar en el log
            Log::info("Sincronización diaria de productos completada", [
                'creados' => $creados,
                'actualizados' => $actualizados,
                'total' => $creados + $actualizados
            ]);

        } catch (\Exception $e) {
            $this->error("❌ Error durante la sincronización diaria: " . $e->getMessage());
            Log::error("Error en sincronización diaria de productos", [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return 1;
        }

        return 0;
    }

    /**
     * Extraer producto de una línea de texto
     */
    private function extraerProductoDeLinea($line)
    {
        try {
            // Dividir la línea por espacios para extraer campos individuales
            $fields = preg_split('/\s+/', trim($line));
            
            if (count($fields) < 5) {
                return null;
            }
            
            // Extraer campos básicos con validación de tipos y límites
            $producto = [
                'KOPR' => substr($fields[0] ?? '', 0, 20),
                'NOKOPR' => substr($fields[1] ?? '', 0, 255),
                'KOPRRA' => substr($fields[2] ?? '', 0, 20),
                'NOKOPRRA' => substr($fields[3] ?? '', 0, 255),
                'KOPRTE' => substr($fields[4] ?? '', 0, 20),
                'UD01PR' => substr($fields[5] ?? '', 0, 10),
                'UD02PR' => substr($fields[6] ?? '', 0, 10),
                'RLUD' => (float)($fields[7] ?? 1.0),
                'POIVPR' => (float)($fields[8] ?? 0.0),
                'RGPR' => substr($fields[9] ?? '', 0, 10),
                'MRPR' => substr($fields[10] ?? '', 0, 50),
                'FMPR' => substr($fields[11] ?? '', 0, 50),
                'PFPR' => substr($fields[12] ?? '', 0, 50),
                'HFPR' => substr($fields[13] ?? '', 0, 50),
                'DIVISIBLE' => ($fields[14] ?? 'N') === 'S' ? 1 : 0,
                'FECRPR' => null, // Por ahora lo dejamos null
                'DIVISIBLE2' => ($fields[16] ?? 'N') === 'S' ? 1 : 0,
            ];
            
            // Validar que el código del producto no esté vacío
            if (empty($producto['KOPR'])) {
                return null;
            }
            
            return $producto;
            
        } catch (\Exception $e) {
            return null;
        }
    }
}
