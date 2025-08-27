<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotaVentaPendiente extends Model
{
    use HasFactory;

    protected $table = 'nota_venta_pendientes';
    
    protected $fillable = [
        'cotizacion_id',
        'cotizacion_numero',
        'cliente_codigo',
        'cliente_nombre',
        'cliente_direccion',
        'cliente_telefono',
        'cliente_lista_precios',
        'vendedor_id',
        'vendedor_nombre',
        'vendedor_codigo',
        'numero_nota_venta',
        'fecha_nota_venta',
        'subtotal',
        'descuento_global',
        'total',
        'observaciones',
        'estado',
        'aprobado_por',
        'fecha_aprobacion',
        'motivo_rechazo',
        'comentarios_supervisor',
        'tiene_problemas_stock',
        'detalle_problemas_stock'
    ];

    protected $casts = [
        'fecha_nota_venta' => 'date',
        'fecha_aprobacion' => 'datetime',
        'subtotal' => 'decimal:2',
        'descuento_global' => 'decimal:2',
        'total' => 'decimal:2',
        'tiene_problemas_stock' => 'boolean'
    ];

    /**
     * Relación con la cotización
     */
    public function cotizacion()
    {
        return $this->belongsTo(Cotizacion::class);
    }

    /**
     * Relación con el vendedor
     */
    public function vendedor()
    {
        return $this->belongsTo(User::class, 'vendedor_id');
    }

    /**
     * Relación con quien aprobó
     */
    public function aprobadoPor()
    {
        return $this->belongsTo(User::class, 'aprobado_por');
    }

    /**
     * Relación con los productos
     */
    public function productos()
    {
        return $this->hasMany(NotaVentaPendienteProducto::class);
    }

    /**
     * Scope para notas pendientes
     */
    public function scopePendientes($query)
    {
        return $query->where('estado', 'pendiente');
    }

    /**
     * Scope para notas aprobadas
     */
    public function scopeAprobadas($query)
    {
        return $query->where('estado', 'aprobada');
    }

    /**
     * Scope para notas con problemas de stock
     */
    public function scopeConProblemasStock($query)
    {
        return $query->where('tiene_problemas_stock', true);
    }

    /**
     * Aprobar nota de venta
     */
    public function aprobar($supervisorId, $comentarios = null)
    {
        $this->update([
            'estado' => 'aprobada',
            'aprobado_por' => $supervisorId,
            'fecha_aprobacion' => now(),
            'comentarios_supervisor' => $comentarios
        ]);
    }

    /**
     * Rechazar nota de venta
     */
    public function rechazar($supervisorId, $motivo)
    {
        $this->update([
            'estado' => 'rechazada',
            'aprobado_por' => $supervisorId,
            'fecha_aprobacion' => now(),
            'motivo_rechazo' => $motivo
        ]);
    }
}
