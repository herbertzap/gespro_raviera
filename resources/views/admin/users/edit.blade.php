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
                                                    <button class="btn btn-outline-secondary" type="button" id="toggleNewPassword" onclick="togglePasswordVisibility('new_password', 'toggleNewPassword')">
                                                        <i class="tim-icons icon-single-02" id="iconNewPassword"></i>
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
                                                    <button class="btn btn-outline-secondary" type="button" id="togglePasswordConfirmation" onclick="togglePasswordVisibility('password_confirmation', 'togglePasswordConfirmation')">
                                                        <i class="tim-icons icon-single-02" id="iconPasswordConfirmation"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            @error('password_confirmation')
                                                <div class="text-danger small">{{ $message }}</div>
                                            @enderror
                                            <small id="passwordMatchMessage" class="form-text" style="display: none;"></small>
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
    const rutInput = document.getElementById('rut');
    
    if (rutInput) {
        // Formatear RUT mientras se escribe
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
                    // Si hay más de un carácter después del guión, el último es el dígito verificador y los anteriores van al número
                    if (digitoVerificador.length > 1) {
                        // Mover todos excepto el último al número
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
                // Si no tiene guión, procesar y agregar guión antes del último carácter
                value = value.replace(/[^0-9kK]/g, '');
                
                if (value.length === 0) {
                    e.target.value = '';
                } else if (value.length === 1) {
                    // Si solo hay 1 carácter, mostrarlo tal cual
                    e.target.value = value.toUpperCase();
                } else {
                    // Si hay 2 o más caracteres, agregar guión antes del último
                    const numero = value.slice(0, -1).replace(/[^0-9]/g, '');
                    const digitoVerificador = value.slice(-1).toUpperCase();
                    
                    // Solo formatear si el último carácter es válido (número o K) y hay números antes
                    if (digitoVerificador.match(/^[0-9K]$/) && numero.length > 0) {
                        e.target.value = numero + '-' + digitoVerificador;
                    } else {
                        // Si no es válido, solo mostrar números
                        e.target.value = value.replace(/[^0-9]/g, '');
                    }
                }
            }
        });

        // Limitar longitud máxima (máximo 9 dígitos + 1 guión + 1 dígito verificador = 11 caracteres)
        rutInput.addEventListener('input', function(e) {
            if (e.target.value.length > 11) {
                e.target.value = e.target.value.slice(0, 11);
            }
        });
    }

    // Validar que las contraseñas coincidan
    const passwordInput = document.getElementById('new_password');
    const passwordConfirmationInput = document.getElementById('password_confirmation');
    const passwordMatchMessage = document.getElementById('passwordMatchMessage');

    function validatePasswordMatch() {
        if (!passwordInput || !passwordConfirmationInput || !passwordMatchMessage) {
            return;
        }

        const password = passwordInput.value;
        const passwordConfirmation = passwordConfirmationInput.value;

        if (passwordConfirmation.length === 0) {
            passwordMatchMessage.style.display = 'none';
            passwordConfirmationInput.classList.remove('is-valid', 'is-invalid');
            return;
        }

        if (password === passwordConfirmation && password.length >= 8) {
            passwordMatchMessage.style.display = 'block';
            passwordMatchMessage.className = 'form-text text-success';
            passwordMatchMessage.textContent = '✓ Las contraseñas coinciden';
            passwordConfirmationInput.classList.remove('is-invalid');
            passwordConfirmationInput.classList.add('is-valid');
        } else if (password.length > 0 && passwordConfirmation.length > 0) {
            passwordMatchMessage.style.display = 'block';
            passwordMatchMessage.className = 'form-text text-danger';
            passwordMatchMessage.textContent = '✗ Las contraseñas no coinciden';
            passwordConfirmationInput.classList.remove('is-valid');
            if (password !== passwordConfirmation) {
                passwordConfirmationInput.classList.add('is-invalid');
            }
        }
    }

    if (passwordInput && passwordConfirmationInput) {
        passwordInput.addEventListener('input', validatePasswordMatch);
        passwordConfirmationInput.addEventListener('input', validatePasswordMatch);
    }
});

// Función para mostrar/ocultar contraseña
function togglePasswordVisibility(inputId, buttonId) {
    const input = document.getElementById(inputId);
    if (!input) return;

    let iconId;
    if (inputId === 'new_password') {
        iconId = 'iconNewPassword';
    } else if (inputId === 'password_confirmation') {
        iconId = 'iconPasswordConfirmation';
    } else {
        return;
    }

    const icon = document.getElementById(iconId);
    if (!icon) return;

    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'tim-icons icon-lock-circle';
    } else {
        input.type = 'password';
        icon.className = 'tim-icons icon-single-02';
    }
}
</script>

<style>
.input-group-append .btn {
    border-left: 0;
    border-radius: 0 0.25rem 0.25rem 0;
    cursor: pointer;
}

.input-group-append .btn:focus {
    box-shadow: none;
    outline: none;
}

.input-group .form-control.is-valid {
    border-color: #28a745;
}

.input-group .form-control.is-valid:focus {
    border-color: #28a745;
    box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
}

.input-group .form-control.is-invalid {
    border-color: #dc3545;
}

.input-group .form-control.is-invalid:focus {
    border-color: #dc3545;
    box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
}
</style>
@endsection
