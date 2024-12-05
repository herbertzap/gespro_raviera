@extends('layouts.app', ['page' => __('Editar Usuario'), 'pageSlug' => 'users'])

@section('content')
<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">Editar Usuario</h4>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('user.update', $user->id) }}">
                    @csrf
                    @method('PUT')
                    <div class="form-group">
                        <label for="name">Nombre</label>
                        <input type="text" name="name" id="name" class="form-control" value="{{ $user->name }}" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Correo Electrónico</label>
                        <input type="email" name="email" id="email" class="form-control" value="{{ $user->email }}" required>
                    </div>
                    <div class="form-group">
    <label for="roles">Roles</label>
    <select name="roles[]" id="roles" class="form-control" multiple>
        @foreach($roles as $role)
            <option value="{{ $role->id }}" {{ $user->roles->contains($role->id) ? 'selected' : '' }}>
                {{ $role->name }}
            </option>
        @endforeach
    </select>
</div>

                    <button type="submit" class="btn btn-primary">Actualizar</button>
                    <a href="{{ route('user.index') }}" class="btn btn-secondary">Cancelar</a>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
