<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cotizacion extends Model
{
    use HasFactory;

    protected $table = 'cotizaciones';

    protected $fillable = [
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
        'motivo_rechazo',
        'requiere_aprobacion',
        'nota_venta_id',
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
        'tiene_problemas_stock',
        'detalle_problemas_stock',
        'tiene_problemas_credito',
        'detalle_problemas_credito',
        'nota_original_id',
        'productos_separados'
    ];

    protected $casts = [
        'fecha' => 'datetime',
        'fecha_aprobacion' => 'datetime',
        'fecha_cancelacion' => 'datetime',
        'subtotal' => 'decimal:2',
        'descuento_global' => 'decimal:2',
        'total' => 'decimal:2',
        'requiere_aprobacion' => 'boolean',
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
        return $query->where('estado_aprobacion', 'aprobada_supervisor')
                    ->where('tiene_problemas_stock', true);
    }

    public function scopePendientesPicking($query)
    {
        return $query->whereIn('estado_aprobacion', ['pendiente_picking', 'aprobada_compras'])
                    ->where('tiene_problemas_stock', true);
    }

    public function scopePendientesPickingSinProblemas($query)
    {
        return $query->where('estado_aprobacion', 'pendiente_picking')
                    ->where('tiene_problemas_stock', false)
                    ->where('tiene_problemas_credito', false);
    }

    public function scopeAprobadasCompletamente($query)
    {
        return $query->where('estado_aprobacion', 'aprobada_picking');
    }

    public function scopeConProblemasStock($query)
    {
        return $query->where('tiene_problemas_stock', true);
    }

    public function scopeConProblemasCredito($query)
    {
        return $query->where('tiene_problemas_credito', true);
    }

    // Métodos
    public function puedeAprobar()
    {
        return in_array($this->estado, ['borrador', 'enviada']) && $this->requiere_aprobacion;
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
        return $this->estado_aprobacion === 'pendiente' && $this->tiene_problemas_credito;
    }

    public function puedeAprobarCompras()
    {
        return $this->estado_aprobacion === 'aprobada_supervisor' && $this->tiene_problemas_stock;
    }

    public function puedeAprobarPicking()
    {
        return $this->estado_aprobacion === 'aprobada_compras' && $this->tiene_problemas_stock;
    }

    public function aprobarPorSupervisor($supervisorId, $comentarios = null)
    {
        $this->update([
            'estado_aprobacion' => 'aprobada_supervisor',
            'aprobado_por_supervisor' => $supervisorId,
            'fecha_aprobacion_supervisor' => now(),
            'comentarios_supervisor' => $comentarios
        ]);
    }

    public function aprobarPorCompras($comprasId, $comentarios = null)
    {
        $this->update([
            'estado_aprobacion' => 'aprobada_compras',
            'aprobado_por_compras' => $comprasId,
            'fecha_aprobacion_compras' => now(),
            'comentarios_compras' => $comentarios
        ]);
    }

    public function aprobarPorPicking($pickingId, $comentarios = null)
    {
        $this->update([
            'estado_aprobacion' => 'aprobada_picking',
            'aprobado_por_picking' => $pickingId,
            'fecha_aprobacion_picking' => now(),
            'comentarios_picking' => $comentarios
        ]);
    }

    public function rechazar($usuarioId, $motivo, $rol = 'supervisor')
    {
        $campoAprobado = 'aprobado_por_' . $rol;
        $campoFecha = 'fecha_aprobacion_' . $rol;
        $campoComentarios = 'comentarios_' . $rol;

        $this->update([
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
        if ($problemasCredito) {
            // Cliente con problemas de crédito → Requiere aprobación del Supervisor
            $this->update([
                'estado' => 'enviada',
                'requiere_aprobacion' => true,
                'estado_aprobacion' => 'pendiente',
                'tiene_problemas_credito' => true,
                'tiene_problemas_stock' => $problemasStock,
                'detalle_problemas_stock' => $problemasStock ? json_encode($detalleProblemasStock) : null,
                'detalle_problemas_credito' => 'Cliente con problemas de crédito o facturas impagas'
            ]);
        } elseif ($problemasStock) {
            // Solo problemas de stock → Va directo a Picking para validación
            $this->update([
                'estado' => 'enviada',
                'requiere_aprobacion' => true,
                'estado_aprobacion' => 'pendiente_picking',
                'tiene_problemas_credito' => false,
                'tiene_problemas_stock' => true,
                'detalle_problemas_stock' => json_encode($detalleProblemasStock)
            ]);
        } else {
            // No hay problemas → Va directo a Picking para validación final
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
