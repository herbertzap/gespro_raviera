@extends('layouts.app', ['pageSlug' => 'manejo-stock'])

@section('content')
<div class="content">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Seleccionar Bodega</h4>
                    <p class="category mb-0">Elige la bodega y, opcionalmente, la ubicación donde realizarás el conteo.</p>
                </div>
                <div class="card-body">
                    <form action="{{ route('manejo-stock.contabilidad') }}" method="GET" id="formSeleccionBodega">
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
                            <label for="ubicacion_id">Ubicación (opcional)</label>
                            <select name="ubicacion_id" id="ubicacion_id" class="form-control" disabled>
                                <option value="">Todas las ubicaciones</option>
                            </select>
                        </div>

                        <div class="text-right">
                            <button type="submit" class="btn btn-primary">Continuar</button>
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

        ubicacionSelect.innerHTML = '<option value=\"\">Todas las ubicaciones</option>';

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

