@extends('layouts.app', ['pageSlug' => 'cotizaciones'])

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <div class="row">
                        <div class="col-md-8">
                            <h4 class="card-title">
                                <i class="material-icons">description</i>
                                @if(request('tipo_documento') == 'cotizacion')
                                    Cotizaciones
                                @elseif(request('tipo_documento') == 'nota_venta')
                                 Notas de Venta
                                @else
                                    Cotizaciones y Notas de Venta
                                @endif
                            </h4>
                        </div>
                        <div class="col-md-4 text-right">
                            <!-- Los botones de nueva cotización/NVV se han movido a las páginas de cliente -->
                        </div>
                    </div>
                </div>
                
                <!-- Filtros -->
                <div class="card-body">
                    <form method="GET" action="{{ route('cotizaciones.index') }}" class="mb-4">
                        <div class="row">
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label for="tipo_documento">Tipo:</label>
                                    <select name="tipo_documento" id="tipo_documento" class="form-control">
                                        <option value="">Todos</option>
                                        <option value="cotizacion" {{ request('tipo_documento') == 'cotizacion' ? 'selected' : '' }}>Cotizaciones</option>
                                        <option value="nota_venta" {{ request('tipo_documento') == 'nota_venta' ? 'selected' : '' }}>Notas de Venta</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="estado">Estado:</label>
                                    <select name="estado" id="estado" class="form-control">
                                        <option value="">Todos los estados</option>
                                        <option value="borrador" {{ $estado == 'borrador' ? 'selected' : '' }}>Borrador</option>
                                        <option value="enviada" {{ $estado == 'enviada' ? 'selected' : '' }}>Enviada</option>
                                        <option value="aprobada" {{ $estado == 'aprobada' ? 'selected' : '' }}>Aprobada</option>
                                        <option value="rechazada" {{ $estado == 'rechazada' ? 'selected' : '' }}>Rechazada</option>
                                        <option value="pendiente_stock" {{ $estado == 'pendiente_stock' ? 'selected' : '' }}>Pendiente por Stock</option>
                                        <option value="procesada" {{ $estado == 'procesada' ? 'selected' : '' }}>Procesada</option>
                                        <option value="cancelada" {{ $estado == 'cancelada' ? 'selected' : '' }}>Cancelada</option>
                                        <option value="ingresada" {{ $estado == 'ingresada' ? 'selected' : '' }}>Ingresada (SQL)</option>
                                        <option value="pendiente" {{ $estado == 'pendiente' ? 'selected' : '' }}>Pendiente (SQL)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label for="cliente">Cliente:</label>
                                    <input type="text" name="cliente" id="cliente" class="form-control" 
                                           value="{{ $cliente }}" placeholder="Código o nombre del cliente">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label for="buscar">Buscar:</label>
                                    <input type="text" name="buscar" id="buscar" class="form-control" 
                                           value="{{ $buscar }}" placeholder="N° NV, cliente, etc.">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label for="fecha_inicio">Desde:</label>
                                    <input type="date" name="fecha_inicio" id="fecha_inicio" class="form-control" 
                                           value="{{ $fechaInicio }}">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label for="fecha_fin">Hasta:</label>
                                    <input type="date" name="fecha_fin" id="fecha_fin" class="form-control" 
                                           value="{{ $fechaFin }}">
                                </div>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label for="monto_min">Monto Mín:</label>
                                    <input type="number" name="monto_min" id="monto_min" class="form-control" 
                                           value="{{ $montoMin }}" placeholder="0">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label for="monto_max">Monto Máx:</label>
                                    <input type="number" name="monto_max" id="monto_max" class="form-control" 
                                           value="{{ $montoMax }}" placeholder="999999999">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>&nbsp;</label>
                                    <div>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="material-icons">search</i> Filtrar
                                        </button>
                                        <a href="{{ route('cotizaciones.index') }}" class="btn btn-secondary">
                                            <i class="material-icons">clear</i> Limpiar
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                <div class="card-body">
                    
                    @if(count($cotizaciones) > 0)
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>{{ request('tipo_documento') === 'nota_venta' ? 'N° NVV' : 'N° COT' }}</th>
                                        <th>Cliente</th>
                                        <th>Fecha</th>
                                        <th>Total (c/IVA)</th>
                                        <th>Saldo</th>
                                        <th>Estado</th>
                                        <th>Fuente</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($cotizaciones as $cotizacion)
                                    @php
                                        // Convertir objeto a array si es necesario
                                        $cotizacion = is_object($cotizacion) ? (array)$cotizacion : $cotizacion;
                                    @endphp
                                    <tr>
                                        <td>
                                            <strong>
                                                @php $tipo = $cotizacion['tipo_documento'] ?? (request('tipo_documento') ?: 'nota_venta'); @endphp
                                                @if($tipo === 'nota_venta')
                                                    NVV#{{ $cotizacion['numero'] ?? $cotizacion['id'] }}
                                                @else
                                                    COT#{{ $cotizacion['numero'] ?? $cotizacion['id'] }}
                                                @endif
                                            </strong><br>
                                            <small class="text-muted">ID: {{ $cotizacion['id'] }}</small>
                                        </td>
                                        <td>
                                            <strong>{{ $cotizacion['cliente_codigo'] }}</strong><br>
                                            <small>{{ $cotizacion['cliente_nombre'] }}</small>
                                        </td>
                                        <td>
                                            @if(isset($cotizacion['fecha_emision']))
                                                {{ \Carbon\Carbon::parse($cotizacion['fecha_emision'])->format('d/m/Y') }}
                                            @elseif(isset($cotizacion['fecha']))
                                                {{ \Carbon\Carbon::parse($cotizacion['fecha'])->format('d/m/Y') }}
                                            @else
                                                N/A
                                            @endif
                                        </td>
                                        <td>
                                            <strong>${{ number_format($cotizacion['total'], 0, ',', '.') }}</strong>
                                        </td>
                                        <td>
                                            @if(isset($cotizacion['saldo']) && $cotizacion['saldo'] > 0)
                                                <span class="text-danger">${{ number_format($cotizacion['saldo'], 0, ',', '.') }}</span>
                                            @else
                                                <span class="text-success">$0</span>
                                            @endif
                                        </td>
                                        <td>
                                            @switch($cotizacion['estado'])
                                                @case('borrador')
                                                    <span class="badge badge-secondary">Borrador</span>
                                                    @break
                                                @case('enviada')
                                                    <span class="badge badge-info">Enviada</span>
                                                    @break
                                                @case('aprobada')
                                                    <span class="badge badge-success">Aprobada</span>
                                                    @break
                                                @case('rechazada')
                                                    <span class="badge badge-danger">Rechazada</span>
                                                    @break
                                                @case('pendiente_stock')
                                                    <span class="badge badge-warning">Pendiente por Stock</span>
                                                    @break
                                                @case('procesada')
                                                    <span class="badge badge-primary">Procesada</span>
                                                    @break
                                                @case('cancelada')
                                                    <span class="badge badge-dark">Cancelada</span>
                                                    @break
                                                @case('ingresada')
                                                    <span class="badge badge-info">Ingresada</span>
                                                    @break
                                                @case('pendiente')
                                                    <span class="badge badge-warning">Pendiente</span>
                                                    @break
                                                @default
                                                    <span class="badge badge-secondary">{{ $cotizacion['estado'] }}</span>
                                            @endswitch
                                        </td>
                                        <td>
                                            @if(isset($cotizacion['fuente']))
                                                @if($cotizacion['fuente'] === 'local')
                                                    <span class="badge badge-primary">Local</span>
                                                @else
                                                    <span class="badge badge-info">SQL Server</span>
                                                @endif
                                            @else
                                                <span class="badge badge-secondary">N/A</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if(isset($cotizacion['fuente']) && $cotizacion['fuente'] === 'local')
                                                @php
                                                    // Estados editables: solo borrador (pendiente) o rechazada
                                                    $estadoAprobacion = $cotizacion['estado_aprobacion'] ?? 'pendiente';
                                                    $tipoDocumento = $cotizacion['tipo_documento'] ?? 'nota_venta';
                                                    
                                                    // Para Cotizaciones: siempre editable si no fue convertida
                                                    // Para Notas de Venta: solo editable si está pendiente o rechazada
                                                    if ($tipoDocumento === 'cotizacion') {
                                                        $esEditable = in_array($estadoAprobacion, ['pendiente', 'rechazada']);
                                                    } else {
                                                        // Nota de venta: SOLO editable si está pendiente o rechazada
                                                        // NO editable si ya fue aprobada por alguien
                                                        $esEditable = in_array($estadoAprobacion, ['pendiente', 'rechazada']);
                                                    }
                                                @endphp
                                                @if($esEditable)
                                                    <!-- Botones para cotizaciones editables (borrador o rechazadas) -->
                                                    <div class="btn-group" role="group">
                                                        <a href="{{ ($cotizacion['tipo_documento'] === 'nota_venta') ? route('nota-venta.ver', $cotizacion['id']) : route('cotizacion.ver', $cotizacion['id']) }}" 
                                                           class="btn btn-sm btn-info" title="Ver">
                                                            <i class="material-icons">visibility</i>
                                                        </a>
                                                        <a href="{{ ($cotizacion['tipo_documento'] === 'nota_venta') ? route('nota-venta.editar', $cotizacion['id']) : route('cotizacion.editar', $cotizacion['id']) }}" 
                                                           class="btn btn-sm btn-warning" title="Editar">
                                                            <i class="material-icons">edit</i>
                                                        </a>
                                                        <a href="{{ route('cotizacion.historial', $cotizacion['id']) }}" 
                                                           class="btn btn-sm btn-secondary" title="Historial">
                                                            <i class="material-icons">history</i>
                                                        </a>
                                                        
                                                        @if(isset($cotizacion['tipo_documento']) && $cotizacion['tipo_documento'] === 'cotizacion')
                                                            <!-- Botón para convertir cotización a nota de venta -->
                                                            <button type="button" class="btn btn-sm btn-success" 
                                                                    onclick="convertirANotaVenta({{ $cotizacion['id'] }})" 
                                                                    title="Convertir a Nota de Venta">
                                                                <i class="material-icons">transform</i>
                                                            </button>
                                                        @endif
                                                        
                                                        <button type="button" class="btn btn-sm btn-danger" 
                                                                onclick="eliminarCotizacion({{ $cotizacion['id'] }})" title="Eliminar">
                                                            <i class="material-icons">delete</i>
                                                        </button>
                                                    </div>
                                                @elseif($cotizacion['estado'] === 'aprobada')
                                                    <!-- Botón para generar nota de venta -->
                                                    <div class="btn-group" role="group">
                                                        <a href="{{ $cotizacion['tipo_documento'] === 'nota_venta' ? route('nota-venta.ver', $cotizacion['id']) : route('cotizacion.ver', $cotizacion['id']) }}" 
                                                           class="btn btn-sm btn-info" title="Ver">
                                                            <i class="material-icons">visibility</i>
                                                        </a>
                                                        <a href="{{ route('cotizacion.historial', $cotizacion['id']) }}" 
                                                           class="btn btn-sm btn-secondary" title="Historial">
                                                            <i class="material-icons">history</i>
                                                        </a>
                                                        <button type="button" class="btn btn-sm btn-success" 
                                                                onclick="generarNotaVenta({{ $cotizacion['id'] }})" title="Generar Nota de Venta">
                                                            <i class="material-icons">receipt</i>
                                                        </button>
                                                    </div>
                                                @else
                                                    <!-- Solo ver para cotizaciones ya aprobadas o procesadas -->
                                                    <div class="btn-group" role="group">
                                                        <a href="{{ $cotizacion['tipo_documento'] === 'nota_venta' ? route('nota-venta.ver', $cotizacion['id']) : route('cotizacion.ver', $cotizacion['id']) }}" 
                                                           class="btn btn-sm btn-info" title="Ver">
                                                            <i class="material-icons">visibility</i>
                                                        </a>
                                                        <a href="{{ route('cotizacion.historial', $cotizacion['id']) }}" 
                                                           class="btn btn-sm btn-secondary" title="Historial">
                                                            <i class="material-icons">history</i>
                                                        </a>
                                                        @if(in_array($estadoAprobacion, ['aprobada_picking', 'ingresada']))
                                                            <span class="badge badge-success ml-2">
                                                                <i class="material-icons" style="font-size: 14px;">check_circle</i>
                                                                Procesada
                                                            </span>
                                                        @elseif(in_array($estadoAprobacion, ['pendiente_picking', 'aprobada_supervisor', 'aprobada_compras']))
                                                            <span class="badge badge-warning ml-2" title="No se puede editar - En proceso de aprobación">
                                                                <i class="material-icons" style="font-size: 14px;">lock</i>
                                                                En aprobación
                                                            </span>
                                                        @endif
                                                    </div>
                                                @endif
                                            @else
                                                <!-- Cotizaciones de SQL Server - solo ver -->
                                                <div class="btn-group" role="group">
                                                    <a href="{{ $cotizacion['tipo_documento'] === 'nota_venta' ? route('nota-venta.ver', $cotizacion['id']) : route('cotizacion.ver', $cotizacion['id']) }}" 
                                                       class="btn btn-sm btn-info" title="Ver">
                                                        <i class="material-icons">visibility</i>
                                                    </a>
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="text-center mt-3">
                            <small class="text-muted">Mostrando hasta 50 cotizaciones más recientes</small>
                        </div>
                    @else
                        <div class="text-center py-4">
                            <i class="material-icons" style="font-size: 64px; color: #ccc;">description</i>
                            <h5 class="text-muted mt-3">No se encontraron cotizaciones</h5>
                            <p class="text-muted">Intenta ajustar los filtros de búsqueda</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de confirmación para generar nota de venta -->
<div class="modal fade" id="modalConfirmacion" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmar Generación</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>¿Estás seguro de que quieres generar la nota de venta para esta cotización?</p>
                <p class="text-muted">Esta acción no se puede deshacer.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" id="btnConfirmarGenerar">
                    <i class="material-icons">receipt</i>
                    Generar Nota de Venta
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de confirmación para eliminar cotización -->
<div class="modal fade" id="modalEliminar" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="material-icons">warning</i>
                    Confirmar Eliminación
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p><strong>¿Estás seguro de que quieres eliminar esta cotización?</strong></p>
                <p class="text-danger">Esta acción no se puede deshacer y se perderán todos los datos de la cotización.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="btnConfirmarEliminar">
                    <i class="material-icons">delete</i>
                    Eliminar Cotización
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('js')
<script>
let cotizacionIdActualCotizaciones = null;
let cotizacionIdEliminar = null;

function generarNotaVenta(cotizacionId) {
    cotizacionIdActualCotizaciones = cotizacionId;
    $('#modalConfirmacion').modal('show');
}

function eliminarCotizacion(cotizacionId) {
    cotizacionIdEliminar = cotizacionId;
    $('#modalEliminar').modal('show');
}

function convertirANotaVenta(cotizacionId) {
    // Abrir modal de conversión
    $('#modalConvertirNVV').data('cotizacion-id', cotizacionId);
    $('#modalConvertirNVV').modal('show');
}

function confirmarConversionNVV() {
    const cotizacionId = $('#modalConvertirNVV').data('cotizacion-id');
    const numeroOrdenCompra = $('#numero_orden_compra_nvv').val();
    const observacionVendedor = $('#observacion_vendedor_nvv').val();
    const solicitarDescuentoExtra = $('#solicitar_descuento_extra_nvv').is(':checked');
    
    $.ajax({
        url: `/cotizacion/convertir-a-nota-venta/${cotizacionId}`,
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        data: {
            numero_orden_compra: numeroOrdenCompra,
            observacion_vendedor: observacionVendedor,
            solicitar_descuento_extra: solicitarDescuentoExtra
        },
        success: function(response) {
            $('#modalConvertirNVV').modal('hide');
            alert('Cotización convertida exitosamente a Nota de Venta');
            location.reload();
        },
        error: function(xhr) {
            const errorMsg = xhr.responseJSON?.message || 'Error al convertir la cotización';
            alert('Error: ' + errorMsg);
        }
    });
}

$('#btnConfirmarGenerar').click(function() {
    if (!cotizacionIdActualCotizaciones) return;
    
    const btn = $(this);
    const originalText = btn.html();
    
    btn.prop('disabled', true).html(`
        <i class="material-icons">hourglass_empty</i>
        Generando...
    `);
    
    $.ajax({
        url: `/cotizacion/generar-nota-venta/${cotizacionIdActualCotizaciones}`,
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
            if (response.success) {
                // Mostrar mensaje de éxito
                alert('¡Éxito! ' + response.message);
                // Recargar la página para mostrar los cambios
                location.reload();
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function(xhr) {
            let message = 'Error al generar nota de venta';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                message = xhr.responseJSON.message;
            }
            
            alert('Error: ' + message);
        },
        complete: function() {
            btn.prop('disabled', false).html(originalText);
            $('#modalConfirmacion').modal('hide');
        }
    });
});

$('#btnConfirmarEliminar').click(function() {
    if (!cotizacionIdEliminar) return;
    
    const btn = $(this);
    const originalText = btn.html();
    
    btn.prop('disabled', true).html(`
        <i class="material-icons">hourglass_empty</i>
        Eliminando...
    `);
    
    console.log('Iniciando eliminación de cotización ID:', cotizacionIdEliminar);
    console.log('Token CSRF:', $('meta[name="csrf-token"]').attr('content'));
    
    $.ajax({
        url: `/cotizacion/${cotizacionIdEliminar}`,
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
            console.log('Respuesta exitosa:', response);
            if (response.success) {
                alert('¡Éxito! ' + response.message);
                location.reload();
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error en petición:', {xhr, status, error});
            let message = 'Error al eliminar cotización';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                message = xhr.responseJSON.message;
            } else if (xhr.responseText) {
                message = 'Error del servidor: ' + xhr.status;
            }
            
            alert('Error: ' + message);
        },
        complete: function() {
            console.log('Petición completada');
            btn.prop('disabled', false).html(originalText);
            $('#modalEliminar').modal('hide');
        }
    });
});

// Las funciones de selección de cliente se han movido a las páginas de cliente

// Las funciones de búsqueda de clientes se han movido a las páginas de cliente
</script>

<!-- Modal de selección de cliente removido - se usa en páginas de cliente -->

<!-- Modal de Conversión a NVV -->
<div class="modal fade" id="modalConvertirNVV" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="material-icons">description</i>
                    Convertir a Nota de Venta
                </h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="material-icons">info</i>
                    <strong>Información:</strong> Una vez convertida, la cotización entrará al flujo de aprobaciones (Supervisor, Compras, Picking).
                </div>
                
                <div class="form-group">
                    <label for="numero_orden_compra_nvv">Número de Orden de Compra</label>
                    <input type="text" 
                           class="form-control" 
                           id="numero_orden_compra_nvv" 
                           maxlength="40"
                           placeholder="Número de orden de compra del cliente (opcional)">
                    <small class="form-text text-muted">
                        <i class="material-icons" style="font-size: 14px; vertical-align: middle;">info</i>
                        Campo opcional - Máximo 40 caracteres
                    </small>
                </div>
                
                <div class="form-group">
                    <label for="observacion_vendedor_nvv">Observación del Vendedor</label>
                    <textarea class="form-control" 
                              id="observacion_vendedor_nvv" 
                              rows="3" 
                              maxlength="250"
                              placeholder="Observación personal del vendedor (opcional)"></textarea>
                    <small class="form-text text-muted">
                        <i class="material-icons" style="font-size: 14px; vertical-align: middle;">info</i>
                        Campo opcional - Máximo 250 caracteres
                    </small>
                </div>
                
                <div class="form-group">
                    <div class="form-check">
                        <label class="form-check-label">
                            <input class="form-check-input" type="checkbox" id="solicitar_descuento_extra_nvv">
                            <span class="form-check-sign"><span class="check"></span></span>
                            <strong>Solicitar descuento extra</strong>
                        </label>
                    </div>
                    <small class="form-text text-muted">
                        Si está marcado, la NVV requerirá aprobación de Supervisor aunque el cliente no tenga problemas de crédito.
                    </small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="confirmarConversionNVV()">
                    <i class="material-icons">check</i> Convertir a NVV
                </button>
            </div>
        </div>
    </div>
</div>
@endpush 