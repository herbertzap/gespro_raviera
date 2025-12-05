@extends('layouts.app', ['page' => __('Gestión de Roles'), 'pageSlug' => 'admin-roles'])

@section('content')
<div class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h4 class="card-title mb-0">
                                    <i class="material-icons text-primary" style="vertical-align: middle;">admin_panel_settings</i>
                                    Gestión de Roles y Permisos
                                </h4>
                                <p class="card-category">Administra los roles del sistema y sus permisos</p>
                            </div>
                            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#modalCrearRol">
                                <i class="material-icons">add</i> Nuevo Rol
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        @if(session('success'))
                            <div class="alert alert-success alert-dismissible fade show">
                                <button type="button" class="close" data-dismiss="alert">&times;</button>
                                {{ session('success') }}
                            </div>
                        @endif

                        @if(session('error'))
                            <div class="alert alert-danger alert-dismissible fade show">
                                <button type="button" class="close" data-dismiss="alert">&times;</button>
                                {{ session('error') }}
                            </div>
                        @endif

                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="text-primary">
                                    <tr>
                                        <th>Rol</th>
                                        <th>Usuarios</th>
                                        <th>Permisos</th>
                                        <th>Creado</th>
                                        <th class="text-right">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($roles as $role)
                                    <tr>
                                        <td>
                                            <span class="badge badge-pill badge-{{ $role->name == 'Super Admin' ? 'danger' : ($role->name == 'Administrativo' ? 'warning' : 'info') }}" style="font-size: 14px;">
                                                {{ $role->name }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-secondary">
                                                {{ $role->users->count() }} usuarios
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-success">
                                                {{ $role->permissions->count() }} permisos
                                            </span>
                                        </td>
                                        <td>{{ $role->created_at->format('d/m/Y') }}</td>
                                        <td class="text-right">
                                            @if($role->name != 'Super Admin')
                                                <a href="{{ route('admin.roles.edit', $role->id) }}" 
                                                   class="btn btn-sm btn-info" title="Editar permisos">
                                                    <i class="material-icons">edit</i>
                                                </a>
                                                @if($role->users->count() == 0)
                                                    <form action="{{ route('admin.roles.destroy', $role->id) }}" 
                                                          method="POST" class="d-inline"
                                                          onsubmit="return confirm('¿Eliminar este rol?')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-sm btn-danger" title="Eliminar">
                                                            <i class="material-icons">delete</i>
                                                        </button>
                                                    </form>
                                                @else
                                                    <button class="btn btn-sm btn-secondary" disabled title="No se puede eliminar: tiene usuarios asignados">
                                                        <i class="material-icons">delete</i>
                                                    </button>
                                                @endif
                                            @else
                                                <span class="text-muted">
                                                    <i class="material-icons">lock</i> Protegido
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="5" class="text-center">No hay roles registrados</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Resumen de Módulos -->
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">
                            <i class="material-icons text-info" style="vertical-align: middle;">widgets</i>
                            Módulos del Sistema
                        </h4>
                        <p class="card-category">Permisos organizados por área funcional</p>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            @foreach($modulos as $modulo => $info)
                            <div class="col-md-3 col-sm-6 mb-3">
                                <div class="card bg-{{ $info['color'] }} text-white h-100">
                                    <div class="card-body text-center">
                                        <i class="material-icons" style="font-size: 40px;">{{ $info['icon'] }}</i>
                                        <h5 class="mt-2 mb-1">{{ $info['nombre'] }}</h5>
                                        <small>{{ count($info['permisos']) }} permisos</small>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Crear Rol -->
<div class="modal fade" id="modalCrearRol" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('admin.roles.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Crear Nuevo Rol</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="name">Nombre del Rol *</label>
                        <input type="text" name="name" id="name" class="form-control" required 
                               placeholder="Ej: Contador, Bodeguero, etc.">
                        <small class="form-text text-muted">
                            Use un nombre descriptivo para identificar fácilmente el rol
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="material-icons">save</i> Crear Rol
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

