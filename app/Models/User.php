<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;
    use HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'codigo_vendedor',
        'rut',
        'email_alternativo',
        'es_vendedor',
        'primer_login',
        'fecha_ultimo_cambio_password'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'es_vendedor' => 'boolean',
        'primer_login' => 'boolean',
        'fecha_ultimo_cambio_password' => 'datetime'
    ];

    /**
     * Relación con vendedor
     */
    public function vendedor()
    {
        return $this->hasOne(Vendedor::class, 'user_id');
    }

    /**
     * Obtener el rol principal del usuario
     */
    public function getRolPrincipalAttribute()
    {
        $roles = $this->roles;
        
        // Prioridad de roles
        $prioridad = ['administrativo', 'supervisor', 'compras', 'finanzas', 'vendedor'];
        
        foreach ($prioridad as $rol) {
            if ($roles->where('name', $rol)->isNotEmpty()) {
                return $rol;
            }
        }
        
        return $roles->first()?->name ?? 'vendedor';
    }

    /**
     * Verificar si es vendedor
     */
    public function isVendedor()
    {
        return $this->es_vendedor || $this->hasRole('vendedor');
    }

    /**
     * Obtener opciones de login (email, email alternativo, RUT)
     */
    public function getOpcionesLoginAttribute()
    {
        $opciones = [];
        
        if ($this->email) {
            $opciones[] = $this->email;
        }
        
        if ($this->email_alternativo) {
            $opciones[] = $this->email_alternativo;
        }
        
        if ($this->rut) {
            $opciones[] = $this->rut;
        }
        
        return $opciones;
    }
}
