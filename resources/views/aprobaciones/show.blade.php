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
                                <button type="button" class="btn btn-warning ml-2" onclick="sincronizarStock()" id="btnSincronizarStock">
                                    <i class="material-icons">refresh</i> Sincronizar Productos
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal de Sincronización -->
        <div class="modal fade" id="modalSincronizacion" tabindex="-1" role="dialog" aria-labelledby="modalSincronizacionLabel" aria-hidden="true" data-backdrop="static" data-keyboard="false">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header bg-warning">
                        <h5 class="modal-title" id="modalSincronizacionLabel">
                            <i class="material-icons">refresh</i> Sincronizando Stock
                        </h5>
                    </div>
                    <div class="modal-body text-center">
                        <div class="spinner-border text-warning" role="status" style="width: 3rem; height: 3rem;">
                            <span class="sr-only">Sincronizando...</span>
                        </div>
                        <h5 class="mt-3" id="mensajeSincronizacion">Sincronizando productos desde SQL Server...</h5>
                        <p class="text-muted" id="detalleSincronizacion">Por favor, espere. Esto puede tomar varios minutos.</p>
                        <div class="progress mt-3" style="height: 25px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated bg-warning" role="progressbar" style="width: 100%" id="progressBar">
                                <span id="progressText">Procesando...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal de Éxito NVV -->
        @if(session('success') && session('numero_nvv'))
        <div class="modal fade" id="modalExitoNVV" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title">
                            <i class="material-icons">check_circle</i> NVV Creada Exitosamente
                        </h5>
                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body text-center">
                        <div class="alert alert-success">
                            <h3><i class="material-icons" style="font-size: 48px;">assignment_turned_in</i></h3>
                            <h4>NVV N° {{ session('numero_nvv') }}</h4>
                            <p class="mb-0">Insertada correctamente en SQL Server</p>
                        </div>
                        <div class="text-left">
                            <p><strong>📋 Número NVV:</strong> {{ session('numero_nvv') }}</p>
                            <p><strong>🔢 ID Interno:</strong> {{ session('id_nvv_interno') }}</p>
                            <p><strong>👤 Cliente:</strong> {{ $cotizacion->cliente_nombre }}</p>
                            <p><strong>💰 Total:</strong> ${{ number_format($cotizacion->total, 0, ',', '.') }}</p>
                            <p><strong>📦 Productos:</strong> {{ $cotizacion->productos->count() }}</p>
                            <p><strong>⏰ Fecha:</strong> {{ now()->format('d/m/Y H:i:s') }}</p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                        <a href="{{ route('aprobaciones.index') }}" class="btn btn-primary">
                            <i class="material-icons">list</i> Ver Todas las NVV
                        </a>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- Alertas de Sesión -->
        @if(session('success'))
            <div class="row">
                <div class="col-md-12">
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                        <strong><i class="material-icons">check_circle</i> Éxito!</strong>
                        <pre style="white-space: pre-wrap; font-family: inherit; margin: 10px 0 0 0;">{{ session('success') }}</pre>
                    </div>
                </div>
            </div>
        @endif

        @if(session('error'))
            <div class="row">
                <div class="col-md-12">
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                        <strong><i class="material-icons">error</i> Error!</strong>
                        <pre style="white-space: pre-wrap; font-family: inherit; margin: 10px 0 0 0;">{{ session('error') }}</pre>
                    </div>
                </div>
            </div>
        @endif

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
                                @if($cotizacion->tiene_problemas_credito)
                                    <p><strong>Solicita Descuento Extra:</strong> <span class="badge badge-warning">SÍ</span></p>
                                @endif
                            </div>
                            <div class="col-md-6">
                                <p><strong>Cliente:</strong> {{ $cotizacion->cliente_codigo }}</p>
                                <p><strong>Nombre:</strong> {{ $cotizacion->cliente_nombre }}</p>
                                <p><strong>Dirección:</strong> {{ $cotizacion->cliente_direccion ?: 'No especificada' }}</p>
                                <p><strong>Teléfono:</strong> {{ $cotizacion->cliente_telefono ?: 'No especificado' }}</p>
                                @if($cotizacion->numero_orden_compra)
                                    <p><strong>Orden de Compra:</strong> {{ $cotizacion->numero_orden_compra }}</p>
                                @endif
                            </div>
                        </div>
                        
                        @if($cotizacion->observacion_vendedor)
                            <div class="row mt-3">
                                <div class="col-md-12">
                                    <div class="alert alert-info">
                                        <h6><i class="material-icons">message</i> Observación del Vendedor</h6>
                                        <p class="mb-0">{{ $cotizacion->observacion_vendedor }}</p>
                                    </div>
                                </div>
                            </div>
                        @endif
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
                                <p><strong>Subtotal:</strong> <span id="resumen-subtotal">${{ number_format($cotizacion->subtotal, 0) }}</span></p>
                                <p><strong>Descuento:</strong> <span id="resumen-descuento">${{ number_format($cotizacion->descuento_global, 0) }}</span></p>
                                <p><strong>Total:</strong> <span id="resumen-total">${{ number_format($cotizacion->total, 0) }}</span></p>
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
                                @if($cotizacion->estado_aprobacion === 'aprobada_picking')
                                    <span class="badge badge-success">Aprobada</span>
                                    <br><small>{{ $cotizacion->fecha_aprobacion_picking ? $cotizacion->fecha_aprobacion_picking->format('d/m/Y H:i') : '' }}</small>
                                    <br><small>Por: {{ $cotizacion->aprobadoPorPicking->name ?? 'N/A' }}</small>
                                @elseif($cotizacion->estado_aprobacion === 'pendiente_entrega')
                                    <span class="badge badge-warning">Pendiente de Entrega</span>
                                    <br><small>{{ $cotizacion->fecha_aprobacion_picking ? $cotizacion->fecha_aprobacion_picking->format('d/m/Y H:i') : '' }}</small>
                                    <br><small>Por: {{ $cotizacion->aprobadoPorPicking->name ?? 'N/A' }}</small>
                                    @if($cotizacion->observaciones_picking)
                                        <br><small class="text-info"><i class="material-icons">info</i> {{ Str::limit($cotizacion->observaciones_picking, 50) }}</small>
                                    @endif
                                @endif
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
                            @if($cotizacion->estado_aprobacion === 'aprobada_picking')
                                <div class="col-md-12 text-center">
                                        @if($cotizacion->numero_nvv)
                                            <br><br>
                                            <div class="alert alert-success" style="padding: 10px; margin-top: 10px;">
                                                <strong><i class="material-icons" style="font-size: 18px; vertical-align: middle;">assignment</i> NVV Generada</strong>
                                                <br>
                                                <h4 style="margin: 5px 0;">N° {{ str_pad($cotizacion->numero_nvv, 10, '0', STR_PAD_LEFT) }}</h4>
                                                <br>
                                                <a href="{{ route('nvv-pendientes.ver', str_pad($cotizacion->numero_nvv, 10, '0', STR_PAD_LEFT)) }}" class="btn btn-sm btn-info mt-2">
                                                    <i class="material-icons">visibility</i> Ver NVV en Sistema
                                                </a>
                                            </div>
                                            @endif
                                </div>
                                        
                            @endif
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
                                            <th>Desc. (%)</th>
                                            <th>Desc. ($)</th>
                                            <th>Subtotal</th>
                                            <th>IVA (19%)</th>
                                            <th>Total</th>
                                            <th>Stock</th>
                                            <th>Estado</th>
                                            @if(Auth::user()->hasRole('Compras') && $cotizacion->tiene_problemas_stock)
                                                <th>Acciones</th>
                                            @endif
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($cotizacion->productos as $producto)
                                            <tr data-producto-id="{{ $producto->id }}" data-cantidad="{{ $producto->cantidad }}" data-precio="{{ $producto->precio_unitario }}">
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
                                                        @php
                                                            $multiploVenta = optional(\App\Models\Producto::where('KOPR', $producto->codigo_producto)->first())->multiplo_venta ?? 1;
                                                            if ($multiploVenta <= 0) { $multiploVenta = 1; }
                                                        @endphp
                                                        <div class="input-group input-group-sm">
                                                            <input type="number" class="form-control separar-input" 
                                                                   value="{{ $producto->cantidad_separar ?? 0 }}" 
                                                                   min="{{ $multiploVenta }}" 
                                                                   step="{{ $multiploVenta }}" 
                                                                   max="{{ $producto->cantidad }}"
                                                                   data-producto-id="{{ $producto->id }}"
                                                                   data-precio="{{ $producto->precio_unitario }}"
                                                                   data-cantidad-original="{{ $producto->cantidad }}"
                                                                   data-multiplo="{{ $multiploVenta }}">
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
                                                    @if(Auth::user()->hasRole('Supervisor') && $cotizacion->puedeAprobarSupervisor())
                                                        <input type="number" 
                                                               class="form-control form-control-sm descuento-porcentaje" 
                                                               value="{{ $producto->descuento_porcentaje ?? 0 }}" 
                                                               min="0" 
                                                               max="100" 
                                                               step="0.01"
                                                               data-producto-id="{{ $producto->id }}"
                                                               onchange="actualizarDescuento({{ $producto->id }})"
                                                               style="width: 80px;">
                                                    @else
                                                        <span class="badge badge-warning">{{ $producto->descuento_porcentaje ?? 0 }}%</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <span class="descuento-valor-{{ $producto->id }}">
                                                        ${{ number_format($producto->descuento_valor ?? 0, 0) }}
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="subtotal-con-descuento-{{ $producto->id }}">
                                                        ${{ number_format($producto->subtotal_con_descuento ?? ($producto->cantidad * $producto->precio_unitario), 0) }}
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="iva-valor-{{ $producto->id }}">
                                                        ${{ number_format($producto->iva_valor ?? 0, 0) }}
                                                    </span>
                                                </td>
                                                <td>
                                                    <strong class="total-{{ $producto->id }}">
                                                        ${{ number_format($producto->total_producto ?? (($producto->subtotal_con_descuento ?? ($producto->cantidad * $producto->precio_unitario)) * 1.19), 0) }}
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
            ($cotizacion->puedeAprobarPicking() && Auth::user()->hasRole('Picking')) ||
            ($cotizacion->estado_aprobacion === 'pendiente_entrega' && Auth::user()->hasRole('Picking')))
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
                                    <p>Esta nota de venta requiere tu aprobación. Puedes modificar los descuentos antes de aprobar.</p>
                                </div>
                                <button type="button" class="btn btn-warning btn-lg" id="btnGuardarDescuentos" onclick="guardarCambiosDescuentos({{ $cotizacion->id }})">
                                    <i class="material-icons">save</i> Guardar Cambios de Descuentos
                                </button>
                                <br><br>
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
                            @elseif(($cotizacion->puedeAprobarPicking() || $cotizacion->estado_aprobacion === 'pendiente_entrega') && Auth::user()->hasRole('Picking'))
                                @if($cotizacion->estado_aprobacion === 'pendiente_entrega')
                                    <div class="alert alert-info">
                                        <h6><i class="material-icons">local_shipping</i> Nota de Venta Pendiente de Entrega</h6>
                                        <p>Esta nota de venta está marcada como pendiente de entrega. Puedes aprobarla cuando esté lista.</p>
                                        @if($cotizacion->observaciones_picking)
                                            <p><strong>Observaciones:</strong> {{ $cotizacion->observaciones_picking }}</p>
                                        @endif
                                        <p><strong>⚠️ IMPORTANTE:</strong> Al aprobar se insertará la NVV en la base de datos de producción.</p>
                                    </div>
                                @else
                                    <div class="alert alert-warning">
                                        <h6><i class="material-icons">local_shipping</i> Requiere Aprobación Final de Picking</h6>
                                        <p>Esta nota de venta requiere tu aprobación.</p>
                                        <p><strong>⚠️ IMPORTANTE:</strong> Al aprobar se insertará la NVV en la base de datos de producción.</p>
                                    </div>
                                @endif
                                <form id="formAprobarPicking" action="{{ route('aprobaciones.picking', $cotizacion->id) }}" method="POST" style="display: inline;">
                                    @csrf
                                    <input type="hidden" name="validar_stock_real" value="0">
                                    <input type="hidden" name="comentarios" value="">
                                    <button type="submit" id="btnAprobarPicking" class="btn btn-success btn-lg" onclick="return confirmarAprobacionPicking(event)">
                                        <i class="material-icons">check</i> <span id="textoBotonAprobar">Aprobar</span>
                                    </button>
                                </form>
                                @if($cotizacion->estado_aprobacion !== 'pendiente_entrega')
                                    <button type="button" class="btn btn-warning btn-lg ml-2" onclick="mostrarModalGuardarPendiente()">
                                        <i class="material-icons">save</i> Guardar Pendiente
                                    </button>
                                @else
                                    <button type="button" class="btn btn-warning btn-lg ml-2" onclick="mostrarModalGuardarPendiente()">
                                        <i class="material-icons">edit</i> Actualizar Pendiente
                                    </button>
                                @endif
                                <button type="button" class="btn btn-danger btn-lg ml-2" onclick="rechazarNota({{ $cotizacion->id }})">
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

@push('js')
<script>
// Mostrar modal de éxito automáticamente si existe
@if(session('success') && session('numero_nvv'))
$(document).ready(function() {
    $('#modalExitoNVV').modal('show');
});
@endif
</script>
@endpush

<!-- Modal para Guardar Pendiente de Entrega -->
<div class="modal fade" id="modalGuardarPendiente" tabindex="-1" role="dialog" aria-labelledby="modalGuardarPendienteLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-warning text-white">
                <h5 class="modal-title" id="modalGuardarPendienteLabel">
                    <i class="material-icons">save</i>
                    Guardar como Pendiente de Entrega
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="material-icons">info</i>
                    <strong>Información:</strong> Esta acción marcará la nota de venta como "Pendiente de Entrega" para uso interno.
                    Los productos que no están disponibles se entregarán cuando lleguen al almacén.
                </div>
                
                <form id="formGuardarPendiente" action="{{ route('aprobaciones.guardar-pendiente-entrega', $cotizacion->id) }}" method="POST">
                    @csrf
                    <div class="form-group">
                        <label for="observaciones_picking" class="bmd-label-floating">
                            <i class="material-icons">comment</i>
                            Observaciones Internas <span class="text-danger">*</span>
                        </label>
                        <textarea 
                            class="form-control" 
                            id="observaciones_picking" 
                            name="observaciones_picking" 
                            rows="4" 
                            maxlength="1000"
                            placeholder="Ejemplo: Producto ABC123 llegará mañana, Producto XYZ789 llegará en 3 días..."
                            required>{{ old('observaciones_picking', $cotizacion->observaciones_picking) }}</textarea>
                        <small class="form-text text-muted">
                            <i class="material-icons">info</i>
                            Describe qué productos están pendientes y cuándo llegarán (máximo 1000 caracteres)
                        </small>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th style="width: 40px;">Pend.</th>
                                    <th>Producto</th>
                                    <th class="text-right">Cant.</th>
                                    <th class="text-right">Stock Disp.</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($cotizacion->productos as $producto)
                                <tr>
                                    <td>
                                        <input type="checkbox" name="productos_pendientes[]" value="{{ $producto->id }}" @if($producto->pendiente_entrega ?? false) checked @endif>
                                    </td>
                                    <td>{{ $producto->codigo_producto }} - {{ $producto->nombre_producto }}</td>
                                    <td class="text-right">{{ number_format($producto->cantidad, 0) }}</td>
                                    <td class="text-right">{{ number_format($producto->stock_disponible ?? 0, 0) }}</td>
                                    <td>
                                        @if($producto->pendiente_entrega ?? false)
                                            <span class="badge badge-warning">Pendiente</span>
                                        @else
                                            <span class="badge badge-success">Embalado</span>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="material-icons">close</i> Cancelar
                </button>
                <button type="button" class="btn btn-warning" onclick="guardarPendienteEntrega()">
                    <i class="material-icons">save</i> Guardar Pendiente
                </button>
            </div>
        </div>
    </div>
</div>

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
        
        // Mostrar mensaje de procesamiento
        const alert = document.createElement('div');
        alert.className = 'alert alert-info';
        alert.style.cssText = 'position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 9999; padding: 20px; text-align: center;';
        alert.innerHTML = '<i class="material-icons">hourglass_empty</i> Procesando aprobación e insertando en SQL Server. Por favor espera...';
        document.body.appendChild(alert);
        
        // Timeout de 30 segundos
        setTimeout(() => {
            if (alert.parentNode) {
                alert.remove();
                const errorAlert = document.createElement('div');
                errorAlert.className = 'alert alert-warning';
                errorAlert.style.cssText = 'position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 9999; padding: 20px; text-align: center;';
                errorAlert.innerHTML = '<i class="material-icons">warning</i> El proceso está tomando más tiempo del esperado. Recarga la página para verificar el estado.';
                document.body.appendChild(errorAlert);
                setTimeout(() => errorAlert.remove(), 5000);
            }
        }, 30000);
        
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

// Confirmar y bloquear botón de aprobación Picking
function confirmarAprobacionPicking(event) {
    console.log('🔵 INICIO: confirmarAprobacionPicking llamado');
    console.log('Event:', event);
    
    event.preventDefault(); // Prevenir el envío automático primero
    
    if (!confirm('¿Estás seguro de aprobar esta nota de venta? Se insertará en la base de datos de producción.')) {
        console.log('🔴 Usuario canceló la confirmación');
        return false;
    }
    
    console.log('✅ Usuario confirmó la aprobación');
    
    // Bloquear el botón para evitar doble clic
    const btn = document.getElementById('btnAprobarPicking');
    const texto = document.getElementById('textoBotonAprobar');
    const form = document.getElementById('formAprobarPicking');
    
    console.log('Botón encontrado:', btn);
    console.log('Texto encontrado:', texto);
    console.log('Formulario encontrado:', form);
    
    if (btn) {
        btn.disabled = true;
        btn.classList.remove('btn-success');
        btn.classList.add('btn-secondary');
        texto.innerHTML = 'Procesando...';
        
        console.log('✅ Botón bloqueado y texto cambiado');
        
        // Mostrar mensaje de espera
        const alert = document.createElement('div');
        alert.className = 'alert alert-info mt-3';
        alert.innerHTML = '<i class="material-icons">hourglass_empty</i> Procesando aprobación e insertando en SQL Server. Por favor espera...';
        btn.parentElement.parentElement.appendChild(alert);
        
        console.log('✅ Mensaje de espera mostrado');
    }
    
    // Enviar el formulario manualmente
    if (form) {
        console.log('🟢 Enviando formulario manualmente...');
        form.submit();
        console.log('✅ Formulario enviado');
    } else {
        console.error('❌ ERROR: Formulario no encontrado');
    }
    
    return false; // Prevenir el envío automático ya que lo hacemos manualmente
}

// Función para mostrar modal de guardar pendiente
function mostrarModalGuardarPendiente() {
    $('#modalGuardarPendiente').modal('show');
}

// Función para guardar como pendiente de entrega
function guardarPendienteEntrega() {
    const observaciones = document.getElementById('observaciones_picking').value.trim();
    
    if (!observaciones) {
        alert('Por favor, ingresa las observaciones sobre los productos pendientes.');
        return;
    }
    
    if (observaciones.length < 10) {
        alert('Las observaciones deben tener al menos 10 caracteres.');
        return;
    }
    
    if (!confirm('¿Estás seguro de guardar esta nota de venta como pendiente de entrega?')) {
        return;
    }
    
    // Enviar el formulario
    document.getElementById('formGuardarPendiente').submit();
}

// Ver detalle de NVV en sistema SQL Server
function verDetalleNVV(numeroNVV) {
    alert('🔍 Consultando NVV N° ' + numeroNVV + ' en SQL Server...\n\n' +
          'Esta funcionalidad mostrará los detalles de la NVV directamente desde SQL Server.\n\n' +
          'Por ahora puedes verificar la NVV en el sistema principal de gestión.');
    // TODO: Implementar consulta real a SQL Server para mostrar detalles
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
    const cantidadSepararRaw = parseFloat(separarInput.value) || 0;
    const cantidadMaxima = parseFloat(separarInput.max) || 0;
    const multiplo = parseInt(separarInput.getAttribute('data-multiplo')) || 1;

    // Ajustar a múltiplos válidos
    let cantidadSeparar = cantidadSepararRaw;
    if (multiplo > 1) {
        cantidadSeparar = Math.floor(cantidadSepararRaw / multiplo) * multiplo;
        if (cantidadSeparar === 0 && cantidadSepararRaw > 0) {
            cantidadSeparar = multiplo; // mínimo el primer múltiplo
        }
        // No exceder máximo
        if (cantidadSeparar > cantidadMaxima) {
            cantidadSeparar = Math.floor(cantidadMaxima / multiplo) * multiplo;
        }
        separarInput.value = cantidadSeparar; // reflejar ajuste
    }
    
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
    const cantidadSepararRaw = parseFloat(separarInput?.value) || 0;
    const multiplo = parseInt(separarInput?.getAttribute('data-multiplo')) || 1;
    let cantidadSeparar = cantidadSepararRaw;
    if (multiplo > 1) {
        cantidadSeparar = Math.floor(cantidadSepararRaw / multiplo) * multiplo;
        if (cantidadSeparar === 0 && cantidadSepararRaw > 0) cantidadSeparar = multiplo;
        separarInput.value = cantidadSeparar;
    }
    
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

// Función para actualizar descuento en tiempo real
function actualizarDescuento(productoId) {
    const input = document.querySelector(`input[data-producto-id="${productoId}"]`);
    if (!input) {
        console.error('No se encontró el input para el producto:', productoId);
        return;
    }
    
    const porcentaje = parseFloat(input.value) || 0;
    
    // Validar que el porcentaje esté entre 0 y 100
    if (porcentaje < 0 || porcentaje > 100) {
        alert('El descuento debe estar entre 0 y 100%');
        input.value = 0;
        return;
    }
    
    // Obtener datos del producto desde la fila de la tabla
    const row = input.closest('tr');
    if (!row) {
        console.error('No se encontró la fila para el producto:', productoId);
        return;
    }
    
    // Obtener cantidad y precio desde data-* del <tr>
    const cantidad = parseFloat(row?.dataset?.cantidad || '0');
    const precioUnitario = parseFloat(row?.dataset?.precio || '0');
    
    // Calcular valores
    const subtotal = cantidad * precioUnitario;
    const descuentoValor = (subtotal * porcentaje) / 100;
    const subtotalConDescuento = subtotal - descuentoValor;
    const iva = subtotalConDescuento * 0.19;
    const total = subtotalConDescuento + iva;
    
    // Actualizar valores en la tabla usando clases específicas
    const descuentoValorEl = row.querySelector(`.descuento-valor-${productoId}`);
    const subtotalEl = row.querySelector(`.subtotal-con-descuento-${productoId}`);
    const ivaEl = row.querySelector(`.iva-valor-${productoId}`);
    const totalEl = row.querySelector(`.total-${productoId}`);
    
    if (descuentoValorEl) {
        descuentoValorEl.textContent = '$' + Math.round(descuentoValor).toLocaleString('es-CL');
    } else {
        console.warn('No se encontró el elemento de descuento valor para el producto:', productoId);
    }
    
    if (subtotalEl) {
        subtotalEl.textContent = '$' + Math.round(subtotalConDescuento).toLocaleString('es-CL');
    } else {
        console.warn('No se encontró el elemento de subtotal para el producto:', productoId);
    }
    
    if (ivaEl) {
        ivaEl.textContent = '$' + Math.round(iva).toLocaleString('es-CL');
    } else {
        console.warn('No se encontró el elemento de IVA para el producto:', productoId);
    }
    
    if (totalEl) {
        totalEl.textContent = '$' + Math.round(total).toLocaleString('es-CL');
    } else {
        console.warn('No se encontró el elemento de total para el producto:', productoId);
    }
}

// Función para guardar cambios de descuentos
function guardarCambiosDescuentos(notaId) {
    const descuentos = [];
    
    // Recopilar todos los descuentos modificados
    document.querySelectorAll('.descuento-porcentaje').forEach(input => {
        const productoId = input.dataset.productoId;
        const porcentaje = parseFloat(input.value) || 0;
        
        descuentos.push({
            producto_id: productoId,
            descuento_porcentaje: porcentaje
        });
    });
    
    if (descuentos.length === 0) {
        alert('No hay descuentos para guardar');
        return;
    }
    
    // Buscar el botón de guardar
    const btn = document.getElementById('btnGuardarDescuentos');
    let originalText = '';
    if (btn) {
        originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="material-icons">hourglass_empty</i> Guardando...';
    }
    
    // Enviar petición AJAX
    fetch(`/aprobaciones/${notaId}/modificar-descuentos`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            descuentos: descuentos
        })
    })
    .then(response => {
        if (!response.ok) {
            return response.text().then(text => {
                throw new Error(text);
            });
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            showNotification('Descuentos actualizados correctamente', 'success');
            if (data.totales) {
                const resumenSub = document.getElementById('resumen-subtotal');
                const resumenDesc = document.getElementById('resumen-descuento');
                const resumenTotal = document.getElementById('resumen-total');
                if (resumenSub) resumenSub.textContent = '$' + Math.round(data.totales.subtotal_neto || 0).toLocaleString('es-CL');
                if (resumenDesc) resumenDesc.textContent = '$' + Math.round(data.totales.descuento || 0).toLocaleString('es-CL');
                if (resumenTotal) resumenTotal.textContent = '$' + Math.round(data.totales.total || 0).toLocaleString('es-CL');
            }
            if (btn) { btn.disabled = false; btn.innerHTML = originalText; }
        } else {
            showNotification('Error al actualizar descuentos: ' + (data.message || 'Error desconocido'), 'error');
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error al actualizar descuentos: ' + error.message, 'error');
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    });
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

// Función para sincronizar stock desde SQL Server
function sincronizarStock() {
    const btn = document.getElementById('btnSincronizarStock');
    
    if (!btn) {
        console.error('Error: No se encontró el botón de sincronización');
        showNotification('Error: No se encontró el botón de sincronización', 'error');
        return;
    }
    
    // Confirmar antes de sincronizar
    if (!confirm('¿Está seguro de que desea sincronizar el stock de productos desde SQL Server? Esto puede tomar varios minutos si hay muchos productos.')) {
        return;
    }
    
    const originalText = btn.innerHTML;
    
    // Deshabilitar botón y mostrar estado de carga
    btn.disabled = true;
    btn.innerHTML = '<i class="material-icons">hourglass_empty</i> Sincronizando...';
    
    // Mostrar modal de sincronización
    const modal = document.getElementById('modalSincronizacion');
    const mensajeSincronizacion = document.getElementById('mensajeSincronizacion');
    const detalleSincronizacion = document.getElementById('detalleSincronizacion');
    const progressText = document.getElementById('progressText');
    
    if (modal) {
        mensajeSincronizacion.textContent = 'Sincronizando productos desde SQL Server...';
        detalleSincronizacion.textContent = 'Por favor, espere. Esto puede tomar varios minutos.';
        progressText.textContent = 'Iniciando sincronización...';
        $(modal).modal('show');
    }
    
    // Iniciar tiempo de sincronización
    const startTime = Date.now();
    
    fetch('{{ route("aprobaciones.sincronizar-stock", $cotizacion->id) }}', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        const elapsedTime = ((Date.now() - startTime) / 1000).toFixed(0);
        
        // Cerrar modal
        if (modal) {
            $(modal).modal('hide');
        }
        
        if (data.success) {
            // Mostrar mensaje de éxito con detalles
            const productosSync = data.productos_sincronizados || 0;
            const mensaje = `Stock sincronizado exitosamente.\n${productosSync} productos actualizados.\nTiempo: ${elapsedTime} segundos`;
            showNotification(mensaje, 'success');
            
            // Recargar la página para ver los cambios de stock
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        } else {
            showNotification(data.message || 'Error al sincronizar stock', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        
        // Cerrar modal
        if (modal) {
            $(modal).modal('hide');
        }
        
        showNotification('Error al sincronizar stock: ' + (error.message || 'Por favor, intente nuevamente.'), 'error');
    })
    .finally(() => {
        // Restaurar botón
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    });
}
</script>
@endpush

@endsection