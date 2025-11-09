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
                                    @if($tipoAprobacion === 'supervisor')
                                        <i class="material-icons">supervisor_account</i>
                                        Aprobaciones Pendientes - Supervisor
                                    @elseif($tipoAprobacion === 'compras')
                                        <i class="material-icons">shopping_cart</i>
                                        Aprobaciones Pendientes - Compras
                                    @elseif($tipoAprobacion === 'picking')
                                        <i class="material-icons">local_shipping</i>
                                        Aprobaciones Pendientes - Picking
                                    @endif
                                </h4>
                                <p class="card-category">
                                    @if($tipoAprobacion === 'supervisor')
                                        Notas de venta con problemas de crédito que requieren tu aprobación
                                    @elseif($tipoAprobacion === 'compras')
                                        Notas de venta con problemas de stock que requieren tu aprobación
                                    @elseif($tipoAprobacion === 'picking')
                                        Notas de venta pendientes de validación de stock y aprobación final
                                    @endif
                                </p>
                            </div>
                            <div class="col-md-4 text-right">
                                <div class="stats">
                                    <span class="badge badge-info">{{ $cotizaciones->count() }}</span>
                                    <span class="text-white">Notas Pendientes</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtros y Búsqueda -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <form method="GET" action="{{ route('aprobaciones.index') }}" id="filtrosForm">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="buscar">Buscar por Cliente o Código</label>
                                        <input type="text" id="buscar" name="buscar" class="form-control" placeholder="Buscar..." value="{{ request('buscar') }}">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="region">Región</label>
                                        <select id="region" name="region" class="form-control">
                                            <option value="">Todas</option>
                                            @foreach($regiones ?? [] as $region)
                                                <option value="{{ $region }}" {{ request('region') == $region ? 'selected' : '' }}>{{ $region }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="comuna">Comuna</label>
                                        <select id="comuna" name="comuna" class="form-control">
                                            <option value="">Todas</option>
                                            @foreach($comunas ?? [] as $comuna)
                                                <option value="{{ $comuna }}" {{ request('comuna') == $comuna ? 'selected' : '' }}>{{ $comuna }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="fecha_desde">Fecha Desde</label>
                                        <input type="date" id="fecha_desde" name="fecha_desde" class="form-control" value="{{ request('fecha_desde') }}">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="fecha_hasta">Fecha Hasta</label>
                                        <input type="date" id="fecha_hasta" name="fecha_hasta" class="form-control" value="{{ request('fecha_hasta') }}">
                                    </div>
                                </div>
                                <div class="col-md-1">
                                    <div class="form-group">
                                        <label>&nbsp;</label>
                                        <button type="submit" class="btn btn-primary btn-block">
                                            <i class="material-icons">search</i> Filtrar
                                        </button>
                                    </div>
                                </div>
                            </div>
                            @if(request()->hasAny(['region', 'comuna', 'fecha_desde', 'fecha_hasta', 'buscar']))
                            <div class="row">
                                <div class="col-md-12">
                                    <a href="{{ route('aprobaciones.index') }}" class="btn btn-sm btn-secondary">
                                        <i class="material-icons">clear</i> Limpiar Filtros
                                    </a>
                                </div>
                            </div>
                            @endif
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lista de Notas de Venta -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Notas de Venta Pendientes</h4>
                    </div>
                    <div class="card-body">
                        @if($cotizaciones->count() > 0)
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Cliente</th>
                                            <th>Vendedor</th>
                                            <th>Total</th>
                                            <th>Estado</th>
                                            <th>Problemas</th>
                                            <th>Fecha</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($cotizaciones as $cotizacion)
                                            <tr data-id="{{ $cotizacion->id }}">
                                                <td>
                                                    <strong>#{{ $cotizacion->id }}</strong>
                                                </td>
                                                <td>
                                                    <div>
                                                        <strong>{{ $cotizacion->cliente_codigo }}</strong><br>
                                                        <small>{{ Str::limit($cotizacion->cliente_nombre, 30) }}</small>
                                                        @if($cotizacion->cliente)
                                                            <br><small class="text-muted">
                                                                {{ $cotizacion->cliente->comuna ?? '' }}{{ $cotizacion->cliente->region ? ', ' . $cotizacion->cliente->region : '' }}
                                                            </small>
                                                        @endif
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge badge-info">{{ $cotizacion->user->name ?? 'N/A' }}</span>
                                                </td>
                                                <td>
                                                    <strong>${{ number_format($cotizacion->total, 0) }}</strong>
                                                </td>
                                                <td>
                                                    @switch($cotizacion->estado_aprobacion)
                                                        @case('pendiente')
                                                            <span class="badge badge-warning">Pendiente Supervisor</span>
                                                            @break
                                                        @case('pendiente_picking')
                                                            <span class="badge badge-info">Pendiente Picking</span>
                                                            @break
                                                        @case('pendiente_entrega')
                                                            <span class="badge badge-warning">Pendiente de Entrega</span>
                                                            @if($cotizacion->observaciones_picking)
                                                                <br><small class="text-muted"><i class="material-icons">info</i> {{ Str::limit($cotizacion->observaciones_picking, 40) }}</small>
                                                            @endif
                                                            @break
                                                        @case('aprobada_supervisor')
                                                            <span class="badge badge-success">Aprobada Supervisor</span>
                                                            @break
                                                        @case('aprobada_compras')
                                                            <span class="badge badge-primary">Aprobada Compras</span>
                                                            @break
                                                        @case('aprobada_picking')
                                                            <span class="badge badge-success">Aprobada Picking</span>
                                                            @break
                                                        @case('rechazada')
                                                            <span class="badge badge-danger">Rechazada</span>
                                                            @break
                                                        @default
                                                            <span class="badge badge-secondary">{{ $cotizacion->estado_aprobacion }}</span>
                                                    @endswitch
                                                </td>
                                                <td>
                                                    @if($cotizacion->tiene_problemas_credito)
                                                        <span class="badge badge-danger">Crédito</span>
                                                    @endif
                                                    @if($cotizacion->tiene_problemas_stock)
                                                        <span class="badge badge-warning">Stock</span>
                                                    @endif
                                                    @if(!$cotizacion->tiene_problemas_credito && !$cotizacion->tiene_problemas_stock)
                                                        <span class="badge badge-success">Sin Problemas</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <small>{{ $cotizacion->created_at->format('d/m/Y H:i') }}</small>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="{{ route('aprobaciones.show', $cotizacion->id) }}" 
                                                           class="btn btn-sm btn-info" 
                                                           title="Ver Detalles">
                                                            <i class="material-icons">visibility</i> Ver
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="text-center py-4">
                                <i class="material-icons text-muted" style="font-size: 4rem;">assignment_turned_in</i>
                                <h4 class="text-muted">No hay notas de venta pendientes</h4>
                                <p class="text-muted">Todas las notas han sido procesadas o no hay notas que requieran tu aprobación.</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


@endsection

@push('js')
<script>
// Búsqueda en tiempo real (filtrado local si no se envía el formulario)
document.addEventListener('DOMContentLoaded', function() {
    const buscarInput = document.getElementById('buscar');
    if (buscarInput) {
        buscarInput.addEventListener('keyup', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('filtrosForm').submit();
            }
        });
    }
    
    // Filtrar comunas basado en región seleccionada (opcional, si se quiere implementar)
    const regionSelect = document.getElementById('region');
    const comunaSelect = document.getElementById('comuna');
    
    if (regionSelect && comunaSelect) {
        regionSelect.addEventListener('change', function() {
            // Si cambia la región, se puede limpiar la comuna o mantenerla según necesidad
            // Por ahora solo permitimos que el usuario seleccione manualmente
        });
    }
});
</script>
@endpush
