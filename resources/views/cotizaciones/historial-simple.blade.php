@extends('layouts.app', ['pageSlug' => 'cotizaciones'])

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="mb-0">
                        <i class="tim-icons icon-time-alarm text-primary"></i>
                        Historial de Cotización #{{ $cotizacion->id }}
                    </h3>
                </div>
                <div class="card-body">
                    @if(isset($historial) && $historial->count() > 0)
                        <div class="timeline">
                            @foreach($historial as $registro)
                            <div class="timeline-item">
                                <div class="timeline-marker bg-primary"></div>
                                <div class="timeline-content">
                                    <div class="card">
                                        <div class="card-body">
                                            <h5>{{ $registro->estado_nuevo ?? 'Sin estado' }}</h5>
                                            <p><strong>Usuario:</strong> {{ $registro->usuario_nombre ?? 'Sistema' }}</p>
                                            <p><strong>Fecha:</strong> {{ $registro->fecha_accion->format('d/m/Y H:i:s') }}</p>
                                            @if($registro->comentarios)
                                            <p><strong>Comentarios:</strong> {{ $registro->comentarios }}</p>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-4">
                            <i class="tim-icons icon-time-alarm text-muted" style="font-size: 3rem;"></i>
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
