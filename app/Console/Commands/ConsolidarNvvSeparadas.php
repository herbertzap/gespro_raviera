<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Cotizacion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ConsolidarNvvSeparadas extends Command
{
    protected $signature = 'nvv:consolidar-separadas {nvv_principal_id : ID de la NVV principal}';
    protected $description = 'Consolida todas las NVVs separadas de una NVV principal en una sola NVV';

    public function handle()
    {
        $nvvPrincipalId = $this->argument('nvv_principal_id');
        
        $nvvPrincipal = Cotizacion::find($nvvPrincipalId);
        if (!$nvvPrincipal) {
            $this->error("NVV principal #{$nvvPrincipalId} no encontrada");
            return 1;
        }
        
        // Buscar todas las NVVs separadas de esta NVV principal
        $nvvSeparadas = Cotizacion::where('nota_original_id', $nvvPrincipalId)
            ->orderBy('id')
            ->get();
            
        if ($nvvSeparadas->isEmpty()) {
            $this->info("No se encontraron NVVs separadas para la NVV principal #{$nvvPrincipalId}");
            return 0;
        }
        
        $this->info("Encontradas " . $nvvSeparadas->count() . " NVVs separadas de la NVV principal #{$nvvPrincipalId}");
        
        if ($nvvSeparadas->count() === 1) {
            $this->info("Solo hay una NVV separada, no es necesario consolidar");
            return 0;
        }
        
        // Usar la primera NVV como base para consolidar
        $nvvConsolidada = $nvvSeparadas->first();
        $nvvAEliminar = $nvvSeparadas->slice(1);
        
        $this->info("Consolidando en la NVV #{$nvvConsolidada->id}");
        
        try {
            DB::beginTransaction();
            
            // Consolidar productos de todas las NVVs separadas en la primera
            $productosConsolidados = [];
            foreach ($nvvSeparadas as $nvvSeparada) {
                foreach ($nvvSeparada->productos as $producto) {
                    // Verificar si ya existe un producto con el mismo código en la NVV consolidada
                    $productoExistente = $nvvConsolidada->productos()
                        ->where('codigo_producto', $producto->codigo_producto)
                        ->first();
                    
                    if ($productoExistente) {
                        // Si existe, sumar las cantidades
                        $nuevaCantidad = $productoExistente->cantidad + $producto->cantidad;
                        $productoExistente->update([
                            'cantidad' => $nuevaCantidad,
                            'subtotal' => $productoExistente->precio_unitario * $nuevaCantidad,
                        ]);
                        
                        // Recalcular descuentos proporcionales
                        $subtotalBruto = $productoExistente->precio_unitario * $nuevaCantidad;
                        $descuentoPorcentaje = $productoExistente->descuento_porcentaje ?? 0;
                        
                        // Sumar descuentos de ambos productos
                        $descuentoExistente = $productoExistente->descuento_valor ?? 0;
                        $descuentoNuevo = $producto->descuento_valor ?? 0;
                        $descuentoValor = $descuentoExistente + $descuentoNuevo;
                        
                        // Si hay descuento porcentual, calcularlo sobre el total
                        if ($descuentoPorcentaje > 0) {
                            $descuentoValor = $subtotalBruto * ($descuentoPorcentaje / 100);
                        }
                        
                        $subtotalConDescuento = $subtotalBruto - $descuentoValor;
                        $ivaValor = $subtotalConDescuento * 0.19;
                        $totalProducto = $subtotalConDescuento + $ivaValor;
                        
                        $productoExistente->update([
                            'descuento_valor' => $descuentoValor,
                            'subtotal_con_descuento' => $subtotalConDescuento,
                            'iva_valor' => $ivaValor,
                            'total_producto' => $totalProducto,
                        ]);
                        
                        $this->info("  - Producto {$producto->codigo_producto} consolidado (cantidad: {$nuevaCantidad})");
                    } else {
                        // Si no existe, mover el producto a la NVV consolidada
                        $producto->update([
                            'cotizacion_id' => $nvvConsolidada->id
                        ]);
                        $this->info("  - Producto {$producto->codigo_producto} movido");
                    }
                }
            }
            
            // Actualizar observaciones de la NVV consolidada
            $nvvIdsEliminadas = $nvvAEliminar->pluck('id')->implode(', #');
            $observacionActual = $nvvConsolidada->observaciones ?? '';
            $nvvConsolidada->update([
                'observaciones' => $observacionActual . "\n\n🔄 CONSOLIDADA: Esta NVV consolidó las NVVs separadas #{$nvvIdsEliminadas}"
            ]);
            
            // Actualizar totales usando la misma lógica del controlador
            $this->actualizarTotales($nvvConsolidada);
            
            // Eliminar las NVVs duplicadas
            foreach ($nvvAEliminar as $nvv) {
                // Eliminar productos primero
                $nvv->productos()->delete();
                // Eliminar historial
                DB::table('cotizacion_historial')->where('cotizacion_id', $nvv->id)->delete();
                // Eliminar la NVV
                $nvv->delete();
                $this->info("  - NVV #{$nvv->id} eliminada");
            }
            
            DB::commit();
            
            $this->info("✅ Consolidación completada. NVV #{$nvvConsolidada->id} ahora contiene todos los productos.");
            $this->info("   Total de productos: " . $nvvConsolidada->productos()->count());
            $this->info("   Total: $" . number_format($nvvConsolidada->total, 0));
            
            return 0;
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Error al consolidar: " . $e->getMessage());
            Log::error("Error consolidando NVVs separadas: " . $e->getMessage());
            return 1;
        }
    }
    
    private function actualizarTotales($cotizacion)
    {
        $cotizacion = $cotizacion->fresh();
        $productos = $cotizacion->productos;
        
        // Calcular subtotal sin descuentos (precio base * cantidad)
        $subtotal = $productos->sum(function($producto) {
            return $producto->precio_unitario * $producto->cantidad;
        });
        
        // Calcular descuento total (suma de descuento_valor de cada producto)
        $descuentoGlobal = $productos->sum(function($producto) {
            return floatval($producto->descuento_valor ?? 0);
        });
        
        // Calcular subtotal neto (suma de subtotal_con_descuento de cada producto)
        $subtotalNeto = $productos->sum(function($producto) {
            if (isset($producto->subtotal_con_descuento) && $producto->subtotal_con_descuento > 0) {
                return floatval($producto->subtotal_con_descuento);
            }
            $subtotalBruto = $producto->precio_unitario * $producto->cantidad;
            $descuentoValor = floatval($producto->descuento_valor ?? 0);
            return $subtotalBruto - $descuentoValor;
        });
        
        // Calcular IVA total (suma de iva_valor de cada producto)
        $ivaTotal = $productos->sum(function($producto) {
            if (isset($producto->iva_valor) && $producto->iva_valor > 0) {
                return floatval($producto->iva_valor);
            }
            $subtotalBruto = $producto->precio_unitario * $producto->cantidad;
            $descuentoValor = floatval($producto->descuento_valor ?? 0);
            $subtotalConDescuento = $subtotalBruto - $descuentoValor;
            return $subtotalConDescuento * 0.19;
        });
        
        // Calcular total final (suma de total_producto de cada producto)
        $total = $productos->sum(function($producto) {
            if (isset($producto->total_producto) && $producto->total_producto > 0) {
                return floatval($producto->total_producto);
            }
            $subtotalBruto = $producto->precio_unitario * $producto->cantidad;
            $descuentoValor = floatval($producto->descuento_valor ?? 0);
            $subtotalConDescuento = $subtotalBruto - $descuentoValor;
            $ivaProducto = $subtotalConDescuento * 0.19;
            return $subtotalConDescuento + $ivaProducto;
        });
        
        $cotizacion->update([
            'subtotal' => $subtotal,
            'descuento_global' => $descuentoGlobal,
            'subtotal_neto' => $subtotalNeto,
            'iva_total' => $ivaTotal,
            'total' => $total
        ]);
    }
}





