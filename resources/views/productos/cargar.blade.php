@extends('layouts.app', ['page' => __('Cargar Productos'), 'pageSlug' => 'cargar_productos'])

@section('content')
<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">Cargar Productos</h4>
            </div>
            <div class="card-body">
                @if (session('success'))
                    <div class="alert alert-success">
                        {{ session('success') }}
                    </div>
                @endif
                <form action="{{ route('productos.cargar') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="form-group">
                        <label for="archivo">Selecciona un archivo Excel</label>
                        <input type="file" name="archivo" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Cargar</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
