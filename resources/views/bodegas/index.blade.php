@extends('layouts.app', ['page' => __('bodegas'), 'pageSlug' => 'bodegas'])

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title"> {{ __('bodegas') }} </h4>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table tablesorter">
                        <thead class="text-primary">
                            <!-- Encabezados de las columnas -->
                        </thead>
                        <tbody>
                            <!-- Contenido de las filas -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
