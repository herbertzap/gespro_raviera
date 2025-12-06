@extends('layouts.app', ['pageSlug' => 'manejo-stock-reporte'])

@section('content')
<div class="content">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Reporte de Documentos GESTPRO (GDI/GRI)</h4>
                    <p class="text-muted mb-0">Documentos con observación "Documento GESTPRO" desde SQL Server</p>
                </div>
                <div class="card-body">
                    <!-- Filtros -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Filtros</h5>
                        </div>
                        <div class="card-body">
                            <form method="GET" action="{{ route('manejo-stock.reporte') }}" id="formFiltros">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="fecha_desde">Fecha Desde</label>
                                            <input type="date" class="form-control" id="fecha_desde" name="fecha_desde" value="{{ $fechaDesde }}">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="fecha_hasta">Fecha Hasta</label>
                                            <input type="date" class="form-control" id="fecha_hasta" name="fecha_hasta" value="{{ $fechaHasta }}">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="usuario">Usuario (Funcionario)</label>
                                            <select class="form-control" id="usuario" name="usuario">
                                                <option value="">Todos</option>
                                                @foreach($usuarios as $usr)
                                                    <option value="{{ $usr->KOFUDO }}" {{ $usuario == $usr->KOFUDO ? 'selected' : '' }}>
                                                        {{ $usr->KOFUDO }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="bodega_codigo">Bodega</label>
                                            <select class="form-control" id="bodega_codigo" name="bodega_codigo">
                                                <option value="">Todas</option>
                                                @foreach($bodegas as $bodega)
                                                    <option value="{{ $bodega->kobo }}" {{ $bodegaCodigo == $bodega->kobo ? 'selected' : '' }}>
                                                        {{ $bodega->nombre_bodega }} ({{ $bodega->kobo }})
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-12">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="tim-icons icon-zoom-split"></i> Filtrar
                                        </button>
                                        <a href="{{ route('manejo-stock.reporte') }}" class="btn btn-secondary">
                                            <i class="tim-icons icon-refresh-02"></i> Limpiar Filtros
                                        </a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Resultados -->
                    <div class="table-responsive">
                        @if(count($documentos) > 0)
                            @foreach($documentos as $doc)
                                <div class="card mb-4">
                                    <div class="card-header bg-{{ $doc['tido'] == 'GRI' ? 'success' : 'warning' }}">
                                        <div class="row align-items-center">
                                            <div class="col-md-6">
                                                <h5 class="mb-0 text-white">
                                                    <span class="badge badge-light mr-2">{{ $doc['tido'] }}</span>
                                                    Documento: {{ $doc['nudo'] }}
                                                </h5>
                                            </div>
                                            <div class="col-md-6 text-right">
                                                <small class="text-white">
                                                    ID: {{ $doc['idmaeedo'] }} | 
                                                    Fecha: {{ \Carbon\Carbon::parse($doc['feemdo'])->format('d/m/Y H:i') }} |
                                                    Funcionario: {{ $doc['funcionario'] ?? '-' }}
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="row mb-3">
                                            <div class="col-md-3">
                                                <strong>ENDO:</strong> {{ $doc['endo'] ?? '-' }}
                                            </div>
                                            <div class="col-md-3">
                                                <strong>SUENDO:</strong> {{ $doc['suendo'] ?? '-' }}
                                            </div>
                                            <div class="col-md-3">
                                                <strong>Observación:</strong> {{ $doc['observacion'] ?? '-' }}
                                            </div>
                                        </div>
                                        <div class="row mb-3">
                                            <div class="col-md-3">
                                                <strong>VANEDO:</strong> {{ number_format($doc['vanedo'] ?? 0, 2) }}
                                            </div>
                                            <div class="col-md-3">
                                                <strong>VAIVDO:</strong> {{ number_format($doc['vaivdo'] ?? 0, 2) }}
                                            </div>
                                            <div class="col-md-3">
                                                <strong>VABRDO:</strong> {{ number_format($doc['vabrdo'] ?? 0, 2) }}
                                            </div>
                                            <div class="col-md-3">
                                                <strong>CAPRCO:</strong> {{ number_format($doc['caprco'] ?? 0, 2) }}
                                            </div>
                                        </div>
                                        
                                        <h6 class="mt-3 mb-2">Detalles de Productos:</h6>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-bordered table-striped">
                                                <thead class="thead-dark">
                                                    <tr>
                                                        <th>Código</th>
                                                        <th>Producto</th>
                                                        <th>Bodega</th>
                                                        <th>CAPRCO1</th>
                                                        <th>CAPRAD1</th>
                                                        <th>VANELI</th>
                                                        <th>VAIVLI</th>
                                                        <th>VABRLI</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($doc['detalles'] as $detalle)
                                                        <tr>
                                                            <td><strong>{{ $detalle['codigo_producto'] }}</strong></td>
                                                            <td>{{ $detalle['nombre_producto'] }}</td>
                                                            <td>
                                                                {{ $detalle['bodega_nombre'] ?? '-' }}
                                                                @if($detalle['bodega_codigo'])
                                                                    <br><small class="text-muted">({{ $detalle['bodega_codigo'] }})</small>
                                                                @endif
                                                            </td>
                                                            <td>{{ number_format($detalle['caprco1'] ?? 0, 2) }}</td>
                                                            <td>{{ number_format($detalle['caprad1'] ?? 0, 2) }}</td>
                                                            <td>{{ number_format($detalle['vaneli'] ?? 0, 2) }}</td>
                                                            <td>{{ number_format($detalle['vaivli'] ?? 0, 2) }}</td>
                                                            <td>{{ number_format($detalle['vabrli'] ?? 0, 2) }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        @else
                            <div class="alert alert-info">
                                <i class="tim-icons icon-info"></i>
                                No se encontraron documentos con los filtros seleccionados.
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection



