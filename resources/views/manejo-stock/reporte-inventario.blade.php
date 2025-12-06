@extends('layouts.app', ['pageSlug' => 'manejo-stock-reporte-inventario'])

@section('content')
<div class="content">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="card-title">
                                <i class="tim-icons icon-chart-bar-32"></i> Reporte de Inventario (TINVENTARIO)
                            </h4>
                            <p class="text-muted mb-0">Datos capturados desde la Aplicación de Barrido</p>
                        </div>
                        <div>
                            <a href="{{ route('manejo-stock.select') }}" class="btn btn-sm btn-primary">
                                <i class="tim-icons icon-tablet-2"></i> Ir a Barrido
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Filtros -->
                    <div class="card mb-4 bg-dark">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="tim-icons icon-zoom-split"></i> Filtros</h5>
                        </div>
                        <div class="card-body">
                            <form method="GET" action="{{ route('manejo-stock.reporte-inventario') }}" id="formFiltros">
                                <div class="row">
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label for="fecha_desde">Fecha Desde</label>
                                            <input type="date" class="form-control" id="fecha_desde" name="fecha_desde" value="{{ $filtros['fecha_desde'] }}">
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label for="fecha_hasta">Fecha Hasta</label>
                                            <input type="date" class="form-control" id="fecha_hasta" name="fecha_hasta" value="{{ $filtros['fecha_hasta'] }}">
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label for="funcionario">Usuario</label>
                                            <select class="form-control" id="funcionario" name="funcionario">
                                                <option value="">Todos</option>
                                                @foreach($funcionarios as $func)
                                                    <option value="{{ $func }}" {{ $filtros['funcionario'] == $func ? 'selected' : '' }}>
                                                        {{ $func }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="bodega_id">Bodega</label>
                                            <select class="form-control" id="bodega_id" name="bodega_id">
                                                <option value="">Todas</option>
                                                @foreach($bodegas as $bodega)
                                                    <option value="{{ $bodega->id }}" {{ $filtros['bodega_id'] == $bodega->id ? 'selected' : '' }}>
                                                        {{ $bodega->nombre_bodega }} ({{ $bodega->kobo }})
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="tipo">Tipo de Reporte</label>
                                            <select class="form-control" id="tipo" name="tipo">
                                                <option value="detallado" {{ $tipoReporte == 'detallado' ? 'selected' : '' }}>
                                                    Detallado (sin consolidar)
                                                </option>
                                                <option value="consolidado" {{ $tipoReporte == 'consolidado' ? 'selected' : '' }}>
                                                    Consolidado (agrupado por SKU y Bodega)
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-12">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="tim-icons icon-zoom-split"></i> Filtrar
                                        </button>
                                        <a href="{{ route('manejo-stock.reporte-inventario') }}" class="btn btn-secondary">
                                            <i class="tim-icons icon-refresh-02"></i> Limpiar
                                        </a>
                                        <a href="{{ route('manejo-stock.exportar-inventario', request()->all()) }}" class="btn btn-success">
                                            <i class="tim-icons icon-cloud-download-93"></i> Exportar CSV
                                        </a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Resultados -->
                    <div class="table-responsive">
                        @if($tipoReporte === 'consolidado')
                            <!-- Tabla Consolidada -->
                            <table class="table table-striped">
                                <thead class="thead-dark">
                                    <tr>
                                        <th>SKU</th>
                                        <th>Producto</th>
                                        <th>Bodega</th>
                                        <th>Unidad</th>
                                        <th class="text-right">Cantidad Total</th>
                                        <th class="text-center">Registros</th>
                                        <th>Primera Fecha</th>
                                        <th>Última Fecha</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($registros as $r)
                                        <tr>
                                            <td><code>{{ $r->sku }}</code></td>
                                            <td>{{ Str::limit($r->nombre_producto, 40) }}</td>
                                            <td>{{ $r->kobo }}</td>
                                            <td>{{ $r->unidad_medida_1 }}</td>
                                            <td class="text-right"><strong>{{ number_format($r->cantidad_total, 2) }}</strong></td>
                                            <td class="text-center">
                                                <span class="badge badge-info">{{ $r->total_registros }}</span>
                                            </td>
                                            <td>{{ $r->primera_fecha ? \Carbon\Carbon::parse($r->primera_fecha)->format('d/m/Y') : '-' }}</td>
                                            <td>{{ $r->ultima_fecha ? \Carbon\Carbon::parse($r->ultima_fecha)->format('d/m/Y') : '-' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="text-center text-muted py-4">
                                                <i class="tim-icons icon-zoom-split" style="font-size: 2rem;"></i>
                                                <p class="mt-2">No se encontraron registros con los filtros seleccionados.</p>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                                @if(count($registros) > 0)
                                <tfoot>
                                    <tr class="bg-dark">
                                        <td colspan="4" class="text-right"><strong>TOTALES:</strong></td>
                                        <td class="text-right"><strong>{{ number_format($registros->sum('cantidad_total'), 2) }}</strong></td>
                                        <td class="text-center"><strong>{{ $registros->sum('total_registros') }}</strong></td>
                                        <td colspan="2"></td>
                                    </tr>
                                </tfoot>
                                @endif
                            </table>
                        @else
                            <!-- Tabla Detallada -->
                            <table class="table table-striped">
                                <thead class="thead-dark">
                                    <tr>
                                        <th>Fecha</th>
                                        <th>SKU</th>
                                        <th>Producto</th>
                                        <th>Bodega</th>
                                        <th>Ubicación</th>
                                        <th class="text-right">Cantidad</th>
                                        <th>Unidad</th>
                                        <th>Funcionario</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($registros as $r)
                                        <tr>
                                            <td>{{ $r->fecha_barrido ? \Carbon\Carbon::parse($r->fecha_barrido)->format('d/m/Y') : '-' }}</td>
                                            <td><code>{{ $r->sku }}</code></td>
                                            <td>{{ Str::limit($r->nombre_producto, 40) }}</td>
                                            <td>{{ $r->kobo }}</td>
                                            <td>{{ $r->codigo_ubicacion ?? '-' }}</td>
                                            <td class="text-right"><strong>{{ number_format($r->cantidad, 2) }}</strong></td>
                                            <td>{{ $r->unidad_medida_1 ?? '-' }}</td>
                                            <td>{{ $r->funcionario ?? '-' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="text-center text-muted py-4">
                                                <i class="tim-icons icon-zoom-split" style="font-size: 2rem;"></i>
                                                <p class="mt-2">No se encontraron registros con los filtros seleccionados.</p>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>

                            <!-- Total de registros -->
                            @if(count($registros) > 0)
                                <div class="mt-3 text-muted">
                                    Total: {{ count($registros) }} registros
                                </div>
                            @endif
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection


