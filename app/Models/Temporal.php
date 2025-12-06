<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Temporal extends Model
{
    use HasFactory;

    protected $table = 'temporales';

    protected $fillable = [
        'bodega_id',
        'ubicacion_id',
        'codigo_ubicacion',
        'empresa',
        'kosu',
        'kobo',
        'centro_costo',
        'sku',
        'nombre_producto',
        'rlud',
        'unidad_medida_1',
        'unidad_medida_2',
        'captura_1',
        'captura_2',
        'stfi1',
        'stfi2',
        'funcionario',
        'tido',
    ];

    protected $casts = [
        'rlud' => 'decimal:3',
        'captura_1' => 'decimal:3',
        'captura_2' => 'decimal:3',
        'stfi1' => 'decimal:3',
        'stfi2' => 'decimal:3',
    ];

    public function bodega()
    {
        return $this->belongsTo(Bodega::class);
    }

    public function ubicacion()
    {
        return $this->belongsTo(Ubicacion::class);
    }
}
