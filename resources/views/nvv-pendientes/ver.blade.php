@extends('layouts.app', ['pageSlug' => 'nvv-pendientes'])

@section('title', 'Ver NVV Pendiente')

@section('content')
<div class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Ver NVV Pendiente #{{ $nvv['NUM'] }}</h4>
                        <p class="card-category">Detalles de la Nota de Venta Pendiente</p>
                    </div>
                    <div class="card-body">
                        <!-- Información del Cliente -->
                        <div class="row">
                            <div class="col-md-6">
                                <h5>Información del Cliente</h5>
                                <table class="table table-borderless">
                                    <tr>
                                        <td><strong>Código:</strong></td>
                                        <td>{{ $nvv['COD_CLI'] }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Nombre:</strong></td>
                                        <td>{{ $nvv['CLIE'] }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Vendedor:</strong></td>
                                        <td>{{ $nvv['NOKOFU'] }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Región:</strong></td>
                                        <td>{{ $nvv['NOKOCI'] }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Comuna:</strong></td>
                                        <td>{{ $nvv['NOKOCM'] }}</td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h5>Información de la NVV</h5>
                                <table class="table table-borderless">
                                    <tr>
                                        <td><strong>Número:</strong></td>
                                        <td>{{ $nvv['NUM'] }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Fecha Emisión:</strong></td>
                                        <td>{{ $nvv['EMIS_FCV'] }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Días Pendiente:</strong></td>
                                        <td>{{ $nvv['DIAS'] }} días</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Rango:</strong></td>
                                        <td><span class="badge badge-info">{{ $nvv['Rango'] }}</span></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Total Pendiente:</strong></td>
                                        <td><strong>${{ number_format($nvv['TOTAL_PENDIENTE'], 0, ',', '.') }}</strong></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Valor Pendiente:</strong></td>
                                        <td><strong>${{ number_format($nvv['TOTAL_VALOR_PENDIENTE'], 0, ',', '.') }}</strong></td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <!-- Productos de la NVV -->
                        <div class="row mt-4">
                            <div class="col-md-12">
                                <h5>Productos de la NVV ({{ $nvv['CANTIDAD_PRODUCTOS'] }} productos)</h5>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead class="thead">
                                            <tr>
                                                <th>Código</th>
                                                <th>Producto</th>
                                                <th>Cantidad</th>
                                                <th>Facturado</th>
                                                <th>Pendiente</th>
                                                <th>Precio Unit.</th>
                                                <th>Valor Pendiente</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($nvv['productos'] as $producto)
                                            <tr>
                                                <td>{{ $producto['KOPRCT'] }}</td>
                                                <td>{{ $producto['NOKOPR'] }}</td>
                                                <td>{{ number_format($producto['CAPRCO1'], 0, ',', '.') }}</td>
                                                <td>{{ number_format($producto['FACT'], 0, ',', '.') }}</td>
                                                <td>
                                                    <span class="badge badge-warning">
                                                        {{ number_format($producto['PEND'], 0, ',', '.') }}
                                                    </span>
                                                </td>
                                                <td>${{ number_format($producto['PUNIT'], 0, ',', '.') }}</td>
                                                <td>${{ number_format($producto['PEND_VAL'], 0, ',', '.') }}</td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Botones de Acción -->
                        <div class="row mt-4">
                            <div class="col-md-12 text-center">
                                <a href="{{ route('nvv-pendientes.index') }}" class="btn btn-secondary btn-lg">
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
