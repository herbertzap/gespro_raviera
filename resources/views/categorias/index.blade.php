@extends('layouts.app', ['page' => __('Categorías'), 'pageSlug' => 'categorias'])

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title"> {{ __('Categorías') }} </h4>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table tablesorter">
                        <thead class="text-primary">
                            <tr>
                                <th>KOFM</th>
                                <th>NOKOFM</th>
                                <th>KOPF</th>
                                <th>NOKOPF</th>
                                <th>KOHF</th>
                                <th>NOKOHF</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($categorias as $categoria)
                            <tr>
                                <td>{{ $categoria->KOFM }}</td>
                                <td>{{ $categoria->NOKOFM }}</td>
                                <td>{{ $categoria->KOPF }}</td>
                                <td>{{ $categoria->NOKOPF }}</td>
                                <td>{{ $categoria->KOHF }}</td>
                                <td>{{ $categoria->NOKOHF }}</td>
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
