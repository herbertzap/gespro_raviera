@extends('layouts.app', ['page' => __('Editar Rol'), 'pageSlug' => 'admin-roles'])

@section('content')
<div class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <form action="{{ route('admin.roles.update', $role->id) }}" method="POST" id="formPermisos">
                    @csrf
                    @method('PUT')
                    
                    <div class="card">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h4 class="card-title mb-0">
                                        <i class="material-icons text-primary" style="vertical-align: middle;">edit</i>
                                        Editar Rol: <span class="text-info">{{ $role->name }}</span>
                                    </h4>
                                    <p class="card-category">Configura los permisos para este rol</p>
                                </div>
                                <div>
                                    <a href="{{ route('admin.roles.index') }}" class="btn btn-secondary">
                                        <i class="material-icons">arrow_back</i> Volver
                                    </a>
                                    <button type="submit" class="btn btn-success">
                                        <i class="material-icons">save</i> Guardar Cambios
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            @if(session('success'))
                                <div class="alert alert-success alert-dismissible fade show">
                                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                                    {{ session('success') }}
                                </div>
                            @endif

                            <!-- Nombre del Rol -->
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="name">Nombre del Rol</label>
                                        <input type="text" name="name" id="name" class="form-control" 
                                               value="{{ $role->name }}" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Usuarios con este rol</label>
                                        <p class="form-control-static">
                                            <span class="badge badge-info" style="font-size: 16px;">
                                                {{ $role->users->count() }} usuarios
                                            </span>
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Acciones Rápidas -->
                            <div class="mb-4">
                                <button type="button" class="btn btn-sm btn-outline-success" onclick="seleccionarTodos()">
                                    <i class="material-icons">check_box</i> Seleccionar Todos
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="deseleccionarTodos()">
                                    <i class="material-icons">check_box_outline_blank</i> Deseleccionar Todos
                                </button>
                            </div>

                            <!-- Permisos por Módulo -->
                            <div class="accordion" id="accordionPermisos">
                                @foreach($modulos as $moduloKey => $modulo)
                                <div class="card mb-2">
                                    <div class="card-header p-0" id="heading{{ $loop->index }}">
                                        <h2 class="mb-0">
                                            <button class="btn btn-block text-left p-3 d-flex justify-content-between align-items-center" 
                                                    type="button" data-toggle="collapse" 
                                                    data-target="#collapse{{ $loop->index }}">
                                                <span>
                                                    <i class="material-icons text-{{ $modulo['color'] }}" style="vertical-align: middle;">
                                                        {{ $modulo['icon'] }}
                                                    </i>
                                                    <strong>{{ $modulo['nombre'] }}</strong>
                                                    <span class="badge badge-{{ $modulo['color'] }} ml-2">
                                                        {{ count(array_intersect($role->permissions->pluck('name')->toArray(), $modulo['permisos'])) }}/{{ count($modulo['permisos']) }}
                                                    </span>
                                                </span>
                                                <span>
                                                    <input type="checkbox" 
                                                           class="modulo-checkbox"
                                                           data-modulo="{{ $moduloKey }}"
                                                           onclick="toggleModulo(event, '{{ $moduloKey }}')"
                                                           {{ count(array_intersect($role->permissions->pluck('name')->toArray(), $modulo['permisos'])) == count($modulo['permisos']) ? 'checked' : '' }}>
                                                    <i class="material-icons">expand_more</i>
                                                </span>
                                            </button>
                                        </h2>
                                    </div>
                                    <div id="collapse{{ $loop->index }}" class="collapse" 
                                         data-parent="#accordionPermisos">
                                        <div class="card-body">
                                            <div class="row">
                                                @foreach($modulo['permisos'] as $permiso)
                                                <div class="col-md-4 col-sm-6 mb-2">
                                                    <div class="custom-control custom-checkbox">
                                                        <input type="checkbox" 
                                                               class="custom-control-input permiso-{{ $moduloKey }}" 
                                                               id="permiso_{{ $permiso }}"
                                                               name="permisos[]" 
                                                               value="{{ $permiso }}"
                                                               {{ $role->hasPermissionTo($permiso) ? 'checked' : '' }}
                                                               onchange="actualizarModulo('{{ $moduloKey }}')">
                                                        <label class="custom-control-label" for="permiso_{{ $permiso }}">
                                                            {{ ucwords(str_replace('_', ' ', $permiso)) }}
                                                        </label>
                                                    </div>
                                                </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        <div class="card-footer text-right">
                            <a href="{{ route('admin.roles.index') }}" class="btn btn-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-success">
                                <i class="material-icons">save</i> Guardar Cambios
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@push('js')
<script>
function toggleModulo(event, modulo) {
    event.stopPropagation();
    const checkboxes = document.querySelectorAll('.permiso-' + modulo);
    const checked = event.target.checked;
    checkboxes.forEach(cb => cb.checked = checked);
}

function actualizarModulo(modulo) {
    const checkboxes = document.querySelectorAll('.permiso-' + modulo);
    const moduloCheckbox = document.querySelector(`[data-modulo="${modulo}"]`);
    const allChecked = Array.from(checkboxes).every(cb => cb.checked);
    moduloCheckbox.checked = allChecked;
}

function seleccionarTodos() {
    document.querySelectorAll('input[name="permisos[]"]').forEach(cb => cb.checked = true);
    document.querySelectorAll('.modulo-checkbox').forEach(cb => cb.checked = true);
}

function deseleccionarTodos() {
    document.querySelectorAll('input[name="permisos[]"]').forEach(cb => cb.checked = false);
    document.querySelectorAll('.modulo-checkbox').forEach(cb => cb.checked = false);
}
</script>
@endpush

<style>
.card-header button {
    background: transparent;
    border: none;
    color: inherit;
}
.card-header button:hover {
    background: rgba(0,0,0,0.05);
}
.card-header button:focus {
    outline: none;
    box-shadow: none;
}
.custom-control-label {
    cursor: pointer;
    font-size: 13px;
}
.modulo-checkbox {
    width: 20px;
    height: 20px;
    cursor: pointer;
}
</style>
@endsection

