@extends('layouts.app', ['page' => __('Editar Precios'), 'pageSlug' => 'productos'])

@section('content')
<form method="POST" action="{{ route('productos.actualizar-precios') }}">
    @csrf
    <input type="hidden" name="codigo" value="{{ $codigo }}">

    <table class="table">
        <thead>
            <tr>
                <th>Lista (KOLT)</th>
                <th>Margen 1 (MG01UD)</th>
                <th>Descuento 1 (DTMA01UD)</th>
                <th>Precio 1 (PP01UD)</th>
                <th>Margen 2 (MG02UD)</th>
                <th>Descuento 2 (DTMA02UD)</th>
                <th>Precio 2 (PP02UD)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($listasPrecios as $lista)
            <tr>
                <td>{{ $lista->KOLT }}</td>
                <td>
                    <input type="number" name="listas[{{ $loop->index }}][MG01UD]" class="form-control mg01ud"
                        value="{{ $lista->MG01UD }}" data-kolt="{{ $lista->KOLT }}" data-pp01ud="{{ $basePrecio }}" />
                </td>
                <td>
                    <input type="number" name="listas[{{ $loop->index }}][DTMA01UD]" class="form-control" value="{{ $lista->DTMA01UD }}" />
                </td>
                <td>
                    <input type="text" class="form-control pp01ud" readonly
                        value="{{ $lista->PP01UD }}" />
                </td>
                <td>
                    <input type="number" name="listas[{{ $loop->index }}][MG02UD]" class="form-control mg02ud"
                        value="{{ $lista->MG02UD }}" data-rlud="{{ $lista->RLUD }}" />
                </td>
                <td>
                    <input type="number" name="listas[{{ $loop->index }}][DTMA02UD]" class="form-control" value="{{ $lista->DTMA02UD }}" />
                </td>
                <td>
                    <input type="text" class="form-control pp02ud" readonly
                        value="{{ $lista->PP02UD }}" />
                </td>
                <input type="hidden" name="listas[{{ $loop->index }}][KOLT]" value="{{ $lista->KOLT }}">
                <input type="hidden" name="listas[{{ $loop->index }}][RLUD]" value="{{ $lista->RLUD }}">
            </tr>
            @endforeach
        </tbody>
    </table>

    <button type="submit" class="btn btn-primary">Actualizar Precios</button>
</form>

<script>
    // Recalcular PP01UD y PP02UD dinámicamente
    document.querySelectorAll('.mg01ud').forEach(input => {
        input.addEventListener('input', function () {
            const kolt = this.dataset.kolt;
            const basePrice = parseFloat(this.dataset.pp01ud);
            const mg01ud = parseFloat(this.value) || 0;

            const pp01ud = basePrice * mg01ud;
            this.closest('tr').querySelector('.pp01ud').value = pp01ud.toFixed(2);
        });
    });

    document.querySelectorAll('.mg02ud').forEach(input => {
        input.addEventListener('input', function () {
            const rlud = parseFloat(this.dataset.rlud);
            const mg02ud = parseFloat(this.value) || 0;

            const pp02ud = rlud * mg02ud;
            this.closest('tr').querySelector('.pp02ud').value = pp02ud.toFixed(2);
        });
    });
</script>
@endsection
