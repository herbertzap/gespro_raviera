@extends('layouts.app')

@section('title', 'Selección de Cliente')

@section('content')
<div class="content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Selección de Cliente</h4>
                        <p class="card-category">Valide el cliente antes de proceder con la venta</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Buscador de Cliente -->
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header card-header-primary">
                        <h4 class="card-title">Buscar Cliente</h4>
                        <p class="card-category">Ingrese el código del cliente</p>
                    </div>
                    <div class="card-body">
                        <form id="formBuscarCliente">
                            <div class="form-group">
                                <label for="codigo_cliente">Código del Cliente</label>
                                <input type="text" class="form-control" id="codigo_cliente" name="codigo_cliente" 
                                       placeholder="Ingrese el código del cliente" required>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="material-icons">search</i> Buscar Cliente
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Resultado de la búsqueda -->
            <div class="col-md-6">
                <div class="card" id="cardResultado" style="display: none;">
                    <div class="card-header">
                        <h4 class="card-title">Información del Cliente</h4>
                    </div>
                    <div class="card-body" id="resultadoCliente">
                        <!-- Aquí se mostrará la información del cliente -->
                    </div>
                </div>
            </div>
        </div>

        <!-- Mis Clientes -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header card-header-info">
                        <h4 class="card-title">Mis Clientes</h4>
                        <p class="card-category">Clientes asignados</p>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead class="text-info">
                                    <tr>
                                        <th>Código</th>
                                        <th>Cliente</th>
                                        <th>Facturas Pendientes</th>
                                        <th>Saldo Total</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($clientes ?? [] as $cliente)
                                    <tr>
                                        <td>{{ $cliente['CODIGO_CLIENTE'] }}</td>
                                        <td>{{ $cliente['NOMBRE_CLIENTE'] }}</td>
                                        <td>{{ $cliente['CANTIDAD_FACTURAS'] }}</td>
                                        <td>${{ number_format($cliente['SALDO_TOTAL'] ?? 0, 2) }}</td>
                                        <td>
                                            @if($cliente['CANTIDAD_FACTURAS'] <= 2)
                                                <span class="badge badge-success">Autorizado</span>
                                            @else
                                                <span class="badge badge-danger">Bloqueado</span>
                                            @endif
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-primary" onclick="seleccionarCliente('{{ $cliente['CODIGO_CLIENTE'] }}')">
                                                <i class="material-icons">check</i> Seleccionar
                                            </button>
                                            <a href="{{ route('clientes.facturas', $cliente['CODIGO_CLIENTE']) }}" class="btn btn-sm btn-info">
                                                <i class="material-icons">receipt</i> Ver Facturas
                                            </a>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="6" class="text-center">No hay clientes asignados</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Validación -->
<div class="modal fade" id="modalValidacion" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Validación de Cliente</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body" id="modalBody">
                <!-- Contenido dinámico -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-primary" id="btnContinuar" style="display: none;">Continuar con Venta</button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
$(document).ready(function() {
    // Buscar cliente por formulario
    $('#formBuscarCliente').on('submit', function(e) {
        e.preventDefault();
        buscarCliente();
    });

    // Buscar cliente por código
    $('#codigo_cliente').on('keypress', function(e) {
        if (e.which == 13) {
            buscarCliente();
        }
    });
});

function buscarCliente() {
    const codigoCliente = $('#codigo_cliente').val();
    
    if (!codigoCliente) {
        mostrarAlerta('Por favor ingrese un código de cliente', 'warning');
        return;
    }

    $.ajax({
        url: '{{ route("clientes.buscar") }}',
        method: 'POST',
        data: {
            codigo_cliente: codigoCliente,
            _token: '{{ csrf_token() }}'
        },
        success: function(response) {
            if (response.success) {
                mostrarResultadoCliente(response);
            } else {
                mostrarAlerta(response.message, 'error');
            }
        },
        error: function() {
            mostrarAlerta('Error al buscar el cliente', 'error');
        }
    });
}

function mostrarResultadoCliente(response) {
    const cliente = response.cliente;
    const validacion = response.validacion;
    
    let html = `
        <div class="row">
            <div class="col-md-12">
                <h5>${cliente.NOMBRE_CLIENTE}</h5>
                <p><strong>Código:</strong> ${cliente.CODIGO_CLIENTE}</p>
                <p><strong>Dirección:</strong> ${cliente.DIRECCION || 'No especificada'}</p>
                <p><strong>Teléfono:</strong> ${cliente.TELEFONO || 'No especificado'}</p>
                
                <div class="alert alert-${validacion.puede_vender ? 'success' : 'danger'}">
                    <strong>Estado:</strong> ${validacion.mensaje}
                </div>
                
                <p><strong>Facturas Pendientes:</strong> ${validacion.facturas_pendientes}</p>
                <p><strong>Saldo Pendiente:</strong> $${parseFloat(validacion.saldo_pendiente || 0).toFixed(2)}</p>
            </div>
        </div>
    `;

    if (validacion.puede_vender) {
        html += `
            <div class="row mt-3">
                <div class="col-md-12">
                    <button class="btn btn-success btn-block" onclick="validarCliente('${cliente.CODIGO_CLIENTE}')">
                        <i class="material-icons">check_circle</i> Proceder con Venta
                    </button>
                </div>
            </div>
        `;
    } else {
        html += `
            <div class="row mt-3">
                <div class="col-md-12">
                    <a href="{{ route('clientes.facturas', '') }}/${cliente.CODIGO_CLIENTE}" class="btn btn-info btn-block">
                        <i class="material-icons">receipt</i> Ver Facturas Pendientes
                    </a>
                </div>
            </div>
        `;
    }

    $('#resultadoCliente').html(html);
    $('#cardResultado').show();
}

function validarCliente(codigoCliente) {
    $.ajax({
        url: '{{ route("clientes.validar") }}',
        method: 'POST',
        data: {
            codigo_cliente: codigoCliente,
            _token: '{{ csrf_token() }}'
        },
        success: function(response) {
            if (response.success) {
                window.location.href = response.redirect;
            } else {
                mostrarAlerta(response.message, 'error');
            }
        },
        error: function() {
            mostrarAlerta('Error al validar el cliente', 'error');
        }
    });
}

function seleccionarCliente(codigoCliente) {
    $('#codigo_cliente').val(codigoCliente);
    buscarCliente();
}

function mostrarAlerta(mensaje, tipo) {
    // Implementar función de alerta según el framework de UI
    alert(mensaje);
}
</script>
@endpush 