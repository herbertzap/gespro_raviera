@extends('layouts.app', ['page' => __('bodegas'), 'pageSlug' => 'bodegas'])

@section('content')

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title"> {{ __('Bodegas') }} </h4>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table tablesorter">
                        <thead class="text-primary">
                            <tr>
                                <th>EMPRESA</th>
                                <th>KOSU</th>
                                <th>KOBO</th>
                                <th>KOFUBO</th>
                                <th>NOMBRE</th>
                                <th>DIRECCIÓN</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($bodegas as $bodega)
                            <tr>
                                <td>{{ $bodega->EMPRESA }}</td>
                                <td>{{ $bodega->KOSU }}</td>
                                <td>{{ $bodega->KOBO }}</td>
                                <td>{{ $bodega->KOFUBO }}</td>
                                <td>{{ $bodega->NOKOBO }}</td>
                                <td>{{ $bodega->DIBO }}</td>
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

@endsection
