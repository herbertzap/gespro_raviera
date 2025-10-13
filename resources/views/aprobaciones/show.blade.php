@extends('layouts.app', ['pageSlug' => 'aprobaciones'])

@section('content')
<div class="content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header card-header-primary">
                        <div class="row">
                            <div class="col-md-8">
                                <h4 class="card-title">
                                    <i class="material-icons">description</i>
                                    Nota de Venta #{{ $cotizacion->id }}
                                </h4>
                                <p class="card-category">
                                    Cliente: {{ $cotizacion->cliente_codigo }} - {{ $cotizacion->cliente_nombre }}
                                </p>
                            </div>
                            <div class="col-md-4 text-right d-inline-flex items-center">
                                <a href="{{ route('aprobaciones.index') }}" class="btn btn-secondary">
                                    <i class="material-icons">arrow_back</i> Volver
                                </a>
                                @if(!auth()->user()->hasRole('Picking'))
                                    <a href="{{ route('clientes.show', $cotizacion->cliente_codigo) }}" class="btn btn-primary ml-2">
                                        <i class="material-icons">person</i> Ver Cliente
                                    </a>
                                @else
                                    <button onclick="mostrarModalImpresion()" class="btn btn-success ml-2">
                                        <i class="material-icons">print</i> Imprimir Nota de Venta
                                    </button>
                                @endif
                                <a href="{{ route('aprobaciones.historial', $cotizacion->id) }}" class="btn btn-info ml-2">
                                    <i class="material-icons">history</i> Historial
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Información General -->
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header card-header-info">
                        <h4 class="card-title">
                            <i class="material-icons">info</i>
                            Información General
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>ID:</strong> #{{ $cotizacion->id }}</p>
                                <p><strong>Fecha:</strong> {{ $cotizacion->fecha->format('d/m/Y H:i') }}</p>
                                <p><strong>Vendedor:</strong> {{ $cotizacion->user->name ?? 'N/A' }}</p>
                                <p><strong>Estado:</strong> 
                                    @switch($cotizacion->estado_aprobacion)
                                        @case('pendiente')
                                            <span class="badge badge-warning">Pendiente Supervisor</span>
                                            @break
                                        @case('pendiente_picking')
                                            <span class="badge badge-info">Pendiente Picking</span>
                                            @break
                                        @case('aprobada_supervisor')
                                            <span class="badge badge-success">Aprobada Supervisor</span>
                                            @break
                                        @case('aprobada_compras')
                                            <span class="badge badge-primary">Aprobada Compras</span>
                                            @break
                                        @case('aprobada_picking')
                                            <span class="badge badge-success">Aprobada Picking</span>
                                            @break
                                        @case('rechazada')
                                            <span class="badge badge-danger">Rechazada</span>
                                            @break
                                        @default
                                            <span class="badge badge-secondary">{{ $cotizacion->estado_aprobacion }}</span>
                                    @endswitch
                                </p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Cliente:</strong> {{ $cotizacion->cliente_codigo }}</p>
                                <p><strong>Nombre:</strong> {{ $cotizacion->cliente_nombre }}</p>
                                <p><strong>Dirección:</strong> {{ $cotizacion->cliente_direccion ?: 'No especificada' }}</p>
                                <p><strong>Teléfono:</strong> {{ $cotizacion->cliente_telefono ?: 'No especificado' }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-header card-header-success">
                        <h4 class="card-title">
                            <i class="material-icons">attach_money</i>
                            Resumen Financiero
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Subtotal:</strong> ${{ number_format($cotizacion->subtotal, 0) }}</p>
                                <p><strong>Descuento:</strong> ${{ number_format($cotizacion->descuento_global, 0) }}</p>
                                <p><strong>Total:</strong> ${{ number_format($cotizacion->total, 0) }}</p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Observaciones:</strong></p>
                                <p class="text-muted">{{ $cotizacion->observaciones ?: 'Sin observaciones' }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Estado de Aprobaciones -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header card-header-warning">
                        <h4 class="card-title">
                            <i class="material-icons">assignment_turned_in</i>
                            Estado de Aprobaciones
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h5>Supervisor</h5>
                                    @if($cotizacion->aprobado_por_supervisor)
                                        <span class="badge badge-success">Aprobada</span>
                                        <br><small>{{ $cotizacion->fecha_aprobacion_supervisor ? $cotizacion->fecha_aprobacion_supervisor->format('d/m/Y H:i') : '' }}</small>
                                        <br><small>Por: {{ $cotizacion->aprobadoPorSupervisor->name ?? 'N/A' }}</small>
                                    @elseif($cotizacion->tiene_problemas_credito && $cotizacion->estado_aprobacion === 'pendiente')
                                        <span class="badge badge-warning">Pendiente</span>
                                    @elseif($cotizacion->estado_aprobacion === 'rechazada')
                                        <span class="badge badge-danger">Rechazada</span>
                                    @else
                                        <span class="badge badge-secondary">No Aplica</span>
                                    @endif
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h5>Compras</h5>
                                    @if($cotizacion->aprobado_por_compras)
                                        <span class="badge badge-success">Aprobada</span>
                                        <br><small>{{ $cotizacion->fecha_aprobacion_compras ? $cotizacion->fecha_aprobacion_compras->format('d/m/Y H:i') : '' }}</small>
                                        <br><small>Por: {{ $cotizacion->aprobadoPorCompras->name ?? 'N/A' }}</small>
                                    @elseif($cotizacion->tiene_problemas_stock && $cotizacion->estado_aprobacion === 'pendiente')
                                        <span class="badge badge-warning">Pendiente</span>
                                    @elseif($cotizacion->estado_aprobacion === 'aprobada_supervisor' && $cotizacion->tiene_problemas_stock)
                                        <span class="badge badge-warning">Pendiente</span>
                                    @elseif($cotizacion->estado_aprobacion === 'rechazada')
                                        <span class="badge badge-danger">Rechazada</span>
                                    @else
                                        <span class="badge badge-secondary">No Aplica</span>
                                    @endif
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h5>Picking</h5>
                                    @if($cotizacion->aprobado_por_picking)
                                        <span class="badge badge-success">Aprobada</span>
                                        <br><small>{{ $cotizacion->fecha_aprobacion_picking ? $cotizacion->fecha_aprobacion_picking->format('d/m/Y H:i') : '' }}</small>
                                        <br><small>Por: {{ $cotizacion->aprobadoPorPicking->name ?? 'N/A' }}</small>
                                    @elseif($cotizacion->estado_aprobacion === 'pendiente_picking')
                                        <span class="badge badge-warning">Pendiente</span>
                                    @elseif($cotizacion->aprobado_por_compras && $cotizacion->tiene_problemas_stock)
                                        <span class="badge badge-warning">Pendiente</span>
                                    @elseif($cotizacion->aprobado_por_supervisor && !$cotizacion->tiene_problemas_stock)
                                        <span class="badge badge-warning">Pendiente</span>
                                    @elseif($cotizacion->estado_aprobacion === 'rechazada')
                                        <span class="badge badge-danger">Rechazada</span>
                                    @else
                                        <span class="badge badge-secondary">No Aplica</span>
                                    @endif
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h5>Estado Final</h5>
                                    @if($cotizacion->estado_aprobacion === 'aprobada_picking')
                                        <span class="badge badge-success">Aprobada</span>
                                        <br><small>Lista para procesar</small>
                                    @elseif($cotizacion->estado_aprobacion === 'rechazada')
                                        <span class="badge badge-danger">Rechazada</span>
                                        <br><small>{{ $cotizacion->motivo_rechazo }}</small>
                                    @else
                                        <span class="badge badge-warning">En Proceso</span>
                                        <br><small>Pendiente de aprobación</small>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Comentarios de Aprobación -->
                        @if($cotizacion->comentarios_supervisor || $cotizacion->comentarios_compras || $cotizacion->comentarios_picking)
                            <hr>
                            <h6>Comentarios de Aprobación:</h6>
                            <div class="row">
                                @if($cotizacion->comentarios_supervisor)
                                    <div class="col-md-4">
                                        <div class="alert alert-info">
                                            <strong>Supervisor:</strong><br>
                                            {{ $cotizacion->comentarios_supervisor }}
                                        </div>
                                    </div>
                                @endif
                                @if($cotizacion->comentarios_compras)
                                    <div class="col-md-4">
                                        <div class="alert alert-primary">
                                            <strong>Compras:</strong><br>
                                            {{ $cotizacion->comentarios_compras }}
                                        </div>
                                    </div>
                                @endif
                                @if($cotizacion->comentarios_picking)
                                    <div class="col-md-4">
                                        <div class="alert alert-success">
                                            <strong>Picking:</strong><br>
                                            {{ $cotizacion->comentarios_picking }}
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Productos -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header card-header-info">
                        <h4 class="card-title">
                            <i class="material-icons">shopping_basket</i>
                            Productos ({{ $cotizacion->productos->count() }})
                        </h4>
                    </div>
                    <div class="card-body">
                        @if($cotizacion->productos->count() > 0)
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            @if(Auth::user()->hasRole('Compras') && $cotizacion->tiene_problemas_stock)
                                                <th>
                                                    <input type="checkbox" id="selectAll" onchange="toggleAllProducts()">
                                                </th>
                                            @endif
                                            <th>Código</th>
                                            <th>Producto</th>
                                            <th>Cantidad</th>
                                            <th>Separar</th>
                                            <th>Precio Unit.</th>
                                            <th>Subtotal</th>
                                            <th>Stock</th>
                                            <th>Estado</th>
                                            @if(Auth::user()->hasRole('Compras') && $cotizacion->tiene_problemas_stock)
                                                <th>Acciones</th>
                                            @endif
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($cotizacion->productos as $producto)
                                            <tr>
                                                @if(Auth::user()->hasRole('Compras') && $cotizacion->tiene_problemas_stock)
                                                    <td>
                                                        <input type="checkbox" class="product-checkbox" value="{{ $producto->id }}" 
                                                               onchange="updateSelectedProducts()">
                                                    </td>
                                                @endif
                                                <td>
                                                    <strong>{{ $producto->codigo_producto }}</strong>
                                                </td>
                                                <td>
                                                    {{ $producto->nombre_producto }}
                                                </td>
                                                <td>
                                                    @if(Auth::user()->hasRole('Compras') && $cotizacion->tiene_problemas_stock && $producto->stock_disponible < $producto->cantidad && !$cotizacion->aprobado_por_compras)
                                                        <div class="input-group input-group-sm">
                                                            <input type="number" class="form-control cantidad-input" 
                                                                   value="{{ $producto->cantidad }}" 
                                                                   min="0" max="{{ $producto->stock_disponible }}"
                                                                   data-producto-id="{{ $producto->id }}"
                                                                   data-precio="{{ $producto->precio_unitario }}"
                                                                   onchange="actualizarMaximoSeparar({{ $producto->id }})">
                                                            <div class="input-group-append">
                                                                <button class="btn btn-outline-primary btn-sm" 
                                                                        onclick="guardarCantidad({{ $producto->id }})">
                                                                    <i class="material-icons">save</i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    @else
                                                        <span class="badge badge-info">{{ $producto->cantidad }}</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if((Auth::user()->hasRole('Compras') || Auth::user()->hasRole('Picking')) && $cotizacion->tiene_problemas_stock && (!$cotizacion->aprobado_por_compras || Auth::user()->hasRole('Picking')))
                                                        <div class="input-group input-group-sm">
                                                            <input type="number" class="form-control separar-input" 
                                                                   value="{{ $producto->cantidad_separar ?? 0 }}" 
                                                                   min="0" 
                                                                   max="{{ $producto->cantidad }}"
                                                                   data-producto-id="{{ $producto->id }}"
                                                                   data-precio="{{ $producto->precio_unitario }}"
                                                                   data-cantidad-original="{{ $producto->cantidad }}">
                                                            <div class="input-group-append">
                                                                <button class="btn btn-outline-warning btn-sm" 
                                                                        onclick="guardarSeparar({{ $producto->id }})">
                                                                    <i class="material-icons">save</i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    @else
                                                        <span class="badge badge-secondary">{{ $producto->cantidad_separar ?? 0 }}</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    ${{ number_format($producto->precio_unitario, 0) }}
                                                </td>
                                                <td>
                                                    <strong class="subtotal-{{ $producto->id }}">
                                                        ${{ number_format($producto->cantidad * $producto->precio_unitario, 0) }}
                                                    </strong>
                                                </td>
                                                <td>
                                                    @if($producto->stock_disponible >= $producto->cantidad)
                                                        <span class="badge badge-success">{{ $producto->stock_disponible }}</span>
                                                    @else
                                                        <span class="badge badge-danger">{{ $producto->stock_disponible }}</span>
                                                        <br><small class="text-danger">Faltan: {{ $producto->cantidad - $producto->stock_disponible }}</small>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($producto->stock_disponible >= $producto->cantidad)
                                                        <span class="badge badge-success">Disponible</span>
                                                    @else
                                                        <span class="badge badge-warning">Stock Insuficiente</span>
                                                    @endif
                                                </td>
                                                @if(Auth::user()->hasRole('Compras') && $cotizacion->tiene_problemas_stock && !$cotizacion->aprobado_por_compras)
                                                    <td>
                                                        @if($producto->stock_disponible < $producto->cantidad)
                                                            <button class="btn btn-warning btn-sm" 
                                                                    onclick="separarProductoIndividual({{ $producto->id }})">
                                                                <i class="material-icons">call_split</i> Separar
                                                            </button>
                                                        @endif
                                                    </td>
                                                @endif
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="text-center py-3">
                                <p class="text-muted">No hay productos en esta nota de venta</p>
                            </div>
                        @endif
                        
                        <!-- Botones de Acción para Compras -->
                        @if(Auth::user()->hasRole('Compras') && $cotizacion->tiene_problemas_stock && !$cotizacion->aprobado_por_compras)
                            <div class="row mt-3">
                                <div class="col-md-12">
                                    <div class="card">
                                        <div class="card-header card-header-warning">
                                            <h4 class="card-title">
                                                <i class="material-icons">build</i>
                                                Herramientas de Compras
                                            </h4>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <h6>Separar Productos Seleccionados</h6>
                                                    <p class="text-muted">Crea una nueva NVV con los productos seleccionados</p>
                                                    <button class="btn btn-warning" onclick="separarProductosSeleccionados()" id="btnSepararSeleccionados" disabled>
                                                        <i class="material-icons">call_split</i> Separar Seleccionados
                                                    </button>
                                                </div>
                                                <div class="col-md-6">
                                                    <h6>Modificar Cantidades</h6>
                                                    <p class="text-muted">Ajusta las cantidades según el stock disponible</p>
                                                    <button class="btn btn-info" onclick="guardarTodasLasCantidades()">
                                                        <i class="material-icons">save</i> Guardar Todas las Cantidades
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Problemas Identificados -->
        @if($cotizacion->tiene_problemas_credito || $cotizacion->tiene_problemas_stock)
            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header card-header-danger">
                            <h4 class="card-title">
                                <i class="material-icons">warning</i>
                                Problemas Identificados
                            </h4>
                        </div>
                        <div class="card-body">
                            @if($cotizacion->tiene_problemas_credito)
                                <div class="alert alert-danger">
                                    <h6><i class="material-icons">credit_card_off</i> Problemas de Crédito</h6>
                                    <p>{{ $cotizacion->detalle_problemas_credito }}</p>
                                </div>
                            @endif

                            @if($cotizacion->tiene_problemas_stock)
                                <div class="alert alert-warning">
                                    <h6><i class="material-icons">inventory_2</i> Problemas de Stock</h6>
                                    <p>{{ $cotizacion->detalle_problemas_stock }}</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- Botones de Acción -->
        @if(($cotizacion->puedeAprobarSupervisor() && Auth::user()->hasRole('Supervisor')) || 
            ($cotizacion->puedeAprobarCompras() && Auth::user()->hasRole('Compras')) || 
            ($cotizacion->puedeAprobarPicking() && Auth::user()->hasRole('Picking')))
            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header card-header-success">
                            <h4 class="card-title">
                                <i class="material-icons">assignment_turned_in</i>
                                Acciones de Aprobación
                            </h4>
                        </div>
                        <div class="card-body text-center">
                            @if($cotizacion->puedeAprobarSupervisor() && Auth::user()->hasRole('Supervisor'))
                                <div class="alert alert-info">
                                    <h6><i class="material-icons">supervisor_account</i> Requiere Aprobación del Supervisor</h6>
                                    <p>Esta nota de venta requiere tu aprobación.</p>
                                </div>
                                <button type="button" class="btn btn-success btn-lg" onclick="aprobarNota({{ $cotizacion->id }}, 'supervisor')">
                                    <i class="material-icons">check</i> Aprobar
                                </button>
                                <button type="button" class="btn btn-danger btn-lg ml-3" onclick="rechazarNota({{ $cotizacion->id }})">
                                    <i class="material-icons">close</i> Rechazar
                                </button>
                            @elseif($cotizacion->puedeAprobarCompras() && Auth::user()->hasRole('Compras'))
                                <div class="alert alert-primary">
                                    <h6><i class="material-icons">shopping_cart</i> Requiere Aprobación de Compras</h6>
                                    <p>Esta nota de venta requiere tu aprobación.</p>
                                </div>
                                <button type="button" class="btn btn-success btn-lg" onclick="aprobarNota({{ $cotizacion->id }}, 'compras')">
                                    <i class="material-icons">check</i> Aprobar
                                </button>
                                <button type="button" class="btn btn-danger btn-lg ml-3" onclick="rechazarNota({{ $cotizacion->id }})">
                                    <i class="material-icons">close</i> Rechazar
                                </button>
                            @elseif($cotizacion->puedeAprobarPicking() && Auth::user()->hasRole('Picking'))
                                <div class="alert alert-warning">
                                    <h6><i class="material-icons">local_shipping</i> Requiere Aprobación Final de Picking</h6>
                                    <p>Esta nota de venta requiere tu aprobación.</p>
                                    <p><strong>⚠️ IMPORTANTE:</strong> Al aprobar se insertará la NVV en la base de datos de producción.</p>
                                </div>
                                <form action="{{ route('aprobaciones.picking', $cotizacion->id) }}" method="POST" style="display: inline;">
                                    @csrf
                                    <input type="hidden" name="validar_stock_real" value="0">
                                    <input type="hidden" name="comentarios" value="">
                                    <button type="submit" class="btn btn-success btn-lg" onclick="return confirm('¿Estás seguro de aprobar esta nota de venta? Se insertará en la base de datos de producción.')">
                                        <i class="material-icons">check</i> Aprobar
                                    </button>
                                </form>
                                <button type="button" class="btn btn-danger btn-lg ml-3" onclick="rechazarNota({{ $cotizacion->id }})">
                                    <i class="material-icons">close</i> Rechazar
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endif

    </div>
</div>

<!-- Modal para Observaciones de Impresión -->
<div class="modal fade" id="modalImpresion" tabindex="-1" role="dialog" aria-labelledby="modalImpresionLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalImpresionLabel">
                    <i class="material-icons">print</i> Imprimir Guía de Despacho
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="observacionesExtra">¿Deseas agregar alguna observación extra a la guía de despacho?</label>
                    <textarea class="form-control" id="observacionesExtra" rows="4" placeholder="Ej: Retira cliente martes, Productos frágiles, etc."></textarea>
                </div>
                <div class="alert alert-info">
                    <i class="material-icons">info</i>
                    <strong>Información:</strong> La guía de despacho incluirá todos los datos del cliente, productos y espacios para timbres y firmas.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" onclick="imprimirNotaVenta()">
                    <i class="material-icons">print</i> Imprimir Guía
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('js')
<script>
// Función para mostrar modal de impresión
function mostrarModalImpresion() {
    $('#modalImpresion').modal('show');
}

// Función para imprimir nota de venta
function imprimirNotaVenta() {
    // Obtener observaciones del modal
    const observacionesExtra = document.getElementById('observacionesExtra').value;
    
    // Cerrar el modal
    $('#modalImpresion').modal('hide');
    
    // Crear ventana de impresión con URL
    const url = '{{ route("aprobaciones.imprimir", $cotizacion->id) }}?observaciones=' + encodeURIComponent(observacionesExtra);
    const ventanaImpresion = window.open(url, '_blank', 'width=800,height=600');
    
    // Enfocar la ventana
    ventanaImpresion.focus();
}

// Aprobar nota de venta
function aprobarNota(notaId, tipo) {
    // Confirmar aprobación
    if (!confirm('¿Estás seguro de aprobar esta nota de venta?')) {
        return;
    }

    // Usar las rutas específicas que ya funcionaban
    let url = '';
    switch(tipo) {
        case 'supervisor':
            url = `/aprobaciones/${notaId}/supervisor`;
            break;
        case 'compras':
            url = `/aprobaciones/${notaId}/compras`;
            break;
        case 'picking':
            url = `/aprobaciones/${notaId}/picking`;
            break;
    }
    
    if (url) {
        // Crear formulario y enviar
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = url;
        
        const csrfToken = document.createElement('input');
        csrfToken.type = 'hidden';
        csrfToken.name = '_token';
        csrfToken.value = '{{ csrf_token() }}';
        form.appendChild(csrfToken);
        
        // Para picking, agregar campo validar_stock_real
        if (tipo === 'picking') {
            const validarStock = document.createElement('input');
            validarStock.type = 'hidden';
            validarStock.name = 'validar_stock_real';
            validarStock.value = '1';
            form.appendChild(validarStock);
        }
        
        // Para supervisor y compras, agregar comentarios vacíos si no existen
        const comentarios = document.createElement('input');
        comentarios.type = 'hidden';
        comentarios.name = 'comentarios';
        comentarios.value = '';
        form.appendChild(comentarios);
        
        document.body.appendChild(form);
        form.submit();
    }
}

// Rechazar nota de venta
function rechazarNota(notaId) {
    const motivo = prompt('¿Cuál es el motivo del rechazo?');
    if (motivo && motivo.trim() !== '') {
        // Crear formulario y enviar
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `/aprobaciones/${notaId}/rechazar`;
        
        const csrfToken = document.createElement('input');
        csrfToken.type = 'hidden';
        csrfToken.name = '_token';
        csrfToken.value = '{{ csrf_token() }}';
        
        const motivoInput = document.createElement('input');
        motivoInput.type = 'hidden';
        motivoInput.name = 'motivo';
        motivoInput.value = motivo.trim();
        
        form.appendChild(csrfToken);
        form.appendChild(motivoInput);
        document.body.appendChild(form);
        form.submit();
    }
}

// ===== FUNCIONES PARA COMPRAS =====

// Toggle todos los productos
function toggleAllProducts() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.product-checkbox');
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAll.checked;
    });
    
    updateSelectedProducts();
}

// Actualizar productos seleccionados
function updateSelectedProducts() {
    const checkboxes = document.querySelectorAll('.product-checkbox');
    const selectedCount = document.querySelectorAll('.product-checkbox:checked').length;
    const btnSeparar = document.getElementById('btnSepararSeleccionados');
    
    if (btnSeparar) {
        btnSeparar.disabled = selectedCount === 0;
        btnSeparar.textContent = `Separar Seleccionados (${selectedCount})`;
    }
}

// Actualizar máximo del campo separar cuando se modifica cantidad
function actualizarMaximoSeparar(productoId) {
    const cantidadInput = document.querySelector(`input[data-producto-id="${productoId}"].cantidad-input`);
    const separarInput = document.querySelector(`input[data-producto-id="${productoId}"].separar-input`);
    
    if (cantidadInput && separarInput) {
        const cantidadActual = cantidadInput.value;
        separarInput.max = cantidadActual;
        separarInput.placeholder = `Máx: ${cantidadActual}`;
    }
}

// Guardar cantidad individual
function guardarCantidad(productoId) {
    const input = document.querySelector(`input[data-producto-id="${productoId}"]`);
    const nuevaCantidad = input.value;
    const precio = input.dataset.precio;
    
    // Actualizar subtotal
    const subtotal = document.querySelector(`.subtotal-${productoId}`);
    if (subtotal) {
        subtotal.textContent = '$' + new Intl.NumberFormat('es-CL').format(nuevaCantidad * precio);
    }
    
    // Actualizar máximo del campo separar
    actualizarMaximoSeparar(productoId);
    
    // Enviar al servidor
    fetch('{{ route("aprobaciones.modificar-cantidades", $cotizacion->id) }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            cotizacion_id: {{ $cotizacion->id }},
            producto_id: productoId,
            nueva_cantidad: nuevaCantidad
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Cantidad actualizada correctamente', 'success');
        } else {
            showNotification('Error al actualizar cantidad: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error al actualizar cantidad', 'error');
    });
}

// Guardar cantidad a separar
function guardarSeparar(productoId) {
    const separarInput = document.querySelector(`input[data-producto-id="${productoId}"].separar-input`);
    const cantidadSeparar = parseFloat(separarInput.value) || 0;
    const cantidadMaxima = parseFloat(separarInput.max) || 0;
    
    if (cantidadSeparar < 0) {
        showNotification('La cantidad a separar no puede ser negativa', 'warning');
        return;
    }
    
    if (cantidadSeparar > cantidadMaxima) {
        showNotification(`La cantidad a separar no puede exceder ${cantidadMaxima}`, 'warning');
        return;
    }
    
    // Enviar al servidor
    fetch('{{ route("aprobaciones.guardar-separar", $cotizacion->id) }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            producto_id: productoId,
            cantidad_separar: cantidadSeparar
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Cantidad a separar guardada correctamente', 'success');
        } else {
            showNotification('Error al guardar cantidad a separar: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error al guardar cantidad a separar', 'error');
    });
}

// Guardar todas las cantidades
function guardarTodasLasCantidades() {
    const inputs = document.querySelectorAll('.cantidad-input');
    const cambios = [];
    
    inputs.forEach(input => {
        const productoId = input.dataset.productoId;
        const nuevaCantidad = input.value;
        const precio = input.dataset.precio;
        
        cambios.push({
            producto_id: productoId,
            nueva_cantidad: nuevaCantidad
        });
        
        // Actualizar subtotal
        const subtotal = document.querySelector(`.subtotal-${productoId}`);
        if (subtotal) {
            subtotal.textContent = '$' + new Intl.NumberFormat('es-CL').format(nuevaCantidad * precio);
        }
    });
    
    // Enviar al servidor
    fetch('{{ route("aprobaciones.modificar-cantidades", $cotizacion->id) }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            cotizacion_id: {{ $cotizacion->id }},
            cambios: cambios
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Todas las cantidades actualizadas correctamente', 'success');
        } else {
            showNotification('Error al actualizar cantidades: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error al actualizar cantidades', 'error');
    });
}

// Separar producto individual
function separarProductoIndividual(productoId) {
    const separarInput = document.querySelector(`input[data-producto-id="${productoId}"].separar-input`);
    const cantidadSeparar = parseFloat(separarInput?.value) || 0;
    
    console.log('Producto ID:', productoId);
    console.log('Input encontrado:', separarInput);
    console.log('Valor del input:', separarInput?.value);
    console.log('Cantidad a separar:', cantidadSeparar);
    
    if (cantidadSeparar <= 0) {
        showNotification('Debe especificar una cantidad a separar mayor a 0. Valor actual: ' + cantidadSeparar, 'warning');
        return;
    }
    
    // Primero guardar la cantidad a separar en el servidor
    fetch('{{ route("aprobaciones.guardar-separar", $cotizacion->id) }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            producto_id: productoId,
            cantidad_separar: cantidadSeparar
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Ahora proceder con la separación
            if (confirm(`¿Estás seguro de que quieres separar ${cantidadSeparar} unidades de este producto en una nueva NVV?`)) {
                fetch('{{ route("aprobaciones.separar-producto-individual", $cotizacion->id) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        producto_id: productoId,
                        motivo: 'Separación de producto individual por Compras'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification(data.message, 'success');
                        // Recargar la página después de un momento
                        setTimeout(() => {
                            window.location.reload();
                        }, 2000);
                    } else {
                        showNotification('Error al separar el producto: ' + data.error, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Error al separar el producto', 'error');
                });
            }
        } else {
            showNotification('Error al guardar cantidad a separar: ' + data.error, 'error');
        }
    })
    .catch(error => {
        console.error('Error al guardar cantidad a separar:', error);
        showNotification('Error al guardar cantidad a separar', 'error');
    });
}

// Separar productos seleccionados con lógica de cantidades
function separarProductosSeleccionados() {
    const checkboxes = document.querySelectorAll('.product-checkbox:checked');
    const productos = Array.from(checkboxes).map(cb => cb.value);
    
    if (productos.length === 0) {
        showNotification('Selecciona al menos un producto', 'warning');
        return;
    }
    
    // Verificar que todos los productos tengan cantidad a separar > 0
    let productosConSeparar = [];
    let productosSinSeparar = [];
    
    productos.forEach(productoId => {
        const separarInput = document.querySelector(`input[data-producto-id="${productoId}"].separar-input`);
        const cantidadSeparar = parseFloat(separarInput?.value) || 0;
        
        if (cantidadSeparar > 0) {
            productosConSeparar.push({
                id: productoId,
                cantidad: cantidadSeparar
            });
        } else {
            productosSinSeparar.push(productoId);
        }
    });
    
    if (productosSinSeparar.length > 0) {
        showNotification('Algunos productos seleccionados no tienen cantidad a separar especificada', 'warning');
        return;
    }
    
    if (productosConSeparar.length === 0) {
        showNotification('Debe especificar cantidades a separar para los productos seleccionados', 'warning');
        return;
    }
    
    const totalProductos = productosConSeparar.length;
    const totalCantidades = productosConSeparar.reduce((sum, p) => sum + p.cantidad, 0);
    
    if (confirm(`¿Estás seguro de que quieres separar ${totalProductos} productos (${totalCantidades} unidades totales) en una nueva NVV?`)) {
        // Separar cada producto individualmente
        let separacionesExitosas = 0;
        let separacionesFallidas = 0;
        
        productosConSeparar.forEach((producto, index) => {
            fetch('{{ route("aprobaciones.separar-producto-individual", $cotizacion->id) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    producto_id: producto.id,
                    motivo: 'Separación múltiple de productos por Compras'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    separacionesExitosas++;
                } else {
                    separacionesFallidas++;
                }
                
                // Si es el último producto, mostrar resultado final
                if (index === productosConSeparar.length - 1) {
                    if (separacionesExitosas === totalProductos) {
                        showNotification(`Todos los productos separados correctamente (${separacionesExitosas} productos)`, 'success');
                    } else if (separacionesExitosas > 0) {
                        showNotification(`Separación parcial: ${separacionesExitosas} exitosas, ${separacionesFallidas} fallidas`, 'warning');
                    } else {
                        showNotification('Error al separar todos los productos', 'error');
                    }
                    
                    // Recargar la página después de un momento
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                separacionesFallidas++;
                
                // Si es el último producto, mostrar resultado final
                if (index === productosConSeparar.length - 1) {
                    showNotification(`Error en la separación: ${separacionesExitosas} exitosas, ${separacionesFallidas} fallidas`, 'error');
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                }
            });
        });
    }
}

// Mostrar notificación
function showNotification(message, type = 'info') {
    // Crear elemento de notificación
    const notification = document.createElement('div');
    notification.className = `alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show`;
    notification.style.position = 'fixed';
    notification.style.top = '20px';
    notification.style.right = '20px';
    notification.style.zIndex = '9999';
    notification.style.minWidth = '300px';
    
    notification.innerHTML = `
        ${message}
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    `;
    
    document.body.appendChild(notification);
    
    // Auto-remover después de 5 segundos
    setTimeout(() => {
        if (notification.parentNode) {
            notification.parentNode.removeChild(notification);
        }
    }, 5000);
}
</script>
@endpush