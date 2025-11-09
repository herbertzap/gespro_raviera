<?php

namespace App\Services;

use App\Models\StockLocal;
use Illuminate\Support\Facades\Log;

class StockService
{
    /**
     * Sincronizar stock desde SQL Server
     */
    public function sincronizarStockDesdeSQLServer()
    {
        try {
            $host = env('SQLSRV_EXTERNAL_HOST');
            $port = env('SQLSRV_EXTERNAL_PORT', '1433');
            $database = env('SQLSRV_EXTERNAL_DATABASE');
            $username = env('SQLSRV_EXTERNAL_USERNAME');
            $password = env('SQLSRV_EXTERNAL_PASSWORD');
            
            $query = "
                SELECT 
                    CAST(MAEPR.KOPR AS VARCHAR(4000)) + '|' +
                    CAST(MAEPR.NOKOPR AS VARCHAR(4000)) + '|' +
                    CAST(ISNULL(TABBO.NOKOBO, 'BODEGA LIB') AS VARCHAR(4000)) + '|' +
                    CAST(ISNULL(MAEST.KOBO, 'LIB') AS VARCHAR(4000)) + '|' +
                    CAST(ISNULL(MAEST.STFI1, 0) AS VARCHAR(4000)) + '|' +
                    CAST(ISNULL(MAEST.STOCNV1, 0) AS VARCHAR(4000)) + '|' +
                    CAST(ISNULL(MAEPR.UD01PR, 'UN') AS VARCHAR(4000)) + '|' +
                    CAST(ISNULL(TABPRE.PP01UD, 0) AS VARCHAR(4000)) AS DATOS
                FROM MAEST
                LEFT JOIN MAEPR ON MAEPR.KOPR = MAEST.KOPR
                LEFT JOIN TABBO ON MAEST.KOBO = TABBO.KOBO
                LEFT JOIN TABPRE ON MAEPR.KOPR = TABPRE.KOPR AND TABPRE.KOLT = '01'
                WHERE MAEST.KOBO = 'LIB'
                ORDER BY MAEPR.KOPR
            ";
            
            $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
            file_put_contents($tempFile, $query . "\ngo\nquit");
            
            $command = "tsql -H {$host} -p {$port} -U {$username} -P {$password} -D {$database} < {$tempFile} 2>&1";
            $output = shell_exec($command);
            
            unlink($tempFile);
            
            $productos = $this->parsearProductosStock($output);
            
            Log::info('Productos parseados: ' . count($productos));
            if (count($productos) > 0) {
                Log::info('Primer producto: ' . json_encode($productos[0]));
            }
            
            foreach ($productos as $producto) {
                $this->actualizarStockLocal($producto);
            }
            
            Log::info('✅ Stock sincronizado desde SQL Server: ' . count($productos) . ' productos');
            return count($productos);
            
        } catch (\Exception $e) {
            Log::error('Error sincronizando stock: ' . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Parsear la salida de tsql para productos
     */
    private function parsearProductosStock($output)
    {
        $productos = [];
        $lines = explode("\n", $output);
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Saltar líneas de configuración
            if (empty($line) || strpos($line, 'locale') !== false || 
                strpos($line, 'Setting') !== false || strpos($line, 'rows affected') !== false ||
                strpos($line, 'Msg ') !== false || strpos($line, 'Warning:') !== false ||
                strpos($line, 'DATOS') !== false || strpos($line, '1>') !== false) {
                continue;
            }
            
            // Parsear línea de producto usando '|'
            if (strpos($line, '|') !== false) {
                $fields = explode('|', $line);
                
                if (count($fields) >= 8) {
                    $stockFisico = (float)$fields[4];
                    $stockComprometidoSQL = (float)$fields[5];
                    
                    // Obtener stock NVV comprometido local (MySQL) para este producto
                    $stockComprometidoLocal = \App\Models\StockComprometido::calcularStockComprometido(trim($fields[0]));
                    
                    // Calcular stock disponible: STFI1 - (STOCNV1 + stock_nvv_mysql)
                    $stockDisponible = $stockFisico - ($stockComprometidoSQL + $stockComprometidoLocal);
                    
                    $producto = [
                        'codigo_producto' => trim($fields[0]),
                        'nombre_producto' => trim($fields[1]),
                        'nombre_bodega' => (trim($fields[2]) ?: 'BODEGA LIB'),
                        'codigo_bodega' => (trim($fields[3]) ?: 'LIB'),
                        'stock_fisico' => $stockFisico,
                        'stock_comprometido' => $stockComprometidoSQL,
                        'stock_disponible' => $stockDisponible,
                        'unidad_medida' => trim($fields[6]) ?: 'UN',
                        'precio_venta' => (float)$fields[7]
                    ];
                    
                    // Log para debuggear el primer producto problemático
                    if (strpos($fields[0], '1002225000000') !== false) {
                        Log::info('Producto problemático parseado: ' . json_encode($producto));
                        Log::info('Fields originales: ' . json_encode($fields));
                    }
                    
                    $productos[] = $producto;
                }
            }
        }
        
        return $productos;
    }
    
    /**
     * Actualizar o crear stock local
     */
    private function actualizarStockLocal($producto)
    {
        $stockLocal = StockLocal::updateOrCreate(
            [
                'codigo_producto' => $producto['codigo_producto'],
                'codigo_bodega' => $producto['codigo_bodega']
            ],
            [
                'nombre_producto' => $producto['nombre_producto'],
                'nombre_bodega' => $producto['nombre_bodega'],
                'stock_fisico' => $producto['stock_fisico'],
                'stock_disponible' => $producto['stock_disponible'],
                'unidad_medida' => $producto['unidad_medida'],
                'precio_venta' => $producto['precio_venta'],
                'activo' => true,
                'ultima_actualizacion' => now()
            ]
        );
        
        // Actualizar la tabla productos (usada por el cotizador)
        try {
            \DB::table('productos')
                ->where('KOPR', $producto['codigo_producto'])
                ->update([
                    'NOKOPR' => $producto['nombre_producto'],
                    'UD01PR' => $producto['unidad_medida'],
                    'stock_fisico' => $producto['stock_fisico'],
                    'stock_comprometido' => $producto['stock_comprometido'] ?? 0,
                    'stock_disponible' => $producto['stock_disponible'],
                    'updated_at' => now()
                ]);
        } catch (\Exception $e) {
            Log::warning('No se pudo actualizar tabla productos para ' . $producto['codigo_producto'] . ': ' . $e->getMessage());
        }
        
        return $stockLocal;
    }
    
    /**
     * Validar stock para una cotización
     */
    public function validarStockCotizacion($productos)
    {
        $resultado = [
            'valida' => true,
            'productos_sin_stock' => [],
            'productos_insuficientes' => [],
            'mensajes' => []
        ];
        
        foreach ($productos as $producto) {
            $stockLocal = StockLocal::where('codigo_producto', $producto['codigo'])
                                   ->where('codigo_bodega', 'LIB')
                                   ->first();
            
            if (!$stockLocal) {
                $resultado['productos_sin_stock'][] = $producto['codigo'];
                $resultado['valida'] = false;
                $resultado['mensajes'][] = "Producto {$producto['codigo']} no encontrado en stock local";
                continue;
            }
            
            if (!$stockLocal->tieneStockSuficiente($producto['cantidad'])) {
                $resultado['productos_insuficientes'][] = [
                    'codigo' => $producto['codigo'],
                    'nombre' => $stockLocal->nombre_producto,
                    'stock_disponible' => $stockLocal->stock_disponible,
                    'cantidad_solicitada' => $producto['cantidad']
                ];
                $resultado['valida'] = false;
                $resultado['mensajes'][] = "Stock insuficiente para {$producto['codigo']}: disponible {$stockLocal->stock_disponible}, solicitado {$producto['cantidad']}";
            }
        }
        
        return $resultado;
    }
    
    /**
     * Validar cliente para cotización
     */
    public function validarClienteParaCotizacion($codigoCliente)
    {
        try {
            // Por ahora, validación simple sin depender de métodos que no existen
            $resultado = [
                'valido' => true,
                'alertas' => [],
                'mensajes' => []
            ];
            
            // Aquí se pueden agregar validaciones específicas del cliente
            // Por ejemplo, verificar si está bloqueado, etc.
            
            return $resultado;
            
        } catch (\Exception $e) {
            Log::error('Error validando cliente: ' . $e->getMessage());
            return [
                'valido' => false,
                'alertas' => [],
                'mensajes' => ['Error validando cliente']
            ];
        }
    }
    
    /**
     * Reservar stock para cotización
     */
    public function reservarStockCotizacion($productos)
    {
        $reservas = [];
        
        foreach ($productos as $producto) {
            $stockLocal = StockLocal::where('codigo_producto', $producto['codigo'])
                                   ->where('codigo_bodega', 'LIB')
                                   ->first();
            
            if ($stockLocal && $stockLocal->reservarStock($producto['cantidad'])) {
                $reservas[] = [
                    'producto' => $producto['codigo'],
                    'cantidad' => $producto['cantidad'],
                    'reservado' => true
                ];
            } else {
                $reservas[] = [
                    'producto' => $producto['codigo'],
                    'cantidad' => $producto['cantidad'],
                    'reservado' => false,
                    'error' => 'No se pudo reservar stock'
                ];
            }
        }
        
        return $reservas;
    }
    
    /**
     * Liberar stock reservado
     */
    public function liberarStockCotizacion($productos)
    {
        foreach ($productos as $producto) {
            $stockLocal = StockLocal::where('codigo_producto', $producto['codigo'])
                                   ->where('codigo_bodega', 'LIB')
                                   ->first();
            
            if ($stockLocal) {
                $stockLocal->liberarStock($producto['cantidad']);
            }
        }
    }
} 