<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class Cotizacion extends Model
{
    use HasFactory;

    protected $table = 'cotizaciones';

    protected $fillable = [
        'tipo_documento',
        'user_id',
        'cliente_codigo',
        'cliente_nombre',
        'cliente_direccion',
        'cliente_telefono',
        'cliente_lista_precios',
        'fecha',
        'estado',
        'subtotal',
        'descuento_global',
        'total',
        'observaciones',
        'fecha_despacho',
        'motivo_rechazo',
        'requiere_aprobacion',
        'nota_venta_id',
        'numero_nvv',
        'facturada',
        'numero_factura',
        'fecha_facturacion',
        'fecha_aprobacion',
        'aprobado_por',
        'fecha_cancelacion',
        'cancelado_por',
        // Nuevos campos para el flujo de aprobaciones
        'estado_aprobacion',
        'aprobado_por_supervisor',
        'aprobado_por_compras',
        'aprobado_por_picking',
        'fecha_aprobacion_supervisor',
        'fecha_aprobacion_compras',
        'fecha_aprobacion_picking',
        'comentarios_supervisor',
        'comentarios_compras',
        'comentarios_picking',
        'observaciones_picking',
        'guia_picking_bodega',
        'guia_picking_separado_por',
        'guia_picking_revisado_por',
        'guia_picking_numero_bultos',
        'guia_picking_firma',
        'tiene_problemas_stock',
        'detalle_problemas_stock',
        'tiene_problemas_credito',
        'detalle_problemas_credito',
        'nota_original_id',
        'productos_separados',
        'observacion_vendedor',
        'numero_orden_compra'
    ];

    protected $casts = [
        'fecha' => 'datetime',
        'fecha_aprobacion' => 'datetime',
        'fecha_cancelacion' => 'datetime',
        'fecha_despacho' => 'datetime',
        'fecha_facturacion' => 'datetime',
        'subtotal' => 'decimal:2',
        'descuento_global' => 'decimal:2',
        'total' => 'decimal:2',
        'requiere_aprobacion' => 'boolean',
        'facturada' => 'boolean',
        // Nuevos casts para el flujo de aprobaciones
        'fecha_aprobacion_supervisor' => 'datetime',
        'fecha_aprobacion_compras' => 'datetime',
        'fecha_aprobacion_picking' => 'datetime',
        'tiene_problemas_stock' => 'boolean',
        'tiene_problemas_credito' => 'boolean'
    ];

    // Relaciones
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'cliente_codigo', 'codigo_cliente');
    }

    public function productos()
    {
        return $this->hasMany(CotizacionProducto::class);
    }

    public function aprobadoPor()
    {
        return $this->belongsTo(User::class, 'aprobado_por');
    }

    public function canceladoPor()
    {
        return $this->belongsTo(User::class, 'cancelado_por');
    }

    // Nuevas relaciones para el flujo de aprobaciones
    public function aprobadoPorSupervisor()
    {
        return $this->belongsTo(User::class, 'aprobado_por_supervisor');
    }

    public function aprobadoPorCompras()
    {
        return $this->belongsTo(User::class, 'aprobado_por_compras');
    }

    public function aprobadoPorPicking()
    {
        return $this->belongsTo(User::class, 'aprobado_por_picking');
    }

    public function notaOriginal()
    {
        return $this->belongsTo(Cotizacion::class, 'nota_original_id');
    }

    public function notasSeparadas()
    {
        return $this->hasMany(Cotizacion::class, 'nota_original_id');
    }

    public function historial()
    {
        return $this->hasMany(CotizacionHistorial::class);
    }

    // Scopes
    public function scopePorVendedor($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopePorEstado($query, $estado)
    {
        return $query->where('estado', $estado);
    }

    public function scopePendientesAprobacion($query)
    {
        return $query->where('requiere_aprobacion', true)
                    ->whereIn('estado', ['borrador', 'enviada']);
    }

    // Nuevos scopes para el flujo de aprobaciones
    public function scopePendientesSupervisor($query)
    {
        return $query->where('estado_aprobacion', 'pendiente')
                    ->where('tiene_problemas_credito', true);
    }

    public function scopePendientesCompras($query)
    {
        return $query->where(function($q) {
            $q->where('estado_aprobacion', 'aprobada_supervisor')
              ->orWhere(function($subQ) {
                  $subQ->where('estado_aprobacion', 'pendiente')
                       ->where('tiene_problemas_stock', true)
                       ->where('tiene_problemas_credito', false);
              });
        })->where('tiene_problemas_stock', true);
    }

    public function scopePendientesPicking($query)
    {
        return $query->whereIn('estado_aprobacion', ['pendiente_picking', 'aprobada_compras'])
                    ->where(function($q) {
                        $q->whereNull('aprobado_por_picking')
                          ->orWhere('aprobado_por_picking', false);
                    })
                    // Excluir NVV separadas por Picking (estas deben ir solo a Compras)
                    ->whereNull('nota_original_id');
    }

    public function scopePendientesPickingSinProblemas($query)
    {
        return $query->where('estado_aprobacion', 'pendiente_picking')
                    ->where('tiene_problemas_stock', false)
                    ->where('tiene_problemas_credito', false)
                    // Excluir NVV separadas por Picking (estas deben ir solo a Compras)
                    ->whereNull('nota_original_id');
    }

    public function scopeAprobadasCompletamente($query)
    {
        return $query->where('estado_aprobacion', 'aprobada_picking');
    }

    /**
     * Scope para notas pendientes de entrega
     */
    public function scopePendientesEntrega($query)
    {
        return $query->where('estado_aprobacion', 'pendiente_entrega')
                    // Excluir NVV separadas
                    ->whereNull('nota_original_id');
    }

    public function scopeConProblemasStock($query)
    {
        return $query->where('tiene_problemas_stock', true);
    }

    public function scopeConProblemasCredito($query)
    {
        return $query->where('tiene_problemas_credito', true);
    }
    
    // Scopes para tipo de documento
    public function scopeCotizaciones($query)
    {
        return $query->where('tipo_documento', 'cotizacion');
    }
    
    public function scopeNotasVenta($query)
    {
        return $query->where('tipo_documento', 'nota_venta');
    }

    // Métodos
    public function puedeAprobar()
    {
        return in_array($this->estado, ['borrador', 'enviada']) && $this->requiere_aprobacion;
    }
    
    /**
     * Convierte una cotización en nota de venta
     * Inicia el flujo de aprobaciones
     * ACTUALIZA los stocks de todos los productos desde SQL Server antes de determinar el estado
     */
    public function convertirANotaVenta($userId = null)
    {
        if ($this->tipo_documento !== 'cotizacion') {
            throw new \Exception('Solo se pueden convertir cotizaciones a notas de venta');
        }
        
        \Log::info("🔄 CONVIRTIENDO COTIZACIÓN {$this->id} A NOTA DE VENTA - ACTUALIZANDO STOCKS");

        // 1. ACTUALIZAR STOCKS PRODUCTO POR PRODUCTO (usando el mismo método que funciona en la búsqueda)
        $stockConsultaService = new \App\Services\StockConsultaService();

        // ACTUALIZAR cada producto individualmente usando el mismo método que funciona bien
        foreach ($this->productos as $productoCotizacion) {
            $codigo = $productoCotizacion->codigo_producto;
            
            // Usar el mismo método que usa obtenerStockProducto (tsql directo con un solo producto)
            try {
                $host = env('SQLSRV_EXTERNAL_HOST');
                $port = env('SQLSRV_EXTERNAL_PORT', '1433');
                $database = env('SQLSRV_EXTERNAL_DATABASE');
                $username = env('SQLSRV_EXTERNAL_USERNAME');
                $password = env('SQLSRV_EXTERNAL_PASSWORD');
                
                $codigoEscapado = "'" . addslashes(trim($codigo)) . "'";
                $query = "
                    SELECT 
                        CAST(SUM(ISNULL(STFI1, 0)) AS FLOAT) AS STOCK_FISICO,
                        CAST(SUM(ISNULL(STOCNV1, 0)) AS FLOAT) AS STOCK_COMPROMETIDO
                    FROM MAEST
                    WHERE KOPR = {$codigoEscapado}
                    AND KOBO = 'LIB'
                ";
                
                $tempFile = tempnam(sys_get_temp_dir(), 'sql_stock_');
                file_put_contents($tempFile, $query . "\ngo\nquit");
                
                $command = "tsql -H {$host} -p {$port} -U {$username} -P {$password} -D {$database} < {$tempFile} 2>&1";
                $output = shell_exec($command);
                unlink($tempFile);
                
                // Parsear resultado (mismo método que obtenerStockProducto)
                $stockFisico = 0;
                $stockComprometido = 0;
                
                $lines = explode("\n", $output);
                $headerFound = false;
                
                foreach ($lines as $line) {
                    $line = trim($line);
                    
                    if (empty($line) || strpos($line, 'locale') !== false || 
                        strpos($line, 'Setting') !== false || strpos($line, 'rows affected') !== false ||
                        strpos($line, 'Msg ') !== false || strpos($line, 'Warning:') !== false ||
                        preg_match('/^\d+>$/', $line) || preg_match('/^\d+>\s+\d+>\s+\d+>/', $line)) {
                        continue;
                    }
                    
                    if (stripos($line, 'STOCK_FISICO') !== false || stripos($line, 'STOCK_COMPROMETIDO') !== false) {
                        $headerFound = true;
                        continue;
                    }
                    
                    if (preg_match('/^\s*([0-9.]+)\s+([0-9.]+)\s*$/', $line, $matches)) {
                        $stockFisico = (float)$matches[1];
                        $stockComprometido = (float)$matches[2];
                        break;
                    }
                    
                    if ($headerFound) {
                        $parts = preg_split('/\s+/', $line);
                        if (count($parts) >= 2 && is_numeric($parts[0]) && is_numeric($parts[1])) {
                            $stockFisico = (float)$parts[0];
                            $stockComprometido = (float)$parts[1];
                            break;
                        }
                    }
                }
                
                // Actualizar MySQL con los valores obtenidos
                if ($stockFisico >= 0 && $stockComprometido >= 0) {
                    $stockConsultaService->actualizarStockSiEsDiferente($codigo, $stockFisico, $stockComprometido);
                    \Log::info("✅ Stock actualizado en MySQL para {$codigo}: Físico={$stockFisico}, Comprometido={$stockComprometido}");
                } else {
                    \Log::warning("⚠️ No se pudo parsear stock para {$codigo}. Output: " . substr($output, 0, 200));
                }
            } catch (\Exception $e) {
                \Log::error("❌ Error actualizando stock para {$codigo}: " . $e->getMessage());
                // Continuar con los otros productos aunque falle uno
            }
        }
        
        // 2. Ahora obtener los stocks actualizados desde MySQL
        $stockService = new \App\Services\StockComprometidoService();
        $productosSinStockSuficiente = [];
        $productosConStockSuficiente = [];
        
        foreach ($this->productos as $productoCotizacion) {
            // Consultar stock REAL desde MySQL (ya actualizado)
            $stockDisponibleReal = $stockService->obtenerStockDisponibleReal($productoCotizacion->codigo_producto);
            $stockFisico = \App\Models\Producto::where('KOPR', $productoCotizacion->codigo_producto)
                ->value('stock_fisico') ?? 0;
            
            \Log::info("📦 Producto {$productoCotizacion->codigo_producto}: Stock físico={$stockFisico}, Disponible={$stockDisponibleReal}, Cantidad pedida={$productoCotizacion->cantidad}");
            
            // Actualizar el stock en la tabla cotizacion_productos con el valor REAL
            // Guardamos tanto stock_fisico como stock_disponible para referencia
            $updateData = [
                'stock_disponible' => $stockDisponibleReal,
                'stock_suficiente' => $stockFisico >= $productoCotizacion->cantidad
            ];
            
            // También actualizar el stock_fisico si existe la columna
            if (Schema::hasColumn('cotizacion_productos', 'stock_fisico')) {
                $updateData['stock_fisico'] = $stockFisico;
            }
            
            $productoCotizacion->update($updateData);
            
            // Verificar si tiene stock FÍSICO suficiente (no stock disponible)
            // Si stock físico >= cantidad pedida → tiene stock suficiente
            if ($stockFisico >= $productoCotizacion->cantidad) {
                $productosConStockSuficiente[] = $productoCotizacion->codigo_producto;
                \Log::info("   ✓ Stock FÍSICO suficiente: {$stockFisico} >= {$productoCotizacion->cantidad}");
            } else {
                $productosSinStockSuficiente[] = [
                    'codigo' => $productoCotizacion->codigo_producto,
                    'nombre' => $productoCotizacion->nombre_producto,
                    'stock_fisico' => $stockFisico,
                    'cantidad_pedida' => $productoCotizacion->cantidad
                ];
                \Log::warning("   ⚠️ Stock FÍSICO insuficiente: {$stockFisico} < {$productoCotizacion->cantidad}");
            }
        }
        
        // 3. Determinar estado de aprobación basado en stock REAL y problemas de crédito
        $tieneStockSuficiente = empty($productosSinStockSuficiente);
        $tieneProblemasCredito = $this->tiene_problemas_credito ?? false;
        
        // Si stock físico >= cantidad pedida Y no hay problemas de crédito → pendiente_picking
        // Si stock físico >= cantidad pedida PERO hay problemas de crédito → pendiente (supervisor)
        // Si stock físico < cantidad pedida → pendiente (compras), o si hay crédito también → pendiente (supervisor primero)
        if ($tieneStockSuficiente && !$tieneProblemasCredito) {
            $estadoAprobacion = 'pendiente_picking'; // Pasa directo a picking
            $tieneProblemasStock = false;
        } elseif ($tieneStockSuficiente && $tieneProblemasCredito) {
            $estadoAprobacion = 'pendiente'; // Requiere supervisor primero (crédito), luego picking
            $tieneProblemasStock = false;
        } else {
            $estadoAprobacion = 'pendiente'; // Requiere compras (stock), y si hay crédito también → supervisor primero
            $tieneProblemasStock = true;
        }
        
        \Log::info("📋 Estado determinado: {$estadoAprobacion} | Stock suficiente: " . ($tieneStockSuficiente ? 'SÍ' : 'NO') . " | Problemas crédito: " . ($tieneProblemasCredito ? 'SÍ' : 'NO'));
        
        // 4. Actualizar cotización a nota de venta con el estado correcto
        $this->tipo_documento = 'nota_venta';
        $this->estado = 'enviada';
        $this->estado_aprobacion = $estadoAprobacion;
        $this->requiere_aprobacion = true;
        $this->tiene_problemas_stock = $tieneProblemasStock;
        
        // Resetear aprobaciones previas si existían
        $this->aprobado_por_supervisor = null;
        $this->aprobado_por_compras = null;
        $this->aprobado_por_picking = null;
        $this->fecha_aprobacion_supervisor = null;
        $this->fecha_aprobacion_compras = null;
        $this->fecha_aprobacion_picking = null;
        
        $this->save();
        
        // Registrar en historial
        $mensajeHistorial = "Cotización convertida a Nota de Venta - Stock actualizado desde SQL Server";
        if (!empty($productosSinStockSuficiente)) {
            $mensajeHistorial .= " - Requiere aprobación de Compras por stock insuficiente";
        } elseif ($tieneProblemasCredito) {
            $mensajeHistorial .= " - Requiere aprobación de Supervisor por problemas de crédito";
        } else {
            $mensajeHistorial .= " - Estado: {$estadoAprobacion}";
        }
        
        \App\Models\CotizacionHistorial::crearRegistro(
            $this->id,
            $estadoAprobacion,
            'conversion',
            $estadoAprobacion,
            $mensajeHistorial,
            [
                'convertido_por' => $userId ?? auth()->id(),
                'productos_sin_stock' => $productosSinStockSuficiente,
                'tiene_problemas_credito' => $tieneProblemasCredito
            ]
        );
        
        return $this;
    }

    public function puedeCancelar()
    {
        return in_array($this->estado, ['borrador', 'enviada']);
    }

    public function puedeProcesar()
    {
        return $this->estado_aprobacion === 'aprobada_picking';
    }

    // Nuevos métodos para el flujo de aprobaciones
    public function puedeAprobarSupervisor()
    {
        return $this->estado_aprobacion === 'pendiente' && (
            $this->tiene_problemas_credito || $this->requiere_aprobacion
        );
    }

    public function puedeAprobarCompras()
    {
        return $this->tiene_problemas_stock && (
            $this->estado_aprobacion === 'aprobada_supervisor' ||
            $this->estado_aprobacion === 'pendiente'
        );
    }

    public function puedeAprobarPicking()
    {
        return in_array($this->estado_aprobacion, ['pendiente_picking', 'aprobada_compras', 'pendiente_entrega']);
    }

    public function aprobarPorSupervisor($supervisorId, $comentarios = null)
    {
        // Si hay problemas de stock, va a Compras; si no, va directo a Picking
        $nuevoEstado = $this->tiene_problemas_stock ? 'aprobada_supervisor' : 'pendiente_picking';
        
        $this->update([
            'estado_aprobacion' => $nuevoEstado,
            'aprobado_por_supervisor' => $supervisorId,
            'fecha_aprobacion_supervisor' => now(),
            'comentarios_supervisor' => $comentarios
        ]);
    }

    public function aprobarPorCompras($comprasId, $comentarios = null)
    {
        $this->update([
            'estado_aprobacion' => 'pendiente_picking',
            'aprobado_por_compras' => $comprasId,
            'fecha_aprobacion_compras' => now(),
            'comentarios_compras' => $comentarios
        ]);
    }

    public function aprobarPorPicking($pickingId, $comentarios = null, $bodega = null, $separadoPor = null, $revisadoPor = null, $numeroBultos = null, $firma = null)
    {
        $this->update([
            'estado_aprobacion' => 'aprobada_picking',
            'aprobado_por_picking' => $pickingId,
            'fecha_aprobacion_picking' => now(),
            'comentarios_picking' => $comentarios,
            'guia_picking_bodega' => $bodega,
            'guia_picking_separado_por' => $separadoPor,
            'guia_picking_revisado_por' => $revisadoPor,
            'guia_picking_numero_bultos' => $numeroBultos,
            'guia_picking_firma' => $firma
        ]);
    }

    /**
     * Guardar como pendiente de entrega (nuevo estado)
     */
    public function guardarPendienteEntrega($pickingId, $observaciones = null, $bodega = null, $separadoPor = null, $revisadoPor = null, $numeroBultos = null, $firma = null)
    {
        $this->update([
            'estado_aprobacion' => 'pendiente_entrega',
            'aprobado_por_picking' => $pickingId,
            'fecha_aprobacion_picking' => now(),
            'observaciones_picking' => $observaciones,
            'guia_picking_bodega' => $bodega,
            'guia_picking_separado_por' => $separadoPor,
            'guia_picking_revisado_por' => $revisadoPor,
            'guia_picking_numero_bultos' => $numeroBultos,
            'guia_picking_firma' => $firma
        ]);
    }

    public function rechazar($usuarioId, $motivo, $rol = 'supervisor')
    {
        $campoAprobado = 'aprobado_por_' . $rol;
        $campoFecha = 'fecha_aprobacion_' . $rol;
        $campoComentarios = 'comentarios_' . $rol;

        $this->update([
            'estado' => 'rechazada',
            'estado_aprobacion' => 'rechazada',
            $campoAprobado => $usuarioId,
            $campoFecha => now(),
            $campoComentarios => $motivo,
            'motivo_rechazo' => $motivo
        ]);
    }

    public function separarPorProblemasStock($productosProblematicos)
    {
        // Crear una nueva nota de venta solo con los productos problemáticos
        $notaSeparada = $this->replicate();
        $notaSeparada->nota_original_id = $this->id;
        $notaSeparada->estado_aprobacion = 'pendiente';
        $notaSeparada->tiene_problemas_stock = true;
        $notaSeparada->productos_separados = json_encode($productosProblematicos);
        $notaSeparada->save();

        // Copiar solo los productos problemáticos
        foreach ($productosProblematicos as $productoId) {
            $productoOriginal = $this->productos()->find($productoId);
            if ($productoOriginal) {
                $productoOriginal->replicate()->fill([
                    'cotizacion_id' => $notaSeparada->id
                ])->save();
            }
        }

        // Remover los productos problemáticos de la nota original
        $this->productos()->whereIn('id', $productosProblematicos)->delete();

        // Recalcular totales de la nota original
        $this->calcularTotales();

        return $notaSeparada;
    }

    public function calcularTotales()
    {
        $subtotal = $this->productos->sum(function($producto) {
            return $producto->cantidad * $producto->precio_unitario;
        });

        // Calcular descuento global (5% si supera $400,000)
        $descuentoGlobal = 0;
        if ($subtotal > 400000) {
            $descuentoGlobal = $subtotal * 0.05;
        }

        $total = $subtotal - $descuentoGlobal;

        $this->update([
            'subtotal' => $subtotal,
            'descuento_global' => $descuentoGlobal,
            'total' => $total
        ]);

        return [
            'subtotal' => $subtotal,
            'descuento_global' => $descuentoGlobal,
            'total' => $total
        ];
    }

    public function verificarAlertas()
    {
        $alertas = [];
        
        // Verificar si hay productos con stock insuficiente
        $productosSinStock = $this->productos->filter(function($producto) {
            return $producto->cantidad > $producto->stock_disponible;
        });

        if ($productosSinStock->count() > 0) {
            $alertas[] = [
                'tipo' => 'warning',
                'titulo' => 'Stock Insuficiente',
                'mensaje' => 'Hay productos con stock insuficiente para entrega inmediata'
            ];
        }

        // Verificar si el cliente está bloqueado
        $cliente = Cliente::where('codigo_cliente', $this->cliente_codigo)->first();
        if ($cliente && $cliente->bloqueado) {
            $alertas[] = [
                'tipo' => 'danger',
                'titulo' => 'Cliente Bloqueado',
                'mensaje' => 'El cliente está bloqueado y no puede generar cotizaciones'
            ];
        }

        return $alertas;
    }

    public function determinarEstado()
    {
        $alertas = $this->verificarAlertas();
        $problemasCredito = false;
        $problemasStock = false;
        $detalleProblemasStock = [];
        
        // Verificar problemas de crédito
        $cliente = Cliente::where('codigo_cliente', $this->cliente_codigo)->first();
        if ($cliente) {
            if ($cliente->bloqueado || $cliente->requiere_autorizacion_credito || $cliente->requiere_autorizacion_retraso) {
                $problemasCredito = true;
            }
        }
        
        // Verificar problemas de stock
        $productosSinStock = $this->productos->filter(function($producto) {
            return $producto->cantidad > $producto->stock_disponible;
        });

        if ($productosSinStock->count() > 0) {
            $problemasStock = true;
            $detalleProblemasStock = $productosSinStock->map(function($producto) {
                return [
                    'id' => $producto->id,
                    'codigo' => $producto->codigo_producto,
                    'nombre' => $producto->nombre_producto,
                    'cantidad_solicitada' => $producto->cantidad,
                    'stock_disponible' => $producto->stock_disponible,
                    'diferencia' => $producto->cantidad - $producto->stock_disponible
                ];
            })->toArray();
        }
        
        // Determinar estado de aprobación según la nueva lógica
        if ($problemasCredito && $problemasStock) {
            // Ambos problemas → Supervisor primero, luego Compras, luego Picking
            $this->update([
                'estado' => 'enviada',
                'requiere_aprobacion' => true,
                'estado_aprobacion' => 'pendiente',
                'tiene_problemas_credito' => true,
                'tiene_problemas_stock' => true,
                'detalle_problemas_stock' => json_encode($detalleProblemasStock),
                'detalle_problemas_credito' => 'Cliente con problemas de crédito o facturas impagas'
            ]);
        } elseif ($problemasCredito) {
            // Solo problemas de crédito → Supervisor, luego Picking
            $this->update([
                'estado' => 'enviada',
                'requiere_aprobacion' => true,
                'estado_aprobacion' => 'pendiente',
                'tiene_problemas_credito' => true,
                'tiene_problemas_stock' => false,
                'detalle_problemas_credito' => 'Cliente con problemas de crédito o facturas impagas'
            ]);
        } elseif ($problemasStock) {
            // Solo problemas de stock → Compras, luego Picking
            $this->update([
                'estado' => 'enviada',
                'requiere_aprobacion' => true,
                'estado_aprobacion' => 'pendiente',
                'tiene_problemas_credito' => false,
                'tiene_problemas_stock' => true,
                'detalle_problemas_stock' => json_encode($detalleProblemasStock)
            ]);
        } else {
            // No hay problemas → Directo a Picking (aprobación final)
            $this->update([
                'estado' => 'enviada',
                'requiere_aprobacion' => true,
                'estado_aprobacion' => 'pendiente_picking',
                'tiene_problemas_credito' => false,
                'tiene_problemas_stock' => false
            ]);
        }

        return $this->estado;
    }
}
