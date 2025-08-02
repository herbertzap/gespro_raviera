@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Dashboard - {{ $tipoUsuario ?? 'Usuario' }}</h4>
                        <p class="card-category">Bienvenido {{ auth()->user()->name }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tarjetas de Resumen -->
        <div class="row">
            @if($tipoUsuario == 'Super Admin')
                <!-- Super Admin - Resumen General -->
                <div class="col-lg-3 col-md-6 col-sm-6">
                    <div class="card card-stats">
                        <div class="card-header card-header-primary card-header-icon">
                            <div class="card-icon">
                                <i class="material-icons">people</i>
                            </div>
                            <p class="card-category">Total Usuarios</p>
                            <h3 class="card-title">{{ number_format($totalUsuarios ?? 0) }}</h3>
                        </div>
                        <div class="card-footer">
                            <div class="stats">
                                <i class="material-icons text-primary">info</i>
                                Usuarios registrados
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            @if(in_array($tipoUsuario, ['Super Admin', 'Vendedor', 'Supervisor']))
                <!-- Resumen de Cobranza -->
                <div class="col-lg-3 col-md-6 col-sm-6">
                    <div class="card card-stats">
                        <div class="card-header card-header-success card-header-icon">
                            <div class="card-icon">
                                <i class="material-icons">receipt</i>
                            </div>
                            <p class="card-category">Total Facturas</p>
                            <h3 class="card-title">{{ number_format($resumenCobranza['TOTAL_FACTURAS'] ?? 0) }}</h3>
                        </div>
                        <div class="card-footer">
                            <div class="stats">
                                <i class="material-icons text-success">trending_up</i>
                                <a href="{{ route('cobranza.index') }}">Ver detalles</a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6 col-sm-6">
                    <div class="card card-stats">
                        <div class="card-header card-header-danger card-header-icon">
                            <div class="card-icon">
                                <i class="material-icons">schedule</i>
                            </div>
                            <p class="card-category">Vencido</p>
                            <h3 class="card-title">${{ number_format($resumenCobranza['SALDO_VENCIDO'] ?? 0, 2) }}</h3>
                        </div>
                        <div class="card-footer">
                            <div class="stats">
                                <i class="material-icons text-danger">warning</i>
                                Requiere atención
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6 col-sm-6">
                    <div class="card card-stats">
                        <div class="card-header card-header-info card-header-icon">
                            <div class="card-icon">
                                <i class="material-icons">check_circle</i>
                            </div>
                            <p class="card-category">Vigente</p>
                            <h3 class="card-title">${{ number_format($resumenCobranza['SALDO_VIGENTE'] ?? 0, 2) }}</h3>
                        </div>
                        <div class="card-footer">
                            <div class="stats">
                                <i class="material-icons text-info">update</i>
                                Al día
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            @if(in_array($tipoUsuario, ['Super Admin', 'Supervisor']))
                <!-- Notas de Venta Pendientes -->
                <div class="col-lg-3 col-md-6 col-sm-6">
                    <div class="card card-stats">
                        <div class="card-header card-header-warning card-header-icon">
                            <div class="card-icon">
                                <i class="material-icons">pending_actions</i>
                            </div>
                            <p class="card-category">NV Pendientes</p>
                            <h3 class="card-title">{{ count($notasPendientes ?? []) }}</h3>
                        </div>
                        <div class="card-footer">
                            <div class="stats">
                                <i class="material-icons text-warning">schedule</i>
                                Por aprobar
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <!-- Contenido Específico por Rol -->
        <div class="row">
            @if($tipoUsuario == 'Super Admin')
                <!-- Super Admin - Usuarios por Rol -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header card-header-primary">
                            <h4 class="card-title">Usuarios por Rol</h4>
                            <p class="card-category">Distribución de usuarios</p>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead class="text-primary">
                                        <tr>
                                            <th>Rol</th>
                                            <th>Cantidad</th>
                                            <th>Porcentaje</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($usuariosPorRol ?? [] as $rol => $cantidad)
                                        <tr>
                                            <td>{{ $rol }}</td>
                                            <td>{{ $cantidad }}</td>
                                            <td>{{ $totalUsuarios > 0 ? number_format(($cantidad / $totalUsuarios) * 100, 1) : 0 }}%</td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            @if($tipoUsuario == 'Vendedor')
                <!-- Vendedor - Mis Clientes -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header card-header-primary">
                            <h4 class="card-title">Mis Clientes</h4>
                        </div>
                        <div class="card-body">
                            <!-- Filtros de búsqueda simplificados -->
                            <form method="GET" action="{{ route('dashboard') }}" class="mb-4">
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label for="buscar">Buscar por código o nombre:</label>
                                            <input type="text" class="form-control" id="buscar" name="buscar" 
                                                   value="{{ $filtros['buscar'] ?? '' }}" 
                                                   placeholder="Código cliente o nombre...">
                                        </div>
                                    </div>
                                    <div class="col-md-12 d-flex align-items-center">
                                        <button type="submit" class="btn btn-primary btn-sm">
                                            <i class="material-icons">search</i> Buscar
                                        </button>
                                        <a href="{{ route('dashboard') }}" class="btn btn-secondary btn-sm ml-2">
                                            <i class="material-icons">clear</i> Limpiar
                                        </a>
                                    </div>
                                </div>
                            </form>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead class="text-primary">
                                        <tr>
                                            <th>
                                                <a href="{{ request()->fullUrlWithQuery(['ordenar_por' => 'CODIGO_CLIENTE', 'orden' => request('ordenar_por') == 'CODIGO_CLIENTE' && request('orden') == 'asc' ? 'desc' : 'asc']) }}" 
                                                   class="text-decoration-none text-primary">
                                                    Código
                                                    @if(request('ordenar_por') == 'CODIGO_CLIENTE')
                                                        <i class="material-icons">{{ request('orden') == 'asc' ? 'keyboard_arrow_up' : 'keyboard_arrow_down' }}</i>
                                                    @else
                                                        <i class="material-icons text-muted">unfold_more</i>
                                                    @endif
                                                </a>
                                            </th>
                                            <th>
                                                <a href="{{ request()->fullUrlWithQuery(['ordenar_por' => 'NOMBRE_CLIENTE', 'orden' => request('ordenar_por') == 'NOMBRE_CLIENTE' && request('orden') == 'asc' ? 'desc' : 'asc']) }}" 
                                                   class="text-decoration-none text-primary">
                                                    Cliente
                                                    @if(request('ordenar_por') == 'NOMBRE_CLIENTE')
                                                        <i class="material-icons">{{ request('orden') == 'asc' ? 'keyboard_arrow_up' : 'keyboard_arrow_down' }}</i>
                                                    @else
                                                        <i class="material-icons text-muted">unfold_more</i>
                                                    @endif
                                                </a>
                                            </th>
                                            <th>
                                                <a href="{{ request()->fullUrlWithQuery(['ordenar_por' => 'CANTIDAD_FACTURAS', 'orden' => request('ordenar_por') == 'CANTIDAD_FACTURAS' && request('orden') == 'asc' ? 'desc' : 'asc']) }}" 
                                                   class="text-decoration-none text-primary">
                                                    Facturas
                                                    @if(request('ordenar_por') == 'CANTIDAD_FACTURAS')
                                                        <i class="material-icons">{{ request('orden') == 'asc' ? 'keyboard_arrow_up' : 'keyboard_arrow_down' }}</i>
                                                    @else
                                                        <i class="material-icons text-muted">unfold_more</i>
                                                    @endif
                                                </a>
                                            </th>
                                            <th>
                                                <a href="{{ request()->fullUrlWithQuery(['ordenar_por' => 'SALDO_TOTAL', 'orden' => request('ordenar_por') == 'SALDO_TOTAL' && request('orden') == 'asc' ? 'desc' : 'asc']) }}" 
                                                   class="text-decoration-none text-primary">
                                                    Saldo
                                                    @if(request('ordenar_por') == 'SALDO_TOTAL')
                                                        <i class="material-icons">{{ request('orden') == 'asc' ? 'keyboard_arrow_up' : 'keyboard_arrow_down' }}</i>
                                                    @else
                                                        <i class="material-icons text-muted">unfold_more</i>
                                                    @endif
                                                </a>
                                            </th>
                                            <th>
                                                <a href="{{ request()->fullUrlWithQuery(['ordenar_por' => 'BLOQUEADO', 'orden' => request('ordenar_por') == 'BLOQUEADO' && request('orden') == 'asc' ? 'desc' : 'asc']) }}" 
                                                   class="text-decoration-none text-primary">
                                                    Estado
                                                    @if(request('ordenar_por') == 'BLOQUEADO')
                                                        <i class="material-icons">{{ request('orden') == 'asc' ? 'keyboard_arrow_up' : 'keyboard_arrow_down' }}</i>
                                                    @else
                                                        <i class="material-icons text-muted">unfold_more</i>
                                                    @endif
                                                </a>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($clientesAsignados ?? [] as $cliente)
                                        <tr>
                                            <td>
                                                @if(isset($cliente['BLOQUEADO']) && $cliente['BLOQUEADO'] == 1)
                                                    <span class="text-muted">{{ $cliente['CODIGO_CLIENTE'] }}</span>
                                                @else
                                                    <a href="{{ url('/cotizacion/nueva?cliente=' . $cliente['CODIGO_CLIENTE'] . '&nombre=' . urlencode($cliente['NOMBRE_CLIENTE'])) }}" 
                                                       class="text-primary font-weight-bold" 
                                                       title="Crear nueva cotización para {{ $cliente['NOMBRE_CLIENTE'] }}">
                                                        {{ $cliente['CODIGO_CLIENTE'] }}
                                                    </a>
                                                @endif
                                            </td>
                                            <td>
                                                @if(isset($cliente['BLOQUEADO']) && $cliente['BLOQUEADO'] == 1)
                                                    <span class="text-muted">{{ $cliente['NOMBRE_CLIENTE'] }}</span>
                                                @else
                                                    <a href="{{ url('/cotizacion/nueva?cliente=' . $cliente['CODIGO_CLIENTE'] . '&nombre=' . urlencode($cliente['NOMBRE_CLIENTE'])) }}" 
                                                       class="text-primary" 
                                                       title="Crear nueva cotización para {{ $cliente['NOMBRE_CLIENTE'] }}">
                                                        {{ $cliente['NOMBRE_CLIENTE'] }}
                                                    </a>
                                                @endif
                                            </td>
                                            <td>{{ $cliente['CANTIDAD_FACTURAS'] }}</td>
                                            <td>${{ number_format($cliente['SALDO_TOTAL'] ?? 0, 2) }}</td>
                                            <td>
                                                @if(isset($cliente['BLOQUEADO']) && $cliente['BLOQUEADO'] == 1)
                                                    <span class="badge badge-danger">
                                                        <i class="material-icons" style="font-size: 12px;">block</i>
                                                        Bloqueado
                                                    </span>
                                                @else
                                                    <span class="badge badge-success">
                                                        <i class="material-icons" style="font-size: 12px;">check_circle</i>
                                                        Activo
                                                    </span>
                                                @endif
                                            </td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="5" class="text-center">No hay clientes asignados</td>
                                        </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Información de resultados -->
                            @if(isset($filtros) && (array_filter($filtros) !== []))
                                <div class="alert alert-info mt-3">
                                    <i class="material-icons">info</i>
                                    <strong>Filtros aplicados:</strong>
                                    @if(!empty($filtros['buscar']))
                                        <span class="badge badge-primary">Búsqueda: "{{ $filtros['buscar'] }}"</span>
                                    @endif
                                    @if(!empty($filtros['saldo_min']))
                                        <span class="badge badge-success">Saldo ≥ ${{ number_format($filtros['saldo_min'], 0) }}</span>
                                    @endif
                                    @if(!empty($filtros['saldo_max']))
                                        <span class="badge badge-success">Saldo ≤ ${{ number_format($filtros['saldo_max'], 0) }}</span>
                                    @endif
                                    @if(!empty($filtros['facturas_min']))
                                        <span class="badge badge-warning">Facturas ≥ {{ $filtros['facturas_min'] }}</span>
                                    @endif
                                    @if(!empty($filtros['facturas_max']))
                                        <span class="badge badge-warning">Facturas ≤ {{ $filtros['facturas_max'] }}</span>
                                    @endif
                                    <span class="badge badge-info">Ordenado por: {{ $filtros['ordenar_por'] ?? 'NOMBRE_CLIENTE' }} ({{ $filtros['orden'] ?? 'asc' }})</span>
                                </div>
                            @endif
                            
                            <!-- Paginación -->
                            @if($clientesAsignados instanceof \Illuminate\Pagination\LengthAwarePaginator && $clientesAsignados->hasPages())
                                <div class="card-footer">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="text-muted">
                                            Mostrando {{ $clientesAsignados->firstItem() }} a {{ $clientesAsignados->lastItem() }} de {{ $clientesAsignados->total() }} clientes
                                            @if(isset($filtros) && (array_filter($filtros) !== []))
                                                <span class="text-info">(filtrados)</span>
                                            @endif
                                        </div>
                                        <div class="d-flex gap-2">
                                            <!-- Botón "Cargar más" -->
                                            @if($clientesAsignados->hasMorePages())
                                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="cargarMasClientes()">
                                                    <i class="fas fa-plus"></i> Cargar más
                                                </button>
                                            @endif
                                            <!-- Paginación tradicional -->
                                            {{ $clientesAsignados->appends(request()->query())->links() }}
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            @if(in_array($tipoUsuario, ['Super Admin', 'Supervisor']))
                <!-- Notas de Venta Pendientes -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header card-header-warning">
                            <h4 class="card-title">Notas de Venta Pendientes</h4>
                            <p class="card-category">Esperando aprobación</p>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead class="text-warning">
                                        <tr>
                                            <th>Número</th>
                                            <th>Vendedor</th>
                                            <th>Cliente</th>
                                            <th>Total</th>
                                            <th>Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($notasPendientes ?? [] as $nota)
                                        <tr>
                                            <td>{{ $nota->numero_nota_venta ?? 'N/A' }}</td>
                                            <td>{{ $nota->user->name ?? 'N/A' }}</td>
                                            <td>{{ $nota->nombre_cliente ?? 'N/A' }}</td>
                                            <td>${{ number_format($nota->total ?? 0, 2) }}</td>
                                            <td>
                                                <span class="badge badge-warning">Por Aprobar</span>
                                            </td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="5" class="text-center">No hay notas de venta pendientes</td>
                                        </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            @if($tipoUsuario == 'Vendedor')
                <!-- Vendedor - Cotizaciones Recientes -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header card-header-success">
                            <h4 class="card-title">Cotizaciones Recientes</h4>
                            <p class="card-category">Últimas cotizaciones</p>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead class="text-success">
                                        <tr>
                                            <th>Tipo</th>
                                            <th>Número</th>
                                            <th>Cliente</th>
                                            <th>Saldo</th>
                                            <th>Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($cotizaciones ?? [] as $cotizacion)
                                        <tr>
                                            <td>
                                                <span class="badge badge-{{ $cotizacion['TIPO_DOCTO'] == 'FCV' ? 'success' : ($cotizacion['TIPO_DOCTO'] == 'FDV' ? 'info' : 'warning') }}">
                                                    {{ $cotizacion['TIPO_DOCTO'] }}
                                                </span>
                                            </td>
                                            <td>{{ $cotizacion['NRO_DOCTO'] }}</td>
                                            <td>{{ $cotizacion['CLIENTE'] }}</td>
                                            <td>${{ number_format($cotizacion['SALDO'], 2) }}</td>
                                            <td>
                                                <span class="badge badge-{{ 
                                                    $cotizacion['ESTADO'] == 'VIGENTE' ? 'success' : 
                                                    ($cotizacion['ESTADO'] == 'POR VENCER' ? 'warning' : 
                                                    ($cotizacion['ESTADO'] == 'VENCIDO' ? 'danger' : 
                                                    ($cotizacion['ESTADO'] == 'MOROSO' ? 'danger' : 'dark'))) 
                                                }}">
                                                    {{ $cotizacion['ESTADO'] }}
                                                </span>
                                            </td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="5" class="text-center">No hay cotizaciones recientes</td>
                                        </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <!-- Acciones Rápidas -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Acciones Rápidas</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            @if($tipoUsuario == 'Super Admin')
                                <div class="col-md-3">
                                    <a href="{{ route('users.index') }}" class="btn btn-primary btn-block">
                                        <i class="material-icons">people</i> Gestionar Usuarios
                                    </a>
                                </div>
                            @endif

                            @if(in_array($tipoUsuario, ['Super Admin', 'Vendedor', 'Supervisor']))
                                <div class="col-md-3">
                                    <a href="{{ route('cobranza.index') }}" class="btn btn-success btn-block">
                                        <i class="material-icons">receipt</i> Ver Cobranza
                                    </a>
                                </div>
                            @endif

                            @if($tipoUsuario == 'Vendedor')
                                <div class="col-md-3">
                                    <a href="{{ route('clientes.index') }}" class="btn btn-info btn-block">
                                        <i class="material-icons">add_shopping_cart</i> Seleccionar Cliente
                                    </a>
                                </div>
                            @endif

                            @if(in_array($tipoUsuario, ['Super Admin', 'Supervisor']))
                                <div class="col-md-3">
                                    <a href="#" class="btn btn-warning btn-block">
                                        <i class="material-icons">assessment</i> Reportes
                                    </a>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('js')
<script>
function cargarMasClientes() {
    // Obtener la página actual
    const urlParams = new URLSearchParams(window.location.search);
    const currentPage = parseInt(urlParams.get('page')) || 1;
    const nextPage = currentPage + 1;
    
    // Construir la nueva URL
    const newUrl = new URL(window.location);
    newUrl.searchParams.set('page', nextPage);
    
    // Redirigir a la siguiente página
    window.location.href = newUrl.toString();
}

// Función para mostrar/ocultar paginación según el tamaño de pantalla
function togglePagination() {
    const pagination = document.querySelector('.pagination');
    const loadMoreBtn = document.querySelector('.btn-outline-primary');
    
    if (window.innerWidth < 768) {
        if (pagination) pagination.style.display = 'none';
        if (loadMoreBtn) loadMoreBtn.style.display = 'block';
    } else {
        if (pagination) pagination.style.display = 'flex';
        if (loadMoreBtn) loadMoreBtn.style.display = 'inline-block';
    }
}

// Ejecutar al cargar la página y al cambiar el tamaño de ventana
document.addEventListener('DOMContentLoaded', togglePagination);
window.addEventListener('resize', togglePagination);
</script>
@endpush
@endsection 