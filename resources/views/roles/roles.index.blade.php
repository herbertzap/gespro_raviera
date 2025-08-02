@extends('layouts.app', ['page' => __('Roles'), 'pageSlug' => 'roles'])

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">Gestión de Roles</h4>
            </div>
            <div class="card-body">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Permisos</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($roles as $role)
                        <tr>
                            <td>{{ $role->name }}</td>
                            <td>
                                @foreach ($role->permissions as $permission)
                                    <span class="badge badge-primary">{{ $permission->name }}</span>
                                @endforeach
                            </td>
                            <td>
                                <a href="{{ route('roles.edit', $role) }}" class="btn btn-sm btn-primary">Editar Permisos</a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
