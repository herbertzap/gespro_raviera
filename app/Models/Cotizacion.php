<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cotizacion extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'cliente_codigo',
        'cliente_nombre',
        'fecha',
        'estado',
        'total',
        'total_sin_descuento',
        'descuento_global',
        'porcentaje_descuento',
        'fecha_aprobacion',
        'aprobado_por',
        'motivo_rechazo',
        'requiere_aprobacion',
        'observaciones',
        'nota_venta_id'
    ];

    protected $casts = [
        'fecha' => 'datetime',
        'total' => 'decimal:2'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function detalles()
    {
        return $this->hasMany(CotizacionDetalle::class);
    }
}
