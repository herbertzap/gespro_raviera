@extends('layouts.app')

@section('title', 'Cobranza')

@section('content')
<div class="content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Módulo de Cobranza</h4>
                        <p class="card-category">Gestión de cuentas por cobrar</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Filtros</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="{{ route('cobranza.index') }}">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Fecha Inicio</label>
                                        <input type="date" name="fecha_inicio" class="form-control" value="{{ request('fecha_inicio') }}">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Fecha Fin</label>
                                        <input type="date" name="fecha_fin" class="form-control" value="{{ request('fecha_fin') }}">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Vendedor</label>
                                        <input type="text" name="vendedor" class="form-control" value="{{ request('vendedor') }}" placeholder="Código vendedor">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Cliente</label>
                                        <input type="text" name="cliente" class="form-control" value="{{ request('cliente') }}" placeholder="Código cliente">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <button type="submit" class="btn btn-primary">Filtrar</button>
                                    <a href="{{ route('cobranza.index') }}" class="btn btn-secondary">Limpiar</a>
                                    <a href="{{ route('cobranza.export') }}?{{ http_build_query(request()->all()) }}" class="btn btn-success">Exportar Excel</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Resumen -->
        @if(isset($resumen))
        <div class="row">
            <div class="col-lg-3 col-md-6 col-sm-6">
                <div class="card card-stats">
                    <div class="card-header card-header-warning card-header-icon">
                        <div class="card-icon">
                            <i class="material-icons">receipt</i>
                        </div>
                        <p class="card-category">Total NVV</p>
                        <h3 class="card-title">{{ number_format($resumen['TOTAL_NVV']) }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 col-sm-6">
                <div class="card card-stats">
                    <div class="card-header card-header-danger card-header-icon">
                        <div class="card-icon">
                            <i class="material-icons">money_off</i>
                        </div>
                        <p class="card-category">Saldo Total</p>
                        <h3 class="card-title">${{ number_format($resumen['TOTAL_SALDO'], 2) }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 col-sm-6">
                <div class="card card-stats">
                    <div class="card-header card-header-danger card-header-icon">
                        <div class="card-icon">
                            <i class="material-icons">warning</i>
                        </div>
                        <p class="card-category">Saldo Vencido</p>
                        <h3 class="card-title">${{ number_format($resumen['SALDO_VENCIDO'], 2) }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 col-sm-6">
                <div class="card card-stats">
                    <div class="card-header card-header-success card-header-icon">
                        <div class="card-icon">
                            <i class="material-icons">check_circle</i>
                        </div>
                        <p class="card-category">Saldo Vigente</p>
                        <h3 class="card-title">${{ number_format($resumen['SALDO_VIGENTE'], 2) }}</h3>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- Tabla de Cobranza -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Notas de Venta Pendientes</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>NVV</th>
                                        <th>Fecha</th>
                                        <th>Cliente</th>
                                        <th>Vendedor</th>
                                        <th>Total</th>
                                        <th>Saldo</th>
                                        <th>Vencimiento</th>
                                        <th>Días Vencido</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($cobranza ?? [] as $item)
                                    <tr>
                                        <td>{{ $item['NUMERO_NVV'] }}</td>
                                        <td>{{ \Carbon\Carbon::parse($item['FECHA_NVV'])->format('d/m/Y') }}</td>
                                        <td>{{ $item['NOMBRE_CLIENTE'] }}</td>
                                        <td>{{ $item['NOMBRE_VENDEDOR'] }}</td>
                                        <td>${{ number_format($item['TOTAL_NVV'], 2) }}</td>
                                        <td>${{ number_format($item['SALDO_PENDIENTE'], 2) }}</td>
                                        <td>{{ \Carbon\Carbon::parse($item['FECHA_VENCIMIENTO'])->format('d/m/Y') }}</td>
                                        <td>
                                            @if($item['DIAS_VENCIDO'] > 0)
                                                <span class="badge badge-danger">{{ $item['DIAS_VENCIDO'] }} días</span>
                                            @else
                                                <span class="badge badge-success">Vigente</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($item['DIAS_VENCIDO'] > 30)
                                                <span class="badge badge-danger">Crítico</span>
                                            @elseif($item['DIAS_VENCIDO'] > 0)
                                                <span class="badge badge-warning">Vencido</span>
                                            @else
                                                <span class="badge badge-success">Vigente</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="9" class="text-center">No hay datos de cobranza</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Resumen por Vendedor -->
        @if(isset($porVendedor) && count($porVendedor) > 0)
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Resumen por Vendedor</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Vendedor</th>
                                        <th>Cantidad NVV</th>
                                        <th>Total Saldo</th>
                                        <th>Saldo Vencido</th>
                                        <th>% Vencido</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($porVendedor as $vendedor)
                                    <tr>
                                        <td>{{ $vendedor['NOMBRE_VENDEDOR'] }}</td>
                                        <td>{{ number_format($vendedor['CANTIDAD_NVV']) }}</td>
                                        <td>${{ number_format($vendedor['TOTAL_SALDO'], 2) }}</td>
                                        <td>${{ number_format($vendedor['SALDO_VENCIDO'], 2) }}</td>
                                        <td>
                                            @if($vendedor['TOTAL_SALDO'] > 0)
                                                {{ number_format(($vendedor['SALDO_VENCIDO'] / $vendedor['TOTAL_SALDO']) * 100, 1) }}%
                                            @else
                                                0%
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
        @endif
    </div>
</div>
@endsection 