@extends('layouts.app')

@section('title', 'Cliente - ' . $cliente->nombre_cliente)

@section('content')
<div class="content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h4 class="card-title">{{ $cliente->nombre_cliente }}</h4>
                                <p class="card-category">Código: {{ $cliente->codigo_cliente }}</p>
                            </div>
                            <div>
                                <a href="{{ route('dashboard') }}" class="btn btn-secondary">
                                    <i class="material-icons">arrow_back</i> Volver
                                </a>
                                @if($validacion['puede'] && auth()->user()->hasRole('Vendedor'))
                                    <a href="{{ url('/cotizacion/nueva?cliente=' . $cliente->codigo_cliente . '&nombre=' . urlencode($cliente->nombre_cliente) . '&tipo_documento=cotizacion') }}" 
                                       class="btn btn-info">
                                        <i class="material-icons">description</i> Nueva Cotización
                                    </a>
                                    <a href="{{ url('/nota-venta/nueva?cliente=' . $cliente->codigo_cliente . '&nombre=' . urlencode($cliente->nombre_cliente)) }}" 
                                       class="btn btn-primary">
                                        <i class="material-icons">add_shopping_cart</i> Nueva Nota De Venta
                                    </a>
                                @elseif(!$validacion['puede'])
                                    <button class="btn btn-danger" disabled title="{{ $validacion['motivo'] }}">
                                        <i class="material-icons">block</i> Bloqueado
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Información del Cliente -->
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header card-header-primary">
                        <h4 class="card-title">Información General</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Código:</strong> {{ $cliente->codigo_cliente }}</p>
                                <p><strong>Nombre:</strong> {{ $cliente->nombre_cliente }}</p>
                                <p><strong>Dirección:</strong> {{ $cliente->direccion ?: 'No especificada' }}</p>
                                <p><strong>Teléfono:</strong> {{ $cliente->telefono ?: 'No especificado' }}</p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Región:</strong> {{ $cliente->region ?: 'No especificada' }}</p>
                                <p><strong>Comuna:</strong> {{ $cliente->comuna ?: 'No especificada' }}</p>
                                <p><strong>Vendedor:</strong> {{ $cliente->codigo_vendedor }}</p>
                                <p><strong>Estado:</strong> 
                                    @if($cliente->bloqueado)
                                        <span class="badge badge-danger">Bloqueado</span>
                                    @else
                                        <span class="badge badge-success">Activo</span>
                                    @endif
                                </p>
                                <p><strong>Condición de Pago:</strong> 
                                    <span class="badge badge-info">{{ $cliente->condicion_pago ?: 'No especificada' }}</span>
                                </p>
                                @if($cliente->comentario_administracion)
                                <p><strong>Comentario de Administración:</strong></p>
                                <div class="badge badge-info">
                                    {{ $cliente->comentario_administracion }}
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-header card-header-info">
                        <h4 class="card-title">Información Comercial</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Lista de Precios:</strong></p>
                                <p>{{ $cliente->lista_precios_nombre ?: 'No asignada' }}</p>
                                <p><strong>Código Lista:</strong> {{ $cliente->lista_precios_codigo ?: 'N/A' }}</p>
                                <p><strong>Última Venta:</strong></p>
                                <p class="text-muted">{{ $creditoCliente['ultima_venta'] ?: 'Sin registros' }}</p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Compras ultimos 3 meses:</strong></p>
                                <p class="h5 text-primary">${{ number_format($creditoCompras['venta_3_meses'] ?? 0, 0) }}</p>
                                <p><strong>Promedio Mensual:</strong></p>
                                <p class="h6 text-info">${{ number_format($creditoCompras['venta_mensual_promedio'] ?? 0, 0) }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Información de Crédito -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header card-header-warning">
                        <h4 class="card-title">Información de Crédito</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <!-- Crédito Sin Documentos -->
                            <div class="col-md-4">
                                <div class="card card-stats">
                                    <div class="card-header card-header-primary card-header-icon">
                                        <div class="card-icon">
                                            <i class="material-icons">description</i>
                                        </div>
                                        <p class="card-category">CRÉDITO SIN DOC.</p>
                                        <h3 class="card-title">${{ number_format($creditoCliente['credito_sin_doc'] ?? 0, 0) }}</h3>
                                    </div>
                                    <div class="card-footer">
                                        <div class="stats">
                                            <div class="row">
                                                <div class="col-6">
                                                    <small class="text-muted">Utilizado:</small><br>
                                                    <span class="text-danger">${{ number_format($creditoCliente['credito_sin_doc_util'] ?? 0, 0) }}</span>
                                                </div>
                                                <div class="col-6">
                                                    <small class="text-muted">Disponible:</small><br>
                                                    <span class="text-success">${{ number_format($creditoCliente['credito_sin_doc_disp'] ?? 0, 0) }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Crédito Cheques -->
                            <div class="col-md-4">
                                <div class="card card-stats">
                                    <div class="card-header card-header-info card-header-icon">
                                        <div class="card-icon">
                                            <i class="material-icons">account_balance</i>
                                        </div>
                                        <p class="card-category">CRÉDITO CHEQUES</p>
                                        <h3 class="card-title">${{ number_format($creditoCliente['credito_cheques'] ?? 0, 0) }}</h3>
                                    </div>
                                    <div class="card-footer">
                                        <div class="stats">
                                            <div class="row">
                                                <div class="col-6">
                                                    <small class="text-muted">Utilizado:</small><br>
                                                    <span class="text-danger">${{ number_format($creditoCliente['credito_cheques_util'] ?? 0, 0) }}</span>
                                                </div>
                                                <div class="col-6">
                                                    <small class="text-muted">Disponible:</small><br>
                                                    <span class="text-success">${{ number_format($creditoCliente['credito_cheques_disp'] ?? 0, 0) }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Crédito Total -->
                            <div class="col-md-4">
                                <div class="card card-stats">
                                    <div class="card-header card-header-success card-header-icon">
                                        <div class="card-icon">
                                            <i class="material-icons">account_balance_wallet</i>
                                        </div>
                                        <p class="card-category">CRÉDITO TOTAL</p>
                                        <h3 class="card-title">${{ number_format($creditoCliente['credito_total'] ?? 0, 0) }}</h3>
                                    </div>
                                    <div class="card-footer">
                                        <div class="stats">
                                            <div class="row">
                                                <div class="col-6">
                                                    <small class="text-muted">Utilizado:</small><br>
                                                    <span class="text-danger">${{ number_format($creditoCliente['credito_total_util'] ?? 0, 0) }}</span>
                                                </div>
                                                <div class="col-6">
                                                    <small class="text-muted">Disponible:</small><br>
                                                    <span class="text-success">${{ number_format($creditoCliente['credito_total_disp'] ?? 0, 0) }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Resumen de Crédito -->
                        <div class="row mt-3">
                            <div class="col-md-12">
                                <div class="alert ">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <strong>Estado:</strong> 
                                            <span class="badge badge-{{ $creditoCliente['estado'] == 'BLOQUEADO' ? 'danger' : 'success' }}">
                                                {{ $creditoCliente['estado'] }}
                                            </span>
                                        </div>
                                        <div class="col-md-3">
                                            <strong>Venta Mes:</strong> 
                                            <span class="text-primary">${{ number_format($creditoCliente['venta_mes'] ?? 0, 0) }}</span>
                                        </div>
                                        <div class="col-md-3">
                                            <strong>Venta 3M:</strong> 
                                            <span class="text-info">${{ number_format($creditoCliente['venta_3m'] ?? 0, 0) }}</span>
                                        </div>
                                        <div class="col-md-3">
                                            <strong>Última Venta:</strong> 
                                            <span class="text-muted">{{ $creditoCliente['ultima_venta'] ?: 'Sin registros' }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Facturas Pendientes -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header card-header-danger">
                        <h4 class="card-title">Facturas Pendientes</h4>
                        <p class="card-category">Documentos por cobrar</p>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead class="text-danger">
                                    <tr>
                                        <th>Tipo</th>
                                        <th>Número</th>
                                        <th>Emisión</th>
                                        <th>Vencimiento</th>
                                        <th>Días Vencido</th>
                                        <th>Valor</th>
                                        <th>Abonos</th>
                                        <th>Saldo</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($facturasPendientes as $factura)
                                    <tr>
                                        <td>
                                            <span class="badge badge-{{ 
                                                $factura['TIPO_DOCTO'] == 'FCV' ? 'success' : 
                                                ($factura['TIPO_DOCTO'] == 'FDV' ? 'info' : 'warning') 
                                            }}">
                                                {{ $factura['TIPO_DOCTO'] }}
                                            </span>
                                        </td>
                                        <td>{{ $factura['NRO_DOCTO'] }}</td>
                                        <td>{{ $factura['EMISION'] }}</td>
                                        <td>{{ $factura['VENCIMIENTO'] }}</td>
                                        <td>
                                            <span class="badge badge-{{ 
                                                $factura['DIAS_VENCIDO'] < 0 ? 'success' : 
                                                ($factura['DIAS_VENCIDO'] < 8 ? 'warning' : 
                                                ($factura['DIAS_VENCIDO'] < 31 ? 'danger' : 'dark')) 
                                            }}">
                                                {{ $factura['DIAS_VENCIDO'] }}
                                            </span>
                                        </td>
                                        <td>${{ number_format($factura['VALOR'], 0) }}</td>
                                        <td>${{ number_format($factura['ABONOS'], 0) }}</td>
                                        <td>${{ number_format($factura['SALDO'], 0) }}</td>
                                        <td>
                                            <span class="badge badge-{{ 
                                                $factura['ESTADO'] == 'VIGENTE' ? 'success' : 
                                                ($factura['ESTADO'] == 'POR VENCER' ? 'warning' : 
                                                ($factura['ESTADO'] == 'VENCIDO' ? 'danger' : 
                                                ($factura['ESTADO'] == 'MOROSO' ? 'danger' : 'dark'))) 
                                            }}">
                                                {{ $factura['ESTADO'] }}
                                            </span>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="9" class="text-center">No hay facturas pendientes</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- NVV del Sistema -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header card-header-info">
                        <h4 class="card-title">NVV del Sistema</h4>
                        <p class="card-category">Notas de venta creadas en el sistema</p>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead class="text-info">
                                    <tr>
                                        <th>Número</th>
                                        <th>Fecha Creación</th>
                                        <th>Vendedor</th>
                                        <th>Productos</th>
                                        <th>Valor Total</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($nvvSistema as $nvv)
                                    <tr>
                                        <td>
                                            <strong>{{ $nvv['numero'] }}</strong>
                                        </td>
                                        <td>{{ $nvv['fecha_creacion'] }}</td>
                                        <td>{{ $nvv['vendedor'] }}</td>
                                        <td>
                                            <span class="badge badge-primary">{{ $nvv['total_productos'] }} productos</span>
                                        </td>
                                        <td>${{ number_format($nvv['valor_total'], 0) }}</td>
                                        <td>
                                            @if($nvv['estado'] == 'Ingresada')
                                                <span class="badge badge-success">{{ $nvv['estado'] }}</span>
                                            @else
                                                <span class="badge badge-warning">{{ $nvv['estado'] }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            <a href="{{ route('aprobaciones.show', $nvv['id']) }}" class="btn btn-sm btn-info">
                                                <i class="material-icons">visibility</i> Ver
                                            </a>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="7" class="text-center">No hay NVV creadas en el sistema</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cotizaciones del Cliente (MySQL) -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header card-header-info">
                        <h4 class="card-title">Cotizaciones</h4>
                        <p class="card-category">Cotizaciones enviadas al cliente (sin aprobaciones)</p>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead class="text-info">
                                    <tr>
                                        <th>N° Cotización</th>
                                        <th>Fecha</th>
                                        <th>Total</th>
                                        <th>Productos</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $cotizacionesCliente = \App\Models\Cotizacion::where('cliente_codigo', $cliente->codigo_cliente)
                                            ->where('tipo_documento', 'cotizacion')
                                            ->with('productos')
                                            ->orderBy('created_at', 'desc')
                                            ->limit(10)
                                            ->get();
                                    @endphp
                                    @forelse($cotizacionesCliente as $cotizacion)
                                    <tr>
                                        <td><strong>COT#{{ $cotizacion->id }}</strong></td>
                                        <td>{{ $cotizacion->created_at->format('d/m/Y') }}</td>
                                        <td><strong>${{ number_format($cotizacion->total, 0) }}</strong></td>
                                        <td>
                                            <span class="badge badge-primary">{{ $cotizacion->productos->count() }} producto(s)</span>
                                        </td>
                                        <td>
                                            @if($cotizacion->estado_aprobacion === 'pendiente' || $cotizacion->estado_aprobacion === 'rechazada')
                                                <span class="badge badge-info">Cotización</span>
                                            @else
                                                <span class="badge badge-success">Convertida a NVV</span>
                                            @endif
                                        </td>
                                        <td>
                                            <a href="{{ route('cotizacion.ver', $cotizacion->id) }}" class="btn btn-sm btn-info" title="Ver">
                                                <i class="material-icons">visibility</i>
                                            </a>
                                            @if(in_array($cotizacion->estado_aprobacion, ['pendiente', 'rechazada']))
                                                <a href="{{ route('cotizacion.editar', $cotizacion->id) }}" class="btn btn-sm btn-warning" title="Editar">
                                                    <i class="material-icons">edit</i>
                                                </a>
                                            @endif
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="6" class="text-center">No hay cotizaciones para este cliente</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        @if($cotizacionesCliente->count() > 0)
                        <div class="text-right mt-3">
                            <a href="{{ route('cotizaciones.index') }}?cliente={{ $cliente->codigo_cliente }}&tipo_documento=cotizacion" class="btn btn-sm btn-info">
                                <i class="material-icons">list</i> Ver todas las cotizaciones
                            </a>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Notas de Venta Pendientes (SQL Server) -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header card-header-warning">
                        <h4 class="card-title">Notas de Venta Pendientes (SQL Server)</h4>
                        <p class="card-category">Notas de venta pendientes de facturación</p>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead class="text-warning">
                                    <tr>
                                        <th>Número NVV</th>
                                        <th>Emisión</th>
                                        <th>Producto</th>
                                        <th>Cantidad Total</th>
                                        <th>Facturado</th>
                                        <th>Pendiente</th>
                                        <th>Días</th>
                                        <th>Rango</th>
                                        <th>Valor Pendiente</th>
                                        <th>Factura</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($notasVenta as $nota)
                                    <tr>
                                        <td>{{ $nota['NRO_DOCTO'] }}</td>
                                        <td>{{ $nota['EMISION'] }}</td>
                                        <td>
                                            <strong>{{ $nota['CODIGO_PRODUCTO'] }}</strong><br>
                                            <small>{{ $nota['NOMBRE_PRODUCTO'] }}</small>
                                        </td>
                                        <td>{{ number_format($nota['CANTIDAD_TOTAL'], 0) }}</td>
                                        <td>
                                            <span class="badge badge-success">{{ number_format($nota['CANTIDAD_FACTURADA'], 0) }}</span>
                                        </td>
                                        <td>
                                            <span class="badge badge-warning">{{ number_format($nota['CANTIDAD_PENDIENTE'], 0) }}</span>
                                        </td>
                                        <td>
                                            <span class="badge badge-{{ 
                                                $nota['DIAS'] < 8 ? 'success' : 
                                                ($nota['DIAS'] < 31 ? 'warning' : 
                                                ($nota['DIAS'] < 61 ? 'danger' : 'dark')) 
                                            }}">
                                                {{ $nota['DIAS'] }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-{{ 
                                                strpos($nota['RANGO'], '1 y 7') !== false ? 'success' : 
                                                (strpos($nota['RANGO'], '8 y 30') !== false ? 'warning' : 
                                                (strpos($nota['RANGO'], '31 y 60') !== false ? 'danger' : 'dark')) 
                                            }}">
                                                {{ $nota['RANGO'] }}
                                            </span>
                                        </td>
                                        <td>${{ number_format($nota['VALOR_PENDIENTE'], 0) }}</td>
                                        <td>
                                            @if(!empty($nota['TIPO_FACTURA']) && !empty($nota['NUMERO_FACTURA']))
                                                <span class="badge badge-info">{{ $nota['TIPO_FACTURA'] }} {{ $nota['NUMERO_FACTURA'] }}</span>
                                            @else
                                                <span class="badge badge-secondary">Sin facturar</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="10" class="text-center">No hay notas de venta pendientes</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Resumen -->
        <div class="row">
            <div class="col-md-3">
                <div class="card card-stats">
                    <div class="card-header card-header-danger card-header-icon">
                        <div class="card-icon">
                            <i class="material-icons">receipt</i>
                        </div>
                        <p class="card-category">Facturas Pendientes</p>
                        <h3 class="card-title">{{ count($facturasPendientes) }}</h3>
                    </div>
                    <div class="card-footer">
                        <div class="stats">
                            <i class="material-icons text-danger">info</i>
                            Documentos por cobrar
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card card-stats">
                    <div class="card-header card-header-info card-header-icon">
                        <div class="card-icon">
                            <i class="material-icons">shopping_cart</i>
                        </div>
                        <p class="card-category">NVV del Sistema</p>
                        <h3 class="card-title">{{ count($nvvSistema) }}</h3>
                    </div>
                    <div class="card-footer">
                        <div class="stats">
                            <i class="material-icons text-info">info</i>
                            NVV creadas en el sistema
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card card-stats">
                    <div class="card-header card-header-warning card-header-icon">
                        <div class="card-icon">
                            <i class="material-icons">pending_actions</i>
                        </div>
                        <p class="card-category">NVV SQL Server</p>
                        <h3 class="card-title">{{ count($notasVenta) }}</h3>
                    </div>
                    <div class="card-footer">
                        <div class="stats">
                            <i class="material-icons text-warning">info</i>
                            Historial de NVV
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card card-stats">
                    <div class="card-header card-header-success card-header-icon">
                        <div class="card-icon">
                            <i class="material-icons">attach_money</i>
                        </div>
                        <p class="card-category">Total Saldo</p>
                        <h3 class="card-title">${{ number_format(array_sum(array_column($facturasPendientes, 'SALDO')), 0) }}</h3>
                    </div>
                    <div class="card-footer">
                        <div class="stats">
                            <i class="material-icons text-success">trending_up</i>
                            Saldo total pendiente
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card card-stats">
                    <div class="card-header card-header-primary card-header-icon">
                        <div class="card-icon">
                            <i class="material-icons">shopping_cart</i>
                        </div>
                        <p class="card-category">Crédito 3M</p>
                        <h3 class="card-title">${{ number_format($creditoCompras['venta_3_meses'] ?? 0, 0) }}</h3>
                    </div>
                    <div class="card-footer">
                        <div class="stats">
                            <i class="material-icons text-primary">trending_up</i>
                            Ventas últimos 3 meses
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
