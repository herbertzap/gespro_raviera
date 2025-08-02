@extends('layouts.app', ['page' => __('Validación de Productos'), 'pageSlug' => 'productos_validar'])

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">Validación de Productos</h4>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table tablesorter">
                        <thead class="text-primary">
                            <tr>
                                <th>ID</th>
                                <th>Descripción</th>
                                <th>Fecha de Carga</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($productos as $producto)
                            <tr>
                                <td>{{ $producto->id }}</td>
                                <td>{{ $producto->NOKOPR }}</td>
                                <td>{{ $producto->FECRPR }}</td>
                                <td>
                                    <a href="{{ route('productos.editar', $producto->id) }}" class="btn btn-primary btn-sm">Editar</a>
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
