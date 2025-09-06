@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-0 text-gray-800">
                        <i class="fas fa-shopping-cart text-primary"></i>
                        Módulo de Compras
                    </h1>
                    <p class="text-muted">Gestión de compras y control de stock</p>
                </div>
                <div>
                    <a href="{{ route('compras.crear') }}" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Nueva Orden de Compra
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Resumen de Compras -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Compras del Año
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                {{ number_format($resumenCompras['total_compras']) }}
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-shopping-cart fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Monto Total
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                ${{ number_format($resumenCompras['total_monto'], 0, ',', '.') }}
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Productos Bajo Stock
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                {{ $resumenCompras['productos_bajo_stock'] }}
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Promedio Mensual
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                {{ $resumenCompras['promedio_mensual'] }}
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Productos con Bajo Stock -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-exclamation-triangle"></i>
                        Productos con Bajo Stock
                    </h6>
                    <a href="{{ route('compras.productos-bajo-stock') }}" class="btn btn-sm btn-primary">
                        Ver Todos
                    </a>
                </div>
                <div class="card-body">
                    @if($productosBajoStock->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Código</th>
                                        <th>Producto</th>
                                        <th>Stock</th>
                                        <th>Mínimo</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($productosBajoStock->take(5) as $producto)
                                    <tr>
                                        <td>{{ $producto['codigo'] }}</td>
                                        <td>{{ Str::limit($producto['nombre'], 30) }}</td>
                                        <td>
                                            <span class="badge badge-{{ $producto['stock_actual'] <= 0 ? 'danger' : 'warning' }}">
                                                {{ $producto['stock_actual'] }}
                                            </span>
                                        </td>
                                        <td>{{ $producto['stock_minimo'] }}</td>
                                        <td>
                                            <span class="badge badge-{{ $producto['estado'] === 'Sin Stock' ? 'danger' : 'warning' }}">
                                                {{ $producto['estado'] }}
                                            </span>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                            <p>¡Excelente! No hay productos con bajo stock.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Compras Recientes -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-history"></i>
                        Compras Recientes
                    </h6>
                    <a href="{{ route('compras.historial') }}" class="btn btn-sm btn-primary">
                        Ver Historial
                    </a>
                </div>
                <div class="card-body">
                    @if($comprasRecientes->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Orden</th>
                                        <th>Proveedor</th>
                                        <th>Fecha</th>
                                        <th>Total</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($comprasRecientes->take(5) as $compra)
                                    <tr>
                                        <td>{{ $compra->NUMERO_ORDEN }}</td>
                                        <td>{{ Str::limit($compra->PROVEEDOR, 20) }}</td>
                                        <td>{{ \Carbon\Carbon::parse($compra->FECHA_COMPRA)->format('d/m/Y') }}</td>
                                        <td>${{ number_format($compra->TOTAL, 0, ',', '.') }}</td>
                                        <td>
                                            <span class="badge badge-{{ $this->getEstadoColor($compra->ESTADO) }}">
                                                {{ $compra->ESTADO }}
                                            </span>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                            <p>No hay compras registradas.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Acciones Rápidas -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-bolt"></i>
                        Acciones Rápidas
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <a href="{{ route('compras.crear') }}" class="btn btn-primary btn-block">
                                <i class="fas fa-plus"></i><br>
                                Nueva Orden de Compra
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="{{ route('compras.productos-bajo-stock') }}" class="btn btn-warning btn-block">
                                <i class="fas fa-exclamation-triangle"></i><br>
                                Productos Bajo Stock
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="{{ route('compras.historial') }}" class="btn btn-info btn-block">
                                <i class="fas fa-history"></i><br>
                                Historial de Compras
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="#" class="btn btn-success btn-block">
                                <i class="fas fa-chart-bar"></i><br>
                                Reportes
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@php
    function getEstadoColor($estado) {
        switch($estado) {
            case 'PENDIENTE': return 'warning';
            case 'APROBADA': return 'info';
            case 'RECIBIDA': return 'success';
            case 'CANCELADA': return 'danger';
            default: return 'secondary';
        }
    }
@endphp
@endsection
