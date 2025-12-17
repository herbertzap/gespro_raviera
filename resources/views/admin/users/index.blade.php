@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header card-header-primary">
                    <h4 class="card-title">
                        <i class="material-icons">people</i>
                        Gestión de Usuarios
                    </h4>
                    <p class="card-category">Administrar usuarios del sistema</p>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <a href="{{ route('admin.users.create-from-vendedor') }}" class="btn btn-success">
                                <i class="material-icons">person_add</i>
                                Crear Usuario desde Vendedor
                            </a>
                        </div>
                        <div class="col-md-6 text-right">
                            <button class="btn btn-info" onclick="sincronizarVendedores()">
                                <i class="material-icons">sync</i>
                                Sincronizar Vendedores
                            </button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="text-primary">
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>Email</th>
                                    <th>RUT</th>
                                    <th>Código Vendedor</th>
                                    <th>Roles</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($users as $user)
                                <tr>
                                    <td>{{ $user->id }}</td>
                                    <td>{{ $user->name }}</td>
                                    <td>{{ $user->email }}</td>
                                    <td>{{ $user->rut ?? 'N/A' }}</td>
                                    <td>{{ $user->codigo_vendedor ?? 'N/A' }}</td>
                                    <td>
                                        @foreach($user->roles as $role)
                                            <span class="badge badge-info">{{ $role->name }}</span>
                                        @endforeach
                                    </td>
                                    <td>
                                        @if($user->primer_login)
                                            <span class="badge badge-warning">Primer Login</span>
                                        @else
                                            <span class="badge badge-success">Activo</span>
                                        @endif
                                    </td>
                                    <td>
                                        <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-sm btn-primary">
                                            <i class="material-icons">edit</i>
                                        </a>
                                        @php
                                            $currentUser = auth()->user();
                                            $puedeEliminar = true;
                                            
                                            // No permitir eliminar el super admin específico
                                            if ($user->email === 'herbert.zapata19@gmail.com') {
                                                $puedeEliminar = false;
                                            }
                                            
                                            // Si el usuario tiene rol Super Admin y el actual NO es Super Admin, no puede eliminar
                                            if ($user->hasRole('Super Admin') && !$currentUser->hasRole('Super Admin')) {
                                                $puedeEliminar = false;
                                            }
                                        @endphp
                                        @if($puedeEliminar)
                                        <button class="btn btn-sm btn-danger" onclick="eliminarUsuario({{ $user->id }})">
                                            <i class="material-icons">delete</i>
                                        </button>
                                        @else
                                        <button class="btn btn-sm btn-danger" disabled title="No tienes permisos para eliminar usuarios con rol Super Admin">
                                            <i class="material-icons">delete</i>
                                        </button>
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="8" class="text-center">No hay usuarios registrados</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{ $users->links() }}
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de confirmación para eliminar usuario -->
<div class="modal fade" id="modalEliminarUsuario" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmar Eliminación</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                ¿Está seguro de que desea eliminar este usuario?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <form id="formEliminarUsuario" method="POST" style="display: inline;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">Eliminar</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('js')
<script>
function eliminarUsuario(userId) {
    if (confirm('¿Está seguro de que desea eliminar este usuario? Esta acción no se puede deshacer.')) {
        // Crear un formulario temporal para enviar la petición DELETE
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `{{ url('/admin/users') }}/${userId}`;
        
        // Agregar el token CSRF
        const csrfToken = document.createElement('input');
        csrfToken.type = 'hidden';
        csrfToken.name = '_token';
        csrfToken.value = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        form.appendChild(csrfToken);
        
        // Agregar el método DELETE
        const methodField = document.createElement('input');
        methodField.type = 'hidden';
        methodField.name = '_method';
        methodField.value = 'DELETE';
        form.appendChild(methodField);
        
        // Agregar el formulario al DOM y enviarlo
        document.body.appendChild(form);
        form.submit();
    }
}

function sincronizarVendedores() {
    if (confirm('¿Desea sincronizar los vendedores desde SQL Server?')) {
        fetch('/admin/vendedores/sincronizar', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Vendedores sincronizados exitosamente');
                location.reload();
            } else {
                alert('Error al sincronizar vendedores: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al sincronizar vendedores');
        });
    }
}
</script>
@endpush
