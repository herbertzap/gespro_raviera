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
        'stock_disponible',
        'stock_suficiente'
    ];

    protected $casts = [
        'cantidad' => 'integer',
        'cantidad_separar' => 'decimal:2',
        'precio_unitario' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'stock_disponible' => 'integer',
        'stock_suficiente' => 'boolean'
    ];

    public function cotizacion()
    {
        return $this->belongsTo(Cotizacion::class);
    }
}
