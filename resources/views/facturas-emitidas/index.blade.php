@extends('layouts.app', ['pageSlug' => 'facturas-emitidas'])

@section('title', 'Informe de Facturas Emitidas')

@section('content')
    <div class="container-fluid">
        <!-- Header -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Informe de Facturas Emitidas</h4>
                        <p class="card-category">Todas las facturas emitidas por el vendedor</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <form method="GET" action="{{ route('facturas-emitidas.index') }}" class="mb-4">
                            <div class="row">
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="buscar">Buscar:</label>
                                        <input type="text" name="buscar" id="buscar" class="form-control" 
                                               value="{{ $filtros['buscar'] }}" placeholder="N° factura, cliente...">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="estado">Estado:</label>
                                        <select name="estado" id="estado" class="form-control">
                                            <option value="">Todos</option>
                                            <option value="VIGENTE" {{ $filtros['estado'] == 'VIGENTE' ? 'selected' : '' }}>Vigente</option>
                                            <option value="POR VENCER" {{ $filtros['estado'] == 'POR VENCER' ? 'selected' : '' }}>Por Vencer</option>
                                            <option value="VENCIDO" {{ $filtros['estado'] == 'VENCIDO' ? 'selected' : '' }}>Vencido</option>
                                            <option value="MOROSO" {{ $filtros['estado'] == 'MOROSO' ? 'selected' : '' }}>Moroso</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="tipo_documento">Tipo:</label>
                                        <select name="tipo_documento" id="tipo_documento" class="form-control">
                                            <option value="">Todos</option>
                                            <option value="FCV" {{ $filtros['tipo_documento'] == 'FCV' ? 'selected' : '' }}>Factura</option>
                                            <option value="NCV" {{ $filtros['tipo_documento'] == 'NCV' ? 'selected' : '' }}>Nota Crédito</option>
                                            <option value="FDV" {{ $filtros['tipo_documento'] == 'FDV' ? 'selected' : '' }}>Factura Devolución</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="cliente">Cliente:</label>
                                        <input type="text" name="cliente" id="cliente" class="form-control" 
                                               value="{{ $filtros['cliente'] }}" placeholder="Código o nombre">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="fecha_desde">Desde:</label>
                                        <input type="date" name="fecha_desde" id="fecha_desde" class="form-control" 
                                               value="{{ $filtros['fecha_desde'] }}">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="fecha_hasta">Hasta:</label>
                                        <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control" 
                                               value="{{ $filtros['fecha_hasta'] }}">
                                    </div>
                                </div>
                            </div>
                            
                            @if(isset($vendedores) && count($vendedores) > 0)
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="vendedores">Vendedores:</label>
                                        <select name="vendedores[]" id="vendedores" class="form-control" multiple>
                                            @foreach($vendedores as $vendedor)
                                                <option value="{{ $vendedor['codigo'] }}" 
                                                        {{ in_array($vendedor['codigo'], $filtros['vendedores']) ? 'selected' : '' }}>
                                                    {{ $vendedor['codigo'] }} - {{ $vendedor['nombre'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                            @endif
                            
                            <div class="row">
                                <div class="col-md-12">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="material-icons">search</i> Filtrar
                                    </button>
                                    <a href="{{ route('facturas-emitidas.index') }}" class="btn btn-secondary">
                                        <i class="material-icons">clear</i> Limpiar
                                    </a>
                                    <button type="button" class="btn btn-success" onclick="exportarFacturas()">
                                        <i class="material-icons">file_download</i> Exportar Excel
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabla de Facturas -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        @if($pagination->count() > 0)
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>N° Documento</th>
                                            <th>Tipo</th>
                                            <th>Cliente</th>
                                            <th>Fecha Emisión</th>
                                            <th>Fecha Vencimiento</th>
                                            <th>Días Vencido</th>
                                            <th>Valor Neto</th>
                                            <th>Valor Total</th>
                                            <th>Abonos</th>
                                            <th>Saldo</th>
                                            <th>Estado</th>
                                            <th>Vendedor</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($pagination as $factura)
                                        <tr>
                                            <td>
                                                <strong>{{ $factura['NRO_DOCTO'] ?? 'N/A' }}</strong>
                                            </td>
                                            <td>
                                                <span class="badge badge-info">{{ $factura['TIPO_DOCTO'] ?? 'N/A' }}</span>
                                            </td>
                                            <td>
                                                <strong>{{ $factura['CODIGO_CLIENTE'] ?? 'N/A' }}</strong><br>
                                                <small>{{ $factura['NOMBRE_CLIENTE'] ?? 'N/A' }}</small>
                                            </td>
                                            <td>{{ $factura['EMISION'] ?? 'N/A' }}</td>
                                            <td>{{ $factura['VENCIMIENTO'] ?? 'N/A' }}</td>
                                            <td>
                                                @if(($factura['DIAS_VENCIDO'] ?? 0) > 0)
                                                    <span class="text-danger">{{ $factura['DIAS_VENCIDO'] }} días</span>
                                                @elseif(($factura['DIAS_VENCIDO'] ?? 0) < 0)
                                                    <span class="text-success">{{ abs($factura['DIAS_VENCIDO']) }} días</span>
                                                @else
                                                    <span class="text-warning">Hoy</span>
                                                @endif
                                            </td>
                                            <td>
                                                <strong>${{ number_format($factura['VALOR_NETO'] ?? 0, 0) }}</strong>
                                            </td>
                                            <td>
                                                <strong>${{ number_format($factura['VALOR_TOTAL'] ?? 0, 0) }}</strong>
                                            </td>
                                            <td>
                                                @if(($factura['SALDO'] ?? 0) > 0)
                                                    <span class="text-danger">${{ number_format($factura['SALDO'], 0) }}</span>
                                                @else
                                                    <span class="text-success">$0</span>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="text-info">${{ number_format($factura['ABONOS'] ?? 0, 0) }}</span>
                                            </td>
                                            <td>
                                                @switch($factura['ESTADO'] ?? '')
                                                    @case('VIGENTE')
                                                        <span class="badge badge-success">Vigente</span>
                                                        @break
                                                    @case('POR VENCER')
                                                        <span class="badge badge-warning">Por Vencer</span>
                                                        @break
                                                    @case('VENCIDO')
                                                        <span class="badge badge-danger">Vencido</span>
                                                        @break
                                                    @case('MOROSO')
                                                        <span class="badge badge-dark">Moroso</span>
                                                        @break
                                                    @default
                                                        <span class="badge badge-secondary">{{ $factura['ESTADO'] ?? 'N/A' }}</span>
                                                @endswitch
                                            </td>
                                            <td>{{ $factura['NOMBRE_VENDEDOR'] ?? 'N/A' }}</td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Paginación -->
                            <div class="d-flex justify-content-between align-items-center mt-3">
                                <div>
                                    <p class="text-muted">
                                        Mostrando {{ $pagination->firstItem() }} a {{ $pagination->lastItem() }} 
                                        de {{ $pagination->total() }} facturas
                                    </p>
                                </div>
                                <div>
                                    {{ $pagination->links() }}
                                </div>
                            </div>
                        @else
                            <div class="alert alert-info">
                                <h4 class="alert-heading">No hay facturas emitidas</h4>
                                <p>No se encontraron facturas emitidas con los filtros aplicados.</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('js')
<script>
function exportarFacturas() {
    // Obtener los filtros actuales
    const filtros = {
        buscar: document.getElementById('buscar').value,
        estado: document.getElementById('estado').value,
        tipo_documento: document.getElementById('tipo_documento').value,
        cliente: document.getElementById('cliente').value,
        fecha_desde: document.getElementById('fecha_desde').value,
        fecha_hasta: document.getElementById('fecha_hasta').value
    };
    
    // Agregar vendedores si es administrador
    const vendedoresSelect = document.getElementById('vendedores');
    if (vendedoresSelect) {
        const vendedores = Array.from(vendedoresSelect.selectedOptions).map(option => option.value);
        filtros.vendedores = vendedores;
    }
    
    // Construir URL con filtros
    const params = new URLSearchParams(filtros);
    const url = '{{ route("facturas-emitidas.export") }}?' + params.toString();
    
    // Mostrar mensaje de carga simple
    const button = event.target;
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="material-icons">hourglass_empty</i> Exportando...';
    button.disabled = true;
    
    // Realizar petición AJAX
    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Descargar el archivo directamente
                window.location.href = data.download_url;
                
                // Mostrar mensaje de éxito
                alert('¡Exportación completada! Se exportaron ' + data.total_registros + ' registros');
            } else {
                alert('Error: No se pudo exportar el archivo');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Ocurrió un error durante la exportación');
        })
        .finally(() => {
            // Restaurar el botón
            button.innerHTML = originalText;
            button.disabled = false;
        });
}
</script>
@endpush
