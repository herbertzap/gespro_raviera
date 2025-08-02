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
                                Cotizaciones (Notas de Venta)
                            </h4>
                        </div>
                        <div class="col-md-4 text-right">
                            <a href="{{ route('cotizacion.nueva') }}" class="btn btn-primary">
                                <i class="material-icons">add</i>
                                Nueva Cotización
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Filtros -->
                <div class="card-body">
                    <form method="GET" action="{{ route('cotizaciones.index') }}" class="mb-4">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="estado">Estado:</label>
                                    <select name="estado" id="estado" class="form-control">
                                        <option value="">Todos los estados</option>
                                        <option value="ingresada" {{ $estado == 'ingresada' ? 'selected' : '' }}>Ingresada</option>
                                        <option value="pendiente" {{ $estado == 'pendiente' ? 'selected' : '' }}>Pendiente de Aprobación</option>
                                        <option value="aprobada" {{ $estado == 'aprobada' ? 'selected' : '' }}>Aprobada</option>
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
                    <!-- Debug info -->
                    <div class="alert alert-info">
                        <strong>Debug:</strong> 
                        Cotizaciones encontradas: {{ count($cotizaciones) }} | 
                        Estado filtro: {{ $estado }} | 
                        Cliente filtro: {{ $cliente }} | 
                        Buscar filtro: {{ $buscar }}
                    </div>
                    
                    @if(count($cotizaciones) > 0)
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>N° NV</th>
                                        <th>Cliente</th>
                                        <th>Fecha</th>
                                        <th>Total</th>
                                        <th>Saldo</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($cotizaciones as $cotizacion)
                                    <tr>
                                        <td>
                                            <strong>NV#{{ $cotizacion->numero }}</strong><br>
                                            <small class="text-muted">ID: {{ $cotizacion->id }}</small>
                                        </td>
                                        <td>
                                            <strong>{{ $cotizacion->cliente_codigo }}</strong><br>
                                            <small>{{ $cotizacion->cliente_nombre }}</small>
                                        </td>
                                        <td>{{ \Carbon\Carbon::parse($cotizacion->fecha_emision)->format('d/m/Y') }}</td>
                                        <td>
                                            <strong>${{ number_format($cotizacion->total, 0, ',', '.') }}</strong>
                                        </td>
                                        <td>
                                            @if($cotizacion->saldo > 0)
                                                <span class="text-danger">${{ number_format($cotizacion->saldo, 0, ',', '.') }}</span>
                                            @else
                                                <span class="text-success">$0</span>
                                            @endif
                                        </td>
                                        <td>
                                            @switch($cotizacion->estado)
                                                @case('ingresada')
                                                    <span class="badge badge-info">Ingresada</span>
                                                    @break
                                                @case('pendiente')
                                                    <span class="badge badge-warning">Pendiente de Aprobación</span>
                                                    @break
                                                @case('aprobada')
                                                    <span class="badge badge-success">Aprobada</span>
                                                    @break
                                                @case('procesada')
                                                    <span class="badge badge-primary">Procesada</span>
                                                    @break
                                                @default
                                                    <span class="badge badge-secondary">{{ $cotizacion->estado }}</span>
                                            @endswitch
                                        </td>
                                        <td>
                                            @if($cotizacion->estado === 'aprobada')
                                                <button type="button" class="btn btn-sm btn-success" 
                                                        onclick="generarNotaVenta({{ $cotizacion->id }})">
                                                    <i class="material-icons">receipt</i>
                                                    Generar NV
                                                </button>
                                            @elseif($cotizacion->estado === 'pendiente')
                                                <span class="text-muted">
                                                    <i class="material-icons" style="font-size: 14px;">warning</i>
                                                    Requiere aprobación
                                                </span>
                                            @elseif($cotizacion->estado === 'procesada')
                                                <span class="text-success">
                                                    <i class="material-icons" style="font-size: 16px;">check_circle</i>
                                                    Completada
                                                </span>
                                            @else
                                                <span class="text-info">
                                                    <i class="material-icons" style="font-size: 14px;">info</i>
                                                    Ingresada
                                                </span>
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
                            <p class="text-muted">Intenta ajustar los filtros o crear una nueva cotización</p>
                            <a href="{{ route('cotizacion.nueva') }}" class="btn btn-primary">
                                <i class="material-icons">add</i>
                                Crear Cotización
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de confirmación -->
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

@endsection

@push('js')
<script>
let cotizacionIdActualCotizaciones = null;

function generarNotaVenta(cotizacionId) {
    cotizacionIdActualCotizaciones = cotizacionId;
    $('#modalConfirmacion').modal('show');
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
                Swal.fire({
                    icon: 'success',
                    title: '¡Éxito!',
                    text: response.message,
                    confirmButtonText: 'OK'
                }).then(() => {
                    // Recargar la página para mostrar los cambios
                    location.reload();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: response.message,
                    confirmButtonText: 'OK'
                });
            }
        },
        error: function(xhr) {
            let message = 'Error al generar nota de venta';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                message = xhr.responseJSON.message;
            }
            
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: message,
                confirmButtonText: 'OK'
            });
        },
        complete: function() {
            btn.prop('disabled', false).html(originalText);
            $('#modalConfirmacion').modal('hide');
        }
    });
});
</script>
@endpush 