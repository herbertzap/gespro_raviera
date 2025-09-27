@extends('layouts.app', ['pageSlug' => 'facturas-pendientes'])

@section('title', 'Ver Factura Pendiente')

@section('content')
<div class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Ver Factura Pendiente {{ $factura['TIPO_DOCTO'] }}-{{ $factura['NRO_DOCTO'] }}</h4>
                        <p class="card-category">Detalles de la Factura Pendiente</p>
                    </div>
                    <div class="card-body">
                        <!-- Información del Cliente -->
                        <div class="row">
                            <div class="col-md-6">
                                <h5>Información del Cliente</h5>
                                <table class="table table-borderless">
                                    <tr>
                                        <td><strong>Código:</strong></td>
                                        <td>{{ $factura['CODIGO'] }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Nombre:</strong></td>
                                        <td>{{ $factura['CLIENTE'] }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Sucursal:</strong></td>
                                        <td>{{ $factura['SUC'] }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Vendedor:</strong></td>
                                        <td>{{ $factura['VENDEDOR'] }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Teléfono:</strong></td>
                                        <td>{{ $factura['FONO'] }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Dirección:</strong></td>
                                        <td>{{ $factura['DIRECCION'] }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Región:</strong></td>
                                        <td>{{ $factura['REGION'] }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Comuna:</strong></td>
                                        <td>{{ $factura['COMUNA'] }}</td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h5>Información de la Factura</h5>
                                <table class="table table-borderless">
                                    <tr>
                                        <td><strong>Número:</strong></td>
                                        <td>{{ $factura['TIPO_DOCTO'] }}-{{ $factura['NRO_DOCTO'] }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Fecha Emisión:</strong></td>
                                        <td>{{ $factura['EMISION'] }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Primer Vencimiento:</strong></td>
                                        <td>{{ $factura['P_VCMTO'] }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Último Vencimiento:</strong></td>
                                        <td>{{ $factura['U_VCMTO'] }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Días Pendiente:</strong></td>
                                        <td>{{ $factura['DIAS'] }} días</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Estado:</strong></td>
                                        <td>
                                            <span class="badge badge-{{ 
                                                $factura['ESTADO'] == 'VIGENTE' ? 'success' : 
                                                ($factura['ESTADO'] == 'POR VENCER' ? 'warning' : 
                                                ($factura['ESTADO'] == 'VENCIDO' ? 'danger' : 
                                                ($factura['ESTADO'] == 'MOROSO' ? 'dark' : 'secondary'))) 
                                            }}">
                                                {{ $factura['ESTADO'] }}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Valor Total:</strong></td>
                                        <td><strong>${{ number_format($factura['VALOR'], 0, ',', '.') }}</strong></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Abonos:</strong></td>
                                        <td><strong>${{ number_format($factura['ABONOS'], 0, ',', '.') }}</strong></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Saldo Pendiente:</strong></td>
                                        <td><strong>${{ number_format($factura['SALDO'], 0, ',', '.') }}</strong></td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <!-- Desglose por Estado -->
                        <div class="row mt-4">
                            <div class="col-md-12">
                                <h5>Desglose por Estado</h5>
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="card card-stats">
                                            <div class="card-header card-header-success card-header-icon">
                                                <div class="card-icon">
                                                    <i class="material-icons">check_circle</i>
                                                </div>
                                                <p class="card-category">Vigente</p>
                                                <h3 class="card-title">${{ number_format($factura['VIGENTE'], 0, ',', '.') }}</h3>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card card-stats">
                                            <div class="card-header card-header-warning card-header-icon">
                                                <div class="card-icon">
                                                    <i class="material-icons">schedule</i>
                                                </div>
                                                <p class="card-category">Por Vencer</p>
                                                <h3 class="card-title">${{ number_format($factura['POR_VENCER'], 0, ',', '.') }}</h3>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card card-stats">
                                            <div class="card-header card-header-danger card-header-icon">
                                                <div class="card-icon">
                                                    <i class="material-icons">warning</i>
                                                </div>
                                                <p class="card-category">Vencido</p>
                                                <h3 class="card-title">${{ number_format($factura['VENCIDO'], 0, ',', '.') }}</h3>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card card-stats">
                                            <div class="card-header card-header-info card-header-icon">
                                                <div class="card-icon">
                                                    <i class="material-icons">inventory</i>
                                                </div>
                                                <p class="card-category">Productos</p>
                                                <h3 class="card-title">{{ $factura['CANTIDAD_PRODUCTOS'] }}</h3>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Productos de la Factura -->
                        @if(isset($factura['productos']) && count($factura['productos']) > 0)
                        <div class="row mt-4">
                            <div class="col-md-12">
                                <h5>Productos de la Factura ({{ count($factura['productos']) }} productos)</h5>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead class="thead">
                                            <tr>
                                                <th>Código</th>
                                                <th>Producto</th>
                                                <th>Cantidad</th>
                                                <th>Precio Unit.</th>
                                                <th>Valor</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($factura['productos'] as $producto)
                                            <tr>
                                                <td>{{ $producto['KOPRCT'] }}</td>
                                                <td>{{ $producto['NOKOPR'] }}</td>
                                                <td>{{ number_format($producto['CAPRCO1'], 0, ',', '.') }}</td>
                                                <td>${{ number_format($producto['PUNIT'], 0, ',', '.') }}</td>
                                                <td>${{ number_format($producto['VALOR'], 0, ',', '.') }}</td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        @endif

                        <!-- Relación FCV con NVV -->
                        @if(isset($relacionFcvNvv) && count($relacionFcvNvv) > 0)
                        <div class="row mt-4">
                            <div class="col-md-12">
                                <h5>Notas de Venta Asociadas ({{ count($relacionFcvNvv) }} productos)</h5>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead class="thead">
                                            <tr>
                                                <th>Producto</th>
                                                <th>Cant. FCV</th>
                                                <th>NVV Asociada</th>
                                                <th>Cant. NVV</th>
                                                <th>Pendiente</th>
                                                <th>Acción</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($relacionFcvNvv as $relacion)
                                            <tr>
                                                <td>
                                                    <strong>{{ $relacion['CODIGO_PRODUCTO'] }}</strong><br>
                                                    <small>{{ $relacion['NOMBRE_PRODUCTO'] }}</small>
                                                </td>
                                                <td>{{ number_format($relacion['CANTIDAD_FCV'], 0, ',', '.') }}</td>
                                                <td>
                                                    @if(!empty($relacion['NUMERO_NVV']))
                                                        <span class="badge badge-info">{{ $relacion['TIPO_NVV'] }}-{{ $relacion['NUMERO_NVV'] }}</span>
                                                    @else
                                                        <span class="badge badge-warning">Sin NVV</span>
                                                    @endif
                                                </td>
                                                <td>{{ number_format($relacion['CANTIDAD_NVV'], 0, ',', '.') }}</td>
                                                <td>
                                                    @if($relacion['PENDIENTE'] > 0)
                                                        <span class="badge badge-danger">{{ number_format($relacion['PENDIENTE'], 0, ',', '.') }}</span>
                                                    @else
                                                        <span class="badge badge-success">0</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if(!empty($relacion['NUMERO_NVV']))
                                                        <a href="#" class="btn btn-sm btn-primary" title="Ver NVV {{ $relacion['NUMERO_NVV'] }}">
                                                            <i class="material-icons">visibility</i>
                                                        </a>
                                                    @endif
                                                </td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        @else
                        <div class="row mt-4">
                            <div class="col-md-12">
                                <div class="alert alert-info">
                                    <i class="material-icons">info</i>
                                    No se encontraron notas de venta asociadas a esta factura.
                                </div>
                            </div>
                        </div>
                        @endif

                        <!-- Botones de Acción -->
                        <div class="row mt-4">
                            <div class="col-md-12 text-center">
                                <a href="{{ route('facturas-pendientes.index') }}" class="btn btn-secondary btn-lg">
                                    <i class="material-icons">arrow_back</i> Volver
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
