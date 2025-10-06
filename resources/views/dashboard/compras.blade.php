@extends('layouts.app')

@section('title', 'Dashboard Compras')

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
                        <h4 class="card-title">Dashboard - Compras</h4>
                        <p class="card-category">Bienvenido {{ auth()->user()->name }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Solo 3 Tarjetas Principales -->
        <div class="row">
            <!-- 1. Facturas Pendientes -->
            <div class="col-lg-4 col-md-6 col-sm-6">
                <div class="card card-stats">
                    <div class="card-header card-header-danger card-header-icon">
                        <div class="card-icon">
                            <i class="material-icons">receipt</i>
                        </div>
                        <p class="card-category">Facturas Pendientes</p>
                        <h3 class="card-title">{{ number_format($resumenCobranza['TOTAL_FACTURAS_PENDIENTES'] ?? 0) }}</h3>
                    </div>
                    <div class="card-footer">
                        <div class="stats">
                            <i class="material-icons text-danger">receipt</i>
                            <a href="{{ route('facturas-pendientes.index') }}" class="text-danger">Ver detalles</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. Total Notas de Venta en SQL -->
            <div class="col-lg-4 col-md-6 col-sm-6">
                <div class="card card-stats">
                    <div class="card-header card-header-info card-header-icon">
                        <div class="card-icon">
                            <i class="material-icons">storage</i>
                        </div>
                        <p class="card-category">NVV en Sistema</p>
                        <h3 class="card-title">{{ number_format($resumenCobranza['TOTAL_NOTAS_VENTA_SQL'] ?? 0) }}</h3>
                    </div>
                    <div class="card-footer">
                        <div class="stats">
                            <i class="material-icons text-info">storage</i>
                            <a href="#notas-sql" class="text-info">Ver en sistema</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. Notas Pendientes por Validar -->
            <div class="col-lg-4 col-md-6 col-sm-6">
                <div class="card card-stats">
                    <div class="card-header card-header-warning card-header-icon">
                        <div class="card-icon">
                            <i class="material-icons">pending_actions</i>
                        </div>
                        <p class="card-category">Por Validar</p>
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
        </div>

        <!-- Tablas de Información -->
        <div class="row">
            <!-- Notas de Venta Pendientes (col-6) -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header card-header-warning">
                        <h4 class="card-title">Notas de Venta Pendientes</h4>
                        <p class="card-category">Esperando aprobación por Compras</p>
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
                                    @forelse($nvvPendientes ?? [] as $nota)
                                    <tr>
                                        <td>{{ $nota['numero'] ?? 'N/A' }}</td>
                                        <td>{{ $nota['vendedor'] ?? 'N/A' }}</td>
                                        <td>{{ $nota['cliente_nombre'] ?? 'N/A' }}</td>
                                        <td>${{ number_format($nota['total'] ?? 0, 2) }}</td>
                                        <td>
                                            <span class="badge badge-{{ 
                                                $nota['estado'] == 'pendiente_supervisor' ? 'warning' : 
                                                ($nota['estado'] == 'aprobada_supervisor' ? 'info' : 'primary')
                                            }}">
                                                {{ ucfirst(str_replace('_', ' ', $nota['estado'] ?? 'pendiente')) }}
                                            </span>
                                        </td>
                                        <td>
                                            <a href="{{ $nota['url'] ?? '#' }}" class="btn btn-sm btn-primary">
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
                                    @forelse($nvvSistema ?? [] as $nota)
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
                        <h4 class="card-title">Facturas Pendientes</h4>
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
                            <!-- Botones específicos para Compras -->
                            <div class="col-md-3">
                                <a href="{{ route('aprobaciones.index') }}" class="btn btn-warning btn-block">
                                    <i class="material-icons">pending_actions</i> Notas Pendientes
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="{{ route('facturas-pendientes.index') }}" class="btn btn-danger btn-block">
                                    <i class="material-icons">receipt</i> Facturas Sistema
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="{{ route('productos.index') }}" class="btn btn-success btn-block">
                                    <i class="material-icons">inventory</i> Gestión Productos
                                </a>
                            </div>
                            <div class="col-md-3">
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
