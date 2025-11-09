<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class StockConsultaService
{
    /**
     * Consultar stock desde SQL Server para un conjunto de productos (con cache)
     * 
     * @param array $codigosProductos Array de códigos de productos
     * @return array Array asociativo ['CODIGO' => ['stock_fisico' => X, 'stock_comprometido' => Y]]
     */
    public function consultarStockDesdeSQLServer(array $codigosProductos)
    {
        if (empty($codigosProductos)) {
            return [];
        }

        // Filtrar productos que ya están en cache (últimos 5 minutos)
        $stocks = [];
        $codigosAConsultar = [];
        
        foreach ($codigosProductos as $codigo) {
            $cacheKey = "stock_sql_{$codigo}";
            $cached = Cache::get($cacheKey);
            
            if ($cached !== null) {
                $stocks[$codigo] = $cached;
            } else {
                $codigosAConsultar[] = $codigo;
            }
        }

        // Si todos estaban en cache, retornar
        if (empty($codigosAConsultar)) {
            return $stocks;
        }

        // Consultar SQL Server solo para los que no están en cache
        try {
            $host = env('SQLSRV_EXTERNAL_HOST');
            $port = env('SQLSRV_EXTERNAL_PORT', '1433');
            $database = env('SQLSRV_EXTERNAL_DATABASE');
            $username = env('SQLSRV_EXTERNAL_USERNAME');
            $password = env('SQLSRV_EXTERNAL_PASSWORD');

            // Crear lista de códigos para el WHERE IN
            $codigosEscapados = array_map(function($codigo) {
                return "'" . addslashes(trim($codigo)) . "'";
            }, $codigosAConsultar);
            
            $codigosList = implode(',', $codigosEscapados);

            $query = "
                SELECT 
                    CAST(MAEST.KOPR AS VARCHAR(4000)) AS KOPR,
                    CAST(ISNULL(MAEST.STFI1, 0) AS FLOAT) AS STOCK_FISICO,
                    CAST(ISNULL(MAEST.STOCNV1, 0) AS FLOAT) AS STOCK_COMPROMETIDO
                FROM MAEST
                WHERE MAEST.KOBO = 'LIB'
                AND MAEST.KOPR IN ({$codigosList})
            ";
            
            $tempFile = tempnam(sys_get_temp_dir(), 'sql_stock_');
            file_put_contents($tempFile, $query . "\ngo\nquit");
            
            $command = "tsql -H {$host} -p {$port} -U {$username} -P {$password} -D {$database} < {$tempFile} 2>&1";
            $output = shell_exec($command);
            
            unlink($tempFile);
            
            // Parsear resultado - tsql devuelve los datos en formato tabular
            $lines = explode("\n", $output);
            $headerFound = false;
            
            foreach ($lines as $line) {
                $line = trim($line);
                
                // Saltar líneas de configuración
                if (empty($line) || strpos($line, 'locale') !== false || 
                    strpos($line, 'Setting') !== false || strpos($line, 'rows affected') !== false ||
                    strpos($line, 'Msg ') !== false || strpos($line, 'Warning:') !== false ||
                    strpos($line, '1>') !== false || strpos($line, '2>') !== false) {
                    continue;
                }
                
                // Buscar header (KOPR)
                if (stripos($line, 'KOPR') !== false) {
                    $headerFound = true;
                    continue;
                }
                
                // Si encontramos el header, procesar líneas de datos
                if ($headerFound) {
                    // Parsear línea (formato: CODIGO STOCK_FISICO STOCK_COMPROMETIDO)
                    // Puede haber espacios múltiples, usar preg_split
                    $parts = preg_split('/\s+/', $line);
                    if (count($parts) >= 3) {
                        $codigo = trim($parts[0]);
                        $stockFisico = (float)$parts[1];
                        $stockComprometido = (float)$parts[2];
                        
                        // Validar que el código no esté vacío
                        if (empty($codigo)) {
                            continue;
                        }
                        
                        $stockData = [
                            'stock_fisico' => $stockFisico,
                            'stock_comprometido' => $stockComprometido,
                            'consultado_at' => now()->toDateTimeString()
                        ];
                        
                        $stocks[$codigo] = $stockData;
                        
                        // Guardar en cache por 5 minutos
                        Cache::put("stock_sql_{$codigo}", $stockData, 300);
                    }
                }
            }
            
            // Para productos que no se encontraron en SQL Server, guardar valores por defecto en cache
            foreach ($codigosAConsultar as $codigo) {
                if (!isset($stocks[$codigo])) {
                    $stockData = [
                        'stock_fisico' => 0,
                        'stock_comprometido' => 0,
                        'consultado_at' => now()->toDateTimeString()
                    ];
                    $stocks[$codigo] = $stockData;
                    Cache::put("stock_sql_{$codigo}", $stockData, 300);
                }
            }
            
            Log::info('Stock consultado desde SQL Server para ' . count($codigosAConsultar) . ' productos');
            
        } catch (\Exception $e) {
            Log::error('Error consultando stock desde SQL Server: ' . $e->getMessage());
            
            // En caso de error, retornar valores por defecto
            foreach ($codigosAConsultar as $codigo) {
                if (!isset($stocks[$codigo])) {
                    $stocks[$codigo] = [
                        'stock_fisico' => 0,
                        'stock_comprometido' => 0,
                        'consultado_at' => now()->toDateTimeString()
                    ];
                }
            }
        }

        return $stocks;
    }

    /**
     * Actualizar stock en MySQL solo si es diferente
     */
    public function actualizarStockSiEsDiferente(string $codigo, float $stockFisico, float $stockComprometido)
    {
        try {
            // Obtener stock actual desde MySQL
            $producto = DB::table('productos')->where('KOPR', $codigo)->first();
            
            if (!$producto) {
                return false;
            }
            
            $stockFisicoActual = (float)($producto->stock_fisico ?? 0);
            $stockComprometidoActual = (float)($producto->stock_comprometido ?? 0);
            
            // Solo actualizar si es diferente
            if ($stockFisicoActual != $stockFisico || $stockComprometidoActual != $stockComprometido) {
                // Calcular stock comprometido local (NVV)
                $stockComprometidoLocal = \App\Models\StockComprometido::calcularStockComprometido($codigo);
                
                // Stock disponible = stock físico - (stock comprometido SQL + stock comprometido local)
                $stockDisponible = $stockFisico - ($stockComprometido + $stockComprometidoLocal);
                
                DB::table('productos')
                    ->where('KOPR', $codigo)
                    ->update([
                        'stock_fisico' => $stockFisico,
                        'stock_comprometido' => $stockComprometido,
                        'stock_disponible' => max(0, $stockDisponible),
                        'updated_at' => now()
                    ]);
                
                Log::debug("Stock actualizado para {$codigo}: Físico={$stockFisico}, Comprometido={$stockComprometido}, Disponible={$stockDisponible}");
                return true;
            }
            
            return false;
            
        } catch (\Exception $e) {
            Log::error("Error actualizando stock para {$codigo}: " . $e->getMessage());
            return false;
        }
    }
}

