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
                            <form action="{{ route('admin.users.change-password', $user) }}" method="POST" id="changePasswordForm">
                                @csrf
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="new_password" class="bmd-label-floating">Nueva Contraseña *</label>
                                            <div class="input-group">
                                                <input type="password" name="password" id="new_password" class="form-control @error('password') is-invalid @enderror" required minlength="8">
                                                <div class="input-group-append">
                                                    <button type="button" class="btn btn-outline-secondary toggle-password" data-target="new_password" type="button">
                                                        <i class="material-icons" id="icon_new_password">visibility</i>
                                                    </button>
                                                </div>
                                            </div>
                                            @error('password')
                                                <div class="text-danger small">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="password_confirmation" class="bmd-label-floating">Confirmar Contraseña *</label>
                                            <div class="input-group">
                                                <input type="password" name="password_confirmation" id="password_confirmation" class="form-control @error('password_confirmation') is-invalid @enderror" required minlength="8">
                                                <div class="input-group-append">
                                                    <button type="button" class="btn btn-outline-secondary toggle-password" data-target="password_confirmation" type="button">
                                                        <i class="material-icons" id="icon_password_confirmation">visibility</i>
                                                    </button>
                                                </div>
                                            </div>
                                            @error('password_confirmation')
                                                <div class="text-danger small">{{ $message }}</div>
                                            @enderror
                                            <div class="text-danger small" id="password-match-error" style="display: none;">
                                                Las contraseñas no coinciden
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="bmd-label-floating">&nbsp;</label>
                                            <button type="submit" class="btn btn-warning btn-block">
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
    // Formatear RUT
    const rutInput = document.getElementById('rut');
    
    if (rutInput) {
        rutInput.addEventListener('input', function(e) {
            // Limpiar: solo números, K y guiones
            let value = e.target.value.replace(/[^0-9kK-]/g, '');
            
            // Si ya tiene guión, mantener el formato
            if (value.includes('-')) {
                const parts = value.split('-');
                let numero = parts[0].replace(/[^0-9]/g, '');
                let digitoVerificador = '';
                
                // Si hay algo después del guión, tomarlo como dígito verificador
                if (parts.length > 1) {
                    digitoVerificador = parts.slice(1).join('').replace(/[^0-9kK]/g, '').toUpperCase();
                    // Si hay más de un carácter después del guión, mover los anteriores al número
                    if (digitoVerificador.length > 1) {
                        numero += digitoVerificador.slice(0, -1).replace(/[^0-9]/g, '');
                        digitoVerificador = digitoVerificador.slice(-1);
                    }
                }
                
                // Formatear siempre que haya al menos un número antes del guión
                if (numero.length > 0) {
                    e.target.value = numero + '-' + digitoVerificador;
                } else {
                    e.target.value = '';
                }
            } else {
                // Si no tiene guión, agregar automáticamente antes del último carácter
                value = value.replace(/[^0-9kK]/g, '');
                
                if (value.length > 1) {
                    let numero = value.slice(0, -1);
                    let dv = value.slice(-1).toUpperCase();
                    e.target.value = numero + '-' + dv;
                } else {
                    e.target.value = value;
                }
            }
            
            // Limpiar validación al escribir
            e.target.classList.remove('is-invalid');
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
    
    // Toggle mostrar/ocultar contraseña
    document.querySelectorAll('.toggle-password').forEach(function(button) {
        button.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const input = document.getElementById(targetId);
            const icon = document.getElementById('icon_' + targetId);
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.textContent = 'visibility_off';
            } else {
                input.type = 'password';
                icon.textContent = 'visibility';
            }
        });
    });
    
    // Validar que las contraseñas coincidan
    const passwordInput = document.getElementById('new_password');
    const passwordConfirmationInput = document.getElementById('password_confirmation');
    const passwordMatchError = document.getElementById('password-match-error');
    const changePasswordForm = document.getElementById('changePasswordForm');
    
    function validatePasswordMatch() {
        if (passwordInput.value && passwordConfirmationInput.value) {
            if (passwordInput.value !== passwordConfirmationInput.value) {
                passwordConfirmationInput.classList.add('is-invalid');
                passwordMatchError.style.display = 'block';
                return false;
            } else {
                passwordConfirmationInput.classList.remove('is-invalid');
                passwordMatchError.style.display = 'none';
                return true;
            }
        }
        return true;
    }
    
    if (passwordInput && passwordConfirmationInput) {
        passwordInput.addEventListener('input', validatePasswordMatch);
        passwordConfirmationInput.addEventListener('input', validatePasswordMatch);
        
        changePasswordForm.addEventListener('submit', function(e) {
            if (!validatePasswordMatch()) {
                e.preventDefault();
                return false;
            }
        });
    }
});
</script>
@endsection
