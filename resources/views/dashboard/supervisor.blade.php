@extends('layouts.app')

@section('title', 'Dashboard Supervisor')

@section('content')
@php
    $pageSlug = 'dashboard';
@endphp
<div class="content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Dashboard - Supervisor</h4>
                        <p class="card-category">Bienvenido {{ auth()->user()->name }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Primera fila: Cards principales -->
        <div class="row">
            <!-- 1. NVV Por Validar -->
            <div class="col-lg-2 col-md-4 col-sm-6">
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
            <div class="col-lg-2 col-md-4 col-sm-6">
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
            <div class="col-lg-2 col-md-4 col-sm-6">
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
            <div class="col-lg-2 col-md-4 col-sm-6">
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

            <!-- 5. Cheques en Cartera (Informativo) -->
            <div class="col-lg-2 col-md-4 col-sm-6">
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
            <!-- 6. Cheques Protestados (Informativo) -->
            <div class="col-lg-2 col-md-4 col-sm-6">
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
        </div>

        <!-- Tablas de Información -->
        <div class="row">
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
                        <div class="card-footer text-right">
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
                                        <th>Productos</th>
                                        <th>Valor Total</th>
                                        <th>Fecha</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($notasVentaSQL ?? [] as $nota)
                                    <tr>
                                        <td><span class="badge badge-info">{{ $nota['TIPO_DOCTO'] }}</span></td>
                                        <td>{{ $nota['NRO_DOCTO'] }}</td>
                                        <td>{{ $nota['CLIENTE'] }}</td>
                                        <td>{{ $nota['VENDEDOR'] }}</td>
                                        <td><span class="badge badge-primary">{{ $nota['CANTIDAD_TOTAL'] ?? 0 }}</span></td>
                                        <td>${{ number_format($nota['VALOR_PENDIENTE'] ?? 0, 0) }}</td>
                                        <td>{{ \Carbon\Carbon::parse($nota['FECHA_EMISION'])->format('d/m/Y') }}</td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="7" class="text-center">No hay notas de venta en sistema</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="card-footer text-right">
                            <a href="{{ route('nvv-pendientes.index') }}" class="btn btn-info btn-lg">
                                <i class="material-icons">visibility</i> Ver Todas
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Facturas Pendientes (col-12) -->
        <div class="row">
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
                                        <td><span class="badge badge-danger">{{ $factura['TIPO_DOCTO'] }}</span></td>
                                        <td>{{ $factura['NRO_DOCTO'] }}</td>
                                        <td>{{ $factura['CLIENTE'] }}</td>
                                        <td>{{ $factura['VENDEDOR'] }}</td>
                                        <td>${{ number_format($factura['VALOR'], 0) }}</td>
                                        <td>${{ number_format($factura['ABONOS'], 0) }}</td>
                                        <td>
                                            <span class="badge badge-{{ $factura['SALDO'] > 0 ? 'warning' : 'success' }}">
                                                ${{ number_format($factura['SALDO'], 0) }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-{{ 
                                                $factura['DIAS'] < 8 ? 'success' : 
                                                ($factura['DIAS'] < 31 ? 'warning' : 
                                                ($factura['DIAS'] < 61 ? 'danger' : 'dark')) 
                                            }}">
                                                {{ $factura['DIAS'] }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-{{ 
                                                $factura['ESTADO'] == 'VIGENTE' ? 'success' : 
                                                ($factura['ESTADO'] == 'POR VENCER' ? 'warning' : 
                                                ($factura['ESTADO'] == 'VENCIDO' ? 'danger' : 'dark')) 
                                            }}">
                                                {{ $factura['ESTADO'] }}
                                            </span>
                                        </td>
                                        <td>
                                            <a href="{{ route('facturas-pendientes.ver', [$factura['TIPO_DOCTO'], $factura['NRO_DOCTO']]) }}" class="btn btn-sm btn-primary">
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
        </div>

        <!-- Cheques Asociados al Cliente (col-12) -->
        <div class="row">
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
                                                <th>Acción</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tbodyChequesCartera">
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
                                                        @php
                                                            try {
                                                                $fecha = \Carbon\Carbon::parse($cheque['fecha_vencimiento']);
                                                                echo $fecha->format('d/m/Y');
                                                            } catch (\Exception $e) {
                                                                echo $cheque['fecha_vencimiento'];
                                                            }
                                                        @endphp
                                                    @else
                                                        N/A
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($cheque['codigo_cliente'])
                                                        <a href="{{ route('cliente.show', $cheque['codigo_cliente']) }}" class="btn btn-sm btn-primary" title="Ver perfil del cliente">
                                                            <i class="material-icons">person</i> Ver Cliente
                                                        </a>
                                                    @endif
                                                </td>
                                            </tr>
                                            @empty
                                            <tr>
                                                <td colspan="6" class="text-center">No hay cheques en cartera</td>
                                            </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                                <div id="paginationCarteraContainer">
                                @if(isset($chequesEnCarteraPagination) && $chequesEnCarteraPagination && isset($chequesEnCarteraPagination['total']) && $chequesEnCarteraPagination['total'] > 10)
                                <div class="d-flex justify-content-between align-items-center mt-3">
                                    <div>
                                        <small class="text-muted">
                                            Mostrando <span id="mostrandoCartera">{{ count($chequesEnCarteraDetalle) }}</span> de <span id="totalCartera">{{ $chequesEnCarteraPagination['total'] }}</span> cheques
                                        </small>
                                    </div>
                                    <nav>
                                        <ul class="pagination pagination-sm mb-0" id="paginationCartera">
                                            @if($chequesEnCarteraPagination['current_page'] > 1)
                                            <li class="page-item">
                                                <a class="page-link pagination-link-cartera" href="#" data-page="{{ $chequesEnCarteraPagination['current_page'] - 1 }}">
                                                    <i class="material-icons" style="font-size: 18px; vertical-align: middle;">chevron_left</i>
                                                </a>
                                            </li>
                                            @endif
                                            @for($i = max(1, $chequesEnCarteraPagination['current_page'] - 2); $i <= min($chequesEnCarteraPagination['last_page'], $chequesEnCarteraPagination['current_page'] + 2); $i++)
                                                @if($i == $chequesEnCarteraPagination['current_page'])
                                                <li class="page-item active">
                                                    <span class="page-link">{{ $i }}</span>
                                                </li>
                                                @else
                                                <li class="page-item">
                                                    <a class="page-link pagination-link-cartera" href="#" data-page="{{ $i }}">{{ $i }}</a>
                                                </li>
                                                @endif
                                            @endfor
                                            @if($chequesEnCarteraPagination['current_page'] < $chequesEnCarteraPagination['last_page'])
                                            <li class="page-item">
                                                <a class="page-link pagination-link-cartera" href="#" data-page="{{ $chequesEnCarteraPagination['current_page'] + 1 }}">
                                                    <i class="material-icons" style="font-size: 18px; vertical-align: middle;">chevron_right</i>
                                                </a>
                                            </li>
                                            @endif
                                        </ul>
                                    </nav>
                                </div>
                                @endif
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
                                                <th>Acción</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tbodyChequesProtestados">
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
                                                        @php
                                                            try {
                                                                $fecha = \Carbon\Carbon::parse($cheque['fecha_vencimiento']);
                                                                echo $fecha->format('d/m/Y');
                                                            } catch (\Exception $e) {
                                                                echo $cheque['fecha_vencimiento'];
                                                            }
                                                        @endphp
                                                    @else
                                                        N/A
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($cheque['codigo_cliente'])
                                                        <a href="{{ route('cliente.show', $cheque['codigo_cliente']) }}" class="btn btn-sm btn-primary" title="Ver perfil del cliente">
                                                            <i class="material-icons">person</i> Ver Cliente
                                                        </a>
                                                    @endif
                                                </td>
                                            </tr>
                                            @empty
                                            <tr>
                                                <td colspan="6" class="text-center">No hay cheques protestados</td>
                                            </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                                <div id="paginationProtestadosContainer">
                                @if(isset($chequesProtestadosPagination) && $chequesProtestadosPagination['last_page'] > 1)
                                <div class="d-flex justify-content-between align-items-center mt-3">
                                    <div>
                                        <small class="text-muted">
                                            Mostrando <span id="mostrandoProtestados">{{ count($chequesProtestadosDetalle) }}</span> de <span id="totalProtestados">{{ $chequesProtestadosPagination['total'] }}</span> cheques
                                        </small>
                                    </div>
                                    <nav>
                                        <ul class="pagination pagination-sm mb-0" id="paginationProtestados">
                                            @if($chequesProtestadosPagination['current_page'] > 1)
                                            <li class="page-item">
                                                <a class="page-link pagination-link-protestados" href="#" data-page="{{ $chequesProtestadosPagination['current_page'] - 1 }}">
                                                    <i class="material-icons" style="font-size: 18px; vertical-align: middle;">chevron_left</i>
                                                </a>
                                            </li>
                                            @endif
                                            @for($i = max(1, $chequesProtestadosPagination['current_page'] - 2); $i <= min($chequesProtestadosPagination['last_page'], $chequesProtestadosPagination['current_page'] + 2); $i++)
                                                @if($i == $chequesProtestadosPagination['current_page'])
                                                <li class="page-item active">
                                                    <span class="page-link">{{ $i }}</span>
                                                </li>
                                                @else
                                                <li class="page-item">
                                                    <a class="page-link pagination-link-protestados" href="#" data-page="{{ $i }}">{{ $i }}</a>
                                                </li>
                                                @endif
                                            @endfor
                                            @if($chequesProtestadosPagination['current_page'] < $chequesProtestadosPagination['last_page'])
                                            <li class="page-item">
                                                <a class="page-link pagination-link-protestados" href="#" data-page="{{ $chequesProtestadosPagination['current_page'] + 1 }}">
                                                    <i class="material-icons" style="font-size: 18px; vertical-align: middle;">chevron_right</i>
                                                </a>
                                            </li>
                                            @endif
                                        </ul>
                                    </nav>
                                </div>
                                @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
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
                            <!-- Solo 3 botones -->
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
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('js')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Variables para mantener el estado de la paginación
    let currentPageCartera = {{ $chequesEnCarteraPagination['current_page'] ?? 1 }};
    let currentPageProtestados = {{ $chequesProtestadosPagination['current_page'] ?? 1 }};
    
    // Función para formatear fecha
    function formatearFecha(fecha) {
        if (!fecha) return 'N/A';
        try {
            const date = new Date(fecha);
            if (isNaN(date.getTime())) return fecha;
            const day = String(date.getDate()).padStart(2, '0');
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const year = date.getFullYear();
            return `${day}/${month}/${year}`;
        } catch (e) {
            return fecha;
        }
    }
    
    // Función para renderizar cheques en cartera
    function renderizarChequesCartera(cheques, pagination) {
        const tbody = document.getElementById('tbodyChequesCartera');
        if (!tbody) return;
        
        if (!cheques || cheques.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center">No hay cheques en cartera</td></tr>';
            return;
        }
        
        let html = '';
        cheques.forEach(cheque => {
            html += `
                <tr>
                    <td><strong>${cheque.numero || ''}</strong></td>
                    <td>
                        <strong>${cheque.codigo_cliente || ''}</strong>
                        ${cheque.cliente ? `<br><small class="text-muted">${cheque.cliente}</small>` : ''}
                    </td>
                    <td>${cheque.vendedor || ''}</td>
                    <td>$${new Intl.NumberFormat('es-CL').format(cheque.valor || 0)}</td>
                    <td>${formatearFecha(cheque.fecha_vencimiento)}</td>
                    <td>
                        ${cheque.codigo_cliente ? `
                            <a href="/cliente/${cheque.codigo_cliente}" class="btn btn-sm btn-primary" title="Ver perfil del cliente">
                                <i class="material-icons">person</i> Ver Cliente
                            </a>
                        ` : ''}
                    </td>
                </tr>
            `;
        });
        tbody.innerHTML = html;
        
        // Actualizar paginación (mostrar si hay más de 10 cheques)
        if (pagination && pagination.total > 10) {
            const container = document.getElementById('paginationCarteraContainer');
            if (container) {
                let pagHtml = `
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <div>
                            <small class="text-muted">
                                Mostrando <span id="mostrandoCartera">${cheques.length}</span> de <span id="totalCartera">${pagination.total}</span> cheques
                            </small>
                        </div>
                        <nav>
                            <ul class="pagination pagination-sm mb-0" id="paginationCartera">
                `;
                
                if (pagination.current_page > 1) {
                    pagHtml += `
                        <li class="page-item">
                            <a class="page-link pagination-link-cartera" href="#" data-page="${pagination.current_page - 1}">
                                <i class="material-icons" style="font-size: 18px; vertical-align: middle;">chevron_left</i>
                            </a>
                        </li>
                    `;
                }
                
                const startPage = Math.max(1, pagination.current_page - 2);
                const endPage = Math.min(pagination.last_page, pagination.current_page + 2);
                
                for (let i = startPage; i <= endPage; i++) {
                    if (i === pagination.current_page) {
                        pagHtml += `<li class="page-item active"><span class="page-link">${i}</span></li>`;
                    } else {
                        pagHtml += `<li class="page-item"><a class="page-link pagination-link-cartera" href="#" data-page="${i}">${i}</a></li>`;
                    }
                }
                
                if (pagination.current_page < pagination.last_page) {
                    pagHtml += `
                        <li class="page-item">
                            <a class="page-link pagination-link-cartera" href="#" data-page="${pagination.current_page + 1}">
                                <i class="material-icons" style="font-size: 18px; vertical-align: middle;">chevron_right</i>
                            </a>
                        </li>
                    `;
                }
                
                pagHtml += `
                            </ul>
                        </nav>
                    </div>
                `;
                container.innerHTML = pagHtml;
                
                // Re-agregar event listeners
                attachCarteraListeners();
            } else {
                // Si no hay más de 10 cheques, ocultar paginación
                const container = document.getElementById('paginationCarteraContainer');
                if (container) {
                    container.innerHTML = '';
                }
            }
        }
    }
    
    // Función para renderizar cheques protestados
    function renderizarChequesProtestados(cheques, pagination) {
        const tbody = document.getElementById('tbodyChequesProtestados');
        if (!tbody) return;
        
        if (!cheques || cheques.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center">No hay cheques protestados</td></tr>';
            return;
        }
        
        let html = '';
        cheques.forEach(cheque => {
            html += `
                <tr>
                    <td><strong>${cheque.numero || ''}</strong></td>
                    <td>
                        <strong>${cheque.codigo_cliente || ''}</strong>
                        ${cheque.cliente ? `<br><small class="text-muted">${cheque.cliente}</small>` : ''}
                    </td>
                    <td>${cheque.vendedor || ''}</td>
                    <td>$${new Intl.NumberFormat('es-CL').format(cheque.valor || 0)}</td>
                    <td>${formatearFecha(cheque.fecha_vencimiento)}</td>
                    <td>
                        ${cheque.codigo_cliente ? `
                            <a href="/cliente/${cheque.codigo_cliente}" class="btn btn-sm btn-primary" title="Ver perfil del cliente">
                                <i class="material-icons">person</i> Ver Cliente
                            </a>
                        ` : ''}
                    </td>
                </tr>
            `;
        });
        tbody.innerHTML = html;
        
        // Actualizar paginación (mostrar si hay más de 10 cheques)
        if (pagination && pagination.total > 10) {
            const container = document.getElementById('paginationProtestadosContainer');
            if (container) {
                let pagHtml = `
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <div>
                            <small class="text-muted">
                                Mostrando <span id="mostrandoProtestados">${cheques.length}</span> de <span id="totalProtestados">${pagination.total}</span> cheques
                            </small>
                        </div>
                        <nav>
                            <ul class="pagination pagination-sm mb-0" id="paginationProtestados">
                `;
                
                if (pagination.current_page > 1) {
                    pagHtml += `
                        <li class="page-item">
                            <a class="page-link pagination-link-protestados" href="#" data-page="${pagination.current_page - 1}">
                                <i class="material-icons" style="font-size: 18px; vertical-align: middle;">chevron_left</i>
                            </a>
                        </li>
                    `;
                }
                
                const startPage = Math.max(1, pagination.current_page - 2);
                const endPage = Math.min(pagination.last_page, pagination.current_page + 2);
                
                for (let i = startPage; i <= endPage; i++) {
                    if (i === pagination.current_page) {
                        pagHtml += `<li class="page-item active"><span class="page-link">${i}</span></li>`;
                    } else {
                        pagHtml += `<li class="page-item"><a class="page-link pagination-link-protestados" href="#" data-page="${i}">${i}</a></li>`;
                    }
                }
                
                if (pagination.current_page < pagination.last_page) {
                    pagHtml += `
                        <li class="page-item">
                            <a class="page-link pagination-link-protestados" href="#" data-page="${pagination.current_page + 1}">
                                <i class="material-icons" style="font-size: 18px; vertical-align: middle;">chevron_right</i>
                            </a>
                        </li>
                    `;
                }
                
                pagHtml += `
                            </ul>
                        </nav>
                    </div>
                `;
                container.innerHTML = pagHtml;
                
                // Re-agregar event listeners
                attachProtestadosListeners();
            } else {
                // Si no hay más de 10 cheques, ocultar paginación
                const container = document.getElementById('paginationProtestadosContainer');
                if (container) {
                    container.innerHTML = '';
                }
            }
        }
    }
    
    // Función para cargar cheques en cartera vía AJAX
    function cargarChequesCartera(page) {
        const tbody = document.getElementById('tbodyChequesCartera');
        if (tbody) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center"><i class="material-icons">hourglass_empty</i> Cargando...</td></tr>';
        }
        
        fetch(`{{ route('dashboard.cheques') }}?tipo=cartera&page=${page}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    currentPageCartera = page;
                    renderizarChequesCartera(data.cheques, data.pagination);
                } else {
                    if (tbody) {
                        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Error al cargar cheques</td></tr>';
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                if (tbody) {
                    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Error al cargar cheques</td></tr>';
                }
            });
    }
    
    // Función para cargar cheques protestados vía AJAX
    function cargarChequesProtestados(page) {
        const tbody = document.getElementById('tbodyChequesProtestados');
        if (tbody) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center"><i class="material-icons">hourglass_empty</i> Cargando...</td></tr>';
        }
        
        fetch(`{{ route('dashboard.cheques') }}?tipo=protestados&page=${page}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    currentPageProtestados = page;
                    renderizarChequesProtestados(data.cheques, data.pagination);
                } else {
                    if (tbody) {
                        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Error al cargar cheques</td></tr>';
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                if (tbody) {
                    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Error al cargar cheques</td></tr>';
                }
            });
    }
    
    // Función para agregar listeners a los enlaces de paginación de cartera
    function attachCarteraListeners() {
        document.querySelectorAll('.pagination-link-cartera').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const page = parseInt(this.getAttribute('data-page'));
                if (page && page !== currentPageCartera) {
                    cargarChequesCartera(page);
                }
            });
        });
    }
    
    // Función para agregar listeners a los enlaces de paginación de protestados
    function attachProtestadosListeners() {
        document.querySelectorAll('.pagination-link-protestados').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const page = parseInt(this.getAttribute('data-page'));
                if (page && page !== currentPageProtestados) {
                    cargarChequesProtestados(page);
                }
            });
        });
    }
    
    // Inicializar listeners
    attachCarteraListeners();
    attachProtestadosListeners();
});
</script>
@endpush
@endsection
