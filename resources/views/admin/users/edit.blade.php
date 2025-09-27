@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header card-header-primary">
                    <h4 class="card-title">
                        <i class="material-icons">edit</i>
                        Editar Usuario
                    </h4>
                    <p class="card-category">Modificar información del usuario</p>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.users.update', $user) }}" method="POST">
                        @csrf
                        @method('PUT')
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="name" class="bmd-label-floating">Nombre *</label>
                                    <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" 
                                           value="{{ old('name', $user->name) }}" required>
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="email" class="bmd-label-floating">Email *</label>
                                    <input type="email" name="email" id="email" class="form-control @error('email') is-invalid @enderror" 
                                           value="{{ old('email', $user->email) }}" required>
                                    @error('email')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="email_alternativo" class="bmd-label-floating">Email Alternativo</label>
                                    <input type="email" name="email_alternativo" id="email_alternativo" class="form-control @error('email_alternativo') is-invalid @enderror" 
                                           value="{{ old('email_alternativo', $user->email_alternativo) }}">
                                    @error('email_alternativo')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="rut" class="bmd-label-floating">RUT</label>
                                    <input type="text" name="rut" id="rut" class="form-control @error('rut') is-invalid @enderror" 
                                           value="{{ old('rut', $user->rut) }}" placeholder="0000000-0" maxlength="10">
                                    @error('rut')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="codigo_vendedor" class="bmd-label-floating">Código Vendedor</label>
                                    <input type="text" name="codigo_vendedor" id="codigo_vendedor" class="form-control @error('codigo_vendedor') is-invalid @enderror" 
                                           value="{{ old('codigo_vendedor', $user->codigo_vendedor) }}">
                                    @error('codigo_vendedor')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <div class="form-check">
                                        <input type="checkbox" name="es_vendedor" id="es_vendedor" class="form-check-input" 
                                               value="1" {{ old('es_vendedor', $user->es_vendedor) ? 'checked' : '' }}>
                                        <label for="es_vendedor" class="form-check-label">
                                            Es Vendedor
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label class="bmd-label-floating">Roles *</label>
                                    <div class="row">
                                        @foreach($roles as $role)
                                        <div class="col-md-3">
                                            <div class="form-check">
                                                <input type="checkbox" name="roles[]" value="{{ $role->id }}" 
                                                       id="role_{{ $role->id }}" class="form-check-input"
                                                       {{ in_array($role->id, $userRoles) ? 'checked' : '' }}>
                                                <label for="role_{{ $role->id }}" class="form-check-label">
                                                    {{ $role->display_name ?? $role->name }}
                                                </label>
                                            </div>
                                        </div>
                                        @endforeach
                                    </div>
                                    @error('roles')
                                        <div class="text-danger">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="material-icons">save</i>
                                    Actualizar Usuario
                                </button>
                                <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">
                                    <i class="material-icons">arrow_back</i>
                                    Volver
                                </a>
                            </div>
                        </div>
                    </form>

                    <!-- Sección para cambiar contraseña -->
                    <hr>
                    <div class="row">
                        <div class="col-md-12">
                            <h5>Cambiar Contraseña</h5>
                            <form action="{{ route('admin.users.change-password', $user) }}" method="POST">
                                @csrf
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="new_password" class="bmd-label-floating">Nueva Contraseña *</label>
                                            <input type="password" name="password" id="new_password" class="form-control" required minlength="8">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="password_confirmation" class="bmd-label-floating">Confirmar Contraseña *</label>
                                            <input type="password" name="password_confirmation" id="password_confirmation" class="form-control" required minlength="8">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <button type="submit" class="btn btn-warning">
                                                <i class="material-icons">lock</i>
                                                Cambiar Contraseña
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const rutInput = document.getElementById('rut');
    
    if (rutInput) {
        // Formatear RUT mientras se escribe
        rutInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/[^0-9kK]/g, '');
            
            if (value.length > 1) {
                // Separar número y dígito verificador
                let numero = value.slice(0, -1);
                let dv = value.slice(-1);
                
                // Formatear número con puntos (opcional) y guión
                if (numero.length > 0) {
                    value = numero + '-' + dv;
                }
            }
            
            e.target.value = value;
        });
        
        // Validar formato al perder el foco
        rutInput.addEventListener('blur', function(e) {
            let value = e.target.value.trim();
            if (value && !/^\d{1,8}-[0-9kK]$/.test(value)) {
                e.target.classList.add('is-invalid');
            } else {
                e.target.classList.remove('is-invalid');
            }
        });
    }
});
</script>
@endsection
