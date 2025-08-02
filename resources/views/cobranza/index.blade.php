@extends('layouts.app')

@section('title', 'Buscar Clientes')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header card-header-primary">
                    <h4 class="card-title">
                        <i class="material-icons">search</i>
                        Buscar Clientes
                    </h4>
                    <p class="card-category">Encuentra y gestiona tus clientes asignados</p>
                </div>
                <div class="card-body">
                    <!-- Filtros de búsqueda -->
                    <form method="GET" action="{{ route('cobranza.index') }}" class="mb-4">
                        <div class="row">
                            <!-- Búsqueda general -->
                            <div class="col-md-4 mb-3">
                                <div class="form-group">
                                    <label for="buscar">Buscar por código o nombre:</label>
                                    <input type="text" class="form-control" id="buscar" name="buscar" 
                                           value="{{ $filtros['buscar'] ?? '' }}" 
                                           placeholder="Código cliente o nombre...">
                                </div>
                            </div>
                            
                            <!-- Filtros de saldo -->
                            <div class="col-md-2 mb-3">
                                <div class="form-group">
                                    <label for="saldo_min">Saldo mínimo:</label>
                                    <input type="number" class="form-control" id="saldo_min" name="saldo_min" 
                                           value="{{ $filtros['saldo_min'] ?? '' }}" 
                                           placeholder="0">
                                </div>
                            </div>
                            <div class="col-md-2 mb-3">
                                <div class="form-group">
                                    <label for="saldo_max">Saldo máximo:</label>
                                    <input type="number" class="form-control" id="saldo_max" name="saldo_max" 
                                           value="{{ $filtros['saldo_max'] ?? '' }}" 
                                           placeholder="999999999">
                                </div>
                            </div>
                            
                            <!-- Filtros de facturas -->
                            <div class="col-md-2 mb-3">
                                <div class="form-group">
                                    <label for="facturas_min">Facturas mínimo:</label>
                                    <input type="number" class="form-control" id="facturas_min" name="facturas_min" 
                                           value="{{ $filtros['facturas_min'] ?? '' }}" 
                                           placeholder="0">
                                </div>
                            </div>
                            <div class="col-md-2 mb-3">
                                <div class="form-group">
                                    <label for="facturas_max">Facturas máximo:</label>
                                    <input type="number" class="form-control" id="facturas_max" name="facturas_max" 
                                           value="{{ $filtros['facturas_max'] ?? '' }}" 
                                           placeholder="999">
                                </div>
                            </div>
                            
                            <!-- Botones -->
                            <div class="col-md-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="material-icons">search</i> Buscar
                                </button>
                                <a href="{{ route('cobranza.index') }}" class="btn btn-secondary">
                                    <i class="material-icons">clear</i> Limpiar
                                </a>
                            </div>
                        </div>
                    </form>

                    <!-- Información de resultados -->
                    @if(isset($filtros) && (array_filter($filtros) !== []))
                        <div class="alert alert-info">
                            <i class="material-icons">info</i>
                            <strong>Filtros aplicados:</strong>
                            @if(!empty($filtros['buscar']))
                                <span class="badge badge-primary">Búsqueda: "{{ $filtros['buscar'] }}"</span>
                            @endif
                            @if(!empty($filtros['saldo_min']))
                                <span class="badge badge-success">Saldo ≥ ${{ number_format($filtros['saldo_min'], 0) }}</span>
                            @endif
                            @if(!empty($filtros['saldo_max']))
                                <span class="badge badge-success">Saldo ≤ ${{ number_format($filtros['saldo_max'], 0) }}</span>
                            @endif
                            @if(!empty($filtros['facturas_min']))
                                <span class="badge badge-warning">Facturas ≥ {{ $filtros['facturas_min'] }}</span>
                            @endif
                            @if(!empty($filtros['facturas_max']))
                                <span class="badge badge-warning">Facturas ≤ {{ $filtros['facturas_max'] }}</span>
                            @endif
                        </div>
                    @endif

                    <!-- Tabla de clientes -->
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="text-primary">
                                <tr>
                                    <th>
                                        <a href="{{ request()->fullUrlWithQuery(['ordenar_por' => 'CODIGO_CLIENTE', 'orden' => request('ordenar_por') == 'CODIGO_CLIENTE' && request('orden') == 'asc' ? 'desc' : 'asc']) }}" 
                                           class="text-decoration-none text-primary">
                                            Código
                                            @if(request('ordenar_por') == 'CODIGO_CLIENTE')
                                                <i class="material-icons">{{ request('orden') == 'asc' ? 'keyboard_arrow_up' : 'keyboard_arrow_down' }}</i>
                                            @else
                                                <i class="material-icons text-muted">unfold_more</i>
                                            @endif
                                        </a>
                                    </th>
                                    <th>
                                        <a href="{{ request()->fullUrlWithQuery(['ordenar_por' => 'NOMBRE_CLIENTE', 'orden' => request('ordenar_por') == 'NOMBRE_CLIENTE' && request('orden') == 'asc' ? 'desc' : 'asc']) }}" 
                                           class="text-decoration-none text-primary">
                                            Cliente
                                            @if(request('ordenar_por') == 'NOMBRE_CLIENTE')
                                                <i class="material-icons">{{ request('orden') == 'asc' ? 'keyboard_arrow_up' : 'keyboard_arrow_down' }}</i>
                                            @else
                                                <i class="material-icons text-muted">unfold_more</i>
                                            @endif
                                        </a>
                                    </th>
                                    <th>
                                        <a href="{{ request()->fullUrlWithQuery(['ordenar_por' => 'CANTIDAD_FACTURAS', 'orden' => request('ordenar_por') == 'CANTIDAD_FACTURAS' && request('orden') == 'asc' ? 'desc' : 'asc']) }}" 
                                           class="text-decoration-none text-primary">
                                            Facturas
                                            @if(request('ordenar_por') == 'CANTIDAD_FACTURAS')
                                                <i class="material-icons">{{ request('orden') == 'asc' ? 'keyboard_arrow_up' : 'keyboard_arrow_down' }}</i>
                                            @else
                                                <i class="material-icons text-muted">unfold_more</i>
                                            @endif
                                        </a>
                                    </th>
                                    <th>
                                        <a href="{{ request()->fullUrlWithQuery(['ordenar_por' => 'SALDO_TOTAL', 'orden' => request('ordenar_por') == 'SALDO_TOTAL' && request('orden') == 'asc' ? 'desc' : 'asc']) }}" 
                                           class="text-decoration-none text-primary">
                                            Saldo
                                            @if(request('ordenar_por') == 'SALDO_TOTAL')
                                                <i class="material-icons">{{ request('orden') == 'asc' ? 'keyboard_arrow_up' : 'keyboard_arrow_down' }}</i>
                                            @else
                                                <i class="material-icons text-muted">unfold_more</i>
                                            @endif
                                        </a>
                                    </th>
                                    <th>
                                        <a href="{{ request()->fullUrlWithQuery(['ordenar_por' => 'BLOQUEADO', 'orden' => request('ordenar_por') == 'BLOQUEADO' && request('orden') == 'asc' ? 'desc' : 'asc']) }}" 
                                           class="text-decoration-none text-primary">
                                            Estado
                                            @if(request('ordenar_por') == 'BLOQUEADO')
                                                <i class="material-icons">{{ request('orden') == 'asc' ? 'keyboard_arrow_up' : 'keyboard_arrow_down' }}</i>
                                            @else
                                                <i class="material-icons text-muted">unfold_more</i>
                                            @endif
                                        </a>
                                    </th>
                                    <th>Teléfono</th>
                                    <th>Dirección</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($clientes ?? [] as $cliente)
                                <tr>
                                    <td>
                                        @if(isset($cliente['BLOQUEADO']) && $cliente['BLOQUEADO'] == 1)
                                            <span class="text-muted">{{ $cliente['CODIGO_CLIENTE'] }}</span>
                                        @else
                                            <strong>{{ $cliente['CODIGO_CLIENTE'] }}</strong>
                                        @endif
                                    </td>
                                    <td>
                                        <div>
                                            @if(isset($cliente['BLOQUEADO']) && $cliente['BLOQUEADO'] == 1)
                                                <span class="text-muted">{{ $cliente['NOMBRE_CLIENTE'] }}</span>
                                            @else
                                                <strong>{{ $cliente['NOMBRE_CLIENTE'] }}</strong>
                                            @endif
                                            <br>
                                            <small class="text-muted">{{ $cliente['REGION'] }} - {{ $cliente['COMUNA'] }}</small>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-info">{{ $cliente['CANTIDAD_FACTURAS'] }}</span>
                                    </td>
                                    <td>
                                        <span class="badge badge-{{ $cliente['SALDO_TOTAL'] > 500000 ? 'danger' : ($cliente['SALDO_TOTAL'] > 100000 ? 'warning' : 'success') }}">
                                            ${{ number_format($cliente['SALDO_TOTAL'] ?? 0, 0) }}
                                        </span>
                                    </td>
                                    <td>
                                        @if(isset($cliente['BLOQUEADO']) && $cliente['BLOQUEADO'] == 1)
                                            <span class="badge badge-danger">
                                                <i class="material-icons" style="font-size: 12px;">block</i>
                                                Bloqueado
                                            </span>
                                        @else
                                            <span class="badge badge-success">
                                                <i class="material-icons" style="font-size: 12px;">check_circle</i>
                                                Activo
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        <small>{{ $cliente['TELEFONO'] ?? 'N/A' }}</small>
                                    </td>
                                    <td>
                                        <small class="text-muted">{{ Str::limit($cliente['DIRECCION'] ?? 'N/A', 30) }}</small>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                                    onclick="verDetalleCliente('{{ $cliente['CODIGO_CLIENTE'] }}')"
                                                    @if(isset($cliente['BLOQUEADO']) && $cliente['BLOQUEADO'] == 1) disabled @endif>
                                                <i class="material-icons">visibility</i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-success" 
                                                    onclick="contactarCliente('{{ $cliente['CODIGO_CLIENTE'] }}')"
                                                    @if(isset($cliente['BLOQUEADO']) && $cliente['BLOQUEADO'] == 1) disabled @endif>
                                                <i class="material-icons">phone</i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-warning" 
                                                    onclick="nuevaVenta('{{ $cliente['CODIGO_CLIENTE'] }}', '{{ $cliente['NOMBRE_CLIENTE'] }}')"
                                                    @if(isset($cliente['BLOQUEADO']) && $cliente['BLOQUEADO'] == 1) disabled @endif>
                                                <i class="material-icons">add_shopping_cart</i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="8" class="text-center">
                                        <div class="alert alert-info">
                                            <i class="material-icons">info</i>
                                            No se encontraron clientes con los filtros aplicados
                                        </div>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Paginación -->
                    @if($clientes instanceof \Illuminate\Pagination\LengthAwarePaginator && $clientes->hasPages())
                        <div class="d-flex justify-content-between align-items-center mt-4">
                            <div class="text-muted">
                                Mostrando {{ $clientes->firstItem() }} a {{ $clientes->lastItem() }} de {{ $clientes->total() }} clientes
                                @if(isset($filtros) && (array_filter($filtros) !== []))
                                    <span class="text-info">(filtrados)</span>
                                @endif
                            </div>
                            <div>
                                {{ $clientes->appends(request()->query())->links() }}
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para detalles del cliente -->
<div class="modal fade" id="modalDetalleCliente" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalles del Cliente</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body" id="modalDetalleClienteBody">
                <!-- Contenido dinámico -->
            </div>
        </div>
    </div>
</div>

<!-- Modal de confirmación para nueva venta -->
<div class="modal fade" id="modalNuevaVenta" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="material-icons text-warning">add_shopping_cart</i>
                    Confirmar Nueva Venta
                </h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="material-icons">info</i>
                    <strong>¿Estás seguro de generar una nueva nota de venta para el cliente?</strong>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <strong>Código:</strong> <span id="clienteCodigo"></span>
                    </div>
                    <div class="col-md-6">
                        <strong>Nombre:</strong> <span id="clienteNombre"></span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="material-icons">cancel</i> Cancelar
                </button>
                <button type="button" class="btn btn-warning" onclick="confirmarNuevaVenta()">
                    <i class="material-icons">check</i> Confirmar
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('js')
<script>
let clienteSeleccionadoCobranza = null;

function verDetalleCliente(codigoCliente) {
    $('#modalDetalleClienteBody').html('<div class="text-center"><i class="material-icons">hourglass_empty</i> Cargando información del cliente...</div>');
    $('#modalDetalleCliente').modal('show');
    
    // Cargar datos del cliente via AJAX
    $.ajax({
        url: '/api/cliente/detalle/' + codigoCliente,
        method: 'GET',
        success: function(response) {
            if (response.success) {
                const cliente = response.cliente;
                const facturas = response.facturas || [];
                const notasVenta = response.notasVenta || [];
                
                let html = `
                    <div class="row">
                        <div class="col-12">
                            <div class="card mb-3">
                                <div class="card-header bg-primary text-white">
                                    <h6 class="mb-0"><i class="material-icons">person</i> Información del Cliente</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <p><strong>Código:</strong> ${cliente.CODIGO_CLIENTE}</p>
                                            <p><strong>Nombre:</strong> ${cliente.NOMBRE_CLIENTE}</p>
                                            <p><strong>Teléfono:</strong> ${cliente.TELEFONO || 'N/A'}</p>
                                            <p><strong>Email:</strong> ${cliente.EMAIL || 'Sin email'}</p>
                                        </div>
                                        <div class="col-md-6">
                                            <p><strong>Dirección:</strong> ${cliente.DIRECCION || 'N/A'}</p>
                                            <p><strong>Región:</strong> ${cliente.REGION || 'N/A'}</p>
                                            <p><strong>Comuna:</strong> ${cliente.COMUNA || 'N/A'}</p>
                                            <p><strong>Estado:</strong> 
                                                ${cliente.BLOQUEADO == 1 ? 
                                                    '<span class="badge badge-danger"><i class="material-icons" style="font-size: 12px;">block</i> Bloqueado</span>' : 
                                                    '<span class="badge badge-success"><i class="material-icons" style="font-size: 12px;">check_circle</i> Activo</span>'
                                                }
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-12">
                            <div class="card mb-3">
                                <div class="card-header bg-warning text-dark">
                                    <h6 class="mb-0"><i class="material-icons">receipt</i> Facturas Pendientes (${facturas.length})</h6>
                                </div>
                                <div class="card-body">
                                    ${facturas.length > 0 ? `
                                        <div class="table-responsive">
                                            <table class="table table-sm table-striped">
                                                <thead>
                                                    <tr>
                                                        <th>Tipo</th>
                                                        <th>Número</th>
                                                        <th>Emisión</th>
                                                        <th>Vencimiento</th>
                                                        <th>Días</th>
                                                        <th>Valor</th>
                                                        <th>Saldo</th>
                                                        <th>Estado</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    ${facturas.map(factura => `
                                                        <tr>
                                                            <td><span class="badge badge-info">${factura.TIPO_DOCTO}</span></td>
                                                            <td>${factura.NRO_DOCTO}</td>
                                                            <td>${factura.EMISION}</td>
                                                            <td>${factura.VENCIMIENTO}</td>
                                                            <td>${factura.DIAS_VENCIDO}</td>
                                                            <td>$${parseFloat(factura.VALOR).toLocaleString()}</td>
                                                            <td>$${parseFloat(factura.SALDO).toLocaleString()}</td>
                                                            <td><span class="badge badge-${factura.ESTADO === 'VENCIDO' || factura.ESTADO === 'MOROSO' || factura.ESTADO === 'BLOQUEAR' ? 'danger' : factura.ESTADO === 'POR VENCER' ? 'warning' : 'success'}">${factura.ESTADO}</span></td>
                                                        </tr>
                                                    `).join('')}
                                                </tbody>
                                            </table>
                                        </div>
                                    ` : '<p class="text-muted">No hay facturas pendientes</p>'}
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header bg-success text-white">
                                    <h6 class="mb-0"><i class="material-icons">shopping_cart</i> Notas de Venta (${notasVenta.length})</h6>
                                </div>
                                <div class="card-body">
                                    ${notasVenta.length > 0 ? `
                                        <div class="table-responsive">
                                            <table class="table table-sm table-striped">
                                                <thead>
                                                    <tr>
                                                        <th>Número</th>
                                                        <th>Fecha</th>
                                                        <th>Monto</th>
                                                        <th>Estado</th>
                                                        <th>Acciones</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    ${notasVenta.map(nota => `
                                                        <tr>
                                                            <td>${nota.NRO_DOCTO}</td>
                                                            <td>${nota.EMISION}</td>
                                                            <td>$${parseFloat(nota.VALOR).toLocaleString()}</td>
                                                            <td><span class="badge badge-${nota.ESTADO === 'VENCIDO' || nota.ESTADO === 'MOROSO' ? 'danger' : 'success'}">${nota.ESTADO}</span></td>
                                                            <td>
                                                                <button class="btn btn-sm btn-outline-primary" onclick="verCotizacion('${nota.NRO_DOCTO}')">
                                                                    <i class="material-icons" style="font-size: 14px;">visibility</i>
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    `).join('')}
                                                </tbody>
                                            </table>
                                        </div>
                                    ` : '<p class="text-muted">No hay notas de venta</p>'}
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                
                $('#modalDetalleClienteBody').html(html);
            } else {
                $('#modalDetalleClienteBody').html(`
                    <div class="alert alert-danger">
                        <i class="material-icons">error</i>
                        Error al cargar la información del cliente: ${response.message}
                    </div>
                `);
            }
        },
        error: function() {
            $('#modalDetalleClienteBody').html(`
                <div class="alert alert-danger">
                    <i class="material-icons">error</i>
                    Error al conectar con el servidor
                </div>
            `);
        }
    });
}

function contactarCliente(codigoCliente) {
    // Aquí puedes implementar la lógica para contactar al cliente
    alert(`Contactando al cliente ${codigoCliente}...`);
}

function verCotizacion(numeroDocumento) {
    // Redirigir a la página de cotizaciones con filtro por número de documento
    window.location.href = '/cotizaciones?buscar=' + numeroDocumento;
}

function nuevaVenta(codigoCliente, nombreCliente) {
    clienteSeleccionadoCobranza = {
        codigo: codigoCliente,
        nombre: nombreCliente
    };
    
    $('#clienteCodigo').text(codigoCliente);
    $('#clienteNombre').text(nombreCliente);
    $('#modalNuevaVenta').modal('show');
}

function confirmarNuevaVenta() {
    if (clienteSeleccionadoCobranza) {
        // Redirigir a la página de cotización con los datos del cliente
        window.location.href = `{{ url('/cotizacion/nueva') }}?cliente=${clienteSeleccionadoCobranza.codigo}&nombre=${encodeURIComponent(clienteSeleccionadoCobranza.nombre)}`;
    }
}

// Auto-submit del formulario cuando cambian los filtros de ordenamiento
$(document).ready(function() {
    // Los enlaces de ordenamiento ya están configurados para hacer submit automático
});
</script>
@endpush 