@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-0 text-gray-800">
                        <i class="fas fa-boxes text-primary"></i>
                        Módulo de Picking
                    </h1>
                    <p class="text-muted">Preparación y gestión de pedidos</p>
                </div>
                <div>
                    <a href="{{ route('picking.pendientes') }}" class="btn btn-primary">
                        <i class="fas fa-list"></i> Ver Pedidos Pendientes
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Resumen del Día -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Pedidos Completados Hoy
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                {{ $resumenPicking['pedidos_completados'] }}
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-circle fa-2x text-gray-300"></i>
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
                                Pedidos en Preparación
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                {{ $residosEnPreparacion->count() }}
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clock fa-2x text-gray-300"></i>
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
                                Pedidos Pendientes
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                {{ $resumenPicking['pedidos_pendientes'] }}
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
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Eficiencia
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                {{ $resumenPicking['eficiencia'] }}%
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
        <!-- Pedidos Pendientes -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-list"></i>
                        Pedidos Pendientes
                    </h6>
                    <a href="{{ route('picking.pendientes') }}" class="btn btn-sm btn-primary">
                        Ver Todos
                    </a>
                </div>
                <div class="card-body">
                    @if($pedidosPendientes->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>NVV</th>
                                        <th>Cliente</th>
                                        <th>Fecha</th>
                                        <th>Total</th>
                                        <th>Prioridad</th>
                                        <th>Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($pedidosPendientes->take(5) as $pedido)
                                    <tr>
                                        <td>{{ $pedido['numero_nvv'] }}</td>
                                        <td>{{ Str::limit($pedido['cliente_nombre'], 20) }}</td>
                                        <td>{{ \Carbon\Carbon::parse($pedido['fecha'])->format('d/m/Y') }}</td>
                                        <td>${{ number_format($pedido['total'], 0, ',', '.') }}</td>
                                        <td>
                                            <span class="badge badge-{{ $pedido['prioridad'] === 'Alta' ? 'danger' : ($pedido['prioridad'] === 'Media' ? 'warning' : 'success') }}">
                                                {{ $pedido['prioridad'] }}
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-success" onclick="iniciarPreparacion('{{ $pedido['numero_nvv'] }}')">
                                                <i class="fas fa-play"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                            <p>¡Excelente! No hay pedidos pendientes.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Pedidos en Preparación -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-clock"></i>
                        Pedidos en Preparación
                    </h6>
                    <a href="{{ route('picking.en-preparacion') }}" class="btn btn-sm btn-primary">
                        Ver Todos
                    </a>
                </div>
                <div class="card-body">
                    @if($pedidosEnPreparacion->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>NVV</th>
                                        <th>Cliente</th>
                                        <th>Preparador</th>
                                        <th>Tiempo</th>
                                        <th>Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($pedidosEnPreparacion->take(5) as $pedido)
                                    <tr>
                                        <td>{{ $pedido['numero_nvv'] }}</td>
                                        <td>{{ Str::limit($pedido['cliente_nombre'], 20) }}</td>
                                        <td>{{ $pedido['preparador'] }}</td>
                                        <td>
                                            <span class="badge badge-info">
                                                {{ $pedido['tiempo_transcurrido'] }}
                                            </span>
                                        </td>
                                        <td>
                                            <a href="{{ route('picking.preparar', $pedido['numero_nvv']) }}" class="btn btn-sm btn-primary">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-clock fa-3x text-muted mb-3"></i>
                            <p>No hay pedidos en preparación.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Productos con Stock Insuficiente -->
    @if($productosStockInsuficiente->count() > 0)
    <div class="row">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                        Productos con Stock Insuficiente
                    </h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Código</th>
                                    <th>Producto</th>
                                    <th>Stock Actual</th>
                                    <th>Solicitado</th>
                                    <th>Diferencia</th>
                                    <th>NVV</th>
                                    <th>Cliente</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($productosStockInsuficiente->take(10) as $producto)
                                <tr>
                                    <td>{{ $producto['codigo_producto'] }}</td>
                                    <td>{{ Str::limit($producto['nombre_producto'], 30) }}</td>
                                    <td>
                                        <span class="badge badge-danger">{{ $producto['stock_actual'] }}</span>
                                    </td>
                                    <td>{{ $producto['cantidad_solicitada'] }}</td>
                                    <td>
                                        <span class="badge badge-warning">{{ $producto['diferencia'] }}</span>
                                    </td>
                                    <td>{{ $producto['numero_nvv'] }}</td>
                                    <td>{{ Str::limit($producto['cliente'], 20) }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

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
                            <a href="{{ route('picking.pendientes') }}" class="btn btn-warning btn-block">
                                <i class="fas fa-list"></i><br>
                                Pedidos Pendientes
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="{{ route('picking.en-preparacion') }}" class="btn btn-info btn-block">
                                <i class="fas fa-clock"></i><br>
                                En Preparación
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="{{ route('picking.historial') }}" class="btn btn-secondary btn-block">
                                <i class="fas fa-history"></i><br>
                                Historial
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

<!-- Modal para iniciar preparación -->
<div class="modal fade" id="modalIniciarPreparacion" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Iniciar Preparación</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="formIniciarPreparacion" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="form-group">
                        <label for="preparador">Preparador:</label>
                        <input type="text" class="form-control" id="preparador" name="preparador" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Iniciar Preparación</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function iniciarPreparacion(numeroNvv) {
    $('#formIniciarPreparacion').attr('action', '{{ route("picking.iniciar", ":numeroNvv") }}'.replace(':numeroNvv', numeroNvv));
    $('#modalIniciarPreparacion').modal('show');
}
</script>
@endsection
