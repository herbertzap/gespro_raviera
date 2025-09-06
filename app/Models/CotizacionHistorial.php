<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class CotizacionHistorial extends Model
{
    use HasFactory;

    protected $table = 'cotizacion_historial';

    protected $fillable = [
        'cotizacion_id',
        'estado_anterior',
        'estado_nuevo',
        'tipo_accion',
        'usuario_id',
        'usuario_nombre',
        'rol_usuario',
        'comentarios',
        'detalles_adicionales',
        'fecha_accion',
        'tiempo_transcurrido_segundos'
    ];

    protected $casts = [
        'fecha_accion' => 'datetime',
        'detalles_adicionales' => 'array',
        'tiempo_transcurrido_segundos' => 'integer'
    ];

    /**
     * Relación con la cotización
     */
    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(Cotizacion::class);
    }

    /**
     * Relación con el usuario
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /**
     * Crear un nuevo registro de historial
     */
    public static function crearRegistro(
        int $cotizacionId,
        string $estadoNuevo,
        string $tipoAccion,
        ?string $estadoAnterior = null,
        ?string $comentarios = null,
        ?array $detallesAdicionales = null
    ): self {
        $user = auth()->user();
        $fechaAccion = now();
        
        // Calcular tiempo transcurrido desde el último estado
        $tiempoTranscurrido = null;
        if ($estadoAnterior) {
            $ultimoRegistro = self::where('cotizacion_id', $cotizacionId)
                ->where('estado_nuevo', $estadoAnterior)
                ->latest('fecha_accion')
                ->first();
            
            if ($ultimoRegistro) {
                $tiempoTranscurrido = $fechaAccion->diffInSeconds($ultimoRegistro->fecha_accion);
            }
        }

        return self::create([
            'cotizacion_id' => $cotizacionId,
            'estado_anterior' => $estadoAnterior,
            'estado_nuevo' => $estadoNuevo,
            'tipo_accion' => $tipoAccion,
            'usuario_id' => $user?->id,
            'usuario_nombre' => $user?->name,
            'rol_usuario' => $user?->getRoleNames()->first(),
            'comentarios' => $comentarios,
            'detalles_adicionales' => $detallesAdicionales,
            'fecha_accion' => $fechaAccion,
            'tiempo_transcurrido_segundos' => $tiempoTranscurrido
        ]);
    }

    /**
     * Obtener el historial completo de una cotización
     */
    public static function obtenerHistorialCompleto(int $cotizacionId): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('cotizacion_id', $cotizacionId)
            ->with('usuario')
            ->orderBy('fecha_accion', 'asc')
            ->get();
    }

    /**
     * Obtener estadísticas de tiempos de proceso
     */
    public static function obtenerEstadisticasTiempos(): array
    {
        $estadisticas = [];
        
        // Tiempo promedio de aprobación por Supervisor
        $tiempoAprobacionSupervisor = self::where('tipo_accion', 'aprobacion')
            ->where('rol_usuario', 'Supervisor')
            ->whereNotNull('tiempo_transcurrido_segundos')
            ->avg('tiempo_transcurrido_segundos');
        
        // Tiempo promedio de aprobación por Compras
        $tiempoAprobacionCompras = self::where('tipo_accion', 'aprobacion')
            ->where('rol_usuario', 'Compras')
            ->whereNotNull('tiempo_transcurrido_segundos')
            ->avg('tiempo_transcurrido_segundos');
        
        // Tiempo promedio de aprobación por Picking
        $tiempoAprobacionPicking = self::where('tipo_accion', 'aprobacion')
            ->where('rol_usuario', 'Picking')
            ->whereNotNull('tiempo_transcurrido_segundos')
            ->avg('tiempo_transcurrido_segundos');
        
        // Tiempo promedio total hasta despacho
        $tiempoTotalDespacho = self::where('estado_nuevo', 'despachada')
            ->whereNotNull('tiempo_transcurrido_segundos')
            ->avg('tiempo_transcurrido_segundos');

        return [
            'tiempo_aprobacion_supervisor' => $tiempoAprobacionSupervisor ? round($tiempoAprobacionSupervisor / 60, 2) : null, // en minutos
            'tiempo_aprobacion_compras' => $tiempoAprobacionCompras ? round($tiempoAprobacionCompras / 60, 2) : null,
            'tiempo_aprobacion_picking' => $tiempoAprobacionPicking ? round($tiempoAprobacionPicking / 60, 2) : null,
            'tiempo_total_despacho' => $tiempoTotalDespacho ? round($tiempoTotalDespacho / 3600, 2) : null, // en horas
        ];
    }

    /**
     * Obtener cotizaciones que están tardando más de lo esperado
     */
    public static function obtenerCotizacionesRetrasadas(int $horasLimite = 36): \Illuminate\Database\Eloquent\Collection
    {
        $fechaLimite = now()->subHours($horasLimite);
        
        return self::where('fecha_accion', '<', $fechaLimite)
            ->whereNotIn('estado_nuevo', ['despachada', 'rechazada'])
            ->with(['cotizacion', 'usuario'])
            ->get()
            ->groupBy('cotizacion_id')
            ->map(function ($registros) {
                return $registros->last(); // Último estado de cada cotización
            });
    }

    /**
     * Formatear tiempo transcurrido para mostrar
     */
    public function getTiempoTranscurridoFormateadoAttribute(): string
    {
        if (!$this->tiempo_transcurrido_segundos) {
            return 'N/A';
        }

        $segundos = $this->tiempo_transcurrido_segundos;
        
        if ($segundos < 60) {
            return $segundos . ' seg';
        } elseif ($segundos < 3600) {
            return round($segundos / 60, 1) . ' min';
        } elseif ($segundos < 86400) {
            return round($segundos / 3600, 1) . ' hrs';
        } else {
            return round($segundos / 86400, 1) . ' días';
        }
    }

    /**
     * Obtener el estado actual de una cotización
     */
    public static function obtenerEstadoActual(int $cotizacionId): ?self
    {
        return self::where('cotizacion_id', $cotizacionId)
            ->latest('fecha_accion')
            ->first();
    }

    /**
     * Verificar si una cotización está en tiempo
     */
    public static function estaEnTiempo(int $cotizacionId, int $horasLimite = 36): bool
    {
        $primerRegistro = self::where('cotizacion_id', $cotizacionId)
            ->oldest('fecha_accion')
            ->first();
        
        if (!$primerRegistro) {
            return true;
        }

        $tiempoTranscurrido = now()->diffInHours($primerRegistro->fecha_accion);
        return $tiempoTranscurrido <= $horasLimite;
    }
}