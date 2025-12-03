@extends('layouts.app', ['pageSlug' => 'manejo-stock-barrido'])

@section('content')
<div class="content">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">
                        <i class="tim-icons icon-tablet-2"></i> Barrido - Seleccionar Bodega
                    </h4>
                    <p class="category mb-0">
                        Elige la bodega y ubicación para realizar el barrido de inventario.
                        <br>
                        <span class="text-info">
                            <i class="tim-icons icon-bulb-63"></i> 
                            El barrido solo registra productos en TINVENTARIO (sin actualizar stock).
                        </span>
                    </p>
                </div>
                <div class="card-body">
                    <form action="{{ route('manejo-stock.barrido') }}" method="GET" id="formSeleccionBodega">
                        <div class="form-group">
                            <label for="bodega_id">Bodega</label>
                            <select name="bodega_id" id="bodega_id" class="form-control" required>
                                <option value="">Selecciona una bodega</option>
                                @foreach($bodegas as $bodega)
                                    <option value="{{ $bodega->id }}"
                                        data-ubicaciones='@json($bodega->ubicaciones->map->only(["id", "codigo"]))'>
                                        {{ $bodega->nombre_bodega }} ({{ $bodega->kobo }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="ubicacion_id">Ubicación</label>
                            <select name="ubicacion_id" id="ubicacion_id" class="form-control" disabled>
                                <option value="">Selecciona una ubicación</option>
                            </select>
                            <small class="form-text text-muted">
                                Debes seleccionar una ubicación para el barrido.
                            </small>
                        </div>

                        <div class="text-right">
                            <a href="{{ route('manejo-stock.reporte-inventario') }}" class="btn btn-outline-info mr-2">
                                <i class="tim-icons icon-chart-bar-32"></i> Ver Reporte
                            </a>
                            <button type="submit" class="btn btn-info">
                                <i class="tim-icons icon-tablet-2"></i> Iniciar Barrido
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('js')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const bodegaSelect = document.getElementById('bodega_id');
    const ubicacionSelect = document.getElementById('ubicacion_id');

    bodegaSelect.addEventListener('change', function () {
        const option = bodegaSelect.options[bodegaSelect.selectedIndex];
        const ubicaciones = option.dataset.ubicaciones ? JSON.parse(option.dataset.ubicaciones) : [];

        ubicacionSelect.innerHTML = '<option value="">Selecciona una ubicación</option>';

        if (ubicaciones.length === 0) {
            ubicacionSelect.disabled = true;
            return;
        }

        ubicaciones.forEach(function (ubicacion) {
            const opt = document.createElement('option');
            opt.value = ubicacion.id;
            opt.textContent = ubicacion.codigo;
            ubicacionSelect.appendChild(opt);
        });

        ubicacionSelect.disabled = false;
    });
});
</script>
@endpush

