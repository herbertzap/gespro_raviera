@extends('layouts.app', ['pageSlug' => 'manejo-stock-historial'])

@section('content')
<div class="content">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Historial de Capturas y Códigos de Barras</h4>
                </div>
                <div class="card-body">
                    <!-- Filtros de Búsqueda -->
                    <div class="mb-4">
                        <form method="GET" action="{{ route('manejo-stock.historial') }}" class="form-horizontal">
                            <div class="row">
                                <div class="col-md-3">
                                    <label class="form-control-label">Fecha Desde</label>
                                    <input type="date" name="fecha_desde" class="form-control" 
                                           value="{{ $filtros['fecha_desde'] ?? '' }}">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-control-label">Fecha Hasta</label>
                                    <input type="date" name="fecha_hasta" class="form-control" 
                                           value="{{ $filtros['fecha_hasta'] ?? '' }}">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-control-label">Usuario (Funcionario)</label>
                                    <select name="usuario" class="form-control">
                                        <option value="">Todos</option>
                                        @foreach($funcionarios as $func)
                                            <option value="{{ $func }}" {{ ($filtros['usuario'] ?? '') == $func ? 'selected' : '' }}>
                                                {{ $func }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-control-label">Tipo (GDI/GRI)</label>
                                    <select name="tipo" class="form-control">
                                        <option value="">Todos</option>
                                        <option value="GDI" {{ ($filtros['tipo'] ?? '') == 'GDI' ? 'selected' : '' }}>GDI</option>
                                        <option value="GRI" {{ ($filtros['tipo'] ?? '') == 'GRI' ? 'selected' : '' }}>GRI</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-md-3">
                                    <label class="form-control-label">Bodega</label>
                                    <select name="bodega_id" id="bodega_id" class="form-control">
                                        <option value="">Todas</option>
                                        @foreach($bodegas as $bodega)
                                            <option value="{{ $bodega->id }}" {{ ($filtros['bodega_id'] ?? '') == $bodega->id ? 'selected' : '' }}>
                                                {{ $bodega->nombre_bodega }} ({{ $bodega->kobo }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-control-label">Ubicación</label>
                                    <select name="ubicacion_id" id="ubicacion_id" class="form-control" {{ empty($filtros['bodega_id']) ? 'disabled' : '' }}>
                                        <option value="">Todas</option>
                                        @foreach($ubicaciones as $ubicacion)
                                            <option value="{{ $ubicacion->id }}" {{ ($filtros['ubicacion_id'] ?? '') == $ubicacion->id ? 'selected' : '' }}>
                                                {{ $ubicacion->codigo }} - {{ $ubicacion->descripcion }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-control-label">Buscar Código (SKU)</label>
                                    <input type="text" name="codigo_buscar" class="form-control" 
                                           placeholder="Ingrese SKU o código de producto"
                                           value="{{ $filtros['codigo_buscar'] ?? '' }}">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-control-label">&nbsp;</label>
                                    <div>
                                        <button type="submit" class="btn btn-primary btn-sm btn-block">
                                            <i class="tim-icons icon-zoom-split"></i> Buscar
                                        </button>
                                        <a href="{{ route('manejo-stock.historial') }}" class="btn btn-secondary btn-sm btn-block mt-2">
                                            <i class="tim-icons icon-refresh-02"></i> Limpiar
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Historial de Capturas de Stock -->
                    <div class="mb-5">
                        <h5 class="mb-3">
                            <i class="tim-icons icon-notes"></i> Historial de Capturas de Stock
                        </h5>
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered">
                                <thead class="thead-dark">
                                    <tr>
                                        <th>Fecha</th>
                                        <th>SKU</th>
                                        <th>Producto</th>
                                        <th>Bodega</th>
                                        <th>Ubicación</th>
                                        <th>Captura 1</th>
                                        <th>Captura 2</th>
                                        <th>STFI1</th>
                                        <th>STFI2</th>
                                        <th>TIDO</th>
                                        <th>Funcionario</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($capturas as $captura)
                                    <tr>
                                        <td>{{ $captura->created_at->format('d/m/Y H:i') }}</td>
                                        <td><strong>{{ $captura->sku }}</strong></td>
                                        <td>{{ $captura->nombre_producto }}</td>
                                        <td>
                                            {{ $captura->bodega->nombre_bodega ?? '-' }}
                                            @if($captura->bodega)
                                                <br><small class="text-muted">({{ $captura->bodega->kobo }})</small>
                                            @endif
                                        </td>
                                        <td>
                                            {{ $captura->codigo_ubicacion ?? '-' }}
                                            @if($captura->ubicacion)
                                                <br><small class="text-muted">{{ $captura->ubicacion->descripcion }}</small>
                                            @endif
                                        </td>
                                        <td>{{ number_format($captura->captura_1, 3) }}</td>
                                        <td>{{ $captura->captura_2 ? number_format($captura->captura_2, 3) : '-' }}</td>
                                        <td>
                                            @if($captura->stfi1 !== null)
                                                {{ number_format($captura->stfi1, 3) }}
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>
                                            @if($captura->stfi2 !== null)
                                                {{ number_format($captura->stfi2, 3) }}
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>
                                            @if($captura->tido)
                                                <span class="badge badge-{{ $captura->tido == 'GRI' ? 'success' : 'warning' }}">
                                                    {{ $captura->tido }}
                                                </span>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>{{ $captura->funcionario ?? '-' }}</td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="11" class="text-center text-muted">
                                            No hay capturas registradas
                                        </td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-3">
                            {{ $capturas->links() }}
                        </div>
                    </div>

                    <hr class="my-5">

                    <!-- Historial de Modificaciones de Códigos de Barras -->
                    <div>
                        <h5 class="mb-3">
                            <i class="tim-icons icon-barcode"></i> Historial de Modificaciones de Códigos de Barras
                        </h5>
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered">
                                <thead class="thead-dark">
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Código de Barras</th>
                                        <th>Código Anterior</th>
                                        <th>SKU Asociado</th>
                                        <th>Bodega</th>
                                        <th>Usuario</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($codigosBarras as $log)
                                    <tr>
                                        <td>{{ $log->created_at->format('d/m/Y H:i') }}</td>
                                        <td><strong>{{ $log->barcode }}</strong></td>
                                        <td>
                                            @if($log->barcode_anterior)
                                                <span class="text-muted"><del>{{ $log->barcode_anterior }}</del></span>
                                                <br><small class="badge badge-warning">Reemplazado</small>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td><strong>{{ $log->sku }}</strong></td>
                                        <td>
                                            {{ $log->bodega->nombre_bodega ?? '-' }}
                                            @if($log->bodega)
                                                <br><small class="text-muted">({{ $log->bodega->kobo }})</small>
                                            @endif
                                        </td>
                                        <td>{{ $log->user->name ?? '-' }}</td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">
                                            No hay modificaciones de códigos de barras registradas
                                        </td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-3">
                            {{ $codigosBarras->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    // Cargar ubicaciones cuando se selecciona una bodega
    document.getElementById('bodega_id').addEventListener('change', function() {
        const bodegaId = this.value;
        const ubicacionSelect = document.getElementById('ubicacion_id');
        
        if (bodegaId) {
            // Habilitar el select de ubicaciones
            ubicacionSelect.disabled = false;
            
            // Cargar ubicaciones vía AJAX usando la ruta existente
            fetch(`{{ route('manejo-stock.ubicaciones') }}?bodega_id=${bodegaId}`)
                .then(response => response.json())
                .then(data => {
                    ubicacionSelect.innerHTML = '<option value="">Todas</option>';
                    data.forEach(ubicacion => {
                        const option = document.createElement('option');
                        option.value = ubicacion.id;
                        option.textContent = `${ubicacion.codigo} - ${ubicacion.descripcion}`;
                        ubicacionSelect.appendChild(option);
                    });
                })
                .catch(error => {
                    console.error('Error cargando ubicaciones:', error);
                    ubicacionSelect.innerHTML = '<option value="">Todas</option>';
                });
        } else {
            // Deshabilitar y limpiar si no hay bodega seleccionada
            ubicacionSelect.disabled = true;
            ubicacionSelect.innerHTML = '<option value="">Todas</option>';
        }
    });
</script>
@endsection

