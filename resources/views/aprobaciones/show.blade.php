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
                            <div class="col-md-4 text-right">
                                <a href="{{ route('aprobaciones.index') }}" class="btn btn-secondary">
                                    <i class="material-icons">arrow_back</i> Volver
                                </a>
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
                                    @if($cotizacion->estado_aprobacion === 'pendiente')
                                        <span class="badge badge-warning">Pendiente</span>
                                    @elseif($cotizacion->estado_aprobacion === 'aprobada_supervisor')
                                        <span class="badge badge-success">Aprobada</span>
                                        <br><small>{{ $cotizacion->fecha_aprobacion_supervisor ? $cotizacion->fecha_aprobacion_supervisor->format('d/m/Y H:i') : '' }}</small>
                                        <br><small>Por: {{ $cotizacion->aprobadoPorSupervisor->name ?? 'N/A' }}</small>
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
                                    @if($cotizacion->estado_aprobacion === 'aprobada_supervisor')
                                        <span class="badge badge-warning">Pendiente</span>
                                    @elseif($cotizacion->estado_aprobacion === 'aprobada_compras')
                                        <span class="badge badge-success">Aprobada</span>
                                        <br><small>{{ $cotizacion->fecha_aprobacion_compras ? $cotizacion->fecha_aprobacion_compras->format('d/m/Y H:i') : '' }}</small>
                                        <br><small>Por: {{ $cotizacion->aprobadoPorCompras->name ?? 'N/A' }}</small>
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
                                    @if($cotizacion->estado_aprobacion === 'pendiente_picking')
                                        <span class="badge badge-warning">Pendiente</span>
                                    @elseif($cotizacion->estado_aprobacion === 'aprobada_compras')
                                        <span class="badge badge-warning">Pendiente</span>
                                    @elseif($cotizacion->estado_aprobacion === 'aprobada_picking')
                                        <span class="badge badge-success">Aprobada</span>
                                        <br><small>{{ $cotizacion->fecha_aprobacion_picking ? $cotizacion->fecha_aprobacion_picking->format('d/m/Y H:i') : '' }}</small>
                                        <br><small>Por: {{ $cotizacion->aprobadoPorPicking->name ?? 'N/A' }}</small>
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
                                            <th>Código</th>
                                            <th>Producto</th>
                                            <th>Cantidad</th>
                                            <th>Precio Unit.</th>
                                            <th>Subtotal</th>
                                            <th>Stock</th>
                                            <th>Estado</th>
                                            @if(Auth::user()->hasRole('Cobranza') || Auth::user()->hasRole('Super Admin'))
                                                <th>Acciones</th>
                                            @endif
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($cotizacion->productos as $producto)
                                            <tr>
                                                <td>
                                                    <strong>{{ $producto->codigo_producto }}</strong>
                                                </td>
                                                <td>
                                                    {{ $producto->nombre_producto }}
                                                </td>
                                                <td>
                                                    <span class="badge badge-info">{{ $producto->cantidad }}</span>
                                                </td>
                                                <td>
                                                    ${{ number_format($producto->precio_unitario, 0) }}
                                                </td>
                                                <td>
                                                    <strong>${{ number_format($producto->cantidad * $producto->precio_unitario, 0) }}</strong>
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
                                                @if(Auth::user()->hasRole('Cobranza') || Auth::user()->hasRole('Super Admin'))
                                                    <td>
                                                        @if($producto->stock_disponible < $producto->cantidad)
                                                            <button type="button" 
                                                                    class="btn btn-warning btn-sm" 
                                                                    onclick="separarPorStock({{ $producto->id }}, '{{ $producto->nombre_producto }}')"
                                                                    title="Separar producto por problemas de stock">
                                                                <i class="material-icons">call_split</i>
                                                                Separar
                                                            </button>
                                                        @else
                                                            <span class="text-muted">-</span>
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
                    </div>
                </div>
            </div>
        </div>

        <!-- Acciones de Aprobación -->
        @if($puedeAprobar)
            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header card-header-success">
                            <h4 class="card-title">
                                <i class="material-icons">check_circle</i>
                                Acciones de Aprobación
                            </h4>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <button type="button" 
                                            class="btn btn-success btn-lg btn-block" 
                                            onclick="aprobarNota({{ $cotizacion->id }}, '{{ $tipoAprobacion }}')">
                                        <i class="material-icons">check</i>
                                        Aprobar Nota de Venta
                                    </button>
                                </div>
                                <div class="col-md-6">
                                    <button type="button" 
                                            class="btn btn-danger btn-lg btn-block" 
                                            onclick="rechazarNota({{ $cotizacion->id }})">
                                        <i class="material-icons">close</i>
                                        Rechazar Nota de Venta
                                    </button>
                                </div>
                            </div>
                            
                            @if($tipoAprobacion === 'compras' && $cotizacion->tiene_problemas_stock)
                                <hr>
                                <div class="row">
                                    <div class="col-md-12">
                                        <h6>Separar Productos Problemáticos:</h6>
                                        <p class="text-muted">
                                            Si solo algunos productos tienen problemas de stock, puedes separarlos en una nota de venta independiente.
                                        </p>
                                        <button type="button" 
                                                class="btn btn-warning" 
                                                onclick="mostrarSeparacionProductos()">
                                            <i class="material-icons">call_split</i>
                                            Separar Productos con Problemas de Stock
                                        </button>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>

<!-- Modal de Aprobación -->
<div class="modal fade" id="modalAprobacion" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Aprobar Nota de Venta #{{ $cotizacion->id }}</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="formAprobacion">
                    <input type="hidden" id="notaId" name="nota_id" value="{{ $cotizacion->id }}">
                    <input type="hidden" id="tipoAprobacion" name="tipo_aprobacion" value="{{ $tipoAprobacion }}">
                    
                    <div class="form-group">
                        <label for="comentarios">Comentarios (opcional)</label>
                        <textarea id="comentarios" name="comentarios" class="form-control" rows="3" 
                                  placeholder="Agregar comentarios sobre la aprobación..."></textarea>
                    </div>
                    
                    <div id="validacionStock" style="display: none;">
                        <div class="form-group">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="validarStockReal" name="validar_stock_real" value="1" checked>
                                <label class="custom-control-label" for="validarStockReal">
                                    Validar stock real antes de aprobar
                                </label>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" onclick="confirmarAprobacion()">
                    <i class="material-icons">check</i> Aprobar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Rechazo -->
<div class="modal fade" id="modalRechazo" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Rechazar Nota de Venta #{{ $cotizacion->id }}</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="formRechazo">
                    <input type="hidden" id="notaIdRechazo" name="nota_id" value="{{ $cotizacion->id }}">
                    
                    <div class="form-group">
                        <label for="motivoRechazo">Motivo del Rechazo *</label>
                        <textarea id="motivoRechazo" name="motivo" class="form-control" rows="3" 
                                  placeholder="Especificar el motivo del rechazo..." required></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" onclick="confirmarRechazo()">
                    <i class="material-icons">close</i> Rechazar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Separación por Stock -->
<div class="modal fade" id="modalSepararStock" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Separar Producto por Problemas de Stock</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="formSepararStock">
                    <input type="hidden" id="productoIdSeparar" name="producto_id" value="">
                    <input type="hidden" id="cotizacionIdSeparar" name="cotizacion_id" value="{{ $cotizacion->id }}">
                    
                    <div class="alert alert-warning">
                        <i class="material-icons">warning</i>
                        <strong>¿Estás seguro?</strong><br>
                        Esta acción creará una nueva NVV con solo el producto seleccionado y lo eliminará de la NVV actual.
                    </div>
                    
                    <div class="form-group">
                        <label><strong>Producto a separar:</strong></label>
                        <p id="productoNombreSeparar" class="form-control-plaintext"></p>
                    </div>
                    
                    <div class="form-group">
                        <label for="motivoSeparacion">Motivo de la Separación *</label>
                        <textarea id="motivoSeparacion" name="motivo" class="form-control" rows="3" 
                                  placeholder="Especificar el motivo de la separación por problemas de stock..." required></textarea>
                        <small class="form-text text-muted">Este motivo se registrará en el historial y se notificará al vendedor.</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-warning" onclick="confirmarSeparacionStock()">
                    <i class="material-icons">call_split</i> Separar Producto
                </button>
            </div>
        </div>
    </div>
</div>

    <!-- Historial de la Cotización -->
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header card-header-success">
                    <h4 class="card-title">
                        <i class="material-icons">history</i>
                        Historial de la Cotización
                    </h4>
                </div>
                <div class="card-body">
                    <!-- Resumen de tiempos -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card bg-gradient-primary text-white">
                                <div class="card-body text-center">
                                    <h4 class="mb-0">{{ $resumenTiempos['tiempo_total'] ?? 'N/A' }} hrs</h4>
                                    <small>Tiempo Total</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-gradient-{{ $resumenTiempos['objetivo_cumplido'] ? 'success' : 'warning' }} text-white">
                                <div class="card-body text-center">
                                    <h4 class="mb-0">
                                        <i class="material-icons">{{ $resumenTiempos['objetivo_cumplido'] ? 'check_circle' : 'warning' }}</i>
                                    </h4>
                                    <small>{{ $resumenTiempos['objetivo_cumplido'] ? 'En Tiempo' : 'Retrasado' }}</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-gradient-info text-white">
                                <div class="card-body text-center">
                                    <h4 class="mb-0">{{ $historial->count() }}</h4>
                                    <small>Estados</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-gradient-success text-white">
                                <div class="card-body text-center">
                                    <h4 class="mb-0">{{ $historial->last()->estado_nuevo ?? 'N/A' }}</h4>
                                    <small>Estado Actual</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Timeline del historial -->
                    <div class="timeline">
                        @foreach($historial as $index => $registro)
                        <div class="timeline-item">
                            <div class="timeline-marker bg-{{ \App\Helpers\EstadoHelper::getEstadoColor($registro->estado_nuevo) }}"></div>
                            <div class="timeline-content">
                                <div class="card">
                                    <div class="card-header">
                                        <div class="row align-items-center">
                                            <div class="col-8">
                                                <h5 class="mb-0">
                                                    <i class="material-icons">{{ \App\Helpers\EstadoHelper::getEstadoIcon($registro->tipo_accion) }}</i>
                                                    {{ \App\Helpers\EstadoHelper::getEstadoNombre($registro->estado_nuevo) }}
                                                </h5>
                                                <small class="text-muted">
                                                    {{ $registro->fecha_accion->format('d/m/Y H:i:s') }}
                                                </small>
                                            </div>
                                            <div class="col-4 text-right">
                                                @if($registro->tiempo_transcurrido_segundos)
                                                <span class="badge badge-info">
                                                    {{ $registro->tiempo_transcurrido_formateado }}
                                                </span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <p><strong>Usuario:</strong> {{ $registro->usuario_nombre ?? 'Sistema' }}</p>
                                                <p><strong>Rol:</strong> {{ $registro->rol_usuario ?? 'N/A' }}</p>
                                                @if($registro->estado_anterior)
                                                <p><strong>Estado Anterior:</strong> {{ \App\Helpers\EstadoHelper::getEstadoNombre($registro->estado_anterior) }}</p>
                                                @endif
                                            </div>
                                            <div class="col-md-6">
                                                <p><strong>Tipo de Acción:</strong> {{ \App\Helpers\EstadoHelper::getTipoAccionNombre($registro->tipo_accion) }}</p>
                                                @if($registro->comentarios)
                                                <p><strong>Comentarios:</strong> {{ $registro->comentarios }}</p>
                                                @endif
                                            </div>
                                        </div>
                                        
                                        @if($registro->detalles_adicionales)
                                        <div class="mt-3">
                                            <h6>Detalles Adicionales:</h6>
                                            <div class="table-responsive">
                                                <table class="table table-sm">
                                                    @foreach($registro->detalles_adicionales as $key => $value)
                                                    <tr>
                                                        <td><strong>{{ ucfirst(str_replace('_', ' ', $key)) }}:</strong></td>
                                                        <td>{{ is_array($value) ? json_encode($value) : $value }}</td>
                                                    </tr>
                                                    @endforeach
                                                </table>
                                            </div>
                                        </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>

                    @if($historial->isEmpty())
                    <div class="text-center py-4">
                        <i class="material-icons text-muted" style="font-size: 3rem;">history</i>
                        <h4 class="text-muted mt-3">No hay historial disponible</h4>
                        <p class="text-muted">Esta cotización aún no tiene registros de historial.</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 15px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e9ecef;
}

.timeline-item {
    position: relative;
    margin-bottom: 30px;
}

.timeline-marker {
    position: absolute;
    left: -22px;
    top: 20px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    border: 3px solid #fff;
    box-shadow: 0 0 0 3px #e9ecef;
}

.timeline-content {
    margin-left: 20px;
}
</style>

@endsection

@push('js')
<script>
// Aprobar nota de venta
function aprobarNota(notaId, tipo) {
    document.getElementById('notaId').value = notaId;
    document.getElementById('tipoAprobacion').value = tipo;
    
    // Mostrar validación de stock solo para picking
    if (tipo === 'picking') {
        document.getElementById('validacionStock').style.display = 'block';
    } else {
        document.getElementById('validacionStock').style.display = 'none';
    }
    
    $('#modalAprobacion').modal('show');
}

// Confirmar aprobación
function confirmarAprobacion() {
    const notaId = document.getElementById('notaId').value;
    const tipo = document.getElementById('tipoAprobacion').value;
    const comentarios = document.getElementById('comentarios').value;
    const validarStock = document.getElementById('validarStockReal') ? document.getElementById('validarStockReal').checked : false;
    
    let url = '';
    let data = {};
    
    if (tipo === 'supervisor') {
        url = `/aprobaciones/${notaId}/supervisor`;
        data = { comentarios };
    } else if (tipo === 'compras') {
        url = `/aprobaciones/${notaId}/compras`;
        data = { comentarios };
    } else if (tipo === 'picking') {
        url = `/aprobaciones/${notaId}/picking`;
        data = { comentarios, validar_stock_real: validarStock };
    }
    
    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('success', 'Nota de venta aprobada exitosamente');
            $('#modalAprobacion').modal('hide');
            setTimeout(() => location.reload(), 1500);
        } else {
            showNotification('danger', data.error || 'Error al aprobar la nota de venta');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('danger', 'Error al procesar la solicitud');
    });
}

// Rechazar nota de venta
function rechazarNota(notaId) {
    $('#modalRechazo').modal('show');
}

// Confirmar rechazo
function confirmarRechazo() {
    const notaId = document.getElementById('notaIdRechazo').value;
    const motivo = document.getElementById('motivoRechazo').value;
    
    if (!motivo.trim()) {
        showNotification('warning', 'Debe especificar un motivo para el rechazo');
        return;
    }
    
    fetch(`/aprobaciones/${notaId}/rechazar`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({ motivo })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('success', 'Nota de venta rechazada exitosamente');
            $('#modalRechazo').modal('hide');
            setTimeout(() => location.reload(), 1500);
        } else {
            showNotification('danger', data.error || 'Error al rechazar la nota de venta');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('danger', 'Error al procesar la solicitud');
    });
}

// Separar producto por stock
function separarPorStock(productoId, productoNombre) {
    document.getElementById('productoIdSeparar').value = productoId;
    document.getElementById('productoNombreSeparar').textContent = productoNombre;
    document.getElementById('motivoSeparacion').value = '';
    $('#modalSepararStock').modal('show');
}

// Confirmar separación por stock
function confirmarSeparacionStock() {
    const productoId = document.getElementById('productoIdSeparar').value;
    const cotizacionId = document.getElementById('cotizacionIdSeparar').value;
    const motivo = document.getElementById('motivoSeparacion').value;
    
    if (!motivo.trim()) {
        showNotification('warning', 'Por favor, especifica el motivo de la separación');
        return;
    }
    
    fetch(`/aprobaciones/${cotizacionId}/separar-por-stock`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({ 
            producto_id: productoId,
            motivo: motivo
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('success', data.message || 'Producto separado exitosamente');
            $('#modalSepararStock').modal('hide');
            setTimeout(() => location.reload(), 1500);
        } else {
            showNotification('danger', data.error || 'Error al separar el producto');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('danger', 'Error al procesar la solicitud');
    });
}

// Mostrar notificación
function showNotification(type, message) {
    const alertClass = type === 'success' ? 'alert-success' : type === 'warning' ? 'alert-warning' : type === 'info' ? 'alert-info' : 'alert-danger';
    const icon = type === 'success' ? 'check_circle' : type === 'warning' ? 'warning' : type === 'info' ? 'info' : 'error';
    
    const alert = document.createElement('div');
    alert.className = `alert ${alertClass} alert-dismissible fade show`;
    alert.innerHTML = `
        <i class="material-icons">${icon}</i>
        ${message}
        <button type="button" class="close" data-dismiss="alert">
            <span>&times;</span>
        </button>
    `;
    
    document.querySelector('.content').insertBefore(alert, document.querySelector('.content').firstChild);
    
    setTimeout(() => {
        alert.remove();
    }, 5000);
}
</script>
@endpush
