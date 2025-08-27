@extends('layouts.app')

@section('title', 'Detalle NVV - ' . $nvv['numero'])

@section('content')
<div class="content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h4 class="card-title">Detalle NVV - {{ $nvv['numero'] }}</h4>
                                <p class="card-category">Nota de Venta detallada</p>
                            </div>
                            <div>
                                <a href="{{ route('nvv-pendientes.index') }}" class="btn btn-secondary">
                                    <i class="material-icons">arrow_back</i> Volver
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Información General de la NVV -->
        <div class="row">
            <div class="col-lg-3 col-md-6 col-sm-6">
                <div class="card card-stats">
                    <div class="card-header card-header-info card-header-icon">
                        <div class="card-icon">
                            <i class="material-icons">inventory</i>
                        </div>
                        <p class="card-category">Total Productos</p>
                        <h3 class="card-title">{{ $nvv['total_productos'] }}</h3>
                    </div>
                    <div class="card-footer">
                        <div class="stats">
                            <i class="material-icons text-info">category</i>
                            Productos diferentes
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 col-sm-6">
                <div class="card card-stats">
                    <div class="card-header card-header-success card-header-icon">
                        <div class="card-icon">
                            <i class="material-icons">check_circle</i>
                        </div>
                        <p class="card-category">Facturado</p>
                        <h3 class="card-title">{{ number_format($nvv['total_facturado'], 0) }}</h3>
                    </div>
                    <div class="card-footer">
                        <div class="stats">
                            <i class="material-icons text-success">receipt</i>
                            Unidades facturadas
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 col-sm-6">
                <div class="card card-stats">
                    <div class="card-header card-header-warning card-header-icon">
                        <div class="card-icon">
                            <i class="material-icons">pending_actions</i>
                        </div>
                        <p class="card-category">Pendiente</p>
                        <h3 class="card-title">{{ number_format($nvv['total_pendiente'], 0) }}</h3>
                    </div>
                    <div class="card-footer">
                        <div class="stats">
                            <i class="material-icons text-warning">schedule</i>
                            Unidades pendientes
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 col-sm-6">
                <div class="card card-stats">
                    <div class="card-header card-header-danger card-header-icon">
                        <div class="card-icon">
                            <i class="material-icons">attach_money</i>
                        </div>
                        <p class="card-category">Valor Pendiente</p>
                        <h3 class="card-title">${{ number_format($nvv['total_valor_pendiente'], 0) }}</h3>
                    </div>
                    <div class="card-footer">
                        <div class="stats">
                            <i class="material-icons text-danger">monetization_on</i>
                            Valor por facturar
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Información del Cliente y NVV -->
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Información del Cliente</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Código:</strong> {{ $nvv['cliente_codigo'] }}</p>
                                <p><strong>Nombre:</strong> {{ $nvv['cliente_nombre'] }}</p>
                                <p><strong>Región:</strong> {{ $nvv['region'] }}</p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Comuna:</strong> {{ $nvv['comuna'] }}</p>
                                <p><strong>Vendedor:</strong> {{ $nvv['vendedor_nombre'] }}</p>
                                <p><strong>Días desde emisión:</strong> {{ $nvv['dias'] }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Información de la NVV</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Número NVV:</strong> {{ $nvv['numero'] }}</p>
                                <p><strong>Fecha de emisión:</strong> {{ $nvv['fecha'] }}</p>
                                <p><strong>Total valor:</strong> ${{ number_format($nvv['total_valor'], 0) }}</p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Total cantidad:</strong> {{ number_format($nvv['total_cantidad'], 0) }}</p>
                                <p><strong>Estado:</strong> 
                                    @if($nvv['total_pendiente'] == 0)
                                        <span class="badge badge-success">Completamente Facturada</span>
                                    @elseif($nvv['total_facturado'] > 0)
                                        <span class="badge badge-warning">Parcialmente Facturada</span>
                                    @else
                                        <span class="badge badge-danger">Pendiente de Facturación</span>
                                    @endif
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabla de Productos -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Productos de la NVV</h4>
                        <p class="card-category">Detalle de productos y cantidades</p>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead class="text-primary">
                                    <tr>
                                        <th>Código Producto</th>
                                        <th>Nombre Producto</th>
                                        <th>Cantidad Total</th>
                                        <th>Facturado</th>
                                        <th>Pendiente</th>
                                        <th>Precio Unitario</th>
                                        <th>Valor Total</th>
                                        <th>Valor Pendiente</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($productos as $producto)
                                    <tr>
                                        <td>{{ $producto['CODIGO_PRODUCTO'] }}</td>
                                        <td>{{ $producto['NOMBRE_PRODUCTO'] }}</td>
                                        <td>{{ number_format($producto['CANTIDAD_TOTAL'], 0) }}</td>
                                        <td>
                                            <span class="badge badge-success">
                                                {{ number_format($producto['CANTIDAD_FACTURADA'], 0) }}
                                            </span>
                                        </td>
                                        <td>
                                            @if($producto['CANTIDAD_PENDIENTE'] > 0)
                                                <span class="badge badge-warning">
                                                    {{ number_format($producto['CANTIDAD_PENDIENTE'], 0) }}
                                                </span>
                                            @else
                                                <span class="badge badge-success">0</span>
                                            @endif
                                        </td>
                                        <td>${{ number_format($producto['PRECIO_UNITARIO'], 0) }}</td>
                                        <td>${{ number_format($producto['VALOR_TOTAL'], 0) }}</td>
                                        <td>
                                            @if($producto['VALOR_PENDIENTE'] > 0)
                                                <span class="text-warning">
                                                    ${{ number_format($producto['VALOR_PENDIENTE'], 0) }}
                                                </span>
                                            @else
                                                <span class="text-success">$0</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($producto['CANTIDAD_PENDIENTE'] == 0)
                                                <span class="badge badge-success">
                                                    <i class="material-icons" style="font-size: 12px;">check_circle</i>
                                                    Facturado
                                                </span>
                                            @elseif($producto['CANTIDAD_FACTURADA'] > 0)
                                                <span class="badge badge-warning">
                                                    <i class="material-icons" style="font-size: 12px;">pending</i>
                                                    Parcial
                                                </span>
                                            @else
                                                <span class="badge badge-danger">
                                                    <i class="material-icons" style="font-size: 12px;">schedule</i>
                                                    Pendiente
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
