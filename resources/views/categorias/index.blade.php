@extends('layouts.app', ['page' => __('Categorías'), 'pageSlug' => 'categorias'])

@section('content')
<div class="row">
    <!--categoria principal-->
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title"> {{ __('Categorías Pricipales') }} </h4>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table tablesorter">
                        <thead class="text-primary">
                            <tr>
                                <th>KOFM</th>
                                <th>NOKOFM</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($categorias_padre as $categoria)
                            <tr>
                                <td>{{ $categoria->KOFM }}</td>
                                <td>{{ $categoria->NOKOFM }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <!--categoria sub hijo-->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title"> {{ __('Sub Categorías') }} </h4>
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

                            </tr>
                        </thead>
                        <tbody>
                            @foreach($categorias_sc as $categoriasb)
                            <tr>
                                <td>{{ $categoriasb->KOFM }}</td>
                                <td>{{ $categoriasb->NOKOFM }}</td>
                                <td>{{ $categoriasb->KOPF }}</td>
                                <td>{{ $categoriasb->NOKOPF }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <!--categoria sub sub hijo-->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title"> {{ __('Sub Categorías adicionales') }} </h4>
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
                            @foreach($categorias_sch as $categoria_sch)
                            <tr>
                                <td>{{ $categoria_sch->KOFM }}</td>
                                <td>{{ $categoria_sch->NOKOFM }}</td>
                                <td>{{ $categoria_sch->KOPF }}</td>
                                <td>{{ $categoria_sch->NOKOPF }}</td>
                                <td>{{ $categoria_sch->KOHF }}</td>
                                <td>{{ $categoria_sch->NOKOHF }}</td>
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
