@extends('layouts.app', ['page' => __('Lista de Precios'), 'pageSlug' => 'lista-precios'])

@section('content')
<div class="row">
    <div class="col-md-12">
        <form method="GET" action="{{ route('productos.lista-precios') }}">
            <div class="row">
                <!-- Filtro por Código -->
                <div class="col-md-4">
                    <label for="codigo">Código del Producto</label>
                    <input type="text" name="codigo" id="codigo" class="form-control" value="{{ request('codigo') }}" placeholder="Buscar por código">
                </div>

                <!-- Filtro por Lista de Precios -->
                <div class="col-md-4">
                    <label for="lista_precio">Lista de Precios</label>
                    <select name="lista_precio" id="lista_precio" class="form-control">
                        <option value="">Seleccione Lista de Precios</option>
                        @if(isset($listasPrecios) && $listasPrecios->isNotEmpty())
                        @foreach($listasPrecios as $lista)
                            <option value="{{ $lista->KOLT }}" {{ request('lista_precio') == $lista->KOLT ? 'selected' : '' }}>
                                {{ $lista->KOLT }}
                            </option>
                        @endforeach
                        @else
                        <option value="">No hay listas de precios disponibles</option>
                        @endif

                    </select>
                </div>

                <!-- Botones -->
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary mr-2">Buscar</button>
                    <a href="{{ route('productos.lista-precios') }}" class="btn btn-secondary">Limpiar Filtros</a>
                </div>
            </div>
        </form>
    </div>

    <!-- Tabla de Productos -->
    <div class="col-md-12 mt-4">
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead class="thead-light">
                    <tr>
                        <th>Lista de Precios</th>
                        <th>Código</th>
                        <th>Referencia</th>
                        <th>Etiqueta</th>
                        <th>Ecuación</th>
                        <th>RLUD</th>
                        <th>PP01UD</th>
                        <th>MG01UD</th>
                        <th>DTMA01UD</th>
                        <th>PP02UD</th>
                        <th>MG02UD</th>
                        <th>DTMA02UD</th>
                        <th>Ecuación U2</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($productos as $producto)
                        <tr>
                            <td>{{ $producto->KOLT }}</td>
                            <td>{{ $producto->KOPR }}</td>
                            <td>{{ $producto->KOPRRA }}</td>
                            <td>{{ $producto->KOPRTE }}</td>
                            <td>{{ $producto->ECUACION }}</td>
                            <td>{{ $producto->RLUD }}</td>
                            <td>{{ $producto->PP01UD }}</td>
                            <td>{{ $producto->MG01UD }}</td>
                            <td>{{ $producto->DTMA01UD }}</td>
                            <td>{{ $producto->PP02UD }}</td>
                            <td>{{ $producto->MG02UD }}</td>
                            <td>{{ $producto->DTMA02UD }}</td>
                            <td>{{ $producto->ECUACIONU2 }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="13" class="text-center">No se encontraron resultados</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <!-- Paginación -->
        <div class="mt-3">
            {{ $productos->withQueryString()->links() }}
        </div>
    </div>
</div>
@endsection
