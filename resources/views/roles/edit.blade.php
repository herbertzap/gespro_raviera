@extends('layouts.app', ['page' => __('Editar Rol'), 'pageSlug' => 'roles'])

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">Editar Rol</h4>
            </div>
            <div class="card-body">
                <form action="{{ route('roles.update', $role->id) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <!-- Nombre del rol -->
                    <div class="form-group">
                        <label for="name">Nombre del Rol</label>
                        <input type="text" name="name" id="name" class="form-control" value="{{ $role->name }}" required>
                    </div>

                    <!-- Selección de permisos -->
                    <div class="form-group">
                        <label for="permissions">Permisos</label>
                        <select name="permissions[]" id="permissions" class="form-control" multiple>
                            @foreach($permissions as $permission)
                                <option value="{{ $permission->id }}" {{ $role->permissions->contains($permission->id) ? 'selected' : '' }}>
                                    {{ $permission->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Botones -->
                    <div class="form-group text-right">
                        <button type="submit" class="btn btn-primary">Actualizar Rol</button>
                        <a href="{{ route('roles.index') }}" class="btn btn-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
