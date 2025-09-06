@extends('layouts.app', ['pageSlug' => 'aprobaciones'])

@section('content')
<div class="content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header card-header-primary">
                        <div class="row">
                            <div class="col-md-8">
                                <h4 class="card-title">
                                    <i class="material-icons">history</i>
                                    Historial - Nota de Venta #{{ $cotizacion->id }}
                                </h4>
                                <p class="card-category">
                                    Cliente: {{ $cotizacion->cliente_codigo }} - {{ $cotizacion->cliente_nombre }}
                                </p>
                            </div>
                            <div class="col-md-4 text-right">
                                <a href="{{ route('aprobaciones.show', $cotizacion->id) }}" class="btn btn-secondary">
                                    <i class="material-icons">arrow_back</i> Volver a NVV
                                </a>
                                <a href="{{ route('aprobaciones.index') }}" class="btn btn-info ml-2">
                                    <i class="material-icons">list</i> Lista Aprobaciones
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Resumen de Tiempos -->
        @if($resumenTiempos && is_array($resumenTiempos) && count($resumenTiempos) > 0)
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header card-header-info">
                        <h4 class="card-title">
                            <i class="material-icons">schedule</i>
                            Resumen de Tiempos
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            @foreach($resumenTiempos as $resumen)
                                @if(is_array($resumen) && isset($resumen['color']) && isset($resumen['icono']) && isset($resumen['etapa']) && isset($resumen['tiempo']))
                                <div class="col-md-3">
                                    <div class="card card-stats">
                                        <div class="card-header card-header-{{ $resumen['color'] }} card-header-icon">
                                            <div class="card-icon">
                                                <i class="material-icons">{{ $resumen['icono'] }}</i>
                                            </div>
                                            <p class="card-category">{{ $resumen['etapa'] }}</p>
                                            <h3 class="card-title">{{ $resumen['tiempo'] }}</h3>
                                        </div>
                                        <div class="card-footer">
                                            <div class="stats">
                                                <i class="material-icons">{{ $resumen['icono'] }}</i>
                                                {{ $resumen['descripcion'] ?? '' }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- Historial Completo -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header card-header-success">
                        <h4 class="card-title">
                            <i class="material-icons">timeline</i>
                            Historial Completo
                        </h4>
                    </div>
                    <div class="card-body">
                        @if($historial && count($historial) > 0)
                            <div class="timeline">
                                @foreach($historial as $index => $registro)
                                <div class="timeline-item">
                                    <div class="timeline-marker bg-{{ $this->getEstadoColor($registro->estado_nuevo) }}"></div>
                                    <div class="timeline-content">
                                        <div class="card">
                                            <div class="card-header">
                                                <div class="row align-items-center">
                                                    <div class="col-8">
                                                        <h6 class="card-title">
                                                            <i class="material-icons">{{ $this->getEstadoIcono($registro->estado_nuevo) }}</i>
                                                            {{ $this->getEstadoDescripcion($registro->estado_nuevo) }}
                                                        </h6>
                                                    </div>
                                                    <div class="col-4 text-right">
                                                        <small class="text-muted">
                                                            {{ \Carbon\Carbon::parse($registro->fecha_cambio)->format('d/m/Y H:i:s') }}
                                                        </small>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <p><strong>Usuario:</strong> {{ $registro->usuario_nombre ?? 'Sistema' }}</p>
                                                        <p><strong>Estado Anterior:</strong> 
                                                            <span class="badge badge-{{ $this->getEstadoColor($registro->estado_anterior) }}">
                                                                {{ $this->getEstadoDescripcion($registro->estado_anterior) }}
                                                            </span>
                                                        </p>
                                                        <p><strong>Estado Nuevo:</strong> 
                                                            <span class="badge badge-{{ $this->getEstadoColor($registro->estado_nuevo) }}">
                                                                {{ $this->getEstadoDescripcion($registro->estado_nuevo) }}
                                                            </span>
                                                        </p>
                                                    </div>
                                                    <div class="col-md-6">
                                                        @if($registro->comentarios)
                                                        <p><strong>Comentarios:</strong></p>
                                                        <p class="text-muted">{{ $registro->comentarios }}</p>
                                                        @endif
                                                        
                                                        @if($registro->motivo_rechazo)
                                                        <p><strong>Motivo de Rechazo:</strong></p>
                                                        <p class="text-danger">{{ $registro->motivo_rechazo }}</p>
                                                        @endif
                                                    </div>
                                                </div>
                                                
                                                @if($registro->detalles_cambio)
                                                <div class="row mt-3">
                                                    <div class="col-12">
                                                        <h6>Detalles del Cambio:</h6>
                                                        <div class="table-responsive">
                                                            <table class="table table-sm">
                                                                <thead>
                                                                    <tr>
                                                                        <th>Campo</th>
                                                                        <th>Valor Anterior</th>
                                                                        <th>Valor Nuevo</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    @foreach(json_decode($registro->detalles_cambio, true) as $campo => $valores)
                                                                    <tr>
                                                                        <td><strong>{{ $campo }}</strong></td>
                                                                        <td>{{ $valores['anterior'] ?? 'N/A' }}</td>
                                                                        <td>{{ $valores['nuevo'] ?? 'N/A' }}</td>
                                                                    </tr>
                                                                    @endforeach
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
                                                </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-5">
                                <i class="material-icons text-muted" style="font-size: 64px;">history</i>
                                <h4 class="text-muted">No hay historial disponible</h4>
                                <p class="text-muted">Esta nota de venta no tiene registros de historial.</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.timeline {
    position: relative;
    padding: 20px 0;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 30px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e0e0e0;
}

.timeline-item {
    position: relative;
    margin-bottom: 30px;
    padding-left: 80px;
}

.timeline-marker {
    position: absolute;
    left: 20px;
    top: 20px;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    border: 3px solid #fff;
    box-shadow: 0 0 0 3px #e0e0e0;
}

.timeline-content {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.bg-pendiente { background-color: #ffc107 !important; }
.bg-aprobada { background-color: #28a745 !important; }
.bg-rechazada { background-color: #dc3545 !important; }
.bg-en-proceso { background-color: #17a2b8 !important; }
.bg-completada { background-color: #6f42c1 !important; }
</style>

@php
    function getEstadoColor($estado) {
        switch($estado) {
            case 'pendiente': return 'warning';
            case 'aprobada': return 'success';
            case 'rechazada': return 'danger';
            case 'en_proceso': return 'info';
            case 'completada': return 'primary';
            default: return 'secondary';
        }
    }

    function getEstadoIcono($estado) {
        switch($estado) {
            case 'pendiente': return 'schedule';
            case 'aprobada': return 'check_circle';
            case 'rechazada': return 'cancel';
            case 'en_proceso': return 'play_circle';
            case 'completada': return 'done_all';
            default: return 'help';
        }
    }

    function getEstadoDescripcion($estado) {
        switch($estado) {
            case 'pendiente': return 'Pendiente';
            case 'aprobada': return 'Aprobada';
            case 'rechazada': return 'Rechazada';
            case 'en_proceso': return 'En Proceso';
            case 'completada': return 'Completada';
            default: return ucfirst($estado);
        }
    }
@endphp
@endsection
