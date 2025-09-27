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
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="buscar">Buscar por Cliente o Código</label>
                                    <input type="text" id="buscar" class="form-control" placeholder="Buscar...">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="estado">Estado</label>
                                    <select id="estado" class="form-control">
                                        <option value="">Todos</option>
                                        <option value="pendiente">Pendiente</option>
                                        <option value="pendiente_picking">Pendiente Picking</option>
                                        <option value="aprobada_supervisor">Aprobada Supervisor</option>
                                        <option value="aprobada_compras">Aprobada Compras</option>
                                        <option value="rechazada">Rechazada</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="vendedor">Vendedor</label>
                                    <select id="vendedor" class="form-control">
                                        <option value="">Todos</option>
                                        @foreach($cotizaciones->pluck('user.name')->unique() as $vendedor)
                                            <option value="{{ $vendedor }}">{{ $vendedor }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>&nbsp;</label>
                                    <button type="button" class="btn btn-primary btn-block" onclick="aplicarFiltros()">
                                        <i class="material-icons">search</i> Filtrar
                                    </button>
                                </div>
                            </div>
                        </div>
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
let cotizaciones = @json($cotizaciones);

// Aplicar filtros
function aplicarFiltros() {
    const buscar = document.getElementById('buscar').value.toLowerCase();
    const estado = document.getElementById('estado').value;
    const vendedor = document.getElementById('vendedor').value;
    
    const filas = document.querySelectorAll('tbody tr');
    
    filas.forEach(fila => {
        const clienteCodigo = fila.querySelector('td:nth-child(2) strong').textContent.toLowerCase();
        const clienteNombre = fila.querySelector('td:nth-child(2) small').textContent.toLowerCase();
        const vendedorFila = fila.querySelector('td:nth-child(3) .badge').textContent;
        const estadoFila = fila.querySelector('td:nth-child(5) .badge').textContent.toLowerCase();
        
        let mostrar = true;
        
        // Filtro de búsqueda
        if (buscar && !clienteCodigo.includes(buscar) && !clienteNombre.includes(buscar)) {
            mostrar = false;
        }
        
        // Filtro de estado
        if (estado && !estadoFila.includes(estado.toLowerCase())) {
            mostrar = false;
        }
        
        // Filtro de vendedor
        if (vendedor && vendedorFila !== vendedor) {
            mostrar = false;
        }
        
        fila.style.display = mostrar ? '' : 'none';
    });
}


// Mostrar notificación
function showNotification(type, message) {
    const alertClass = type === 'success' ? 'alert-success' : type === 'warning' ? 'alert-warning' : 'alert-danger';
    const icon = type === 'success' ? 'check_circle' : type === 'warning' ? 'warning' : 'error';
    
    const alert = document.createElement('div');
    alert.className = `alert ${alertClass} alert-dismissible fade show`;
    alert.innerHTML = `
        <i class="material-icons">${icon}</i>
        ${message}
        <button type="button" class="close" data-dismiss="alert">
            <span>&times;</span>
        </button>
    `;
    
    document.querySelector('.content').insertBefore(alert, document.querySelector('.content').firstChild);
    
    setTimeout(() => {
        alert.remove();
    }, 5000);
}

// Event listeners
document.addEventListener('DOMContentLoaded', function() {
    // Búsqueda en tiempo real
    document.getElementById('buscar').addEventListener('input', aplicarFiltros);
    
    // Filtros
    document.getElementById('estado').addEventListener('change', aplicarFiltros);
    document.getElementById('vendedor').addEventListener('change', aplicarFiltros);
});
</script>
@endpush
