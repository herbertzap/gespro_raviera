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
                            <p class="card-category">Clientes asignados</p>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead class="text-primary">
                                        <tr>
                                            <th>Código</th>
                                            <th>Cliente</th>
                                            <th>Facturas</th>
                                            <th>Saldo</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($clientesAsignados ?? [] as $cliente)
                                        <tr>
                                            <td>{{ $cliente['CODIGO_CLIENTE'] }}</td>
                                            <td>{{ $cliente['NOMBRE_CLIENTE'] }}</td>
                                            <td>{{ $cliente['CANTIDAD_FACTURAS'] }}</td>
                                            <td>${{ number_format($cliente['SALDO_TOTAL'] ?? 0, 2) }}</td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="4" class="text-center">No hay clientes asignados</td>
                                        </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
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
                                            <th>Número</th>
                                            <th>Cliente</th>
                                            <th>Total</th>
                                            <th>Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($cotizaciones ?? [] as $cotizacion)
                                        <tr>
                                            <td>{{ $cotizacion->numero_cotizacion }}</td>
                                            <td>{{ $cotizacion->nombre_cliente }}</td>
                                            <td>${{ number_format($cotizacion->total, 2) }}</td>
                                            <td>
                                                <span class="badge badge-{{ $cotizacion->estado == 'aprobada' ? 'success' : ($cotizacion->estado == 'enviada' ? 'warning' : 'info') }}">
                                                    {{ ucfirst($cotizacion->estado) }}
                                                </span>
                                            </td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="4" class="text-center">No hay cotizaciones recientes</td>
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
@endsection 