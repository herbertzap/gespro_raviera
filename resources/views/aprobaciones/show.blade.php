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
                            <div class="col-md-6">
                                <h4 class="card-title">
                                    <i class="material-icons">description</i>
                                    Nota de Venta #{{ $cotizacion->id }}
                                </h4>
                                <p class="card-category">
                                    Cliente: {{ $cotizacion->cliente_codigo }} - {{ $cotizacion->cliente_nombre }}
                                </p>
                            </div>
                            <div class="col-md-6 text-right d-inline-flex items-center justify-content-end">
                                <a href="{{ route('aprobaciones.index') }}" class="btn btn-secondary">
                                    <i class="material-icons">arrow_back</i> Volver
                                </a>
                                @if(!auth()->user()->hasRole('Picking') && !auth()->user()->hasRole('Picking Operativo'))
                                    <button type="button" class="btn btn-primary ml-2" onclick="activarTabCliente()" id="btnVerCliente">
                                        <i class="material-icons">person</i> Ver Cliente
                                    </button>
                                @else
                                    <button onclick="mostrarModalImpresion()" class="btn btn-success ml-2">
                                        <i class="material-icons">print</i> Imprimir Guía de Picking
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

        <!-- Sistema de Tabs -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <ul class="nav nav-tabs card-header-tabs" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" id="tab-nvv" data-toggle="tab" href="#content-nvv" role="tab" aria-controls="content-nvv" aria-selected="true">
                                    <i class="material-icons">description</i> NVV
                                </a>
                            </li>
                            @if(!auth()->user()->hasRole('Picking') && !auth()->user()->hasRole('Picking Operativo'))
                            <li class="nav-item">
                                <a class="nav-link" id="tab-cliente" data-toggle="tab" href="#content-cliente" role="tab" aria-controls="content-cliente" aria-selected="false">
                                    <i class="material-icons">person</i> Cliente
                                    <span id="cliente-loading" class="ml-2" style="display: none;">
                                        <i class="material-icons spinner" style="animation: spin 1s linear infinite;">hourglass_empty</i>
                                    </span>
                                </a>
                            </li>
                            @endif
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content" id="tabContent">
                            <!-- Tab NVV -->
                            <div class="tab-pane fade show active" id="content-nvv" role="tabpanel" aria-labelledby="tab-nvv">
                                <!-- Todo el contenido de la NVV va aquí -->
                                
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
                                @if($cotizacion->nota_original_id)
                                    <p><strong>Separado de:</strong> 
                                        <a href="{{ route('aprobaciones.show', $cotizacion->nota_original_id) }}" class="text-primary" target="_blank">
                                            <i class="material-icons" style="font-size: 16px; vertical-align: middle;">link</i> NVV #{{ $cotizacion->nota_original_id }}
                                        </a>
                                    </p>
                                @endif
                                <p><strong>Estado:</strong> 
                                    @php
                                        // Verificar primero si es una NVV separada
                                        $esSeparada = in_array($cotizacion->estado, ['separado_por_compras', 'separado_por_picking']);
                                        $estadoMostrar = $cotizacion->estado_aprobacion;
                                    @endphp
                                    
                                    @if($esSeparada && $cotizacion->estado === 'separado_por_compras')
                                        <span class="badge badge-warning">Separado / Pendiente Compras</span>
                                    @elseif($esSeparada && $cotizacion->estado === 'separado_por_picking')
                                        <span class="badge badge-warning">Separado / Pendiente Compras</span>
                                    @else
                                        @switch($estadoMostrar)
                                            @case('pendiente')
                                                @if($cotizacion->tiene_problemas_credito)
                                                    <span class="badge badge-warning">Pendiente Supervisor</span>
                                                @elseif($cotizacion->tiene_problemas_stock)
                                                    <span class="badge badge-warning">Pendiente Compras</span>
                                                @else
                                                    <span class="badge badge-warning">Pendiente</span>
                                                @endif
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
                                                <span class="badge badge-secondary">{{ $estadoMostrar }}</span>
                                        @endswitch
                                    @endif
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
                                
                                @if((auth()->user()->hasRole('Picking') || auth()->user()->hasRole('Picking Operativo')) && ($cotizacion->puedeAprobarPicking() || $cotizacion->estado_aprobacion === 'pendiente_picking' || $cotizacion->estado_aprobacion === 'aprobada_picking'))
                                <div class="mt-3">
                                    <p><strong>Observaciones Picking:</strong></p>
                                    <p class="text-muted" id="observaciones-picking-text">{{ $cotizacion->observaciones_picking ?: 'Sin observaciones adicionales' }}</p>
                                    <button type="button" class="btn btn-sm btn-info" data-toggle="modal" data-target="#modalObservacionesPicking">
                                        <i class="material-icons">note_add</i> Agregar Nota
                                    </button>
                                </div>
                                @elseif($cotizacion->observaciones_picking)
                                <div class="mt-3">
                                    <p><strong>Observaciones Picking:</strong></p>
                                    <p class="text-muted">{{ $cotizacion->observaciones_picking }}</p>
                                </div>
                                @endif
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
                                    @if($cotizacion->estado_aprobacion === 'rechazada' && $cotizacion->aprobado_por_supervisor)
                                        <span class="badge badge-danger">Rechazada</span>
                                        <br><small>{{ $cotizacion->fecha_aprobacion_supervisor ? $cotizacion->fecha_aprobacion_supervisor->format('d/m/Y H:i') : '' }}</small>
                                        <br><small>Por: {{ $cotizacion->aprobadoPorSupervisor->name ?? 'N/A' }}</small>
                                    @elseif($cotizacion->aprobado_por_supervisor && $cotizacion->estado_aprobacion !== 'rechazada')
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
                                    @if($cotizacion->estado_aprobacion === 'rechazada' && $cotizacion->aprobado_por_compras)
                                        <span class="badge badge-danger">Rechazada</span>
                                        <br><small>{{ $cotizacion->fecha_aprobacion_compras ? $cotizacion->fecha_aprobacion_compras->format('d/m/Y H:i') : '' }}</small>
                                        <br><small>Por: {{ $cotizacion->aprobadoPorCompras->name ?? 'N/A' }}</small>
                                    @elseif($cotizacion->aprobado_por_compras && $cotizacion->estado_aprobacion !== 'rechazada')
                                        <span class="badge badge-success">Aprobada</span>
                                        <br><small>{{ $cotizacion->fecha_aprobacion_compras ? $cotizacion->fecha_aprobacion_compras->format('d/m/Y H:i') : '' }}</small>
                                        <br><small>Por: {{ $cotizacion->aprobadoPorCompras->name ?? 'N/A' }}</small>
                                    @elseif(in_array($cotizacion->estado, ['separado_por_compras', 'separado_por_picking']) && $cotizacion->estado_aprobacion === 'pendiente')
                                        <span class="badge badge-warning">Separado / Pendiente</span>
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

                        <!-- Comentarios de Aprobación / Motivo de Rechazo -->
                        @if($cotizacion->estado_aprobacion === 'rechazada' && $cotizacion->motivo_rechazo)
                            <hr>
                            <h6>Motivo del Rechazo:</h6>
                            <div class="alert alert-danger">
                                <strong><i class="material-icons">cancel</i> Rechazada:</strong><br>
                                {{ $cotizacion->motivo_rechazo }}
                            </div>
                        @elseif($cotizacion->comentarios_supervisor || $cotizacion->comentarios_compras || $cotizacion->comentarios_picking)
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
                        @php
                            // Definir variables una vez para todo el bloque de productos
                            $usuarioEsPicking = Auth::user()->hasRole('Picking') || Auth::user()->hasRole('Picking Operativo');
                            $usuarioEsCompras = Auth::user()->hasRole('Compras');
                            // Picking puede separar cualquier producto, Compras solo si hay problemas de stock
                            $puedeVerCheckbox = false;
                            if ($usuarioEsPicking) {
                                $puedeVerCheckbox = true;
                            } elseif ($usuarioEsCompras && $cotizacion->tiene_problemas_stock && !$cotizacion->aprobado_por_compras) {
                                $puedeVerCheckbox = true;
                            }
                            $puedeVerAcciones = false;
                            if ($usuarioEsPicking) {
                                $puedeVerAcciones = true;
                            } elseif ($usuarioEsCompras && $cotizacion->tiene_problemas_stock && !$cotizacion->aprobado_por_compras) {
                                $puedeVerAcciones = true;
                            }
                        @endphp
                        @if($cotizacion->productos->count() > 0)
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            @if($puedeVerCheckbox)
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
                                            @if($puedeVerAcciones)
                                                <th>Acciones</th>
                                            @endif
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($cotizacion->productos as $producto)
                                            <tr data-producto-id="{{ $producto->id }}" data-cantidad="{{ $producto->cantidad }}" data-precio="{{ $producto->precio_unitario }}">
                                                @if($puedeVerCheckbox)
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
                                                    @php
                                                        // Obtener stock REAL desde tabla productos (actualizado)
                                                        $productoStockTemp = \App\Models\Producto::where('KOPR', $producto->codigo_producto)->first();
                                                        $stockFisicoRealTemp = $productoStockTemp ? ($productoStockTemp->stock_fisico ?? 0) : 0;
                                                        $stockComprometidoSQLTemp = $productoStockTemp ? ($productoStockTemp->stock_comprometido ?? 0) : 0;
                                                        $stockComprometidoLocalTemp = \App\Models\StockComprometido::calcularStockComprometido($producto->codigo_producto);
                                                        $stockDisponibleRealTemp = max(0, $stockFisicoRealTemp - $stockComprometidoSQLTemp - $stockComprometidoLocalTemp);
                                                    @endphp
                                                    @if(Auth::user()->hasRole('Compras') && $cotizacion->tiene_problemas_stock && $stockDisponibleRealTemp < $producto->cantidad && !$cotizacion->aprobado_por_compras)
                                                        <div class="input-group input-group-sm">
                                                            <input type="number" class="form-control cantidad-input" 
                                                                   value="{{ $producto->cantidad }}" 
                                                                   min="0" max="{{ $stockDisponibleRealTemp }}"
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
                                                    @php
                                                        // Picking puede separar cualquier producto (con o sin problemas de stock)
                                                        // Compras solo puede separar si hay problemas de stock
                                                        $puedeSeparar = false;
                                                        if ($usuarioEsPicking) {
                                                            // Picking puede separar siempre
                                                            $puedeSeparar = true;
                                                        } elseif ($usuarioEsCompras && $cotizacion->tiene_problemas_stock && !$cotizacion->aprobado_por_compras) {
                                                            // Compras solo si hay problemas de stock y no está aprobado por compras
                                                            $puedeSeparar = true;
                                                        }
                                                    @endphp
                                                    @if($puedeSeparar)
                                                        @php
                                                            // Obtener stock disponible
                                                            $productoStockSep = \App\Models\Producto::where('KOPR', $producto->codigo_producto)->first();
                                                            $stockFisicoRealSep = $productoStockSep ? ($productoStockSep->stock_fisico ?? 0) : 0;
                                                            $stockComprometidoSQLSep = $productoStockSep ? ($productoStockSep->stock_comprometido ?? 0) : 0;
                                                            $stockComprometidoLocalSep = \App\Models\StockComprometido::calcularStockComprometido($producto->codigo_producto);
                                                            $stockDisponibleRealSep = max(0, $stockFisicoRealSep - $stockComprometidoSQLSep - $stockComprometidoLocalSep);
                                                            
                                                            // Obtener múltiplo de venta
                                                            $multiploVenta = optional($productoStockSep)->multiplo_venta ?? 1;
                                                            if ($multiploVenta <= 0) { $multiploVenta = 1; }
                                                            
                                                            // Para Picking: calcular diferencia (pedido - stock) = diferencia
                                                            // Para Compras: solo si hay problemas de stock
                                                            // Si es Picking o el producto tiene problemas de stock, calcular diferencia
                                                            $diferencia = 0;
                                                            if ($usuarioEsPicking || $stockDisponibleRealSep < $producto->cantidad) {
                                                                $diferencia = max(0, $producto->cantidad - $stockDisponibleRealSep);
                                                            }
                                                            
                                                            // Ajustar diferencia a múltiplos si es necesario
                                                            if ($multiploVenta > 1 && $diferencia > 0) {
                                                                // Redondear hacia arriba al siguiente múltiplo
                                                                $diferencia = ceil($diferencia / $multiploVenta) * $multiploVenta;
                                                                // No exceder la cantidad pedida
                                                                if ($diferencia > $producto->cantidad) {
                                                                    // Redondear hacia abajo si excede
                                                                    $diferencia = floor($producto->cantidad / $multiploVenta) * $multiploVenta;
                                                                }
                                                            }
                                                            
                                                            // Usar cantidad_separar si ya existe, sino usar la diferencia calculada (0 para Picking si no hay problemas)
                                                            $cantidadSepararFinal = $producto->cantidad_separar ?? $diferencia;
                                                            
                                                            // Asegurar que respete múltiplos si ya existe un valor
                                                            if ($multiploVenta > 1 && $cantidadSepararFinal > 0) {
                                                                $cantidadSepararFinal = floor($cantidadSepararFinal / $multiploVenta) * $multiploVenta;
                                                                if ($cantidadSepararFinal === 0 && $diferencia > 0) {
                                                                    $cantidadSepararFinal = $multiploVenta;
                                                                }
                                                            }
                                                            
                                                            // Para Picking: permitir cantidad mínima (el múltiplo) si no hay diferencia calculada
                                                            if ($usuarioEsPicking && $cantidadSepararFinal === 0 && $multiploVenta > 0) {
                                                                $cantidadSepararFinal = $multiploVenta; // Valor mínimo para Picking poder separar
                                                            }
                                                        @endphp
                                                        <div class="input-group input-group-sm">
                                                            <input type="number" class="form-control separar-input" 
                                                                   value="{{ $cantidadSepararFinal }}" 
                                                                   min="{{ $multiploVenta }}" 
                                                                   step="{{ $multiploVenta }}" 
                                                                   max="{{ $producto->cantidad }}"
                                                                   data-producto-id="{{ $producto->id }}"
                                                                   data-precio="{{ $producto->precio_unitario }}"
                                                                   data-cantidad-original="{{ $producto->cantidad }}"
                                                                   data-multiplo="{{ $multiploVenta }}"
                                                                   data-stock-disponible="{{ $stockDisponibleRealSep }}">
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
                                                    @if(Auth::user()->hasRole('Supervisor') && $cotizacion->puedeAprobarSupervisor() && $cotizacion->estado_aprobacion !== 'rechazada')
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
                                                    @php
                                                        // Obtener stock REAL desde tabla productos (actualizado)
                                                        $productoStock = \App\Models\Producto::where('KOPR', $producto->codigo_producto)->first();
                                                        $stockFisicoReal = $productoStock ? ($productoStock->stock_fisico ?? 0) : 0;
                                                        $stockComprometidoSQL = $productoStock ? ($productoStock->stock_comprometido ?? 0) : 0;
                                                        $stockComprometidoLocal = \App\Models\StockComprometido::calcularStockComprometido($producto->codigo_producto);
                                                        $stockDisponibleReal = max(0, $stockFisicoReal - $stockComprometidoSQL - $stockComprometidoLocal);
                                                    @endphp
                                                    @if($stockDisponibleReal >= $producto->cantidad)
                                                        <span class="badge badge-success">{{ $stockDisponibleReal }}</span>
                                                    @else
                                                        <span class="badge badge-danger">{{ $stockDisponibleReal }}</span>
                                                        <br><small class="text-danger">Faltan: {{ $producto->cantidad - $stockDisponibleReal }}</small>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($stockDisponibleReal >= $producto->cantidad)
                                                        <span class="badge badge-success">Disponible</span>
                                                    @else
                                                        <span class="badge badge-warning">Stock Insuficiente</span>
                                                    @endif
                                                </td>
                                                @php
                                                    // Picking puede separar cualquier producto (con o sin problemas de stock)
                                                    // Compras solo puede separar si hay problemas de stock
                                                    $puedeSepararIndividual = false;
                                                    if ($usuarioEsPicking) {
                                                        // Picking puede separar siempre
                                                        $puedeSepararIndividual = true;
                                                    } elseif ($usuarioEsCompras && $cotizacion->tiene_problemas_stock && (!$cotizacion->aprobado_por_compras || $stockDisponibleReal < $producto->cantidad)) {
                                                        // Compras solo si hay problemas de stock y el producto tiene stock insuficiente
                                                        $puedeSepararIndividual = true;
                                                    }
                                                @endphp
                                                @if($puedeSepararIndividual)
                                                    <td>
                                                        <button class="btn btn-warning btn-sm" 
                                                                onclick="separarProductoIndividual({{ $producto->id }})">
                                                            <i class="material-icons">call_split</i> Separar
                                                        </button>
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
                        
                        <!-- Botones de Acción para Compras y Picking -->
                        @php
                            // Picking puede usar herramientas siempre, Compras solo si hay problemas de stock
                            $puedeUsarHerramientas = false;
                            if ($usuarioEsPicking) {
                                // Picking puede usar herramientas siempre
                                $puedeUsarHerramientas = true;
                            } elseif ($usuarioEsCompras && $cotizacion->tiene_problemas_stock && !$cotizacion->aprobado_por_compras) {
                                // Compras solo si hay problemas de stock y no está aprobado
                                $puedeUsarHerramientas = true;
                            }
                        @endphp
                        @if($puedeUsarHerramientas)
                            <div class="row mt-3">
                                <div class="col-md-12">
                                    <div class="card">
                                        <div class="card-header card-header-warning">
                                            <h4 class="card-title">
                                                <i class="material-icons">build</i>
                                                @if(Auth::user()->hasRole('Picking'))
                                                    Herramientas de Picking
                                                @else
                                                    Herramientas de Compras
                                                @endif
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
                                                @if(Auth::user()->hasRole('Compras'))
                                                <div class="col-md-6">
                                                    <h6>Modificar Cantidades</h6>
                                                    <p class="text-muted">Ajusta las cantidades según el stock disponible</p>
                                                    <button class="btn btn-info" onclick="guardarTodasLasCantidades()">
                                                        <i class="material-icons">save</i> Guardar Todas las Cantidades
                                                    </button>
                                                </div>
                                                @endif
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
        @if($cotizacion->tiene_problemas_credito || $cotizacion->tiene_problemas_stock || $cotizacion->estado_aprobacion === 'rechazada')
            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header {{ $cotizacion->estado_aprobacion === 'rechazada' ? 'card-header-danger' : 'card-header-danger' }}">
                            <h4 class="card-title">
                                <i class="material-icons">{{ $cotizacion->estado_aprobacion === 'rechazada' ? 'cancel' : 'warning' }}</i>
                                @if($cotizacion->estado_aprobacion === 'rechazada')
                                    Nota de Venta Rechazada
                                @else
                                    Problemas Identificados
                                @endif
                            </h4>
                        </div>
                        <div class="card-body">
                            @if($cotizacion->estado_aprobacion === 'rechazada')
                                <div class="alert alert-danger">
                                    <h6><i class="material-icons">cancel</i> Motivo del Rechazo</h6>
                                    <p><strong>{{ $cotizacion->motivo_rechazo ?? 'No se especificó motivo' }}</strong></p>
                                    @if($cotizacion->fecha_aprobacion_supervisor || $cotizacion->fecha_aprobacion_compras || $cotizacion->fecha_aprobacion_picking)
                                        <p class="text-muted mt-2">
                                            <small>
                                                Rechazada el: 
                                                @if($cotizacion->fecha_aprobacion_supervisor)
                                                    {{ $cotizacion->fecha_aprobacion_supervisor->format('d/m/Y H:i') }}
                                                    por Supervisor
                                                @elseif($cotizacion->fecha_aprobacion_compras)
                                                    {{ $cotizacion->fecha_aprobacion_compras->format('d/m/Y H:i') }}
                                                    por Compras
                                                @elseif($cotizacion->fecha_aprobacion_picking)
                                                    {{ $cotizacion->fecha_aprobacion_picking->format('d/m/Y H:i') }}
                                                    por Picking
                                                @endif
                                            </small>
                                        </p>
                                    @endif
                                </div>
                            @endif

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
        @if($cotizacion->estado_aprobacion !== 'rechazada' && 
            (($cotizacion->puedeAprobarSupervisor() && Auth::user()->hasRole('Supervisor')) || 
            ($cotizacion->puedeAprobarCompras() && Auth::user()->hasRole('Compras')) || 
            ($cotizacion->puedeAprobarPicking() && Auth::user()->hasRole('Picking')) ||
            ($cotizacion->estado_aprobacion === 'pendiente_entrega' && Auth::user()->hasRole('Picking'))))
            {{-- Nota: Picking Operativo NO puede aprobar, solo Picking puede aprobar --}}
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
                                {{-- Solo Picking puede aprobar, Picking Operativo NO puede aprobar --}}
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
                                <button type="button" id="btnAprobarPicking" class="btn btn-success btn-lg" onclick="mostrarModalAprobarPicking()">
                                    <i class="material-icons">check</i> <span id="textoBotonAprobar">Aprobar</span>
                                </button>
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
                            <!-- Fin Tab NVV -->

                            <!-- Tab Cliente -->
                            @if(!auth()->user()->hasRole('Picking') && !auth()->user()->hasRole('Picking Operativo'))
                            <div class="tab-pane fade" id="content-cliente" role="tabpanel" aria-labelledby="tab-cliente">
                                <div id="cliente-content">
                                    <div class="text-center py-5">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="sr-only">Cargando información del cliente...</span>
                                        </div>
                                        <p class="mt-3 text-muted">Cargando información del cliente...</p>
                                    </div>
                                </div>
                            </div>
                            @endif
                            <!-- Fin Tab Cliente -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Fin Sistema de Tabs -->

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

// Variables globales
let clienteCargado = false;
const clienteCodigo = '{{ $cotizacion->cliente_codigo }}';
const esSupervisor = {{ auth()->user()->hasRole('Supervisor') ? 'true' : 'false' }};

// Función para activar el tab del cliente
function activarTabCliente() {
    // Cambiar al tab cliente
    $('#tab-cliente').tab('show');
    
    // Si no está cargado, cargarlo
    if (!clienteCargado) {
        cargarInfoCliente();
    }
}

// Función para cargar información del cliente vía AJAX
function cargarInfoCliente() {
    const clienteContent = document.getElementById('cliente-content');
    const clienteLoading = document.getElementById('cliente-loading');
    
    if (clienteLoading) {
        clienteLoading.style.display = 'inline-block';
    }
    
    // Mostrar spinner mientras carga
    clienteContent.innerHTML = `
        <div class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="sr-only">Cargando información del cliente...</span>
            </div>
            <p class="mt-3 text-muted">Cargando información del cliente...</p>
        </div>
    `;
    
    fetch('{{ route("clientes.info-ajax", ":codigo") }}'.replace(':codigo', clienteCodigo), {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Error en la respuesta del servidor: ' + response.status);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            clienteContent.innerHTML = data.html;
            clienteCargado = true;
        } else {
            clienteContent.innerHTML = `
                <div class="alert alert-danger">
                    <i class="material-icons">error</i>
                    ${data.message || 'Error al cargar información del cliente'}
                </div>
            `;
        }
    })
    .catch(error => {
        console.error('Error al cargar información del cliente:', error);
        clienteContent.innerHTML = `
            <div class="alert alert-danger">
                <i class="material-icons">error</i>
                Error al cargar información del cliente. Por favor, intente nuevamente.
            </div>
        `;
    })
    .finally(() => {
        if (clienteLoading) {
            clienteLoading.style.display = 'none';
        }
    });
}

// Pre-cargar información del cliente si es Supervisor
@if(auth()->user()->hasRole('Supervisor'))
$(document).ready(function() {
    // Precargar información del cliente en segundo plano
    setTimeout(function() {
        cargarInfoCliente();
    }, 1000);
});
@endif

// Calcular automáticamente cantidad a separar al cargar la página
$(document).ready(function() {
    // Calcular automáticamente cantidad a separar basándose en stock disponible
    calcularSepararAutomatico();
});

// Event listener para cuando se cambia de tab
$('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
    const targetTab = $(e.target).attr('href'); // e.target es el tab activo
    
    // Si se activa el tab cliente y no está cargado, cargarlo
    if (targetTab === '#content-cliente' && !clienteCargado) {
        cargarInfoCliente();
    }
});

// Estilo para spinner
const style = document.createElement('style');
style.textContent = `
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    .spinner {
        animation: spin 1s linear infinite;
    }
`;
document.head.appendChild(style);
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
                    
                    <!-- Campos de Guía de Picking -->
                    <div class="card mb-3">
                        <div class="card-header bg-primary text-white">
                            <h6 class="mb-0"><i class="material-icons">local_shipping</i> Datos de Guía de Picking</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="guia_picking_bodega" class="bmd-label-floating">
                                            <i class="material-icons">warehouse</i>
                                            Bodega
                                        </label>
                                        <input type="text" 
                                            class="form-control" 
                                            id="guia_picking_bodega" 
                                            name="guia_picking_bodega" 
                                            value="{{ old('guia_picking_bodega', $cotizacion->guia_picking_bodega) }}"
                                            placeholder="Ej: Bodega Principal">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="guia_picking_numero_bultos" class="bmd-label-floating">
                                            <i class="material-icons">inventory_2</i>
                                            N° de Bultos
                                        </label>
                                        <input type="text" 
                                            class="form-control" 
                                            id="guia_picking_numero_bultos" 
                                            name="guia_picking_numero_bultos" 
                                            value="{{ old('guia_picking_numero_bultos', $cotizacion->guia_picking_numero_bultos) }}"
                                            placeholder="Ej: 5">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="guia_picking_separado_por" class="bmd-label-floating">
                                            <i class="material-icons">person</i>
                                            Separado Por
                                        </label>
                                        <input type="text" 
                                            class="form-control" 
                                            id="guia_picking_separado_por" 
                                            name="guia_picking_separado_por" 
                                            value="{{ old('guia_picking_separado_por', $cotizacion->guia_picking_separado_por) }}"
                                            placeholder="Nombre de quien separa">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="guia_picking_revisado_por" class="bmd-label-floating">
                                            <i class="material-icons">verified_user</i>
                                            Revisado Por
                                        </label>
                                        <input type="text" 
                                            class="form-control" 
                                            id="guia_picking_revisado_por" 
                                            name="guia_picking_revisado_por" 
                                            value="{{ old('guia_picking_revisado_por', $cotizacion->guia_picking_revisado_por) }}"
                                            placeholder="Nombre de quien revisa">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="guia_picking_firma" class="bmd-label-floating">
                                            <i class="material-icons">edit</i>
                                            Firma Picking
                                        </label>
                                        <input type="text" 
                                            class="form-control" 
                                            id="guia_picking_firma" 
                                            name="guia_picking_firma" 
                                            value="{{ old('guia_picking_firma', $cotizacion->guia_picking_firma) }}"
                                            placeholder="Nombre o firma de picking">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

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
                                    @php
                                        // Obtener stock REAL desde tabla productos (actualizado)
                                        $productoStockHist = \App\Models\Producto::where('KOPR', $producto->codigo_producto)->first();
                                        $stockFisicoRealHist = $productoStockHist ? ($productoStockHist->stock_fisico ?? 0) : 0;
                                        $stockComprometidoSQLHist = $productoStockHist ? ($productoStockHist->stock_comprometido ?? 0) : 0;
                                        $stockComprometidoLocalHist = \App\Models\StockComprometido::calcularStockComprometido($producto->codigo_producto);
                                        $stockDisponibleRealHist = max(0, $stockFisicoRealHist - $stockComprometidoSQLHist - $stockComprometidoLocalHist);
                                    @endphp
                                    <td>{{ $producto->codigo_producto }} - {{ $producto->nombre_producto }}</td>
                                    <td class="text-right">{{ number_format($producto->cantidad, 0) }}</td>
                                    <td class="text-right">{{ number_format($stockDisponibleRealHist, 0) }}</td>
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

<!-- Modal para Aprobar Picking -->
<div class="modal fade" id="modalAprobarPicking" tabindex="-1" role="dialog" aria-labelledby="modalAprobarPickingLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="modalAprobarPickingLabel">
                    <i class="material-icons">check_circle</i>
                    Aprobar Nota de Venta
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <i class="material-icons">warning</i>
                    <strong>Importante:</strong> Al aprobar, se insertará la NVV en la base de datos de producción.
                </div>
                
                <form id="formAprobarPicking" action="{{ route('aprobaciones.picking', $cotizacion->id) }}" method="POST">
                    @csrf
                    <input type="hidden" name="validar_stock_real" value="0">
                    
                    <!-- Campos de Guía de Picking -->
                    <div class="card mb-3">
                        <div class="card-header bg-primary text-white">
                            <h6 class="mb-0"><i class="material-icons">local_shipping</i> Datos de Guía de Picking</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="aprobar_guia_picking_bodega" class="bmd-label-floating">
                                            <i class="material-icons">warehouse</i>
                                            Bodega
                                        </label>
                                        <input type="text" 
                                            class="form-control" 
                                            id="aprobar_guia_picking_bodega" 
                                            name="guia_picking_bodega" 
                                            value="{{ old('guia_picking_bodega', $cotizacion->guia_picking_bodega) }}"
                                            placeholder="Ej: Bodega Principal">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="aprobar_guia_picking_numero_bultos" class="bmd-label-floating">
                                            <i class="material-icons">inventory_2</i>
                                            N° de Bultos
                                        </label>
                                        <input type="text" 
                                            class="form-control" 
                                            id="aprobar_guia_picking_numero_bultos" 
                                            name="guia_picking_numero_bultos" 
                                            value="{{ old('guia_picking_numero_bultos', $cotizacion->guia_picking_numero_bultos) }}"
                                            placeholder="Ej: 5">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="aprobar_guia_picking_separado_por" class="bmd-label-floating">
                                            <i class="material-icons">person</i>
                                            Separado Por
                                        </label>
                                        <input type="text" 
                                            class="form-control" 
                                            id="aprobar_guia_picking_separado_por" 
                                            name="guia_picking_separado_por" 
                                            value="{{ old('guia_picking_separado_por', $cotizacion->guia_picking_separado_por) }}"
                                            placeholder="Nombre de quien separa">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="aprobar_guia_picking_revisado_por" class="bmd-label-floating">
                                            <i class="material-icons">verified_user</i>
                                            Revisado Por
                                        </label>
                                        <input type="text" 
                                            class="form-control" 
                                            id="aprobar_guia_picking_revisado_por" 
                                            name="guia_picking_revisado_por" 
                                            value="{{ old('guia_picking_revisado_por', $cotizacion->guia_picking_revisado_por) }}"
                                            placeholder="Nombre de quien revisa">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="aprobar_guia_picking_firma" class="bmd-label-floating">
                                            <i class="material-icons">edit</i>
                                            Firma Picking
                                        </label>
                                        <input type="text" 
                                            class="form-control" 
                                            id="aprobar_guia_picking_firma" 
                                            name="guia_picking_firma" 
                                            value="{{ old('guia_picking_firma', $cotizacion->guia_picking_firma) }}"
                                            placeholder="Nombre o firma de picking">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="aprobar_comentarios" class="bmd-label-floating">
                            <i class="material-icons">comment</i>
                            Comentarios (Opcional)
                        </label>
                        <textarea 
                            class="form-control" 
                            id="aprobar_comentarios" 
                            name="comentarios" 
                            rows="3" 
                            maxlength="500"
                            placeholder="Comentarios adicionales sobre la aprobación...">{{ old('comentarios', $cotizacion->comentarios_picking) }}</textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="material-icons">close</i> Cancelar
                </button>
                <button type="button" class="btn btn-success" onclick="confirmarAprobacionPicking()">
                    <i class="material-icons">check</i> Confirmar Aprobación
                </button>
            </div>
        </div>
    </div>
</div>

@push('js')
<script>
// Función para mostrar modal de aprobar picking
function mostrarModalAprobarPicking() {
    $('#modalAprobarPicking').modal('show');
}

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
        // Usar AJAX para rechazar
        fetch(`/aprobaciones/${notaId}/rechazar`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                motivo: motivo.trim()
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Redirigir a la página de aprobaciones
                window.location.href = '{{ route("aprobaciones.index") }}';
            } else {
                alert('Error al rechazar la nota de venta: ' + (data.error || data.message || 'Error desconocido'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al rechazar la nota de venta. Por favor, intente nuevamente.');
        });
    }
}

// Confirmar y enviar aprobación Picking desde el modal
function confirmarAprobacionPicking() {
    if (!confirm('¿Estás seguro de aprobar esta nota de venta? Se insertará en la base de datos de producción.')) {
        return false;
    }
    
    // Bloquear el botón para evitar doble clic
    const btn = document.querySelector('#modalAprobarPicking .btn-success');
    const form = document.getElementById('formAprobarPicking');
    
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="material-icons">hourglass_empty</i> Procesando...';
    }
    
    // Enviar el formulario
    if (form) {
        form.submit();
    } else {
        alert('Error: No se encontró el formulario');
    }
    
    return false;
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
        const cantidadActual = parseFloat(cantidadInput.value) || 0;
        const stockDisponible = parseFloat(separarInput.getAttribute('data-stock-disponible')) || 0;
        const multiplo = parseInt(separarInput.getAttribute('data-multiplo')) || 1;
        
        // Actualizar máximo
        separarInput.max = cantidadActual;
        separarInput.placeholder = `Máx: ${cantidadActual}`;
        
        // Recalcular automáticamente cantidad a separar: (pedido - stock) = diferencia
        let diferencia = Math.max(0, cantidadActual - stockDisponible);
        
        // Ajustar a múltiplos si es necesario
        if (multiplo > 1 && diferencia > 0) {
            // Redondear hacia arriba al siguiente múltiplo
            diferencia = Math.ceil(diferencia / multiplo) * multiplo;
            // No exceder la cantidad pedida
            if (diferencia > cantidadActual) {
                // Redondear hacia abajo si excede
                diferencia = Math.floor(cantidadActual / multiplo) * multiplo;
            }
        }
        
        // Actualizar el valor del input solo si la diferencia es diferente y válida
        const valorActual = parseFloat(separarInput.value) || 0;
        if (diferencia !== valorActual && diferencia >= 0 && diferencia <= cantidadActual) {
            separarInput.value = diferencia;
        }
    }
}

// Función para calcular automáticamente cantidad a separar basándose en stock disponible
function calcularSepararAutomatico() {
    const separarInputs = document.querySelectorAll('.separar-input');
    
    separarInputs.forEach(separarInput => {
        const productoId = separarInput.getAttribute('data-producto-id');
        const cantidadOriginal = parseFloat(separarInput.getAttribute('data-cantidad-original')) || 0;
        const stockDisponible = parseFloat(separarInput.getAttribute('data-stock-disponible')) || 0;
        const multiplo = parseInt(separarInput.getAttribute('data-multiplo')) || 1;
        
        // Calcular diferencia: (pedido - stock) = diferencia
        let diferencia = Math.max(0, cantidadOriginal - stockDisponible);
        
        // Ajustar a múltiplos si es necesario
        if (multiplo > 1 && diferencia > 0) {
            // Redondear hacia arriba al siguiente múltiplo
            diferencia = Math.ceil(diferencia / multiplo) * multiplo;
            // No exceder la cantidad pedida
            if (diferencia > cantidadOriginal) {
                // Redondear hacia abajo si excede
                diferencia = Math.floor(cantidadOriginal / multiplo) * multiplo;
            }
        }
        
        // Solo actualizar si no hay un valor previo guardado (valor actual es 0 o vacío)
        const valorActual = parseFloat(separarInput.value) || 0;
        if (valorActual === 0 && diferencia > 0) {
            separarInput.value = diferencia;
        }
    });
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

// Separar productos seleccionados con lógica de cantidades - TODOS EN UNA SOLA NVV
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
                id: parseInt(productoId),
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
    
    if (confirm(`¿Estás seguro de que quieres separar ${totalProductos} productos (${totalCantidades} unidades totales) en UNA nueva NVV?`)) {
        console.log('Iniciando separación de productos en una sola NVV...', productosConSeparar);
        
        // Primero guardar todas las cantidades a separar
        const promesasGuardar = productosConSeparar.map(producto => {
            return fetch('{{ route("aprobaciones.guardar-separar", $cotizacion->id) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    producto_id: producto.id,
                    cantidad_separar: producto.cantidad
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log(`Cantidad guardada para producto ${producto.id}`);
                    return { success: true, producto_id: producto.id };
                } else {
                    console.error('Error guardando cantidad:', data.error);
                    return { success: false, producto_id: producto.id, error: data.error };
                }
            })
            .catch(error => {
                console.error('Error guardando cantidad:', error);
                return { success: false, producto_id: producto.id, error: error.message };
            });
        });
        
        // Esperar a que todas las cantidades se guarden
        Promise.all(promesasGuardar)
            .then(resultadosGuardar => {
                const fallidos = resultadosGuardar.filter(r => !r.success);
                if (fallidos.length > 0) {
                    showNotification(`Error guardando cantidades: ${fallidos.length} productos fallaron`, 'error');
                    return;
                }
                
                console.log('Todas las cantidades guardadas, iniciando separación en una sola NVV...');
                
                // Separar TODOS los productos en UNA sola NVV usando el endpoint separar-productos
                const productosIds = resultadosGuardar.map(r => r.producto_id);
                
                fetch('{{ route("aprobaciones.separar-productos", $cotizacion->id) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        productos_ids: productosIds,
                        motivo: 'Separación múltiple de productos por Compras - Productos agrupados en una sola NVV'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification(`✅ ${data.message}. Nueva NVV #${data.nota_separada_id} creada.`, 'success');
                        // Recargar la página después de un momento
                        setTimeout(() => {
                            window.location.reload();
                        }, 2000);
                    } else {
                        showNotification('Error al separar productos: ' + (data.error || 'Error desconocido'), 'error');
                    }
                })
                .catch(error => {
                    console.error('Error separando productos:', error);
                    showNotification('Error al separar productos: ' + error.message, 'error');
                });
            })
            .catch(error => {
                console.error('Error guardando cantidades:', error);
                showNotification('Error al guardar cantidades a separar: ' + error.message, 'error');
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
            const productosError = data.productos_con_error || 0;
            const totalProductos = data.total_productos || 0;
            
            let mensaje = `✅ Stock sincronizado exitosamente.\n${productosSync} de ${totalProductos} productos actualizados.`;
            if (productosError > 0) {
                mensaje += `\n⚠️ ${productosError} productos con errores.`;
            }
            mensaje += `\n⏱️ Tiempo: ${elapsedTime} segundos`;
            
            showNotification(mensaje, productosError > 0 ? 'warning' : 'success');
            
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

<!-- Modal para Observaciones de Picking -->
@if(auth()->user()->hasRole('Picking') && ($cotizacion->puedeAprobarPicking() || $cotizacion->estado_aprobacion === 'pendiente_picking' || $cotizacion->estado_aprobacion === 'aprobada_picking'))
<div class="modal fade" id="modalObservacionesPicking" tabindex="-1" role="dialog" aria-labelledby="modalObservacionesPickingLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalObservacionesPickingLabel">
                    <i class="material-icons">note_add</i> Agregar Observaciones de Picking
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="formObservacionesPicking" action="{{ route('aprobaciones.agregar-observaciones-picking', $cotizacion->id) }}" method="POST">
                @csrf
                <input type="hidden" name="_method" value="PUT">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="observaciones_picking_modal">Observaciones:</label>
                        <textarea 
                            class="form-control" 
                            id="observaciones_picking_modal" 
                            name="observaciones_picking" 
                            rows="5" 
                            placeholder="Agregar observaciones adicionales de picking que aparecerán en el PDF..."
                            maxlength="1000">{{ $cotizacion->observaciones_picking }}</textarea>
                        <small class="form-text text-muted">Máximo 1000 caracteres. Estas observaciones aparecerán en el PDF de picking.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="material-icons">save</i> Guardar Observaciones
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('js')
<script>
(function() {
    // Usar IIFE para evitar conflictos y asegurar que el código se ejecute
    const formId = 'formObservacionesPicking';
    const textareaName = 'observaciones_picking';
    
    // Esperar a que el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initForm);
    } else {
        initForm();
    }
    
    function initForm() {
        const form = document.getElementById(formId);
        if (!form) {
            console.warn('Formulario no encontrado, reintentando...');
            setTimeout(initForm, 500);
            return;
        }
        
        console.log('✅ Formulario encontrado:', form);
        
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            console.log('🔄 Submit event capturado');
            
            // Buscar el textarea de múltiples formas
            let textarea = form.querySelector('textarea[name="' + textareaName + '"]');
            if (!textarea) {
                textarea = document.getElementById('observaciones_picking');
            }
            if (!textarea) {
                textarea = form.querySelector('textarea');
            }
            
            if (!textarea) {
                console.error('❌ No se encontró el textarea');
                alert('Error: No se encontró el campo de observaciones');
                return false;
            }
            
            const observacionesValue = (textarea.value || '').trim();
            
            console.log('📝 Textarea encontrado:', textarea);
            console.log('📝 Valor capturado:', observacionesValue);
            console.log('📝 Longitud:', observacionesValue.length);
            console.log('📝 Textarea HTML:', textarea.outerHTML.substring(0, 100));
            
            if (!observacionesValue) {
                console.warn('⚠️ El textarea está vacío');
                // Permitir guardar vacío (puede ser intencional para limpiar)
            }
            
            // Crear FormData manualmente
            const formData = new FormData();
            formData.append(textareaName, observacionesValue);
            formData.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
            formData.append('_method', 'PUT');
            
            // Verificar FormData
            console.log('📦 FormData creado');
            console.log('📦 observaciones_picking:', formData.get(textareaName));
            console.log('📦 _method:', formData.get('_method'));
            console.log('📦 _token:', formData.get('_token') ? 'presente' : 'ausente');
            
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="material-icons">hourglass_empty</i> Guardando...';
            
            fetch(form.action, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: formData
            })
            .then(response => {
                console.log('Response status:', response.status);
                if (!response.ok) {
                    return response.text().then(text => {
                        console.error('Error response:', text);
                        try {
                            return JSON.parse(text);
                        } catch (e) {
                            throw new Error('Error en la respuesta del servidor: ' + response.status);
                        }
                    });
                }
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);
                if (data.success) {
                    // Actualizar el texto de observaciones en la página
                    const observacionesText = document.getElementById('observaciones-picking-text');
                    console.log('Buscando elemento observaciones-picking-text:', observacionesText);
                    if (observacionesText) {
                        observacionesText.textContent = data.observaciones || 'Sin observaciones adicionales';
                        console.log('Texto actualizado:', observacionesText.textContent);
                    } else {
                        console.warn('No se encontró el elemento observaciones-picking-text, recargando página...');
                        // Si no se encuentra el elemento, recargar la página
                        window.location.reload();
                        return;
                    }
                    
                    // Cerrar modal
                    const modal = document.getElementById('modalObservacionesPicking');
                    if (modal && typeof $ !== 'undefined' && $.fn.modal) {
                        $('#modalObservacionesPicking').modal('hide');
                    } else if (modal) {
                        modal.style.display = 'none';
                        document.body.classList.remove('modal-open');
                        const backdrop = document.querySelector('.modal-backdrop');
                        if (backdrop) backdrop.remove();
                    }
                    
                    // Mostrar mensaje de éxito
                    if (typeof showNotification === 'function') {
                        showNotification('Observaciones guardadas exitosamente', 'success');
                    } else {
                        alert('Observaciones guardadas exitosamente');
                    }
                } else {
                    console.error('Error en respuesta:', data);
                    if (typeof showNotification === 'function') {
                        showNotification(data.message || 'Error al guardar observaciones', 'error');
                    } else {
                        alert(data.message || 'Error al guardar observaciones');
                    }
                }
            })
            .catch(error => {
                console.error('Error completo:', error);
                console.error('Error stack:', error.stack);
                if (typeof showNotification === 'function') {
                    showNotification('Error al guardar observaciones. Por favor, intente nuevamente.', 'error');
                } else {
                    alert('Error al guardar observaciones. Por favor, intente nuevamente. Error: ' + error.message);
                }
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            });
            
            return false;
        });
    }
})();
</script>
@endpush
@endif

@push('css')
<style>
/* Estilos responsivos para tablets */
@media (min-width: 768px) and (max-width: 1024px) {
    /* Botones del header - Prevenir superposición */
    .card-header .row {
        flex-wrap: wrap;
        margin-left: -5px;
        margin-right: -5px;
    }
    
    .card-header .col-md-6 {
        padding-left: 5px;
        padding-right: 5px;
    }
    
    .card-header .col-md-6.text-right {
        display: flex !important;
        flex-wrap: wrap !important;
        justify-content: flex-end !important;
        align-items: center !important;
        gap: 6px !important;
        margin-top: 10px;
        width: 100% !important;
        max-width: 100% !important;
    }
    
    .card-header .col-md-6.text-right .btn {
        flex-shrink: 1 !important;
        flex-grow: 0 !important;
        font-size: 11px !important;
        padding: 6px 8px !important;
        white-space: nowrap !important;
        margin-left: 0 !important;
        min-width: auto !important;
        max-width: 100% !important;
    }
    
    .card-header .col-md-6.text-right .btn .material-icons {
        font-size: 16px !important;
        vertical-align: middle;
        margin-right: 2px;
    }
    
    /* Botón "Sincronizar Productos" más compacto */
    .card-header .col-md-6.text-right .btn#btnSincronizarStock {
        font-size: 10px !important;
        padding: 6px 6px !important;
    }
    
    /* Ajustar el título y categoría para dar más espacio */
    .card-header .col-md-6:first-child {
        margin-bottom: 10px;
        width: 100% !important;
        max-width: 100% !important;
    }
    
    .card-header .card-title {
        font-size: 16px;
        margin-bottom: 5px;
    }
    
    .card-header .card-category {
        font-size: 12px;
        word-break: break-word;
    }
    
    /* En tablets pequeñas, hacer botones aún más compactos */
    @media (min-width: 768px) and (max-width: 900px) {
        .card-header .col-md-6.text-right {
            gap: 4px !important;
        }
        
        .card-header .col-md-6.text-right .btn {
            font-size: 10px !important;
            padding: 5px 6px !important;
        }
        
        .card-header .col-md-6.text-right .btn .material-icons {
            font-size: 14px !important;
            margin-right: 1px;
        }
        
        /* Hacer botones de texto largo más pequeños */
        .card-header .col-md-6.text-right .btn#btnSincronizarStock {
            font-size: 9px !important;
            padding: 5px 4px !important;
        }
    }
    
    /* Si aún hay problemas, forzar que los botones se apilen */
    @media (min-width: 768px) and (max-width: 1024px) {
        .card-header .col-md-6.text-right {
            flex-direction: row !important;
        }
        
        /* Asegurar que los botones no se salgan del contenedor */
        .card-header .col-md-6.text-right .btn {
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        /* Si el contenedor es muy pequeño, hacer que los botones se apilen en dos filas */
        @media (max-width: 850px) {
            .card-header .col-md-6.text-right {
                flex-direction: column !important;
                align-items: flex-end !important;
            }
            
            .card-header .col-md-6.text-right .btn {
                width: auto;
                margin-bottom: 4px;
            }
        }
    }
    
    /* Tabla de productos - Scroll horizontal */
    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        display: block;
        width: 100%;
        position: relative;
    }
    
    .table-responsive table {
        width: 100%;
        min-width: 1200px; /* Ancho mínimo para mantener todas las columnas */
        margin-bottom: 0;
    }
    
    /* Ajustar tamaño de columnas en tablets */
    .table-responsive th,
    .table-responsive td {
        white-space: nowrap;
        padding: 8px 6px;
        font-size: 13px;
    }
    
    /* Columnas específicas más compactas */
    .table-responsive th:nth-child(1),
    .table-responsive td:nth-child(1) {
        min-width: 40px;
        max-width: 50px;
    }
    
    .table-responsive th:nth-child(2),
    .table-responsive td:nth-child(2) {
        min-width: 120px;
        max-width: 150px;
    }
    
    .table-responsive th:nth-child(3),
    .table-responsive td:nth-child(3) {
        min-width: 200px;
        max-width: 250px;
    }
    
    .table-responsive th:nth-child(4),
    .table-responsive td:nth-child(4) {
        min-width: 70px;
        max-width: 90px;
    }
    
    .table-responsive th:nth-child(5),
    .table-responsive td:nth-child(5) {
        min-width: 80px;
        max-width: 100px;
    }
    
    .table-responsive th:nth-child(6),
    .table-responsive td:nth-child(6) {
        min-width: 90px;
        max-width: 110px;
    }
    
    .table-responsive th:nth-child(7),
    .table-responsive td:nth-child(7) {
        min-width: 80px;
        max-width: 100px;
    }
    
    .table-responsive th:nth-child(8),
    .table-responsive td:nth-child(8) {
        min-width: 80px;
        max-width: 100px;
    }
    
    .table-responsive th:nth-child(9),
    .table-responsive td:nth-child(9) {
        min-width: 90px;
        max-width: 110px;
    }
    
    .table-responsive th:nth-child(10),
    .table-responsive td:nth-child(10) {
        min-width: 90px;
        max-width: 110px;
    }
    
    .table-responsive th:nth-child(11),
    .table-responsive td:nth-child(11) {
        min-width: 90px;
        max-width: 110px;
    }
    
    .table-responsive th:nth-child(12),
    .table-responsive td:nth-child(12) {
        min-width: 80px;
        max-width: 100px;
    }
    
    .table-responsive th:nth-child(13),
    .table-responsive td:nth-child(13) {
        min-width: 100px;
        max-width: 120px;
    }
    
    /* Ajustar inputs dentro de la tabla */
    .table-responsive .form-control-sm {
        font-size: 12px;
        padding: 4px 6px;
    }
    
    .table-responsive .input-group-sm > .form-control {
        font-size: 12px;
        padding: 4px 6px;
    }
    
    .table-responsive .btn-sm {
        font-size: 11px;
        padding: 4px 8px;
    }
    
    /* Badges más compactos */
    .table-responsive .badge {
        font-size: 11px;
        padding: 4px 6px;
    }
    
    /* Evitar que las tablas se superpongan */
    .card {
        margin-bottom: 20px;
        overflow: visible;
    }
    
    .card-body {
        overflow: visible;
    }
    
    /* Asegurar que los contenedores no se superpongan */
    .row {
        margin-left: -10px;
        margin-right: -10px;
    }
    
    .row > [class*="col-"] {
        padding-left: 10px;
        padding-right: 10px;
    }
    
    /* Header de la tabla fijo al hacer scroll horizontal */
    .table-responsive thead {
        position: sticky;
        top: 0;
        z-index: 10;
        background-color: #fff;
    }
    
    .table-responsive thead th {
        background-color: #f8f9fa;
        border-bottom: 2px solid #dee2e6;
    }
}

/* Estilos adicionales para tablets en modo landscape */
@media (min-width: 768px) and (max-width: 1024px) and (orientation: landscape) {
    .table-responsive table {
        min-width: 1400px;
    }
    
    .table-responsive th,
    .table-responsive td {
        padding: 6px 4px;
        font-size: 12px;
    }
}

/* Estilos para prevenir superposición en contenedores */
@media (min-width: 768px) and (max-width: 1024px) {
    .container-fluid {
        padding-left: 15px;
        padding-right: 15px;
    }
    
    /* Asegurar que las cards no se superpongan */
    .card + .card {
        margin-top: 20px;
    }
    
    /* Tabla en modal también responsiva */
    .modal-body .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    
    .modal-body .table-responsive table {
        min-width: 800px;
    }
}
</style>
@endpush

@endsection