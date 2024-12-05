@extends('layouts.app', ['page' => __('Crear Rol'), 'pageSlug' => 'roles'])

@section('content')
<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">Añadir Rol</h4>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('roles.store') }}">
                    @csrf
                    <div class="form-group">
                        <label>Nombre del Rol</label>
                        <input type="text" name="name" class="form-control" placeholder="Nombre del rol" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
