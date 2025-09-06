<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class Notificacion extends Model
{
    use HasFactory;

    protected $table = 'notificaciones';

    protected $fillable = [
        'usuario_id',
        'tipo',
        'titulo',
        'mensaje',
        'prioridad',
        'estado',
        'datos_adicionales',
        'url_accion',
        'icono',
        'color',
        'fecha_vencimiento',
        'fecha_leida'
    ];

    protected $casts = [
        'datos_adicionales' => 'array',
        'fecha_vencimiento' => 'datetime',
        'fecha_leida' => 'datetime'
    ];

    /**
     * Relación con el usuario
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /**
     * Crear una nueva notificación
     */
    public static function crear(
        string $tipo,
        string $titulo,
        string $mensaje,
        ?int $usuarioId = null,
        string $prioridad = 'normal',
        ?array $datosAdicionales = null,
        ?string $urlAccion = null,
        string $icono = 'info',
        string $color = 'primary',
        ?Carbon $fechaVencimiento = null
    ): self {
        return self::create([
            'usuario_id' => $usuarioId,
            'tipo' => $tipo,
            'titulo' => $titulo,
            'mensaje' => $mensaje,
            'prioridad' => $prioridad,
            'datos_adicionales' => $datosAdicionales,
            'url_accion' => $urlAccion,
            'icono' => $icono,
            'color' => $color,
            'fecha_vencimiento' => $fechaVencimiento
        ]);
    }

    /**
     * Obtener notificaciones no leídas de un usuario
     */
    public static function obtenerNoLeidas(int $usuarioId, int $limite = 5): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('usuario_id', $usuarioId)
            ->where('estado', 'no_leida')
            ->where(function($query) {
                $query->whereNull('fecha_vencimiento')
                      ->orWhere('fecha_vencimiento', '>', now());
            })
            ->orderBy('prioridad', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit($limite)
            ->get();
    }

    /**
     * Obtener notificaciones globales no leídas
     */
    public static function obtenerGlobalesNoLeidas(int $limite = 5): \Illuminate\Database\Eloquent\Collection
    {
        return self::whereNull('usuario_id')
            ->where('estado', 'no_leida')
            ->where(function($query) {
                $query->whereNull('fecha_vencimiento')
                      ->orWhere('fecha_vencimiento', '>', now());
            })
            ->orderBy('prioridad', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit($limite)
            ->get();
    }

    /**
     * Marcar como leída
     */
    public function marcarComoLeida(): void
    {
        $this->update([
            'estado' => 'leida',
            'fecha_leida' => now()
        ]);
    }

    /**
     * Marcar como archivada
     */
    public function archivar(): void
    {
        $this->update(['estado' => 'archivada']);
    }

    /**
     * Obtener el color del badge según la prioridad
     */
    public function getColorBadgeAttribute(): string
    {
        $colores = [
            'baja' => 'secondary',
            'normal' => 'primary',
            'alta' => 'warning',
            'urgente' => 'danger'
        ];
        
        return $colores[$this->prioridad] ?? 'primary';
    }

    /**
     * Obtener el icono según el tipo
     */
    public function getIconoCompletoAttribute(): string
    {
        $iconos = [
            'nueva_cotizacion' => 'tim-icons icon-notes',
            'aprobacion_pendiente' => 'tim-icons icon-check-2',
            'factura_vencida' => 'tim-icons icon-alert-triangle',
            'stock_bajo' => 'tim-icons icon-box-2',
            'cliente_bloqueado' => 'tim-icons icon-simple-remove',
            'nvv_generada' => 'tim-icons icon-send',
            'despacho_listo' => 'tim-icons icon-delivery-fast',
            'sistema' => 'tim-icons icon-settings'
        ];
        
        return $iconos[$this->tipo] ?? 'tim-icons icon-info';
    }

    /**
     * Verificar si la notificación está vencida
     */
    public function estaVencida(): bool
    {
        return $this->fecha_vencimiento && $this->fecha_vencimiento->isPast();
    }

    /**
     * Obtener tiempo transcurrido desde la creación
     */
    public function getTiempoTranscurridoAttribute(): string
    {
        return $this->created_at->diffForHumans();
    }

    /**
     * Scopes
     */
    public function scopeNoLeidas($query)
    {
        return $query->where('estado', 'no_leida');
    }

    public function scopePorUsuario($query, int $usuarioId)
    {
        return $query->where('usuario_id', $usuarioId);
    }

    public function scopeGlobales($query)
    {
        return $query->whereNull('usuario_id');
    }

    public function scopePorTipo($query, string $tipo)
    {
        return $query->where('tipo', $tipo);
    }

    public function scopePorPrioridad($query, string $prioridad)
    {
        return $query->where('prioridad', $prioridad);
    }

    public function scopeNoVencidas($query)
    {
        return $query->where(function($q) {
            $q->whereNull('fecha_vencimiento')
              ->orWhere('fecha_vencimiento', '>', now());
        });
    }
}