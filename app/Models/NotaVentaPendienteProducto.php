<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotaVentaPendienteProducto extends Model
{
    use HasFactory;

    protected $table = 'nota_venta_pendiente_productos';
    
    protected $fillable = [
        'nota_venta_pendiente_id',
        'codigo_producto',
        'nombre_producto',
        'cantidad',
        'precio_unitario',
        'subtotal',
        'unidad_medida',
        'stock_disponible',
        'stock_comprometido',
        'stock_suficiente',
        'problemas_stock'
    ];

    protected $casts = [
        'cantidad' => 'decimal:2',
        'precio_unitario' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'stock_disponible' => 'decimal:2',
        'stock_comprometido' => 'decimal:2',
        'stock_suficiente' => 'boolean'
    ];

    /**
     * Relación con la nota de venta pendiente
     */
    public function notaVentaPendiente()
    {
        return $this->belongsTo(NotaVentaPendiente::class);
    }
}
