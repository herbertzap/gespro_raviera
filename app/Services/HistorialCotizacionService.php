<?php

namespace App\Services;

use App\Models\Cotizacion;
use App\Models\CotizacionHistorial;
use Illuminate\Support\Facades\Log;

class HistorialCotizacionService
{
    /**
     * Registrar la creación de una cotización
     */
    public static function registrarCreacion(Cotizacion $cotizacion): void
    {
        CotizacionHistorial::crearRegistro(
            $cotizacion->id,
            'borrador',
            'creacion',
            null,
            'Cotización creada',
            [
                'cliente_codigo' => $cotizacion->cliente_codigo,
                'cliente_nombre' => $cotizacion->cliente_nombre,
                'total' => $cotizacion->total,
                'productos_count' => $cotizacion->productos->count()
            ]
        );
        
        Log::info("📝 Historial: Cotización {$cotizacion->id} creada");
    }

    /**
     * Registrar el envío de una cotización
     */
    public static function registrarEnvio(Cotizacion $cotizacion, string $estadoAnterior = 'borrador'): void
    {
        CotizacionHistorial::crearRegistro(
            $cotizacion->id,
            'enviada',
            'envio',
            $estadoAnterior,
            'Cotización enviada para aprobación',
            [
                'estado_anterior' => $estadoAnterior,
                'requiere_aprobacion' => $cotizacion->requiere_aprobacion
            ]
        );
        
        Log::info("📤 Historial: Cotización {$cotizacion->id} enviada");
    }

    /**
     * Registrar aprobación por Supervisor
     */
    public static function registrarAprobacionSupervisor(Cotizacion $cotizacion, string $comentarios = null): void
    {
        CotizacionHistorial::crearRegistro(
            $cotizacion->id,
            'aprobada_supervisor',
            'aprobacion',
            $cotizacion->estado_aprobacion,
            $comentarios ?? 'Aprobada por Supervisor',
            [
                'rol_aprobador' => 'Supervisor',
                'tiene_problemas_credito' => $cotizacion->tiene_problemas_credito,
                'detalle_problemas_credito' => $cotizacion->detalle_problemas_credito
            ]
        );
        
        Log::info("✅ Historial: Cotización {$cotizacion->id} aprobada por Supervisor");
    }

    /**
     * Registrar aprobación por Compras
     */
    public static function registrarAprobacionCompras(Cotizacion $cotizacion, string $comentarios = null): void
    {
        CotizacionHistorial::crearRegistro(
            $cotizacion->id,
            'aprobada_compras',
            'aprobacion',
            $cotizacion->estado_aprobacion,
            $comentarios ?? 'Aprobada por Compras',
            [
                'rol_aprobador' => 'Compras',
                'tiene_problemas_stock' => $cotizacion->tiene_problemas_stock,
                'detalle_problemas_stock' => $cotizacion->detalle_problemas_stock
            ]
        );
        
        Log::info("✅ Historial: Cotización {$cotizacion->id} aprobada por Compras");
    }

    /**
     * Registrar aprobación por Picking
     */
    public static function registrarAprobacionPicking(Cotizacion $cotizacion, string $comentarios = null, int $numeroNvvSQL = null): void
    {
        $datosAdicionales = [
            'rol_aprobador' => 'Picking',
            'stock_verificado' => true
        ];
        
        // Si se generó el número de NVV en SQL Server, agregarlo
        if ($numeroNvvSQL) {
            $datosAdicionales['numero_nvv_sql'] = $numeroNvvSQL;
        }
        
        // Si ya está guardado en la cotización, usarlo
        if ($cotizacion->numero_nvv) {
            $datosAdicionales['numero_nvv_sql'] = $cotizacion->numero_nvv;
            $comentarios = ($comentarios ?? 'Aprobada por Picking') . " - NVV #{$cotizacion->numero_nvv} generada en SQL Server";
        }
        
        CotizacionHistorial::crearRegistro(
            $cotizacion->id,
            'aprobada_picking',
            'aprobacion',
            $cotizacion->estado_aprobacion,
            $comentarios ?? 'Aprobada por Picking',
            $datosAdicionales
        );
        
        Log::info("✅ Historial: Cotización {$cotizacion->id} aprobada por Picking" . ($cotizacion->numero_nvv ? " - NVV #{$cotizacion->numero_nvv}" : ''));
    }

    /**
     * Registrar rechazo de una cotización
     */
    public static function registrarRechazo(Cotizacion $cotizacion, string $motivo, string $estadoAnterior, string $rol, string $comentarios = null): void
    {
        $user = auth()->user();
        
        CotizacionHistorial::crearRegistro(
            $cotizacion->id,
            'rechazada',
            'rechazo',
            $estadoAnterior,
            $comentarios ?? "Rechazada por {$rol}: {$motivo}",
            [
                'motivo_rechazo' => $motivo,
                'rol_rechazador' => $rol,
                'usuario_rechazador' => $user?->name,
                'usuario_id_rechazador' => $user?->id,
                'estado_anterior' => $estadoAnterior
            ]
        );
        
        Log::info("❌ Historial: Cotización {$cotizacion->id} rechazada por {$rol} - {$motivo}");
    }

    /**
     * Registrar separación de productos por problemas de stock
     */
    public static function registrarSeparacionProductos(Cotizacion $cotizacionOriginal, Cotizacion $cotizacionNueva, array $productosSeparados): void
    {
        // Registrar en la cotización original
        CotizacionHistorial::crearRegistro(
            $cotizacionOriginal->id,
            'aprobada_compras',
            'separacion',
            $cotizacionOriginal->estado_aprobacion,
            'Productos separados por problemas de stock',
            [
                'cotizacion_nueva_id' => $cotizacionNueva->id,
                'productos_separados' => $productosSeparados,
                'cantidad_productos_separados' => count($productosSeparados)
            ]
        );

        // Registrar en la nueva cotización
        CotizacionHistorial::crearRegistro(
            $cotizacionNueva->id,
            'pendiente_picking',
            'creacion',
            null,
            'Cotización creada por separación de productos',
            [
                'cotizacion_original_id' => $cotizacionOriginal->id,
                'productos_separados' => $productosSeparados,
                'motivo_separacion' => 'Problemas de stock'
            ]
        );
        
        Log::info("🔄 Historial: Productos separados de cotización {$cotizacionOriginal->id} a {$cotizacionNueva->id}");
    }

    /**
     * Registrar inserción en SQL Server
     */
    public static function registrarInsercionSQL(Cotizacion $cotizacion, string $nvvId = null): void
    {
        CotizacionHistorial::crearRegistro(
            $cotizacion->id,
            'enviada_sql',
            'insercion_sql',
            $cotizacion->estado_aprobacion,
            'Cotización insertada en SQL Server',
            [
                'nvv_id' => $nvvId,
                'sistema_destino' => 'SQL Server',
                'fecha_insercion' => now()->toISOString()
            ]
        );
        
        Log::info("💾 Historial: Cotización {$cotizacion->id} insertada en SQL Server");
    }

    /**
     * Registrar generación de NVV
     */
    public static function registrarGeneracionNVV(Cotizacion $cotizacion, string $nvvId): void
    {
        CotizacionHistorial::crearRegistro(
            $cotizacion->id,
            'nvv_generada',
            'generacion_nvv',
            'enviada_sql',
            'NVV generada en SQL Server',
            [
                'nvv_id' => $nvvId,
                'sistema_origen' => 'SQL Server',
                'fecha_generacion' => now()->toISOString()
            ]
        );
        
        Log::info("📄 Historial: NVV {$nvvId} generada para cotización {$cotizacion->id}");
    }

    /**
     * Registrar facturación
     */
    public static function registrarFacturacion(Cotizacion $cotizacion, string $facturaId): void
    {
        CotizacionHistorial::crearRegistro(
            $cotizacion->id,
            'nvv_facturada',
            'facturacion',
            'nvv_generada',
            'NVV facturada',
            [
                'factura_id' => $facturaId,
                'sistema_origen' => 'SQL Server',
                'fecha_facturacion' => now()->toISOString()
            ]
        );
        
        Log::info("🧾 Historial: Factura {$facturaId} generada para cotización {$cotizacion->id}");
    }

    /**
     * Registrar despacho
     */
    public static function registrarDespacho(Cotizacion $cotizacion, string $guiaDespacho = null): void
    {
        CotizacionHistorial::crearRegistro(
            $cotizacion->id,
            'despachada',
            'despacho',
            'nvv_facturada',
            'Pedido despachado',
            [
                'guia_despacho' => $guiaDespacho,
                'fecha_despacho' => now()->toISOString(),
                'objetivo_cumplido' => self::verificarObjetivo36Horas($cotizacion)
            ]
        );
        
        Log::info("🚚 Historial: Cotización {$cotizacion->id} despachada");
    }

    /**
     * Verificar si se cumplió el objetivo de 36 horas
     */
    public static function verificarObjetivo36Horas(Cotizacion $cotizacion): bool
    {
        $primerRegistro = CotizacionHistorial::where('cotizacion_id', $cotizacion->id)
            ->oldest('fecha_accion')
            ->first();
        
        if (!$primerRegistro) {
            return false;
        }

        $tiempoTranscurrido = now()->diffInHours($primerRegistro->fecha_accion);
        return $tiempoTranscurrido <= 36;
    }

    /**
     * Obtener resumen de tiempos de una cotización
     *
     * @param  \Illuminate\Support\Collection|\Illuminate\Database\Eloquent\Collection|null  $historialYaCargado
     */
    public static function obtenerResumenTiempos(Cotizacion $cotizacion, $historialYaCargado = null): array
    {
        $historial = $historialYaCargado ?? CotizacionHistorial::obtenerHistorialCompleto($cotizacion->id, $cotizacion);
        
        $resumen = [
            'tiempo_total' => null,
            'tiempo_aprobacion' => null,
            'tiempo_procesamiento' => null,
            'tiempo_despacho' => null,
            'objetivo_cumplido' => false,
            'estados' => []
        ];

        if ($historial->count() > 0) {
            $inicio = $historial->first()->fecha_accion;
            $fin = $historial->last()->fecha_accion;
            
            $resumen['tiempo_total'] = $fin->diffInHours($inicio);
            $resumen['objetivo_cumplido'] = $resumen['tiempo_total'] <= 36;
            
            // Calcular tiempos por etapa
            foreach ($historial as $registro) {
                $resumen['estados'][] = [
                    'estado' => $registro->estado_nuevo,
                    'fecha' => $registro->fecha_accion,
                    'tiempo_transcurrido' => $registro->tiempo_transcurrido_segundos,
                    'usuario' => $registro->usuario_nombre,
                    'rol' => $registro->rol_usuario
                ];
            }
        }

        return $resumen;
    }

    /**
     * Obtener estadísticas generales del sistema
     */
    public static function obtenerEstadisticasGenerales(): array
    {
        $estadisticas = CotizacionHistorial::obtenerEstadisticasTiempos();
        
        // Agregar más estadísticas
        $totalCotizaciones = Cotizacion::count();
        $cotizacionesDespachadas = CotizacionHistorial::where('estado_nuevo', 'despachada')->count();
        $cotizacionesRetrasadas = CotizacionHistorial::obtenerCotizacionesRetrasadas()->count();
        
        return array_merge($estadisticas, [
            'total_cotizaciones' => $totalCotizaciones,
            'cotizaciones_despachadas' => $cotizacionesDespachadas,
            'cotizaciones_retrasadas' => $cotizacionesRetrasadas,
            'porcentaje_cumplimiento' => $totalCotizaciones > 0 ? round(($cotizacionesDespachadas / $totalCotizaciones) * 100, 2) : 0
        ]);
    }
}
