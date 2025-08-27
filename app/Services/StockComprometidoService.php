<?php

namespace App\Services;

use App\Models\StockComprometido;
use App\Models\Cotizacion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StockComprometidoService
{
    /**
     * Comprometer stock para una cotización
     */
    public function comprometerStock($cotizacionId, $productos)
    {
        try {
            DB::beginTransaction();
            
            $cotizacion = Cotizacion::with('detalles')->findOrFail($cotizacionId);
            $stockComprometido = [];
            
            foreach ($productos as $producto) {
                // Verificar stock disponible real
                $stockDisponible = $this->obtenerStockDisponible($producto['codigo']);
                $stockComprometidoActual = StockComprometido::calcularStockComprometido($producto['codigo']);
                $stockRealDisponible = $stockDisponible - $stockComprometidoActual;
                
                if ($stockRealDisponible < $producto['cantidad']) {
                    throw new \Exception("Stock insuficiente para el producto {$producto['codigo']}. Disponible: {$stockRealDisponible}, Solicitado: {$producto['cantidad']}");
                }
                
                // Crear registro de stock comprometido
                $stockComprometido[] = StockComprometido::create([
                    'producto_codigo' => $producto['codigo'],
                    'producto_nombre' => $producto['nombre'],
                    'bodega_codigo' => '001', // Bodega por defecto
                    'bodega_nombre' => 'Bodega Principal',
                    'cantidad_comprometida' => $producto['cantidad'],
                    'stock_disponible_original' => $stockDisponible,
                    'stock_disponible_actual' => $stockRealDisponible,
                    'unidad_medida' => $producto['unidad_medida'] ?? 'UN',
                    'cotizacion_id' => $cotizacionId,
                    'cotizacion_estado' => $cotizacion->estado,
                    'vendedor_id' => $cotizacion->vendedor_id,
                    'vendedor_nombre' => $cotizacion->vendedor_nombre,
                    'cliente_codigo' => $cotizacion->cliente_codigo,
                    'cliente_nombre' => $cotizacion->cliente_nombre,
                    'fecha_compromiso' => now(),
                    'observaciones' => 'Stock comprometido por cotización'
                ]);
                
                Log::info("Stock comprometido: Producto {$producto['codigo']}, Cantidad: {$producto['cantidad']}, Cotización: {$cotizacionId}");
            }
            
            DB::commit();
            
            return [
                'success' => true,
                'stock_comprometido' => $stockComprometido,
                'message' => 'Stock comprometido exitosamente'
            ];
            
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error comprometiendo stock: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Liberar stock comprometido
     */
    public function liberarStock($cotizacionId, $motivo = null)
    {
        try {
            DB::beginTransaction();
            
            $stockComprometido = StockComprometido::porCotizacion($cotizacionId)->activo()->get();
            
            foreach ($stockComprometido as $stock) {
                $stock->liberar($motivo);
                Log::info("Stock liberado: Producto {$stock->producto_codigo}, Cantidad: {$stock->cantidad_comprometida}, Cotización: {$cotizacionId}");
            }
            
            DB::commit();
            
            return [
                'success' => true,
                'stock_liberado' => $stockComprometido->count(),
                'message' => 'Stock liberado exitosamente'
            ];
            
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error liberando stock: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Actualizar estado del stock comprometido cuando cambia el estado de la cotización
     */
    public function actualizarEstadoStock($cotizacionId, $nuevoEstado)
    {
        try {
            $stockComprometido = StockComprometido::porCotizacion($cotizacionId)->activo()->get();
            
            foreach ($stockComprometido as $stock) {
                $stock->update(['cotizacion_estado' => $nuevoEstado]);
            }
            
            Log::info("Estado de stock actualizado: Cotización {$cotizacionId}, Nuevo estado: {$nuevoEstado}");
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('Error actualizando estado de stock: ' . $e->getMessage());
            return false;
        }
    }
    

    
    /**
     * Obtener stock disponible real considerando stock comprometido local
     */
    public function obtenerStockDisponibleReal($productoCodigo, $bodegaCodigo = '01')
    {
        // Obtener stock físico desde SQL Server
        $stockFisicoSQL = $this->obtenerStockDisponible($productoCodigo, $bodegaCodigo);
        
        // Obtener stock comprometido local
        $stockComprometidoLocal = StockComprometido::calcularStockComprometido($productoCodigo, $bodegaCodigo);
        
        // Stock disponible real = Stock físico SQL - Stock comprometido local
        $stockDisponibleReal = $stockFisicoSQL - $stockComprometidoLocal;
        
        Log::info("Stock real para producto {$productoCodigo}: Físico SQL={$stockFisicoSQL}, Comprometido Local={$stockComprometidoLocal}, Disponible Real={$stockDisponibleReal}");
        
        return max(0, $stockDisponibleReal); // No puede ser negativo
    }

    /**
     * Obtener stock disponible desde SQL Server (método privado)
     */
    private function obtenerStockDisponible($productoCodigo, $bodegaCodigo = '01')
    {
        try {
            $host = env('SQLSRV_EXTERNAL_HOST');
            $port = env('SQLSRV_EXTERNAL_PORT', '1433');
            $database = env('SQLSRV_EXTERNAL_DATABASE');
            $username = env('SQLSRV_EXTERNAL_USERNAME');
            $password = env('SQLSRV_EXTERNAL_PASSWORD');
            
            // Consulta para obtener stock disponible
            $query = "
                SELECT TOP 1 
                    ISNULL(STFI1, 0) AS stock_fisico,
                    ISNULL(STOCNV1, 0) AS stock_comprometido,
                    (ISNULL(STFI1, 0) - ISNULL(STOCNV1, 0)) AS stock_disponible
                FROM MAEST
                WHERE KOPR = '{$productoCodigo}' 
                AND KOBO = '{$bodegaCodigo}'
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
            
            // Parsear resultado
            $lines = explode("\n", $output);
            foreach ($lines as $line) {
                $line = trim($line);
                // Buscar línea con datos numéricos (stock_fisico, stock_comprometido, stock_disponible)
                if (preg_match('/^\s*(\d+)\s+(\d+)\s+(\d+)\s*$/', $line, $matches)) {
                    $stockFisico = (float)$matches[1];
                    $stockComprometido = (float)$matches[2];
                    $stockDisponible = (float)$matches[3];
                    
                    Log::info("Stock para producto {$productoCodigo}: Físico={$stockFisico}, Comprometido SQL={$stockComprometido}, Disponible SQL={$stockDisponible}");
                    
                    return $stockFisico; // Devolvemos el stock físico, no el disponible
                }
            }
            
            Log::warning("No se encontraron datos de stock para producto {$productoCodigo}");
            return 0;
            
        } catch (\Exception $e) {
            Log::error('Error obteniendo stock disponible: ' . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Obtener resumen de stock comprometido por producto
     */
    public function obtenerResumenStockComprometido($productoCodigo = null, $bodegaCodigo = '01')
    {
        try {
            $query = StockComprometido::activo();
            
            if ($productoCodigo) {
                $query->where('producto_codigo', $productoCodigo);
            }
            
            if ($bodegaCodigo) {
                $query->where('bodega_codigo', $bodegaCodigo);
            }
            
            return $query->selectRaw('
                    producto_codigo,
                    producto_nombre,
                    bodega_codigo,
                    SUM(cantidad_comprometida) as total_comprometido,
                    COUNT(*) as cotizaciones_activas,
                    MIN(fecha_compromiso) as fecha_compromiso_mas_antiguo,
                    MAX(fecha_compromiso) as fecha_compromiso_mas_reciente
                ')
                ->groupBy('producto_codigo', 'producto_nombre', 'bodega_codigo')
                ->orderBy('total_comprometido', 'desc')
                ->get();
                
        } catch (\Exception $e) {
            Log::error('Error obteniendo resumen de stock comprometido: ' . $e->getMessage());
            return collect();
        }
    }
} 