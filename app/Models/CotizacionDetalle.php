<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CotizacionDetalle extends Model
{
    use HasFactory;

    protected $table = 'cotizacion_detalles';

    protected $fillable = [
        'cotizacion_id',
        'producto_codigo',
        'producto_nombre',
        'cantidad',
        'precio',
        'subtotal'
    ];

    protected $casts = [
        'cantidad' => 'decimal:2',
        'precio' => 'decimal:2',
        'subtotal' => 'decimal:2'
    ];

    public function cotizacion()
    {
        return $this->belongsTo(Cotizacion::class);
    }
} 