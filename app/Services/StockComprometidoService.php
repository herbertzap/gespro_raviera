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
     * Verificar si un producto está oculto (ATPR = 'OCU') consultando SQL Server
     */
    public function verificarProductoOculto($codigoProducto)
    {
        try {
            // Usar conexión directa de Laravel
            $connection = DB::connection('sqlsrv_external');
            
            $producto = $connection->selectOne("
                SELECT TOP 1 ATPR
                FROM MAEPR
                WHERE KOPR = ?
            ", [trim($codigoProducto)]);
            
            if (!$producto) {
                Log::info("Producto {$codigoProducto} no encontrado en MAEPR");
                return false;
            }
            
            $atpr = trim($producto->ATPR ?? '');
            $estaOculto = strtoupper($atpr) === 'OCU';
            
            if ($estaOculto) {
                Log::warning("⚠️ Producto {$codigoProducto} está OCULTO (ATPR = 'OCU')");
            } else {
                Log::info("✅ Producto {$codigoProducto} NO está oculto (ATPR = '{$atpr}')");
            }
            
            return $estaOculto;
            
        } catch (\Exception $e) {
            Log::error('Error verificando producto oculto: ' . $e->getMessage());
            return false; // En caso de error, asumimos que no está oculto
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
        // Obtener stock físico desde tabla productos local (ya sincronizada)
        $producto = DB::table('productos')->where('KOPR', $productoCodigo)->first();
        
        if (!$producto) {
            Log::warning("Producto {$productoCodigo} no encontrado en tabla local");
            return 0;
        }
        
        $stockFisicoLocal = (float)$producto->stock_fisico;
        $stockComprometidoSQL = (float)$producto->stock_comprometido;
        
        // Obtener stock comprometido local adicional (por cotizaciones)
        $stockComprometidoLocal = StockComprometido::calcularStockComprometido($productoCodigo, $bodegaCodigo);
        
        // Stock disponible real = Stock físico local - Stock comprometido SQL - Stock comprometido local
        $stockDisponibleReal = $stockFisicoLocal - $stockComprometidoSQL - $stockComprometidoLocal;
        
        Log::info("Stock real para producto {$productoCodigo}: Físico Local={$stockFisicoLocal}, Comprometido SQL={$stockComprometidoSQL}, Comprometido Local={$stockComprometidoLocal}, Disponible Real={$stockDisponibleReal}");
        
        return max(0, $stockDisponibleReal); // No puede ser negativo
    }

    /**
     * Obtener stock disponible desde tabla productos local (método privado)
     */
    private function obtenerStockDisponible($productoCodigo, $bodegaCodigo = '01')
    {
        try {
            // Obtener stock desde tabla productos local (ya sincronizada)
            $producto = DB::table('productos')->where('KOPR', $productoCodigo)->first();
            
            if (!$producto) {
                Log::warning("Producto {$productoCodigo} no encontrado en tabla local");
                return 0;
            }
            
            $stockFisico = (float)$producto->stock_fisico;
            $stockComprometido = (float)$producto->stock_comprometido;
            $stockDisponible = (float)$producto->stock_disponible;
            
            Log::info("Stock para producto {$productoCodigo}: Físico={$stockFisico}, Comprometido SQL={$stockComprometido}, Disponible SQL={$stockDisponible}");
            
            return $stockFisico; // Devolvemos el stock físico
            
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