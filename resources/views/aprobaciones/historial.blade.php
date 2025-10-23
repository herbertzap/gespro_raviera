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
                                    <div class="timeline-marker bg-{{ getEstadoColor($registro->estado_nuevo) }}"></div>
                                    <div class="timeline-content">
                                        <div class="card">
                                            <div class="card-header">
                                                <div class="row align-items-center">
                                                    <div class="col-8">
                                                        <h6 class="card-title">
                                                            <i class="material-icons">{{ getEstadoIcono($registro->estado_nuevo) }}</i>
                                                            {{ getEstadoDescripcion($registro->estado_nuevo) }}
                                                            @if(isset($registro->tipo_accion))
                                                                <small class="text-muted">- {{ getTipoAccionDescripcion($registro->tipo_accion) }}</small>
                                                            @endif
                                                        </h6>
                                                    </div>
                                                    <div class="col-4 text-right">
                                                        <small class="text-muted">
                                                            {{ \Carbon\Carbon::parse($registro->fecha_accion)->format('d/m/Y H:i:s') }}
                                                        </small>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <p><strong>Usuario:</strong> {{ $registro->usuario_nombre ?? 'Sistema' }}</p>
                                                        <p><strong>Estado Anterior:</strong> 
                                                            <span class="badge badge-{{ getEstadoColor($registro->estado_anterior) }}">
                                                                {{ getEstadoDescripcion($registro->estado_anterior) }}
                                                            </span>
                                                        </p>
                                                        <p><strong>Estado Nuevo:</strong> 
                                                            <span class="badge badge-{{ getEstadoColor($registro->estado_nuevo) }}">
                                                                {{ getEstadoDescripcion($registro->estado_nuevo) }}
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
                                                        
                                                        @if(str_contains($registro->comentarios ?? '', 'NVV N°') || ($registro->estado_nuevo === 'aprobada' && $cotizacion->numero_nvv))
                                                        <!-- Información de NVV Generada -->
                                                        <div class="alert alert-success mt-3">
                                                            <h6><i class="material-icons">assignment_turned_in</i> NVV Generada en Sistema (SQL)</h6>
                                                            @php
                                                                // Extraer número de NVV del comentario o usar el de la cotización
                                                                $numeroNvv = '';
                                                                if (preg_match('/NVV N[°º]\s*(\d+)/', $registro->comentarios ?? '', $matches)) {
                                                                    $numeroNvv = $matches[1];
                                                                } elseif ($cotizacion->numero_nvv) {
                                                                    $numeroNvv = $cotizacion->numero_nvv;
                                                                }
                                                            @endphp
                                                            @if($numeroNvv)
                                                            <p class="mb-2"><strong>📋 Número NVV:</strong> <span class="badge badge-success">{{ $numeroNvv }}</span></p>
                                                            @endif
                                                            <p class="mb-2"><strong>📅 Fecha de Generación:</strong> {{ \Carbon\Carbon::parse($registro->fecha_accion)->format('d/m/Y H:i:s') }}</p>
                                                            <p class="mb-2"><strong>👤 Aprobado por:</strong> {{ $registro->usuario_nombre ?? 'Sistema' }}</p>
                                                            @if($cotizacion->total)
                                                            <p class="mb-2"><strong>💰 Total:</strong> ${{ number_format($cotizacion->total, 0, ',', '.') }}</p>
                                                            @endif
                                                            @if($numeroNvv)
                                                            <a href="{{ route('nvv-pendientes.ver', str_pad($numeroNvv, 10, '0', STR_PAD_LEFT)) }}" class="btn btn-sm btn-success mt-2" target="_blank">
                                                                <i class="material-icons">search</i> Ver NVV en Sistema
                                                            </a>
                                                            @endif
                                                        </div>
                                                        @endif
                                                        
                                                        @if(str_contains($registro->comentarios ?? '', 'facturada como FCV') || $registro->estado_nuevo === 'facturada')
                                                        <!-- Información de Facturación -->
                                                        <div class="alert alert-primary mt-3">
                                                            <h6><i class="material-icons">receipt</i> NVV Facturada en Sistema (SQL)</h6>
                                                            @php
                                                                // Extraer número de factura del comentario
                                                                $numeroFactura = '';
                                                                if (preg_match('/FCV N[°º]\s*(\d+)/', $registro->comentarios ?? '', $matches)) {
                                                                    $numeroFactura = $matches[1];
                                                                } elseif ($cotizacion->numero_factura) {
                                                                    $numeroFactura = $cotizacion->numero_factura;
                                                                }
                                                                
                                                                $detalles = is_string($registro->detalles_adicionales) 
                                                                    ? json_decode($registro->detalles_adicionales, true) 
                                                                    : $registro->detalles_adicionales;
                                                            @endphp
                                                            @if($numeroFactura)
                                                            <p class="mb-2"><strong>🧾 Número Factura:</strong> <span class="badge badge-primary">FCV-{{ $numeroFactura }}</span></p>
                                                            @endif
                                                            <p class="mb-2"><strong>📅 Fecha de Facturación:</strong> 
                                                                @if(isset($detalles['fecha_facturacion']))
                                                                    {{ $detalles['fecha_facturacion'] }}
                                                                @elseif($cotizacion->fecha_facturacion)
                                                                    {{ $cotizacion->fecha_facturacion->format('d/m/Y H:i:s') }}
                                                                @else
                                                                    {{ \Carbon\Carbon::parse($registro->fecha_accion)->format('d/m/Y H:i:s') }}
                                                                @endif
                                                            </p>
                                                            <p class="mb-2"><strong>✅ Estado:</strong> <span class="badge badge-success">Facturada</span></p>
                                                            <p class="mb-0"><small class="text-muted"><i class="material-icons" style="font-size: 14px;">info</i> Verificación automática del sistema</small></p>
                                                        </div>
                                                        @endif
                                                    </div>
                                                </div>
                                                
                                                @if($registro->detalles_adicionales)
                                                <div class="row mt-3">
                                                    <div class="col-12">
                                                        <h6>Detalles Adicionales:</h6>
                                                        @php
                                                            $detalles = is_string($registro->detalles_adicionales) 
                                                                ? json_decode($registro->detalles_adicionales, true) 
                                                                : $registro->detalles_adicionales;
                                                        @endphp
                                                        
                                                        @if(isset($detalles['tipo_modificacion']) && $detalles['tipo_modificacion'] === 'productos')
                                                            <!-- Modificaciones de Productos -->
                                                            <div class="alert alert-info">
                                                                <h6><i class="material-icons">shopping_basket</i> Modificaciones de Productos</h6>
                                                                
                                                                @if(!empty($detalles['productos_agregados']))
                                                                <div class="mb-2">
                                                                    <strong class="text-success">Productos Agregados:</strong>
                                                                    <ul class="mb-0">
                                                                        @foreach($detalles['productos_agregados'] as $producto)
                                                                        <li>{{ $producto['nombre'] ?? $producto['codigo'] }} (Cantidad: {{ $producto['cantidad'] ?? 'N/A' }})</li>
                                                                        @endforeach
                                                                    </ul>
                                                                </div>
                                                                @endif
                                                                
                                                                @if(!empty($detalles['productos_eliminados']))
                                                                <div class="mb-2">
                                                                    <strong class="text-danger">Productos Eliminados:</strong>
                                                                    <ul class="mb-0">
                                                                        @foreach($detalles['productos_eliminados'] as $producto)
                                                                        <li>{{ $producto['nombre'] ?? $producto['codigo'] }} (Cantidad: {{ $producto['cantidad'] ?? 'N/A' }})</li>
                                                                        @endforeach
                                                                    </ul>
                                                                </div>
                                                                @endif
                                                                
                                                                @if(!empty($detalles['productos_modificados']))
                                                                <div class="mb-2">
                                                                    <strong class="text-warning">Productos Modificados:</strong>
                                                                    <ul class="mb-0">
                                                                        @foreach($detalles['productos_modificados'] as $producto)
                                                                        <li>
                                                                            {{ $producto['nombre'] ?? $producto['codigo'] }}
                                                                            @if(isset($producto['cantidad_anterior']) && isset($producto['cantidad_nueva']))
                                                                                (Cantidad: {{ $producto['cantidad_anterior'] }} → {{ $producto['cantidad_nueva'] }})
                                                                            @endif
                                                                        </li>
                                                                        @endforeach
                                                                    </ul>
                                                                </div>
                                                                @endif
                                                            </div>
                                                        @elseif(isset($detalles['total']) || isset($detalles['productos_count']))
                                                            <!-- Información de Creación -->
                                                            <div class="alert alert-info">
                                                                <h6><i class="material-icons">info</i> Información de Creación</h6>
                                                                @if(isset($detalles['total']))
                                                                <p><strong>Total Inicial:</strong> ${{ number_format($detalles['total'], 0) }}</p>
                                                                @endif
                                                                @if(isset($detalles['productos_count']))
                                                                <p><strong>Productos Iniciales:</strong> {{ $detalles['productos_count'] }}</p>
                                                                @endif
                                                                @if(isset($detalles['cliente']))
                                                                <p><strong>Cliente:</strong> {{ $detalles['cliente'] }}</p>
                                                                @endif
                                                            </div>
                                                        @else
                                                            <!-- Detalles Generales -->
                                                            <div class="table-responsive">
                                                                <table class="table table-sm">
                                                                    <thead>
                                                                        <tr>
                                                                            <th>Campo</th>
                                                                            <th>Valor</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        @foreach($detalles as $campo => $valor)
                                                                        <tr>
                                                                            <td><strong>{{ ucfirst(str_replace('_', ' ', $campo)) }}</strong></td>
                                                                            <td>{{ is_array($valor) ? json_encode($valor) : $valor }}</td>
                                                                        </tr>
                                                                        @endforeach
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        @endif
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
            case 'creada': return 'info';
            case 'pendiente': return 'warning';
            case 'aprobada': return 'success';
            case 'rechazada': return 'danger';
            case 'en_proceso': return 'info';
            case 'completada': return 'primary';
            case 'modificada': return 'secondary';
            default: return 'secondary';
        }
    }

    function getEstadoIcono($estado) {
        switch($estado) {
            case 'creada': return 'add_circle';
            case 'pendiente': return 'schedule';
            case 'aprobada': return 'check_circle';
            case 'rechazada': return 'cancel';
            case 'en_proceso': return 'play_circle';
            case 'completada': return 'done_all';
            case 'modificada': return 'edit';
            default: return 'help';
        }
    }

    function getEstadoDescripcion($estado) {
        switch($estado) {
            case 'creada': return 'NVV Creada';
            case 'pendiente': return 'Pendiente';
            case 'aprobada': return 'Aprobada';
            case 'rechazada': return 'Rechazada';
            case 'en_proceso': return 'En Proceso';
            case 'completada': return 'Completada';
            case 'modificada': return 'Modificada';
            default: return ucfirst($estado);
        }
    }

    function getTipoAccionDescripcion($tipoAccion) {
        switch($tipoAccion) {
            case 'creacion': return 'Creación de NVV';
            case 'aprobacion': return 'Aprobación';
            case 'rechazo': return 'Rechazo';
            case 'modificacion_productos': return 'Modificación de Productos';
            case 'separar_productos_multiples': return 'Separación de Productos';
            case 'creada_por_separacion_stock': return 'NVV Creada por Separación';
            default: return ucfirst(str_replace('_', ' ', $tipoAccion));
        }
    }
@endphp
@endsection
