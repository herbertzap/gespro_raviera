<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vendedor extends Model
{
    use HasFactory;

    protected $table = 'vendedores';
    
    protected $fillable = [
        'KOFU',
        'NOKOFU',
        'EMAIL',
        'RTFU',
        'activo',
        'tiene_usuario',
        'user_id'
    ];

    protected $casts = [
        'activo' => 'boolean',
        'tiene_usuario' => 'boolean'
    ];

    /**
     * Relación con el usuario asociado
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope para vendedores activos
     */
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Scope para vendedores sin usuario
     */
    public function scopeSinUsuario($query)
    {
        return $query->where('tiene_usuario', false);
    }

    /**
     * Scope para vendedores con usuario
     */
    public function scopeConUsuario($query)
    {
        return $query->where('tiene_usuario', true);
    }

    /**
     * Obtener vendedores disponibles para crear usuario
     */
    public static function disponiblesParaUsuario()
    {
        return self::activos()->sinUsuario()->get();
    }

    /**
     * Marcar como que tiene usuario
     */
    public function marcarConUsuario($userId)
    {
        $this->update([
            'tiene_usuario' => true,
            'user_id' => $userId
        ]);
    }

    /**
     * Obtener nombre completo del vendedor
     */
    public function getNombreCompletoAttribute()
    {
        return $this->NOKOFU;
    }

    /**
     * Obtener email para login (sistema o alternativo)
     */
    public function getEmailLoginAttribute()
    {
        return $this->EMAIL;
    }
}