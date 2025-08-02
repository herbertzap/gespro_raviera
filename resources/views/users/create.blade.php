@extends('layouts.app', ['page' => __('Crear Usuario'), 'pageSlug' => 'users'])

@section('content')
<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">Añadir Usuario</h4>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('user.store') }}">
                    @csrf
                    <div class="form-group">
                        <label for="name">Nombre</label>
                        <input type="text" name="name" id="name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Correo Electrónico</label>
                        <input type="email" name="email" id="email" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Contraseña</label>
                        <input type="password" name="password" id="password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                    <a href="{{ route('user.index') }}" class="btn btn-secondary">Cancelar</a>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
