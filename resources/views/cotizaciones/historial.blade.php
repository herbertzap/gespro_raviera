@extends('layouts.app', ['pageSlug' => 'cotizaciones'])

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <div class="row align-items-center">
                    <div class="col-8">
                        <h3 class="mb-0">
                            <i class="tim-icons icon-time-alarm text-primary"></i>
                            Historial de Cotización #{{ $cotizacion->id }}
                        </h3>
                        <p class="text-muted mb-0">
                            Cliente: {{ $cotizacion->cliente_nombre }} ({{ $cotizacion->cliente_codigo }})
                        </p>
                    </div>
                    <div class="col-4 text-right">
                        <a href="{{ route('cotizaciones.index') }}" class="btn btn-sm btn-secondary">
                            <i class="tim-icons icon-minimal-left"></i> Volver
                        </a>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <!-- Resumen de tiempos -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-gradient-primary text-white">
                            <div class="card-body text-center">
                                <h4 class="mb-0">{{ $resumenTiempos['tiempo_total'] ?? 'N/A' }} hrs</h4>
                                <small>Tiempo Total</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-gradient-{{ $resumenTiempos['objetivo_cumplido'] ? 'success' : 'warning' }} text-white">
                            <div class="card-body text-center">
                                <h4 class="mb-0">
                                    <i class="tim-icons icon-{{ $resumenTiempos['objetivo_cumplido'] ? 'check-2' : 'alert-triangle' }}"></i>
                                </h4>
                                <small>{{ $resumenTiempos['objetivo_cumplido'] ? 'En Tiempo' : 'Retrasado' }}</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-gradient-info text-white">
                            <div class="card-body text-center">
                                <h4 class="mb-0">{{ $historial->count() }}</h4>
                                <small>Estados</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-gradient-success text-white">
                            <div class="card-body text-center">
                                <h4 class="mb-0">{{ $historial->last()->estado_nuevo ?? 'N/A' }}</h4>
                                <small>Estado Actual</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Timeline del historial -->
                <div class="timeline">
                    @foreach($historial as $index => $registro)
                    <div class="timeline-item">
                        <div class="timeline-marker bg-{{ $this->getEstadoColor($registro->estado_nuevo) }}"></div>
                        <div class="timeline-content">
                            <div class="card">
                                <div class="card-header">
                                    <div class="row align-items-center">
                                        <div class="col-8">
                                            <h5 class="mb-0">
                                                <i class="tim-icons icon-{{ $this->getEstadoIcon($registro->tipo_accion) }}"></i>
                                                {{ $this->getEstadoNombre($registro->estado_nuevo) }}
                                            </h5>
                                            <small class="text-muted">
                                                {{ $registro->fecha_accion->format('d/m/Y H:i:s') }}
                                            </small>
                                        </div>
                                        <div class="col-4 text-right">
                                            @if($registro->tiempo_transcurrido_segundos)
                                            <span class="badge badge-info">
                                                {{ $registro->tiempo_transcurrido_formateado }}
                                            </span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <p><strong>Usuario:</strong> {{ $registro->usuario_nombre ?? 'Sistema' }}</p>
                                            <p><strong>Rol:</strong> {{ $registro->rol_usuario ?? 'N/A' }}</p>
                                            @if($registro->estado_anterior)
                                            <p><strong>Estado Anterior:</strong> {{ $this->getEstadoNombre($registro->estado_anterior) }}</p>
                                            @endif
                                        </div>
                                        <div class="col-md-6">
                                            <p><strong>Tipo de Acción:</strong> {{ $this->getTipoAccionNombre($registro->tipo_accion) }}</p>
                                            @if($registro->comentarios)
                                            <p><strong>Comentarios:</strong> {{ $registro->comentarios }}</p>
                                            @endif
                                        </div>
                                    </div>
                                    
                                    @if($registro->detalles_adicionales)
                                    <div class="mt-3">
                                        <h6>Detalles Adicionales:</h6>
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                @foreach($registro->detalles_adicionales as $key => $value)
                                                <tr>
                                                    <td><strong>{{ ucfirst(str_replace('_', ' ', $key)) }}:</strong></td>
                                                    <td>{{ is_array($value) ? json_encode($value) : $value }}</td>
                                                </tr>
                                                @endforeach
                                            </table>
                                        </div>
                                    </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>

                @if($historial->isEmpty())
                <div class="text-center py-4">
                    <i class="tim-icons icon-time-alarm text-muted" style="font-size: 3rem;"></i>
                    <h4 class="text-muted mt-3">No hay historial disponible</h4>
                    <p class="text-muted">Esta cotización aún no tiene registros de historial.</p>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

<style>
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 15px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e9ecef;
}

.timeline-item {
    position: relative;
    margin-bottom: 30px;
}

.timeline-marker {
    position: absolute;
    left: -22px;
    top: 20px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    border: 3px solid #fff;
    box-shadow: 0 0 0 3px #e9ecef;
}

.timeline-content {
    margin-left: 20px;
}
</style>
@endsection

@php
// Funciones helper para la vista
function getEstadoColor($estado) {
    $colores = [
        'borrador' => 'secondary',
        'enviada' => 'info',
        'pendiente' => 'warning',
        'pendiente_picking' => 'warning',
        'aprobada_supervisor' => 'primary',
        'aprobada_compras' => 'primary',
        'aprobada_picking' => 'success',
        'rechazada' => 'danger',
        'enviada_sql' => 'info',
        'nvv_generada' => 'success',
        'nvv_facturada' => 'success',
        'despachada' => 'success'
    ];
    return $colores[$estado] ?? 'secondary';
}

function getEstadoIcon($tipoAccion) {
    $iconos = [
        'creacion' => 'plus',
        'envio' => 'send',
        'aprobacion' => 'check-2',
        'rechazo' => 'simple-remove',
        'separacion' => 'split',
        'insercion_sql' => 'cloud-upload-94',
        'generacion_nvv' => 'notes',
        'facturacion' => 'money-coins',
        'despacho' => 'delivery-fast'
    ];
    return $iconos[$tipoAccion] ?? 'info';
}

function getEstadoNombre($estado) {
    $nombres = [
        'borrador' => 'Borrador',
        'enviada' => 'Enviada',
        'pendiente' => 'Pendiente de Aprobación',
        'pendiente_picking' => 'Pendiente de Picking',
        'aprobada_supervisor' => 'Aprobada por Supervisor',
        'aprobada_compras' => 'Aprobada por Compras',
        'aprobada_picking' => 'Aprobada por Picking',
        'rechazada' => 'Rechazada',
        'enviada_sql' => 'Enviada a SQL Server',
        'nvv_generada' => 'NVV Generada',
        'nvv_facturada' => 'NVV Facturada',
        'despachada' => 'Despachada'
    ];
    return $nombres[$estado] ?? $estado;
}

function getTipoAccionNombre($tipoAccion) {
    $nombres = [
        'creacion' => 'Creación',
        'envio' => 'Envío',
        'aprobacion' => 'Aprobación',
        'rechazo' => 'Rechazo',
        'separacion' => 'Separación de Productos',
        'insercion_sql' => 'Inserción en SQL Server',
        'generacion_nvv' => 'Generación de NVV',
        'facturacion' => 'Facturación',
        'despacho' => 'Despacho'
    ];
    return $nombres[$tipoAccion] ?? $tipoAccion;
}
@endphp
