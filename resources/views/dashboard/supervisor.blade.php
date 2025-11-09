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
            <!-- 4. Cheques en Cartera (Informativo) -->
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
            <!-- 5. Cheques Protestados (Informativo) -->
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
            <!-- Notas de Venta Pendientes (col-6) -->
            <div class="col-md-6">
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
                                        <td>{{ $nota->numero_nota_venta ?? 'N/A' }}</td>
                                        <td>{{ $nota->user->name ?? 'N/A' }}</td>
                                        <td>{{ $nota->cliente->nombre ?? $nota->nombre_cliente ?? 'N/A' }}</td>
                                        <td>${{ number_format($nota->total ?? 0, 2) }}</td>
                                        <td>
                                            <span class="badge badge-{{ 
                                                $nota->estado_aprobacion == 'pendiente_supervisor' ? 'warning' : 
                                                ($nota->estado_aprobacion == 'pendiente_compras' ? 'info' : 'primary')
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
                                        <td colspan="6" class="text-center">No hay notas de venta pendientes</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Notas de Venta en Sistema SQL (col-6) -->
            <div class="col-md-6" id="notas-sql">
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
@endsection
