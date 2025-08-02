<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockLocal extends Model
{
    use HasFactory;

    protected $table = 'stock_local';
    
    protected $fillable = [
        'codigo_producto',
        'nombre_producto',
        'codigo_bodega',
        'nombre_bodega',
        'stock_fisico',
        'stock_comprometido',
        'stock_disponible',
        'unidad_medida',
        'precio_venta',
        'activo',
        'ultima_actualizacion'
    ];

    protected $casts = [
        'stock_fisico' => 'decimal:2',
        'stock_comprometido' => 'decimal:2',
        'stock_disponible' => 'decimal:2',
        'precio_venta' => 'decimal:2',
        'activo' => 'boolean',
        'ultima_actualizacion' => 'datetime'
    ];

    /**
     * Verificar si hay stock suficiente para una cantidad
     */
    public function tieneStockSuficiente($cantidad)
    {
        return $this->stock_disponible >= $cantidad;
    }

    /**
     * Reservar stock (comprometer)
     */
    public function reservarStock($cantidad)
    {
        if ($this->tieneStockSuficiente($cantidad)) {
            $this->stock_comprometido += $cantidad;
            $this->stock_disponible = $this->stock_fisico - $this->stock_comprometido;
            $this->ultima_actualizacion = now();
            return $this->save();
        }
        return false;
    }

    /**
     * Liberar stock comprometido
     */
    public function liberarStock($cantidad)
    {
        $this->stock_comprometido = max(0, $this->stock_comprometido - $cantidad);
        $this->stock_disponible = $this->stock_fisico - $this->stock_comprometido;
        $this->ultima_actualizacion = now();
        return $this->save();
    }

    /**
     * Actualizar stock físico (desde SQL Server)
     */
    public function actualizarStockFisico($stockFisico)
    {
        $this->stock_fisico = $stockFisico;
        $this->stock_disponible = $this->stock_fisico - $this->stock_comprometido;
        $this->ultima_actualizacion = now();
        return $this->save();
    }

    /**
     * Scope para productos activos
     */
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Scope para productos con stock disponible
     */
    public function scopeConStock($query, $cantidad = 0)
    {
        return $query->where('stock_disponible', '>', $cantidad);
    }

    /**
     * Scope para productos sin stock
     */
    public function scopeSinStock($query)
    {
        return $query->where('stock_disponible', '<=', 0);
    }
}
