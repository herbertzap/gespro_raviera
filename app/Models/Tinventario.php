<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tinventario extends Model
{
    use HasFactory;

    protected $table = 'tinventario';

    protected $fillable = [
        'user_id',
        'bodega_id',
        'ubicacion_id',
        'codigo_ubicacion',
        'empresa',
        'kosu',
        'kobo',
        'centro_costo',
        'sku',
        'nombre_producto',
        'codigo_barras',
        'rlud',
        'unidad_medida_1',
        'unidad_medida_2',
        'cantidad',
        'cantidad_ud2',
        'funcionario',
        'fecha_barrido',
    ];

    protected $casts = [
        'rlud' => 'decimal:3',
        'cantidad' => 'decimal:3',
        'cantidad_ud2' => 'decimal:3',
        'fecha_barrido' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function bodega()
    {
        return $this->belongsTo(Bodega::class);
    }

    public function ubicacion()
    {
        return $this->belongsTo(Ubicacion::class);
    }

    /**
     * Scope para filtrar por rango de fechas
     */
    public function scopeFechaEntre($query, $desde, $hasta)
    {
        if ($desde) {
            $query->whereDate('fecha_barrido', '>=', $desde);
        }
        if ($hasta) {
            $query->whereDate('fecha_barrido', '<=', $hasta);
        }
        return $query;
    }

    /**
     * Scope para filtrar por usuario/funcionario
     */
    public function scopePorUsuario($query, $funcionario)
    {
        if ($funcionario) {
            return $query->where('funcionario', $funcionario);
        }
        return $query;
    }

    /**
     * Scope para filtrar por bodega
     */
    public function scopePorBodega($query, $bodegaId)
    {
        if ($bodegaId) {
            return $query->where('bodega_id', $bodegaId);
        }
        return $query;
    }

    /**
     * Obtener reporte detallado (sin consolidar)
     */
    public static function reporteDetallado($filtros = [])
    {
        $query = self::with(['bodega', 'ubicacion', 'user'])
            ->fechaEntre($filtros['fecha_desde'] ?? null, $filtros['fecha_hasta'] ?? null)
            ->porUsuario($filtros['funcionario'] ?? null)
            ->porBodega($filtros['bodega_id'] ?? null)
            ->orderBy('fecha_barrido', 'desc')
            ->orderBy('created_at', 'desc');

        return $query;
    }

    /**
     * Obtener reporte consolidado (agrupado por SKU y Bodega)
     */
    public static function reporteConsolidado($filtros = [])
    {
        $query = self::selectRaw('
                sku,
                nombre_producto,
                bodega_id,
                kobo,
                unidad_medida_1,
                unidad_medida_2,
                rlud,
                SUM(cantidad) as cantidad_total,
                SUM(cantidad_ud2) as cantidad_ud2_total,
                COUNT(*) as total_registros,
                MIN(fecha_barrido) as primera_fecha,
                MAX(fecha_barrido) as ultima_fecha
            ')
            ->fechaEntre($filtros['fecha_desde'] ?? null, $filtros['fecha_hasta'] ?? null)
            ->porUsuario($filtros['funcionario'] ?? null)
            ->porBodega($filtros['bodega_id'] ?? null)
            ->groupBy('sku', 'nombre_producto', 'bodega_id', 'kobo', 'unidad_medida_1', 'unidad_medida_2', 'rlud')
            ->orderBy('sku');

        return $query;
    }
}


