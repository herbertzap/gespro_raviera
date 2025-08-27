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
        'cancelado_por'
    ];

    protected $casts = [
        'fecha' => 'datetime',
        'fecha_aprobacion' => 'datetime',
        'fecha_cancelacion' => 'datetime',
        'subtotal' => 'decimal:2',
        'descuento_global' => 'decimal:2',
        'total' => 'decimal:2',
        'requiere_aprobacion' => 'boolean'
    ];

    // Relaciones
    public function user()
    {
        return $this->belongsTo(User::class);
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
        return $this->estado === 'aprobada';
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
        
        if (empty($alertas)) {
            // No hay alertas, puede ser aprobada automáticamente
            $this->update([
                'estado' => 'aprobada',
                'requiere_aprobacion' => false,
                'fecha_aprobacion' => now(),
                'aprobado_por' => auth()->id()
            ]);
        } else {
            // Hay alertas, requiere aprobación
            $this->update([
                'estado' => 'enviada',
                'requiere_aprobacion' => true
            ]);
        }

        return $this->estado;
    }
}
