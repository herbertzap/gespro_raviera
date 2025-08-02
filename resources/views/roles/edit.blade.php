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

                    <!-- Selección de permisos con checkboxes -->
                    <div class="form-group">
                        <label for="permissions">Permisos</label>
                        <div class="row">
                            @foreach($permissions as $permission)
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input 
                                            type="checkbox" 
                                            name="permissions[]" 
                                            value="{{ $permission->id }}" 
                                            class="form-check-input" 
                                            id="permission-{{ $permission->id }}" 
                                            {{ $role->permissions->contains($permission->id) ? 'checked' : '' }}
                                        >
                                        <label class="form-check-label" for="permission-{{ $permission->id }}">
                                            {{ $permission->name }}
                                        </label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
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
