@php
use App\Helpers\EstadoHelper;

// Funciones helper para la vista
function getEstadoColor($estado) {
    return \App\Helpers\EstadoHelper::getEstadoColor($estado);
}

function getEstadoIcon($tipoAccion) {
    return \App\Helpers\EstadoHelper::getEstadoIcon($tipoAccion);
}

function getEstadoNombre($estado) {
    return \App\Helpers\EstadoHelper::getEstadoNombre($estado);
}

function getTipoAccionNombre($tipoAccion) {
    return \App\Helpers\EstadoHelper::getTipoAccionNombre($tipoAccion);
}
@endphp

<div class="mt-4">
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
            <div class="timeline-marker bg-{{ getEstadoColor($registro->estado_nuevo) }}"></div>
            <div class="timeline-content">
                <div class="card">
                    <div class="card-header">
                        <div class="row align-items-center">
                            <div class="col-8">
                                <h5 class="mb-0">
                                    <i class="tim-icons icon-{{ getEstadoIcon($registro->tipo_accion) }}"></i>
                                    {{ getEstadoNombre($registro->estado_nuevo) }}
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
                                <p><strong>Estado Anterior:</strong> {{ getEstadoNombre($registro->estado_anterior) }}</p>
                                @endif
                            </div>
                            <div class="col-md-6">
                                <p><strong>Tipo de Acción:</strong> {{ getTipoAccionNombre($registro->tipo_accion) }}</p>
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
