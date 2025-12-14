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

        <!-- Tarjetas de Resumen - Primera Fila (máximo 4 cards) -->
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

            @if(in_array($tipoUsuario, ['Vendedor', 'Supervisor', 'Super Admin']))
                <!-- Resumen de Cobranza -->
                <div class="col-lg-3 col-md-6 col-sm-6">
                    <div class="card card-stats">
                        <div class="card-header card-header-success card-header-icon">
                            <div class="card-icon">
                                <i class="material-icons">receipt</i>
                            </div>
                            <p class="card-category">Total documentos pendientes de pago</p>
                            <h3 class="card-title">{{ number_format($resumenCobranza['TOTAL_FACTURAS'] ?? 0) }}</h3>
                        </div>
                        <div class="card-footer">
                            <div class="stats">
                                <i class="material-icons text-success">trending_up</i>
                                <a href="{{ route('cobranza.index') }}">Ver cobranza del vendedor</a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6 col-sm-6">
                    <div class="card card-stats">
                        <div class="card-header card-header-primary card-header-icon">
                            <div class="card-icon">
                                <i class="material-icons">shopping_cart</i>
                            </div>
                            <p class="card-category">Total Notas de Venta en proceso de aprobación</p>
                            <h3 class="card-title">{{ number_format($resumenCobranza['TOTAL_NOTAS_VENTA'] ?? 0) }}</h3>
                        </div>
                        <div class="card-footer">
                            <div class="stats">
                                <i class="material-icons text-primary">description</i>
                                <a href="{{ route('cotizaciones.index') }}">Ver detalles</a>
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
                            <p class="card-category">Total documentos vencidos</p>
                            <h3 class="card-title">${{ number_format($resumenCobranza['SALDO_VENCIDO'] ?? 0, 0) }}</h3>
                        </div>
                        <div class="card-footer">
                            <div class="stats">
                                <i class="material-icons text-danger">warning</i>
                                Requiere atención
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <!-- Segunda Fila (máximo 4 cards) -->
        <div class="row">
            @if(in_array($tipoUsuario, ['Vendedor', 'Supervisor', 'Super Admin']))
                <div class="col-lg-3 col-md-6 col-sm-6">
                    <div class="card card-stats">
                        <div class="card-header card-header-info card-header-icon">
                            <div class="card-icon">
                                <i class="material-icons">account_balance</i>
                            </div>
                            <p class="card-category">Cheques en Cartera</p>
                            <h3 class="card-title">${{ number_format($resumenCobranza['CHEQUES_EN_CARTERA'] ?? 0, 0) }}</h3>
                        </div>
                        <div class="card-footer">
                            <div class="stats">
                                <i class="material-icons text-info">account_balance_wallet</i>
                                En cartera
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6 col-sm-6">
                    <div class="card card-stats">
                        <div class="card-header card-header-danger card-header-icon">
                            <div class="card-icon">
                                <i class="material-icons">warning</i>
                            </div>
                            <p class="card-category">Cheques Protestados</p>
                            <h3 class="card-title">${{ number_format($resumenCobranza['CHEQUES_PROTESTADOS'] ?? 0, 0) }}</h3>
                        </div>
                        <div class="card-footer">
                            <div class="stats">
                                <i class="material-icons text-danger">block</i>
                                <a href="{{ route('cobranza.index', ['cheques_protestados' => 'si']) }}">Ver clientes</a>
                            </div>
                        </div>
                    </div>
                </div>

                @if(in_array($tipoUsuario, ['Supervisor', 'Compras', 'Picking', 'Super Admin']))
                    <!-- NVV Pendientes -->
                    <div class="col-lg-3 col-md-6 col-sm-6">
                        <div class="card card-stats">
                            <div class="card-header card-header-warning card-header-icon">
                                <div class="card-icon">
                                    <i class="material-icons">pending_actions</i>
                                </div>
                                <p class="card-category">NVV Pendientes</p>
                                <h3 class="card-title">{{ $resumenNvvPendientes['total_nvv'] ?? 0 }}</h3>
                            </div>
                            <div class="card-footer">
                                <div class="stats">
                                    <i class="material-icons text-warning">schedule</i>
                                    Por facturar
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Valor NVV Pendientes -->
                    <div class="col-lg-3 col-md-6 col-sm-6">
                        <div class="card card-stats">
                            <div class="card-header card-header-rose card-header-icon">
                                <div class="card-icon">
                                    <i class="material-icons">attach_money</i>
                                </div>
                                <p class="card-category">Valor NVV Pend.</p>
                                <h3 class="card-title">${{ number_format($resumenNvvPendientes['total_valor_pendiente'] ?? 0, 0) }}</h3>
                            </div>
                            <div class="card-footer">
                                <div class="stats">
                                    <i class="material-icons text-rose">trending_up</i>
                                    Por facturar
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            @endif
        </div>

        @if(in_array($tipoUsuario, ['Supervisor', 'Super Admin']))
            <!-- Supervisor - Primera fila: 4 cards máximo -->
            <div class="row">
                    <!-- 1. NVV Por Validar -->
                    <div class="col-lg-3 col-md-6 col-sm-6">
                        <div class="card card-stats">
                            <div class="card-header card-header-warning card-header-icon">
                                <div class="card-icon">
                                    <i class="material-icons">pending_actions</i>
                                </div>
                                <p class="card-category">NVV Por Validar</p>
                                <h3 class="card-title">{{ number_format($resumenCobranza['TOTAL_NOTAS_PENDIENTES_VALIDAR'] ?? 0) }}</h3>
                            </div>
                            <div class="card-footer">
                                <div class="stats">
                                    <i class="material-icons text-warning">pending_actions</i>
                                    <a href="{{ route('aprobaciones.index') }}" class="text-warning">Ver aprobaciones</a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 2. NVV en Sistema Este Mes -->
                    <div class="col-lg-3 col-md-6 col-sm-6">
                        <div class="card card-stats">
                            <div class="card-header card-header-info card-header-icon">
                                <div class="card-icon">
                                    <i class="material-icons">storage</i>
                                </div>
                                <p class="card-category">NVV en Sistema Este Mes</p>
                                <h3 class="card-title">{{ number_format($resumenCobranza['TOTAL_NOTAS_VENTA_MES_ACTUAL'] ?? 0) }}</h3>
                            </div>
                            <div class="card-footer">
                                <div class="stats">
                                    <i class="material-icons text-info">date_range</i>
                                    {{ date('M Y') }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 3. Facturas Este Mes -->
                    <div class="col-lg-3 col-md-6 col-sm-6">
                        <div class="card card-stats">
                            <div class="card-header card-header-danger card-header-icon">
                                <div class="card-icon">
                                    <i class="material-icons">receipt</i>
                                </div>
                                <p class="card-category">Facturas Este Mes</p>
                                <h3 class="card-title">{{ number_format($resumenCobranza['TOTAL_FACTURAS_MES_ACTUAL'] ?? 0) }}</h3>
                            </div>
                            <div class="card-footer">
                                <div class="stats">
                                    <i class="material-icons text-danger">date_range</i>
                                    {{ date('M Y') }}
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 4. Valor Total Facturas Mensuales No Pagadas -->
                    <div class="col-lg-3 col-md-6 col-sm-6">
                        <div class="card card-stats">
                            <div class="card-header card-header-danger card-header-icon">
                                <div class="card-icon">
                                    <i class="material-icons">attach_money</i>
                                </div>
                                <p class="card-category">Fact. Mensuales No Pagadas</p>
                                <h3 class="card-title">${{ number_format($resumenCobranza['VALOR_TOTAL_FACTURAS_MES_ACTUAL'] ?? 0, 0) }}</h3>
                            </div>
                            <div class="card-footer">
                                <div class="stats">
                                    <i class="material-icons text-danger">trending_up</i>
                                    Mes actual
                                </div>
                            </div>
                        </div>
                    </div>

                    @if($tipoUsuario == 'Super Admin')
                    <!-- 5. Compras del Mes (si es Super Admin) -->
                    @if($tipoUsuario == 'Super Admin')
                    <div class="col-lg-3 col-md-6 col-sm-6">
                        <div class="card card-stats">
                            <div class="card-header card-header-info card-header-icon">
                                <div class="card-icon">
                                    <i class="material-icons">shopping_cart</i>
                                </div>
                                <p class="card-category">Compras del Mes</p>
                                <h3 class="card-title">{{ number_format($resumenCompras['total_compras_mes'] ?? 0) }}</h3>
                            </div>
                            <div class="card-footer">
                                <div class="stats">
                                    <i class="material-icons text-info">date_range</i>
                                    {{ $resumenCompras['mes_actual'] ?? date('Y-m') }}
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
                
                <!-- Segunda fila: Cards informativos (máximo 4) -->
                <div class="row">
                    <!-- 1. Cheques en Cartera -->
                    <div class="col-lg-3 col-md-6 col-sm-6">
                        <div class="card card-stats">
                            <div class="card-header card-header-success card-header-icon">
                                <div class="card-icon">
                                    <i class="material-icons">account_balance</i>
                                </div>
                                <p class="card-category">Cheques en Cartera</p>
                                <h3 class="card-title">${{ number_format($resumenCobranza['CHEQUES_EN_CARTERA'] ?? 0, 0) }}</h3>
                            </div>
                            <div class="card-footer">
                                <div class="stats">
                                    <i class="material-icons text-success">account_balance_wallet</i>
                                    En cartera
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 2. Cheques Protestados -->
                    <div class="col-lg-3 col-md-6 col-sm-6">
                        <div class="card card-stats">
                            <div class="card-header card-header-danger card-header-icon">
                                <div class="card-icon">
                                    <i class="material-icons">warning</i>
                                </div>
                                <p class="card-category">Cheques Protestados</p>
                                <h3 class="card-title">${{ number_format($resumenCobranza['CHEQUES_PROTESTADOS'] ?? 0, 0) }}</h3>
                            </div>
                            <div class="card-footer">
                                <div class="stats">
                                    <i class="material-icons text-danger">block</i>
                                    Requieren atención
                                </div>
                            </div>
                        </div>
                    </div>

                    @if($tipoUsuario == 'Super Admin')
                    <!-- 3. Productos Bajo Stock -->
                    <div class="col-lg-3 col-md-6 col-sm-6">
                        <div class="card card-stats">
                            <div class="card-header card-header-warning card-header-icon">
                                <div class="card-icon">
                                    <i class="material-icons">warning</i>
                                </div>
                                <p class="card-category">Productos Bajo Stock</p>
                                <h3 class="card-title">{{ number_format($resumenCompras['productos_bajo_stock'] ?? 0) }}</h3>
                            </div>
                            <div class="card-footer">
                                <div class="stats">
                                    <i class="material-icons text-warning">inventory_2</i>
                                    Requieren reposición
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 4. Compras Pendientes -->
                    <div class="col-lg-3 col-md-6 col-sm-6">
                        <div class="card card-stats">
                            <div class="card-header card-header-primary card-header-icon">
                                <div class="card-icon">
                                    <i class="material-icons">pending</i>
                                </div>
                                <p class="card-category">Compras Pendientes</p>
                                <h3 class="card-title">{{ number_format($resumenCompras['compras_pendientes'] ?? 0) }}</h3>
                            </div>
                            <div class="card-footer">
                                <div class="stats">
                                    <i class="material-icons text-primary">schedule</i>
                                    En proceso
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
        @endif

        @if($tipoUsuario == 'Compras')
            <!-- Compras - Resumen (solo para rol Compras) -->
            <div class="row">
                    <div class="col-lg-3 col-md-6 col-sm-6">
                        <div class="card card-stats">
                            <div class="card-header card-header-info card-header-icon">
                                <div class="card-icon">
                                    <i class="material-icons">shopping_cart</i>
                                </div>
                                <p class="card-category">Compras del Mes</p>
                                <h3 class="card-title">{{ number_format($resumenCompras['total_compras_mes'] ?? 0) }}</h3>
                            </div>
                            <div class="card-footer">
                                <div class="stats">
                                    <i class="material-icons text-info">date_range</i>
                                    {{ $resumenCompras['mes_actual'] ?? date('Y-m') }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-3 col-md-6 col-sm-6">
                        <div class="card card-stats">
                            <div class="card-header card-header-warning card-header-icon">
                                <div class="card-icon">
                                    <i class="material-icons">warning</i>
                                </div>
                                <p class="card-category">Productos Bajo Stock</p>
                                <h3 class="card-title">{{ number_format($resumenCompras['productos_bajo_stock'] ?? 0) }}</h3>
                            </div>
                            <div class="card-footer">
                                <div class="stats">
                                    <i class="material-icons text-warning">inventory_2</i>
                                    Requieren reposición
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-3 col-md-6 col-sm-6">
                        <div class="card card-stats">
                            <div class="card-header card-header-success card-header-icon">
                                <div class="card-icon">
                                    <i class="material-icons">receipt</i>
                                </div>
                                <p class="card-category">NVV Pendientes</p>
                                <h3 class="card-title">{{ count($nvvPendientes ?? []) }}</h3>
                            </div>
                            <div class="card-footer">
                                <div class="stats">
                                    <i class="material-icons text-success">pending_actions</i>
                                    Por aprobar
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-3 col-md-6 col-sm-6">
                        <div class="card card-stats">
                            <div class="card-header card-header-primary card-header-icon">
                                <div class="card-icon">
                                    <i class="material-icons">pending</i>
                                </div>
                                <p class="card-category">Compras Pendientes</p>
                                <h3 class="card-title">{{ number_format($resumenCompras['compras_pendientes'] ?? 0) }}</h3>
                            </div>
                            <div class="card-footer">
                                <div class="stats">
                                    <i class="material-icons text-primary">schedule</i>
                                    En proceso
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
        @endif

        <!-- Contenido Específico por Rol -->
        <div class="row">
            @if($tipoUsuario == 'Super Admin')
                <!-- Super Admin - Usuarios por Rol (Solo Manejo Stock) -->
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header card-header-primary">
                            <h4 class="card-title">Usuarios por Rol</h4>
                            <p class="card-category">Distribución de usuarios - Manejo Stock</p>
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
                <div class="col-md-12">
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
                                                    <a href="{{ route('cliente.show', $cliente['CODIGO_CLIENTE']) }}" 
                                                       class="text-primary font-weight-bold" 
                                                       title="Ver información de {{ $cliente['NOMBRE_CLIENTE'] }}">
                                                        {{ $cliente['CODIGO_CLIENTE'] }}
                                                    </a>
                                                @endif
                                            </td>
                                            <td>
                                                @if(isset($cliente['BLOQUEADO']) && $cliente['BLOQUEADO'] == 1)
                                                    <span class="text-muted">{{ $cliente['NOMBRE_CLIENTE'] }}</span>
                                                @else
                                                    <a href="{{ route('cliente.show', $cliente['CODIGO_CLIENTE']) }}" 
                                                       class="text-primary" 
                                                       title="Ver información de {{ $cliente['NOMBRE_CLIENTE'] }}">
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
                
                <!-- Sección de Clientes Vencidos, Bloqueados y Morosos -->
                @if(isset($clientesVencidosBloqueadosMorosos) && count($clientesVencidosBloqueadosMorosos) > 0)
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header card-header-danger">
                            <h4 class="card-title">
                                <i class="material-icons">warning</i>
                                Clientes Vencidos, Bloqueados y Morosos
                            </h4>
                            <p class="card-category">Clientes que requieren atención inmediata</p>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="text-primary">
                                        <tr>
                                            <th>Código</th>
                                            <th>Cliente</th>
                                            <th>Teléfono</th>
                                            <th>Dirección</th>
                                            <th>Facturas</th>
                                            <th>Saldo</th>
                                            <th>Problemas</th>
                                            <th>Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($clientesVencidosBloqueadosMorosos as $cliente)
                                        <tr>
                                            <td>
                                                <a href="{{ route('cliente.show', $cliente['CODIGO_CLIENTE']) }}" 
                                                   class="text-primary font-weight-bold" 
                                                   title="Ver información de {{ $cliente['NOMBRE_CLIENTE'] }}">
                                                    {{ $cliente['CODIGO_CLIENTE'] }}
                                                </a>
                                            </td>
                                            <td>
                                                <a href="{{ route('cliente.show', $cliente['CODIGO_CLIENTE']) }}" 
                                                   class="text-primary" 
                                                   title="Ver información de {{ $cliente['NOMBRE_CLIENTE'] }}">
                                                    {{ $cliente['NOMBRE_CLIENTE'] }}
                                                </a>
                                            </td>
                                            <td>{{ $cliente['TELEFONO'] ?: 'No especificado' }}</td>
                                            <td>{{ $cliente['DIRECCION'] ?: 'No especificada' }}</td>
                                            <td>{{ $cliente['CANTIDAD_FACTURAS'] }}</td>
                                            <td>${{ number_format($cliente['SALDO_TOTAL'] ?? 0, 2) }}</td>
                                            <td>
                                                <span class="badge badge-danger">
                                                    {{ $cliente['PROBLEMAS'] }}
                                                </span>
                                            </td>
                                            <td>
                                                @if(isset($cliente['BLOQUEADO']) && $cliente['BLOQUEADO'] == 1)
                                                    <span class="badge badge-danger">
                                                        <i class="material-icons" style="font-size: 12px;">block</i>
                                                        Bloqueado
                                                    </span>
                                                @else
                                                    <span class="badge badge-warning">
                                                        <i class="material-icons" style="font-size: 12px;">warning</i>
                                                        Vencido
                                                    </span>
                                                @endif
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Información de resumen -->
                            <div class="alert alert-warning mt-3">
                                <i class="material-icons">info</i>
                                <strong>Resumen:</strong> Se encontraron {{ count($clientesVencidosBloqueadosMorosos) }} clientes con problemas que requieren atención inmediata.
                            </div>
                        </div>
                    </div>
                </div>
                @endif
            @endif

            @if(in_array($tipoUsuario, ['Supervisor', 'Super Admin']))
                <!-- Supervisor - Tablas de Información -->
                
                <!-- Notas de Venta Pendientes (col-12) -->
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header card-header-warning">
                            <h4 class="card-title">Notas de Venta Pendientes</h4>
                            <p class="card-category">Esperando aprobación por Supervisor</p>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead class="text-warning">
                                        <tr>
                                            <th>Número</th>
                                            <th>Fecha Ingreso</th>
                                            <th>Vendedor</th>
                                            <th>Cliente</th>
                                            <th>Total</th>
                                            <th>Estado</th>
                                            <th>Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($notasPendientes ?? [] as $nota)
                                        <tr>
                                            <td>
                                                <strong>#{{ $nota->id ?? 'N/A' }}</strong>
                                                @if($nota->numero_cotizacion)
                                                    <br><small class="text-muted">{{ $nota->numero_cotizacion }}</small>
                                                @endif
                                            </td>
                                            <td>
                                                <small>{{ $nota->created_at ? $nota->created_at->format('d/m/Y') : ($nota->fecha ? $nota->fecha->format('d/m/Y') : 'N/A') }}</small>
                                            </td>
                                            <td>{{ $nota->user->name ?? 'N/A' }}</td>
                                            <td>
                                                <strong>{{ $nota->cliente_codigo ?? 'N/A' }}</strong>
                                                @if($nota->cliente_nombre)
                                                    <br><small class="text-muted">{{ $nota->cliente_nombre }}</small>
                                                @elseif($nota->cliente && $nota->cliente->nombre_cliente)
                                                    <br><small class="text-muted">{{ $nota->cliente->nombre_cliente }}</small>
                                                @endif
                                            </td>
                                            <td>${{ number_format($nota->total ?? 0, 2) }}</td>
                                            <td>
                                                <span class="badge badge-{{ 
                                                    $nota->estado_aprobacion == 'pendiente' ? 'warning' : 
                                                    ($nota->estado_aprobacion == 'pendiente_picking' ? 'info' : 
                                                    ($nota->estado_aprobacion == 'aprobada_supervisor' ? 'success' : 'primary'))
                                                }}">
                                                    {{ ucfirst(str_replace('_', ' ', $nota->estado_aprobacion ?? 'pendiente')) }}
                                                </span>
                                            </td>
                                            <td>
                                                <a href="{{ route('aprobaciones.show', $nota->id) }}" class="btn btn-sm btn-primary">
                                                    <i class="material-icons">visibility</i> Ver
                                                </a>
                                            </td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="7" class="text-center">No hay notas de venta pendientes</td>
                                        </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            <div class="card-footer text-center">
                                <a href="{{ route('aprobaciones.index') }}" class="btn btn-warning btn-lg">
                                    <i class="material-icons">visibility</i> Ver Todas
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Notas de Venta en Sistema SQL (col-12) -->
                <div class="col-md-12" id="notas-sql">
                    <div class="card">
                        <div class="card-header card-header-info">
                            <h4 class="card-title">Notas de Venta en Sistema</h4>
                            <p class="card-category">NVV ingresadas en SQL Server</p>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead class="text-info">
                                        <tr>
                                            <th>Tipo</th>
                                            <th>Número</th>
                                            <th>Cliente</th>
                                            <th>Vendedor</th>
                                            <th>Valor</th>
                                            <th>Fecha</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($notasVentaSQL ?? [] as $nota)
                                        <tr>
                                            <td><span class="badge badge-info">{{ $nota['TIPO_DOCTO'] ?? 'NVV' }}</span></td>
                                            <td>{{ $nota['NRO_DOCTO'] ?? '' }}</td>
                                            <td>{{ $nota['CLIENTE'] ?? $nota['NOMBRE_CLIENTE'] ?? '' }}</td>
                                            <td>{{ $nota['VENDEDOR'] ?? $nota['NOMBRE_VENDEDOR'] ?? '' }}</td>
                                            <td>${{ number_format($nota['VALOR_PENDIENTE'] ?? $nota['VALOR_DOCUMENTO'] ?? 0, 0) }}</td>
                                            <td>{{ isset($nota['FECHA_EMISION']) ? \Carbon\Carbon::parse($nota['FECHA_EMISION'])->format('d/m/Y') : '' }}</td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="6" class="text-center">No hay notas de venta en sistema</td>
                                        </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            <div class="card-footer text-center">
                                <a href="{{ route('nvv-pendientes.index') }}" class="btn btn-info btn-lg">
                                    <i class="material-icons">visibility</i> Ver Todas
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Facturas Pendientes (col-12) -->
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header card-header-danger">
                            <h4 class="card-title">Últimas 10 Facturas Pendientes</h4>
                            <p class="card-category">Facturas por cobrar del sistema SQL Server</p>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead class="text-danger">
                                        <tr>
                                            <th>Tipo</th>
                                            <th>Número</th>
                                            <th>Cliente</th>
                                            <th>Vendedor</th>
                                            <th>Valor</th>
                                            <th>Abonos</th>
                                            <th>Saldo</th>
                                            <th>Días</th>
                                            <th>Estado</th>
                                            <th>Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($facturasPendientes ?? [] as $factura)
                                        <tr>
                                            <td><span class="badge badge-danger">{{ $factura['TIPO_DOCTO'] ?? '' }}</span></td>
                                            <td>{{ $factura['NRO_DOCTO'] ?? '' }}</td>
                                            <td>{{ $factura['CLIENTE'] ?? '' }}</td>
                                            <td>{{ $factura['VENDEDOR'] ?? '' }}</td>
                                            <td>${{ number_format($factura['VALOR'] ?? 0, 0) }}</td>
                                            <td>${{ number_format($factura['ABONOS'] ?? 0, 0) }}</td>
                                            <td>
                                                <span class="badge badge-{{ ($factura['SALDO'] ?? 0) > 0 ? 'warning' : 'success' }}">
                                                    ${{ number_format($factura['SALDO'] ?? 0, 0) }}
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge badge-{{ 
                                                    ($factura['DIAS'] ?? 0) < 8 ? 'success' : 
                                                    (($factura['DIAS'] ?? 0) < 31 ? 'warning' : 
                                                    (($factura['DIAS'] ?? 0) < 61 ? 'danger' : 'dark')) 
                                                }}">
                                                    {{ $factura['DIAS'] ?? 0 }}
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge badge-{{ 
                                                    ($factura['ESTADO'] ?? '') == 'VIGENTE' ? 'success' : 
                                                    (($factura['ESTADO'] ?? '') == 'POR VENCER' ? 'warning' : 
                                                    (($factura['ESTADO'] ?? '') == 'VENCIDO' ? 'danger' : 'dark')) 
                                                }}">
                                                    {{ $factura['ESTADO'] ?? '' }}
                                                </span>
                                            </td>
                                            <td>
                                                <a href="{{ route('facturas-pendientes.ver', [$factura['TIPO_DOCTO'] ?? '', $factura['NRO_DOCTO'] ?? '']) }}" class="btn btn-sm btn-primary">
                                                    <i class="material-icons">visibility</i> Ver
                                                </a>
                                            </td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="10" class="text-center">No hay facturas pendientes</td>
                                        </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Cheques Asociados al Cliente (col-12) -->
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header card-header-primary">
                            <h4 class="card-title">Cheques Asociados al Cliente</h4>
                            <p class="card-category">Cheques en cartera y cheques protestados</p>
                        </div>
                        <div class="card-body">
                            <!-- Tabs -->
                            <ul class="nav nav-tabs" role="tablist">
                                <li class="nav-item">
                                    <a class="nav-link active" data-toggle="tab" href="#chequesCartera" role="tab">
                                        <i class="material-icons">account_balance</i> Cheques en Cartera
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" data-toggle="tab" href="#chequesProtestados" role="tab">
                                        <i class="material-icons">warning</i> Cheques Protestados
                                    </a>
                                </li>
                            </ul>
                            
                            <!-- Tab Content -->
                            <div class="tab-content">
                                <!-- Tab: Cheques en Cartera -->
                                <div class="tab-pane fade show active" id="chequesCartera" role="tabpanel">
                                    <div class="table-responsive mt-3">
                                        <table class="table">
                                            <thead class="text-primary">
                                                <tr>
                                                    <th>Número</th>
                                                    <th>Cliente</th>
                                                    <th>Vendedor</th>
                                                    <th>Valor</th>
                                                    <th>Fecha Vencimiento</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse($chequesEnCarteraDetalle ?? [] as $cheque)
                                                <tr>
                                                    <td><strong>{{ $cheque['numero'] ?? '' }}</strong></td>
                                                    <td>
                                                        <strong>{{ $cheque['codigo_cliente'] ?? '' }}</strong>
                                                        @if($cheque['cliente'])
                                                            <br><small class="text-muted">{{ $cheque['cliente'] }}</small>
                                                        @endif
                                                    </td>
                                                    <td>{{ $cheque['vendedor'] ?? '' }}</td>
                                                    <td>${{ number_format($cheque['valor'] ?? 0, 0) }}</td>
                                                    <td>
                                                        @if($cheque['fecha_vencimiento'])
                                                            {{ \Carbon\Carbon::parse($cheque['fecha_vencimiento'])->format('d/m/Y') }}
                                                        @else
                                                            N/A
                                                        @endif
                                                    </td>
                                                </tr>
                                                @empty
                                                <tr>
                                                    <td colspan="5" class="text-center">No hay cheques en cartera</td>
                                                </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                
                                <!-- Tab: Cheques Protestados -->
                                <div class="tab-pane fade" id="chequesProtestados" role="tabpanel">
                                    <div class="table-responsive mt-3">
                                        <table class="table">
                                            <thead class="text-danger">
                                                <tr>
                                                    <th>Número</th>
                                                    <th>Cliente</th>
                                                    <th>Vendedor</th>
                                                    <th>Valor</th>
                                                    <th>Fecha Vencimiento</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse($chequesProtestadosDetalle ?? [] as $cheque)
                                                <tr>
                                                    <td><strong>{{ $cheque['numero'] ?? '' }}</strong></td>
                                                    <td>
                                                        <strong>{{ $cheque['codigo_cliente'] ?? '' }}</strong>
                                                        @if($cheque['cliente'])
                                                            <br><small class="text-muted">{{ $cheque['cliente'] }}</small>
                                                        @endif
                                                    </td>
                                                    <td>{{ $cheque['vendedor'] ?? '' }}</td>
                                                    <td>${{ number_format($cheque['valor'] ?? 0, 0) }}</td>
                                                    <td>
                                                        @if($cheque['fecha_vencimiento'])
                                                            {{ \Carbon\Carbon::parse($cheque['fecha_vencimiento'])->format('d/m/Y') }}
                                                        @else
                                                            N/A
                                                        @endif
                                                    </td>
                                                </tr>
                                                @empty
                                                <tr>
                                                    <td colspan="5" class="text-center">No hay cheques protestados</td>
                                                </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            @if(auth()->user()->hasRole('Supervisor') || auth()->user()->hasRole('Super Admin'))
                <!-- NVV Pendientes Detalle -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header card-header-warning">
                            <h4 class="card-title">NVV Pendientes Detalle</h4>
                            <p class="card-category">Notas de venta pendientes de facturación</p>
                        </div>
                        <div class="card-body">
                            <div class="text-center mb-3">
                                <span class="badge badge-info">👤 Rol: {{ $tipoUsuario }}</span>
                                @if($tipoUsuario == 'Picking')
                                    <span class="badge badge-warning ml-2">📦 Validación de Stock</span>
                                @endif
                            </div>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead class="text-warning">
                                        <tr>
                                            <th>Número</th>
                                            <th>Cliente</th>
                                            <th>Producto</th>
                                            <th>Pendiente</th>
                                            <th>Días</th>
                                            <th>Rango</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($nvvPendientes ?? [] as $nvv)
                                        <tr>
                                            <td>
                                                <span class="badge badge-warning">{{ $nvv['TD'] ?? '' }}-{{ $nvv['NUM'] ?? '' }}</span>
                                            </td>
                                            <td>{{ $nvv['CLIE'] ?? '' }}</td>
                                            <td>{{ $nvv['CANTIDAD_PRODUCTOS'] ?? 0 }} productos</td>
                                            <td>{{ number_format($nvv['TOTAL_PENDIENTE'] ?? 0, 0) }}</td>
                                            <td>
                                                <span class="badge badge-{{ 
                                                    ($nvv['DIAS'] ?? 0) < 8 ? 'success' : 
                                                    (($nvv['DIAS'] ?? 0) < 31 ? 'warning' : 
                                                    (($nvv['DIAS'] ?? 0) < 61 ? 'danger' : 'dark')) 
                                                }}">
                                                    {{ $nvv['DIAS'] ?? 0 }}
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge badge-{{ 
                                                    ($nvv['Rango'] ?? '') == 'Entre 1 y 7 días' ? 'success' : 
                                                    (($nvv['Rango'] ?? '') == 'Entre 8 y 30 Días' ? 'warning' : 
                                                    (($nvv['Rango'] ?? '') == 'Entre 31 y 60 Días' ? 'danger' : 'dark')) 
                                                }}">
                                                    {{ $nvv['Rango'] ?? '' }}
                                                </span>
                                            </td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="6" class="text-center">No hay NVV pendientes</td>
                                        </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Resumen de NVV Pendientes -->
                            @if(isset($resumenNvvPendientes))
                            <div class="mt-3">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <h5 class="text-warning">{{ $resumenNvvPendientes['total_nvv'] ?? 0 }}</h5>
                                            <small>Total NVV</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <h5 class="text-info">{{ number_format($resumenNvvPendientes['total_pendiente'] ?? 0, 0) }}</h5>
                                            <small>Unidades Pend.</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <h5 class="text-success">${{ number_format($resumenNvvPendientes['total_valor_pendiente'] ?? 0, 0) }}</h5>
                                            <small>Valor Pend.</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <a href="{{ route('nvv-pendientes.index') }}" class="btn btn-warning btn-sm">
                                                <i class="material-icons">visibility</i> Ver Todo
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Facturas Pendientes (visible solo Supervisor) -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header card-header-danger">
                            <h4 class="card-title">Últimas 10 Facturas Pendientes</h4>
                            <p class="card-category">Facturas por cobrar</p>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead class="text-danger">
                                        <tr>
                                            <th>Tipo</th>
                                            <th>Número</th>
                                            <th>Cliente</th>
                                            <th>Saldo</th>
                                            <th>Días</th>
                                            <th>Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($facturasPendientes ?? [] as $factura)
                                        <tr>
                                            <td>
                                                <span class="badge badge-{{ 
                                                    ($factura['TIPO_DOCTO'] ?? '') == 'FCV' ? 'success' : 
                                                    (($factura['TIPO_DOCTO'] ?? '') == 'FDV' ? 'info' : 'warning') 
                                                }}">
                                                    {{ $factura['TIPO_DOCTO'] ?? '' }}
                                                </span>
                                            </td>
                                            <td>{{ $factura['NRO_DOCTO'] ?? '' }}</td>
                                            <td>{{ $factura['CLIENTE'] ?? '' }}</td>
                                            <td>${{ number_format($factura['SALDO'] ?? 0, 0) }}</td>
                                            <td>
                                                <span class="badge badge-{{ 
                                                    ($factura['DIAS'] ?? 0) < 0 ? 'success' : 
                                                    (($factura['DIAS'] ?? 0) < 8 ? 'warning' : 
                                                    (($factura['DIAS'] ?? 0) < 31 ? 'danger' : 'dark')) 
                                                }}">
                                                    {{ $factura['DIAS'] ?? 0 }}
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge badge-{{ 
                                                    ($factura['ESTADO'] ?? 'VIGENTE') == 'VIGENTE' ? 'success' : 
                                                    (($factura['ESTADO'] ?? 'VIGENTE') == 'POR VENCER' ? 'warning' : 
                                                    (($factura['ESTADO'] ?? 'VIGENTE') == 'VENCIDO' ? 'danger' : 
                                                    (($factura['ESTADO'] ?? 'VIGENTE') == 'MOROSO' ? 'danger' : 'dark'))) 
                                                }}">
                                                    {{ $factura['ESTADO'] ?? 'VIGENTE' }}
                                                </span>
                                            </td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="6" class="text-center">No hay facturas pendientes</td>
                                        </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Resumen de Facturas Pendientes -->
                            @if(isset($resumenFacturasPendientes))
                            <div class="mt-3">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <h5 class="text-danger">{{ $resumenFacturasPendientes['total_facturas'] ?? 0 }}</h5>
                                            <small>Total Facturas</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <h5 class="text-success">${{ number_format($resumenFacturasPendientes['total_saldo'] ?? 0, 0) }}</h5>
                                            <small>Saldo Total</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <h5 class="text-warning">{{ $resumenFacturasPendientes['por_estado']['VENCIDO']['cantidad'] + $resumenFacturasPendientes['por_estado']['MOROSO']['cantidad'] }}</h5>
                                            <small>Vencidas</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <a href="{{ route('facturas-pendientes.index') }}" class="btn btn-danger btn-sm">
                                                <i class="material-icons">visibility</i> Ver Todo
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endif
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

                            @if($tipoUsuario == 'Supervisor')
                                <!-- Acciones específicas del Supervisor -->
                                <div class="col-md-4">
                                    <a href="{{ route('aprobaciones.index') }}" class="btn btn-warning btn-block">
                                        <i class="material-icons">pending_actions</i> Notas Pendientes
                                    </a>
                                </div>
                                <div class="col-md-4">
                                    <a href="{{ route('facturas-pendientes.index') }}" class="btn btn-danger btn-block">
                                        <i class="material-icons">receipt</i> Facturas Sistema
                                    </a>
                                </div>
                                <div class="col-md-4">
                                    <a href="{{ route('clientes.index') }}" class="btn btn-primary btn-block">
                                        <i class="material-icons">people</i> Listado Clientes
                                    </a>
                                </div>
                            @elseif(in_array($tipoUsuario, ['Super Admin', 'Vendedor']))
                                
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

        <!-- Sección específica para Compras -->
        @if(in_array($tipoUsuario, ['Compras', 'Super Admin']))
        <div class="row">
            <!-- NVV Pendientes de Aprobación -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header card-header-success">
                        <h4 class="card-title">
                            <i class="material-icons">pending_actions</i>
                            Notas de Venta Pendientes de Aprobación
                        </h4>
                        <p class="card-category">NVV que requieren aprobación de Compras</p>
                    </div>
                    <div class="card-body">
                        @if(isset($notasPendientes) && count($notasPendientes) > 0)
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>NVV</th>
                                            <th>Cliente</th>
                                            <th>Vendedor</th>
                                            <th>Total</th>
                                            <th>Fecha</th>
                                            <th>Estado</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($notasPendientes as $nota)
                                        <tr>
                                            <td>
                                                <strong>#{{ $nota->id ?? '' }}</strong>
                                            </td>
                                            <td>
                                                <div>
                                                    <strong>{{ $nota->cliente->codigo_cliente ?? '' }}</strong><br>
                                                    <small class="text-muted">{{ $nota->cliente->nombre ?? '' }}</small>
                                                </div>
                                            </td>
                                            <td>{{ $nota->user->name ?? '' }}</td>
                                            <td>
                                                <strong>${{ number_format($nota->total ?? 0, 0) }}</strong>
                                            </td>
                                            <td>
                                                <small>{{ $nota->created_at ? $nota->created_at->format('d/m/Y') : '' }}</small>
                                            </td>
                                            <td>
                                                @php
                                                    $estado = $nota->estado_aprobacion ?? 'pendiente';
                                                @endphp
                                                @if($estado == 'pendiente')
                                                    <span class="badge badge-warning">Pendiente</span>
                                                @elseif($estado == 'pendiente_picking')
                                                    <span class="badge badge-info">Picking</span>
                                                @elseif($estado == 'aprobada_supervisor')
                                                    <span class="badge badge-primary">Aprobada Sup.</span>
                                                @else
                                                    <span class="badge badge-secondary">{{ $estado }}</span>
                                                @endif
                                            </td>
                                            <td>
                                                <a href="{{ route('cotizacion.ver', $nota->id) }}" class="btn btn-info btn-sm">
                                                    <i class="material-icons">visibility</i> Ver
                                                </a>
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="text-center py-4">
                                <i class="material-icons text-muted" style="font-size: 64px;">check_circle</i>
                                <h4 class="text-muted">No hay NVV pendientes</h4>
                                <p class="text-muted">Todas las notas de venta han sido procesadas</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Productos con Bajo Stock -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header card-header-warning">
                        <h4 class="card-title">
                            <i class="material-icons">warning</i>
                            Productos Bajo Stock
                        </h4>
                        <p class="card-category">Requieren reposición urgente</p>
                    </div>
                    <div class="card-body">
                        @if(isset($productosBajoStock) && count($productosBajoStock) > 0)
                            <div class="list-group">
                                @foreach($productosBajoStock as $producto)
                                <div class="list-group-item">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1">{{ $producto['codigo'] ?? '' }}</h6>
                                        <small class="text-danger">Stock: {{ $producto['stock_actual'] ?? 0 }}</small>
                                    </div>
                                    <p class="mb-1">{{ $producto['nombre'] ?? '' }}</p>
                                    <small class="text-warning">
                                        <i class="material-icons" style="font-size: 16px;">warning</i>
                                        Stock bajo (mín. 5)
                                    </small>
                                </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-3">
                                <i class="material-icons text-success" style="font-size: 48px;">inventory</i>
                                <p class="text-muted mt-2">Todos los productos tienen stock suficiente</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Acciones Rápidas para Compras -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header card-header-info">
                        <h4 class="card-title">
                            <i class="material-icons">flash_on</i>
                            Acciones Rápidas
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <a href="{{ route('aprobaciones.index') }}" class="btn btn-success btn-block">
                                    <i class="material-icons">pending_actions</i> Aprobar NVV
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="{{ route('compras.index') }}" class="btn btn-info btn-block">
                                    <i class="material-icons">shopping_cart</i> Gestión Compras
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="#" class="btn btn-warning btn-block">
                                    <i class="material-icons">inventory_2</i> Control Stock
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="#" class="btn btn-primary btn-block">
                                    <i class="material-icons">assessment</i> Reportes
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif
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