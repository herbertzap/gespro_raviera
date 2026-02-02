@extends('layouts.app', ['pageSlug' => 'manejo-stock-historial'])

@section('content')
<div class="content">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Historial de Capturas y Códigos de Barras</h4>
                </div>
                <div class="card-body">
                    <!-- Historial de Capturas de Stock -->
                    <div class="mb-5">
                        <h5 class="mb-3">
                            <i class="tim-icons icon-notes"></i> Historial de Capturas de Stock
                        </h5>
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered">
                                <thead class="thead-dark">
                                    <tr>
                                        <th>Fecha</th>
                                        <th>SKU</th>
                                        <th>Producto</th>
                                        <th>Bodega</th>
                                        <th>Ubicación</th>
                                        <th>Captura 1</th>
                                        <th>Captura 2</th>
                                        <th>STFI1</th>
                                        <th>STFI2</th>
                                        <th>TIDO</th>
                                        <th>Funcionario</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($capturas as $captura)
                                    <tr>
                                        <td>{{ $captura->created_at->format('d/m/Y H:i') }}</td>
                                        <td><strong>{{ $captura->sku }}</strong></td>
                                        <td>{{ $captura->nombre_producto }}</td>
                                        <td>
                                            {{ $captura->bodega->nombre_bodega ?? '-' }}
                                            @if($captura->bodega)
                                                <br><small class="text-muted">({{ $captura->bodega->kobo }})</small>
                                            @endif
                                        </td>
                                        <td>
                                            {{ $captura->codigo_ubicacion ?? '-' }}
                                            @if($captura->ubicacion)
                                                <br><small class="text-muted">{{ $captura->ubicacion->descripcion }}</small>
                                            @endif
                                        </td>
                                        <td>{{ number_format($captura->captura_1, 3) }}</td>
                                        <td>{{ $captura->captura_2 ? number_format($captura->captura_2, 3) : '-' }}</td>
                                        <td>
                                            @if($captura->stfi1 !== null)
                                                {{ number_format($captura->stfi1, 3) }}
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>
                                            @if($captura->stfi2 !== null)
                                                {{ number_format($captura->stfi2, 3) }}
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>
                                            @if($captura->tido)
                                                <span class="badge badge-{{ $captura->tido == 'GRI' ? 'success' : 'warning' }}">
                                                    {{ $captura->tido }}
                                                </span>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>{{ $captura->funcionario ?? '-' }}</td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="11" class="text-center text-muted">
                                            No hay capturas registradas
                                        </td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-3">
                            {{ $capturas->links() }}
                        </div>
                    </div>

                    <hr class="my-5">

                    <!-- Historial de Modificaciones de Códigos de Barras -->
                    <div>
                        <h5 class="mb-3">
                            <i class="tim-icons icon-barcode"></i> Historial de Modificaciones de Códigos de Barras
                        </h5>
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered">
                                <thead class="thead-dark">
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Código de Barras</th>
                                        <th>SKU Asociado</th>
                                        <th>Bodega</th>
                                        <th>Usuario</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($codigosBarras as $log)
                                    <tr>
                                        <td>{{ $log->created_at->format('d/m/Y H:i') }}</td>
                                        <td><strong>{{ $log->barcode }}</strong></td>
                                        <td><strong>{{ $log->sku }}</strong></td>
                                        <td>
                                            {{ $log->bodega->nombre_bodega ?? '-' }}
                                            @if($log->bodega)
                                                <br><small class="text-muted">({{ $log->bodega->kobo }})</small>
                                            @endif
                                        </td>
                                        <td>{{ $log->user->name ?? '-' }}</td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">
                                            No hay modificaciones de códigos de barras registradas
                                        </td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-3">
                            {{ $codigosBarras->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

