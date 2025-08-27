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
                SELECT TOP 20
                    MAEPR.KOPR AS CODIGO_PRODUCTO,
                    MAEPR.NOKOPR AS NOMBRE_PRODUCTO,
                    ISNULL(MAEST.KOBO, '01') AS CODIGO_BODEGA,
                    ISNULL(TABBO.NOKOBO, 'BODEGA PRINCIPAL') AS NOMBRE_BODEGA,
                    ISNULL(MAEST.STFI1, 0) AS STOCK_FISICO,
                    ISNULL(MAEST.STOCNV1, 0) AS STOCK_COMPROMETIDO,
                    ISNULL(MAEST.STFI1 - MAEST.STOCNV1, 0) AS STOCK_DISPONIBLE,
                    ISNULL(MAEPR.UD01PR, 'UN') AS UNIDAD_MEDIDA,
                    ISNULL(TABPRE.PP01UD, 0) AS PRECIO_VENTA
                FROM MAEPR
                LEFT JOIN MAEST ON MAEPR.KOPR = MAEST.KOPR AND MAEST.KOBO = '01'
                LEFT JOIN TABBO ON MAEST.KOBO = TABBO.KOBO
                LEFT JOIN TABPRE ON MAEPR.KOPR = TABPRE.KOPR AND TABPRE.KOLT = '01'
                WHERE MAEPR.ATPR = 'SI' AND MAEPR.KOPR LIKE '000001%'
                ORDER BY MAEPR.KOPR
            ";
            
            $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
            file_put_contents($tempFile, $query . "\ngo\nquit");
            
            $command = "tsql -H {$host} -p {$port} -U {$username} -P {$password} -D {$database} < {$tempFile} 2>&1";
            $output = shell_exec($command);
            
            unlink($tempFile);
            
            $productos = $this->parsearProductosStock($output);
            
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
                strpos($line, 'Setting') !== false || strpos($line, '1>') !== false ||
                strpos($line, '2>') !== false || strpos($line, 'rows affected') !== false ||
                strpos($line, 'Msg ') !== false || strpos($line, 'Warning:') !== false ||
                strpos($line, 'CODIGO_PRODUCTO') !== false) {
                continue;
            }
            
            // Parsear línea de producto
            if (preg_match('/^([A-Z0-9]+)\s+"?([^"]+)"?\s+"?([^"]*)"?\s+"?([^"]*)"?\s+([0-9.]+)\s+([0-9.]+)\s+([0-9.-]+)\s+"?([^"]*)"?\s+([0-9.]+)/', $line, $matches)) {
                $productos[] = [
                    'codigo_producto' => trim($matches[1]),
                    'nombre_producto' => trim($matches[2]),
                    'codigo_bodega' => trim($matches[3]) ?: '01',
                    'nombre_bodega' => trim($matches[4]) ?: 'BODEGA PRINCIPAL',
                    'stock_fisico' => (float)$matches[5],
                    'stock_comprometido' => (float)$matches[6],
                    'stock_disponible' => (float)$matches[7],
                    'unidad_medida' => trim($matches[8]) ?: 'UN',
                    'precio_venta' => (float)$matches[9]
                ];
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
                                   ->where('codigo_bodega', '01')
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
                                   ->where('codigo_bodega', '01')
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
                                   ->where('codigo_bodega', '01')
                                   ->first();
            
            if ($stockLocal) {
                $stockLocal->liberarStock($producto['cantidad']);
            }
        }
    }
} 