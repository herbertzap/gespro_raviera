@extends('layouts.app', ['pageSlug' => 'cotizaciones'])

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="row align-items-center">
                        <div class="col-8">
                            <h3 class="mb-0">
                                <i class="tim-icons icon-notes text-primary"></i>
                                Nota de Venta #{{ $cotizacion->id }}
                            </h3>
                            <p class="text-muted mb-0">
                                Cliente: {{ $cotizacion->cliente_nombre }} ({{ $cotizacion->cliente_codigo }})
                            </p>
                        </div>
                        <div class="col-4 text-right">
                            <a href="{{ route('cotizaciones.index') }}" class="btn btn-sm btn-secondary">
                                <i class="tim-icons icon-minimal-left"></i> Volver
                            </a>
                            <a href="{{ route('aprobaciones.historial', $cotizacion->id) }}" class="btn btn-sm btn-info ml-2">
                                <i class="tim-icons icon-time-alarm"></i> Historial
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Tabs de navegación -->
                    <ul class="nav nav-tabs" id="cotizacionTabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="detalle-tab" data-toggle="tab" href="#detalle" role="tab" aria-controls="detalle" aria-selected="true">
                                <i class="tim-icons icon-notes"></i> Detalle
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="historial-tab" data-toggle="tab" href="#historial" role="tab" aria-controls="historial" aria-selected="false">
                                <i class="tim-icons icon-time-alarm"></i> Historial
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="acciones-tab" data-toggle="tab" href="#acciones" role="tab" aria-controls="acciones" aria-selected="false">
                                <i class="tim-icons icon-settings"></i> Acciones
                            </a>
                        </li>
                    </ul>

                    <!-- Contenido de los tabs -->
                    <div class="tab-content" id="cotizacionTabsContent">
                        <!-- Tab Detalle -->
                        <div class="tab-pane fade show active" id="detalle" role="tabpanel" aria-labelledby="detalle-tab">
                            @include('cotizaciones.partials.detalle', ['cotizacion' => $cotizacion, 'productosCotizacion' => $productosCotizacion, 'cliente' => $cliente])
                        </div>

                        <!-- Tab Historial -->
                        <div class="tab-pane fade" id="historial" role="tabpanel" aria-labelledby="historial-tab">
                            @include('cotizaciones.partials.historial', ['cotizacion' => $cotizacion, 'historial' => $historial, 'resumenTiempos' => $resumenTiempos])
                        </div>

                        <!-- Tab Acciones -->
                        <div class="tab-pane fade" id="acciones" role="tabpanel" aria-labelledby="acciones-tab">
                            @include('cotizaciones.partials.acciones', ['cotizacion' => $cotizacion, 'cliente' => $cliente])
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
