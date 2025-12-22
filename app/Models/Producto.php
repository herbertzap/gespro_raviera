<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    use HasFactory;
    protected $table = 'productos';
    protected $fillable = [
        'TIPR',
        'KOPR',
        'KOPRRA',
        'KOPRTE',
        'NOKOPR',
        'NOKOPRRA',
        'UD01PR',
        'UD02PR',
        'RLUD',
        'POIVPR',
        'RGPR',
        'MRPR',
        'FMPR',
        'PFPR',
        'HFPR',
        'DIVISIBLE',
        'DIVISIBLE2',
        'FECRPR',
        'estado',
        'ultima_sincronizacion',
        // Precios
        'precio_01p',
        'precio_01p_ud2',
        'descuento_maximo_01p',
        'precio_02p',
        'precio_02p_ud2',
        'descuento_maximo_02p',
        'precio_03p',
        'precio_03p_ud2',
        'descuento_maximo_03p',
        // Stock
        'stock_fisico',
        'stock_comprometido',
        'stock_disponible',
        'activo',
        // Múltiplo de venta
        'multiplo_venta'
    ];

    protected $casts = [
        'precio_01p' => 'decimal:2',
        'precio_01p_ud2' => 'decimal:2',
        'descuento_maximo_01p' => 'decimal:2',
        'precio_02p' => 'decimal:2',
        'precio_02p_ud2' => 'decimal:2',
        'descuento_maximo_02p' => 'decimal:2',
        'precio_03p' => 'decimal:2',
        'precio_03p_ud2' => 'decimal:2',
        'descuento_maximo_03p' => 'decimal:2',
        'stock_fisico' => 'decimal:2',
        'stock_comprometido' => 'decimal:2',
        'stock_disponible' => 'decimal:2',
        'DIVISIBLE' => 'boolean',
        'DIVISIBLE2' => 'boolean',
        'activo' => 'boolean',
        'estado' => 'integer',
        'multiplo_venta' => 'integer'
    ];

    // Scope para productos activos
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    // Scope para excluir productos ocultos (TIPR = 'OCU')
    public function scopeNoOcultos($query)
    {
        return $query->where(function($q) {
            $q->where('TIPR', '!=', 'OCU')
              ->orWhereNull('TIPR');
        });
    }

    // Método para obtener precio según lista
    public function getPrecio($listaPrecios = '01P')
    {
        switch ($listaPrecios) {
            case '01P':
                return $this->precio_01p;
            case '02P':
                return $this->precio_02p;
            case '03P':
                return $this->precio_03p;
            default:
                return $this->precio_01p;
        }
    }

    // Método para obtener descuento máximo según lista
    public function getDescuentoMaximo($listaPrecios = '01P')
    {
        switch ($listaPrecios) {
            case '01P':
                return $this->descuento_maximo_01p;
            case '02P':
                return $this->descuento_maximo_02p;
            case '03P':
                return $this->descuento_maximo_03p;
            default:
                return $this->descuento_maximo_01p;
        }
    }
}
