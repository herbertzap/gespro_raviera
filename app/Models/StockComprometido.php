<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockComprometido extends Model
{
    use HasFactory;

    protected $table = 'stock_comprometidos';
    
    protected $fillable = [
        'producto_codigo',
        'producto_nombre',
        'bodega_codigo',
        'bodega_nombre',
        'cantidad_comprometida',
        'stock_disponible_original',
        'stock_disponible_actual',
        'unidad_medida',
        'cotizacion_id',
        'cotizacion_estado',
        'nota_venta_pendiente_id',
        'vendedor_id',
        'vendedor_nombre',
        'cliente_codigo',
        'cliente_nombre',
        'fecha_compromiso',
        'fecha_liberacion',
        'estado',
        'observaciones'
    ];

    protected $casts = [
        'fecha_compromiso' => 'datetime',
        'fecha_liberacion' => 'datetime',
        'cantidad_comprometida' => 'decimal:3',
        'stock_disponible_original' => 'decimal:3',
        'stock_disponible_actual' => 'decimal:3'
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
     * Relación con la nota de venta pendiente
     */
    public function notaVentaPendiente()
    {
        return $this->belongsTo(NotaVentaPendiente::class);
    }

    /**
     * Scope para stock comprometido activo
     */
    public function scopeActivo($query)
    {
        return $query->where('estado', 'activo');
    }

    /**
     * Scope para stock comprometido por producto
     */
    public function scopePorProducto($query, $productoCodigo, $bodegaCodigo = '01')
    {
        return $query->where('producto_codigo', $productoCodigo)
                    ->where('bodega_codigo', $bodegaCodigo)
                    ->activo();
    }

    /**
     * Scope para stock comprometido por cotización
     */
    public function scopePorCotizacion($query, $cotizacionId)
    {
        return $query->where('cotizacion_id', $cotizacionId);
    }

    /**
     * Scope para stock comprometido por nota de venta pendiente
     */
    public function scopePorNotaVentaPendiente($query, $notaVentaPendienteId)
    {
        return $query->where('nota_venta_pendiente_id', $notaVentaPendienteId);
    }

    /**
     * Calcular stock comprometido total para un producto
     */
    public static function calcularStockComprometido($productoCodigo, $bodegaCodigo = '01')
    {
        return self::porProducto($productoCodigo, $bodegaCodigo)
                  ->sum('cantidad_comprometida');
    }

    /**
     * Liberar stock comprometido
     */
    public function liberar($observaciones = null)
    {
        $this->update([
            'fecha_liberacion' => now(),
            'estado' => 'liberado',
            'observaciones' => $observaciones
        ]);
    }

    /**
     * Marcar como procesado (cuando se genera la nota de venta en SQL Server)
     */
    public function procesar()
    {
        $this->update([
            'estado' => 'procesado',
            'observaciones' => $this->observaciones . "\n\n✅ Procesado en SQL Server"
        ]);
    }
}
