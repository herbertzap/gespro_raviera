@extends('layouts.app', ['page' => __('Listas de Precios'), 'pageSlug' => 'listas-precios'])

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">Listas de Precios</h4>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead class="text-primary">
                            <tr>
                                <th>KOLT</th>
                                <th>MOLT</th>
                                <th>TIMOLT</th>
                                <th>NOKOLT</th>
                                <th>ECUDEF01UD</th>
                                <th>ECUDEF02UD</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($listasPrecios as $lista)
                                <tr>
                                    <td>{{ $lista->KOLT }}</td>
                                    <td>{{ $lista->MOLT }}</td>
                                    <td>{{ $lista->TIMOLT }}</td>
                                    <td>{{ $lista->NOKOLT }}</td>
                                    <td>{{ $lista->ECUDEF01UD }}</td>
                                    <td>{{ $lista->ECUDEF02UD }}</td>
                                    <td>
                                        <a href="{{ route('productos.listaPorPrecio', ['kolt' => $lista->KOLT]) }}" class="btn btn-primary btn-sm">
                                            Ver Productos
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
