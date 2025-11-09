<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CotizacionProducto extends Model
{
    use HasFactory;

    protected $table = 'cotizacion_productos';

    protected $fillable = [
        'cotizacion_id',
        'codigo_producto',
        'nombre_producto',
        'precio_unitario',
        'cantidad',
        'cantidad_separar',
        'subtotal',
        'descuento_porcentaje',
        'descuento_valor',
        'subtotal_con_descuento',
        'iva_porcentaje',
        'iva_valor',
        'total_producto',
        'stock_disponible',
        'stock_suficiente',
        'pendiente_entrega'
    ];

    protected $casts = [
        'cantidad' => 'integer',
        'cantidad_separar' => 'decimal:2',
        'precio_unitario' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'descuento_porcentaje' => 'decimal:2',
        'descuento_valor' => 'decimal:2',
        'subtotal_con_descuento' => 'decimal:2',
        'iva_porcentaje' => 'decimal:2',
        'iva_valor' => 'decimal:2',
        'total_producto' => 'decimal:2',
        'stock_disponible' => 'integer',
        'stock_suficiente' => 'boolean',
        'pendiente_entrega' => 'boolean'
    ];

    public function cotizacion()
    {
        return $this->belongsTo(Cotizacion::class);
    }
}
