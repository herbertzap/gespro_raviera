@extends('layouts.app', ['page' => __('Editar Bodegas'), 'pageSlug' => 'productos'])

@section('content')
<form method="POST" action="{{ route('productos.actualizar-bodegas') }}">
    @csrf
    <input type="hidden" name="KOPR" value="{{ $producto->KOPR }}">

    <div class="form-group">
        <label for="bodega">Bodega</label>
        <input type="text" name="bodega" class="form-control" value="{{ $producto->bodega ?? '' }}">
    </div>
    <div class="form-group">
        <label for="cantidad">Cantidad</label>
        <input type="number" name="cantidad" class="form-control" value="{{ $producto->cantidad ?? 0 }}">
    </div>

    <button type="submit" class="btn btn-primary">Actualizar Bodegas</button>
</form>
@endsection
