<?php

namespace App\Services;

use App\Models\Notificacion;
use App\Models\User;
use App\Models\Cotizacion;
use App\Models\Cliente;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class NotificacionService
{
    /**
     * Notificar nueva cotización pendiente de aprobación
     */
    public static function notificarNuevaCotizacion(Cotizacion $cotizacion): void
    {
        $datosAdicionales = [
            'cotizacion_id' => $cotizacion->id,
            'cliente_codigo' => $cotizacion->cliente_codigo,
            'cliente_nombre' => $cotizacion->cliente_nombre,
            'total' => $cotizacion->total,
            'vendedor' => $cotizacion->user->name ?? 'N/A'
        ];

        // Notificar a Supervisores si hay problemas de crédito
        if ($cotizacion->tiene_problemas_credito) {
            $supervisores = User::role('Supervisor')->get();
            
            foreach ($supervisores as $supervisor) {
                Notificacion::crear(
                    'aprobacion_pendiente',
                    'Nueva cotización pendiente de aprobación',
                    "Cotización #{$cotizacion->id} del cliente {$cotizacion->cliente_nombre} requiere aprobación por problemas de crédito",
                    $supervisor->id,
                    'alta',
                    $datosAdicionales,
                    route('aprobaciones.show', $cotizacion->id),
                    'check-2',
                    'warning'
                );
            }
        }

        // Notificar a Compras si hay problemas de stock
        if ($cotizacion->tiene_problemas_stock) {
            $compras = User::role('Compras')->get();
            
            foreach ($compras as $compra) {
                Notificacion::crear(
                    'aprobacion_pendiente',
                    'Nueva cotización con problemas de stock',
                    "Cotización #{$cotizacion->id} del cliente {$cotizacion->cliente_nombre} requiere revisión de stock",
                    $compra->id,
                    'alta',
                    $datosAdicionales,
                    route('aprobaciones.show', $cotizacion->id),
                    'box-2',
                    'warning'
                );
            }
        }

        Log::info("📢 Notificaciones enviadas para cotización {$cotizacion->id}");
    }

    /**
     * Notificar facturas vencidas
     */
    public static function notificarFacturasVencidas(): void
    {
        // Obtener facturas vencidas desde SQL Server
        $cobranzaService = app(CobranzaService::class);
        $facturasVencidas = $cobranzaService->getFacturasVencidas();
        
        if (empty($facturasVencidas)) {
            return;
        }

        foreach ($facturasVencidas as $factura) {
            $vendedor = User::where('codigo_vendedor', $factura['CODIGO_VENDEDOR'])->first();
            
            if ($vendedor) {
                $diasVencidos = Carbon::parse($factura['FECHA_VENCIMIENTO'])->diffInDays(now());
                
                Notificacion::crear(
                    'factura_vencida',
                    'Factura vencida',
                    "Factura {$factura['NUMERO_DOCUMENTO']} del cliente {$factura['NOMBRE_CLIENTE']} está vencida hace {$diasVencidos} días",
                    $vendedor->id,
                    $diasVencidos > 30 ? 'urgente' : 'alta',
                    [
                        'numero_documento' => $factura['NUMERO_DOCUMENTO'],
                        'cliente_codigo' => $factura['CODIGO_CLIENTE'],
                        'cliente_nombre' => $factura['NOMBRE_CLIENTE'],
                        'monto' => $factura['MONTO'],
                        'fecha_vencimiento' => $factura['FECHA_VENCIMIENTO'],
                        'dias_vencidos' => $diasVencidos
                    ],
                    route('clientes.show', $factura['CODIGO_CLIENTE']),
                    'alert-triangle',
                    'danger'
                );
            }
        }

        Log::info("📢 Notificaciones de facturas vencidas enviadas");
    }

    /**
     * Notificar clientes bloqueados
     */
    public static function notificarClientesBloqueados(): void
    {
        $clientesBloqueados = Cliente::where('bloqueado', true)
            ->where('activo', true)
            ->with('vendedor')
            ->get();

        foreach ($clientesBloqueados as $cliente) {
            if ($cliente->vendedor) {
                Notificacion::crear(
                    'cliente_bloqueado',
                    'Cliente bloqueado',
                    "El cliente {$cliente->nombre_cliente} ({$cliente->codigo_cliente}) está bloqueado",
                    $cliente->vendedor->id,
                    'normal',
                    [
                        'cliente_codigo' => $cliente->codigo_cliente,
                        'cliente_nombre' => $cliente->nombre_cliente,
                        'motivo_bloqueo' => $cliente->motivo_bloqueo ?? 'No especificado'
                    ],
                    route('clientes.show', $cliente->codigo_cliente),
                    'simple-remove',
                    'warning'
                );
            }
        }

        Log::info("📢 Notificaciones de clientes bloqueados enviadas");
    }

    /**
     * Notificar cotizaciones retrasadas
     */
    public static function notificarCotizacionesRetrasadas(): void
    {
        $cotizacionesRetrasadas = \App\Models\CotizacionHistorial::obtenerCotizacionesRetrasadas(36);
        
        foreach ($cotizacionesRetrasadas as $registro) {
            $cotizacion = $registro->cotizacion;
            $vendedor = $cotizacion->user;
            
            if ($vendedor) {
                $tiempoTranscurrido = now()->diffInHours($registro->fecha_accion);
                
                Notificacion::crear(
                    'cotizacion_retrasada',
                    'Cotización retrasada',
                    "La cotización #{$cotizacion->id} lleva {$tiempoTranscurrido} horas sin procesar",
                    $vendedor->id,
                    'alta',
                    [
                        'cotizacion_id' => $cotizacion->id,
                        'cliente_codigo' => $cotizacion->cliente_codigo,
                        'cliente_nombre' => $cotizacion->cliente_nombre,
                        'tiempo_transcurrido' => $tiempoTranscurrido,
                        'estado_actual' => $registro->estado_nuevo
                    ],
                    route('cotizacion.historial', $cotizacion->id),
                    'time-alarm',
                    'warning'
                );
            }
        }

        Log::info("📢 Notificaciones de cotizaciones retrasadas enviadas");
    }

    /**
     * Notificar aprobación completada
     */
    public static function notificarAprobacionCompletada(Cotizacion $cotizacion, string $rolAprobador): void
    {
        $vendedor = $cotizacion->user;
        
        if ($vendedor) {
            Notificacion::crear(
                'aprobacion_completada',
                'Cotización aprobada',
                "Su cotización #{$cotizacion->id} ha sido aprobada por {$rolAprobador}",
                $vendedor->id,
                'normal',
                [
                    'cotizacion_id' => $cotizacion->id,
                    'cliente_codigo' => $cotizacion->cliente_codigo,
                    'cliente_nombre' => $cotizacion->cliente_nombre,
                    'rol_aprobador' => $rolAprobador
                ],
                route('cotizacion.ver', $cotizacion->id),
                'check-2',
                'success'
            );
        }

        Log::info("📢 Notificación de aprobación enviada para cotización {$cotizacion->id}");
    }

    /**
     * Notificar rechazo de cotización
     */
    public static function notificarRechazo(Cotizacion $cotizacion, string $motivo): void
    {
        $vendedor = $cotizacion->user;
        
        if ($vendedor) {
            Notificacion::crear(
                'cotizacion_rechazada',
                'Cotización rechazada',
                "Su cotización #{$cotizacion->id} ha sido rechazada: {$motivo}",
                $vendedor->id,
                'alta',
                [
                    'cotizacion_id' => $cotizacion->id,
                    'cliente_codigo' => $cotizacion->cliente_codigo,
                    'cliente_nombre' => $cotizacion->cliente_nombre,
                    'motivo_rechazo' => $motivo
                ],
                route('cotizacion.ver', $cotizacion->id),
                'simple-remove',
                'danger'
            );
        }

        Log::info("📢 Notificación de rechazo enviada para cotización {$cotizacion->id}");
    }

    /**
     * Notificar despacho completado
     */
    public static function notificarDespachoCompletado(Cotizacion $cotizacion): void
    {
        $vendedor = $cotizacion->user;
        
        if ($vendedor) {
            Notificacion::crear(
                'despacho_completado',
                'Pedido despachado',
                "El pedido de la cotización #{$cotizacion->id} ha sido despachado exitosamente",
                $vendedor->id,
                'normal',
                [
                    'cotizacion_id' => $cotizacion->id,
                    'cliente_codigo' => $cotizacion->cliente_codigo,
                    'cliente_nombre' => $cotizacion->cliente_nombre
                ],
                route('cotizacion.historial', $cotizacion->id),
                'delivery-fast',
                'success'
            );
        }

        Log::info("📢 Notificación de despacho enviada para cotización {$cotizacion->id}");
    }

    /**
     * Limpiar notificaciones vencidas
     */
    public static function limpiarNotificacionesVencidas(): void
    {
        $eliminadas = Notificacion::where('fecha_vencimiento', '<', now())
            ->where('estado', 'leida')
            ->delete();

        Log::info("📢 Se eliminaron {$eliminadas} notificaciones vencidas");
    }

    /**
     * Obtener estadísticas de notificaciones
     */
    public static function obtenerEstadisticas(): array
    {
        return [
            'total_no_leidas' => Notificacion::noLeidas()->count(),
            'por_prioridad' => Notificacion::noLeidas()
                ->selectRaw('prioridad, COUNT(*) as total')
                ->groupBy('prioridad')
                ->pluck('total', 'prioridad'),
            'por_tipo' => Notificacion::noLeidas()
                ->selectRaw('tipo, COUNT(*) as total')
                ->groupBy('tipo')
                ->pluck('total', 'tipo'),
            'urgentes' => Notificacion::noLeidas()->porPrioridad('urgente')->count()
        ];
    }
}
