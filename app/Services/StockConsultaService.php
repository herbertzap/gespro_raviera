<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class StockConsultaService
{
    /**
     * Consultar stock desde SQL Server para un conjunto de productos
     * SIEMPRE actualiza MySQL con los valores obtenidos
     * 
     * @param array $codigosProductos Array de códigos de productos
     * @param bool $forzarConsulta Si es true, ignora cache y consulta siempre (por defecto true)
     * @return array Array asociativo ['CODIGO' => ['stock_fisico' => X, 'stock_comprometido' => Y]]
     */
    public function consultarStockDesdeSQLServer(array $codigosProductos, $forzarConsulta = true)
    {
        if (empty($codigosProductos)) {
            return [];
        }

        // SIEMPRE consultar SQL Server (sin cache) para obtener datos frescos
        $stocks = [];
        $codigosAConsultar = $codigosProductos;

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
            
            Log::info("📋 Consultando SQL Server para productos: " . implode(', ', $codigosAConsultar));
            Log::info("📋 Query SQL: " . $query);
            
            $tempFile = tempnam(sys_get_temp_dir(), 'sql_stock_');
            file_put_contents($tempFile, $query . "\ngo\nquit");
            
            $command = "tsql -H {$host} -p {$port} -U {$username} -P {$password} -D {$database} < {$tempFile} 2>&1";
            $output = shell_exec($command);
            
            unlink($tempFile);
            
            // Parsear resultado - tsql devuelve los datos en formato tabular
            Log::info("📋 Output completo de consulta múltiple: " . substr($output, 0, 500));
            
            $lines = explode("\n", $output);
            $headerFound = false;
            
            foreach ($lines as $line) {
                $line = trim($line);
                
                // Saltar líneas de configuración y numeración de tsql
                if (empty($line) || 
                    strpos($line, 'locale') !== false || 
                    strpos($line, 'Setting') !== false || 
                    strpos($line, 'rows affected') !== false ||
                    strpos($line, 'Msg ') !== false || 
                    strpos($line, 'Warning:') !== false ||
                    preg_match('/^\d+>$/', $line) || 
                    preg_match('/^\d+>\s+\d+>\s+\d+>/', $line) ||
                    preg_match('/^[0-9]+\s*>\s*$/', $line)) {  // Líneas como "1> " o "10> "
                    continue;
                }
                
                // Buscar header (KOPR o STOCK_FISICO)
                if (stripos($line, 'KOPR') !== false || (stripos($line, 'STOCK_FISICO') !== false && stripos($line, 'STOCK_COMPROMETIDO') !== false)) {
                    $headerFound = true;
                    Log::info("📋 Header encontrado: {$line}");
                    continue;
                }
                
                // Si encontramos el header, procesar líneas de datos
                if ($headerFound) {
                    // Intentar parsear línea usando regex (más robusto para tabs y espacios)
                    // Formato: CODIGO (alfanumérico) seguido de dos números (puede haber tabs o espacios)
                    if (preg_match('/^([0-9A-Z]{3,})[\s\t]+([0-9.]+)[\s\t]+([0-9.]+)/i', $line, $matches)) {
                        $codigo = trim($matches[1]);
                        $stockFisico = (float)trim($matches[2]);
                        $stockComprometido = (float)trim($matches[3]);
                        
                        // Validar que tenemos valores válidos
                        if (!empty($codigo) && strlen($codigo) >= 3 && is_numeric($stockFisico) && is_numeric($stockComprometido)) {
                            $stockData = [
                                'stock_fisico' => $stockFisico,
                                'stock_comprometido' => $stockComprometido,
                                'consultado_at' => now()->toDateTimeString()
                            ];
                            
                            $stocks[$codigo] = $stockData;
                            
                            // ACTUALIZAR MySQL inmediatamente con los valores obtenidos
                            $this->actualizarStockSiEsDiferente($codigo, $stockFisico, $stockComprometido);
                            Log::info("✅ Stock parseado con regex y actualizado en MySQL para {$codigo}: Físico={$stockFisico}, Comprometido={$stockComprometido}");
                        } else {
                            Log::warning("⚠️ Línea parseada con regex pero con datos inválidos: línea='{$line}', código='{$codigo}', stock_fisico={$stockFisico}, stock_comprometido={$stockComprometido}");
                        }
                    } else {
                        // Si el regex no funciona, intentar método alternativo: dividir por tabs o espacios
                        // Primero intentar con tabs (formato más común de tsql)
                        if (strpos($line, "\t") !== false) {
                            $parts = array_filter(explode("\t", $line), function($p) { return trim($p) !== ''; });
                            $parts = array_values($parts); // Reindexar
                        } else {
                            // Si no hay tabs, usar espacios múltiples
                    $parts = preg_split('/\s+/', $line);
                        }
                        
                        // Validar que tengamos al menos 3 partes
                    if (count($parts) >= 3) {
                        $codigo = trim($parts[0]);
                            $stockFisico = (float)trim($parts[1]);
                            $stockComprometido = (float)trim($parts[2]);
                        
                            // Validar que el código no esté vacío y sea válido
                            if (!empty($codigo) && strlen($codigo) >= 3 && is_numeric($stockFisico) && is_numeric($stockComprometido)) {
                        $stockData = [
                            'stock_fisico' => $stockFisico,
                            'stock_comprometido' => $stockComprometido,
                            'consultado_at' => now()->toDateTimeString()
                        ];
                        
                        $stocks[$codigo] = $stockData;
                                $this->actualizarStockSiEsDiferente($codigo, $stockFisico, $stockComprometido);
                                Log::info("✅ Stock parseado con split y actualizado en MySQL para {$codigo}: Físico={$stockFisico}, Comprometido={$stockComprometido}");
                            } else {
                                Log::warning("⚠️ Línea parseada con split pero con datos inválidos: línea='{$line}', código='{$codigo}', parts=" . json_encode($parts));
                            }
                        }
                    }
                }
            }
            
            // Para productos que no se encontraron en SQL Server, actualizar MySQL con 0
            foreach ($codigosAConsultar as $codigo) {
                if (!isset($stocks[$codigo])) {
                    $stockData = [
                        'stock_fisico' => 0,
                        'stock_comprometido' => 0,
                        'consultado_at' => now()->toDateTimeString()
                    ];
                    $stocks[$codigo] = $stockData;
                    // Actualizar MySQL con 0 si no se encontró
                    $this->actualizarStockSiEsDiferente($codigo, 0, 0);
                    Log::warning("⚠️ Producto {$codigo} no encontrado en SQL Server, actualizado con 0");
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
            
            // SIEMPRE actualizar (no verificar si es diferente)
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
                
            Log::info("✅ Stock ACTUALIZADO en MySQL para {$codigo}: Físico={$stockFisico}, Comprometido={$stockComprometido}, Disponible={$stockDisponible}");
                return true;
            
        } catch (\Exception $e) {
            Log::error("Error actualizando stock para {$codigo}: " . $e->getMessage());
            return false;
        }
    }
}

