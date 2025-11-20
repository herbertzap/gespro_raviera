@extends('layouts.app', ['page' => 'Perfil de Usuario', 'pageSlug' => 'profile'])

@section('content')
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="title">Editar Perfil</h5>
                </div>
                <form method="post" action="{{ route('profile.update') }}" autocomplete="off">
                    <div class="card-body">
                            @csrf
                            @method('put')

                            @include('alerts.success')

                            <div class="form-group{{ $errors->has('name') ? ' has-danger' : '' }}">
                                <label>Nombre</label>
                                <input type="text" name="name" class="form-control{{ $errors->has('name') ? ' is-invalid' : '' }}" placeholder="Nombre" value="{{ old('name', auth()->user()->name) }}" required>
                                @include('alerts.feedback', ['field' => 'name'])
                            </div>

                            <div class="form-group{{ $errors->has('email') ? ' has-danger' : '' }}">
                                <label>Correo Electrónico</label>
                                <input type="email" name="email" class="form-control{{ $errors->has('email') ? ' is-invalid' : '' }}" placeholder="Correo Electrónico" value="{{ old('email', auth()->user()->email) }}" required>
                                @include('alerts.feedback', ['field' => 'email'])
                            </div>

                            <div class="form-group{{ $errors->has('rut') ? ' has-danger' : '' }}">
                                <label>RUT (Opcional)</label>
                                <input type="text" name="rut" id="rut" class="form-control{{ $errors->has('rut') ? ' is-invalid' : '' }}" placeholder="12345678-9" value="{{ old('rut', auth()->user()->rut) }}" maxlength="12">
                                <small class="form-text text-muted">Formato: 12345678-9</small>
                                @include('alerts.feedback', ['field' => 'rut'])
                            </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-fill btn-primary">Guardar</button>
                    </div>
                </form>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="title">Cambiar Contraseña</h5>
                </div>
                <form method="post" action="{{ route('profile.password') }}" autocomplete="off">
                    <div class="card-body">
                        @csrf
                        @method('put')

                        @include('alerts.success', ['key' => 'password_status'])

                        <div class="form-group{{ $errors->has('old_password') ? ' has-danger' : '' }}">
                            <label>Contraseña Actual</label>
                            <input type="password" name="old_password" id="old_password" class="form-control{{ $errors->has('old_password') ? ' is-invalid' : '' }}" placeholder="Contraseña Actual" value="" required>
                            @include('alerts.feedback', ['field' => 'old_password'])
                        </div>

                        <div class="form-group{{ $errors->has('password') ? ' has-danger' : '' }}">
                            <label>Nueva Contraseña</label>
                            <div class="input-group">
                                <input type="password" name="password" id="password" class="form-control{{ $errors->has('password') ? ' is-invalid' : '' }}" placeholder="Nueva Contraseña" value="" required>
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword" onclick="togglePasswordVisibility('password', 'togglePassword')">
                                        <i class="tim-icons icon-single-02" id="iconPassword"></i>
                                    </button>
                                </div>
                            </div>
                            @include('alerts.feedback', ['field' => 'password'])
                            <small class="form-text text-muted">Mínimo 6 caracteres</small>
                        </div>
                        <div class="form-group{{ $errors->has('password_confirmation') ? ' has-danger' : '' }}">
                            <label>Confirmar Nueva Contraseña</label>
                            <div class="input-group">
                                <input type="password" name="password_confirmation" id="password_confirmation" class="form-control{{ $errors->has('password_confirmation') ? ' is-invalid' : '' }}" placeholder="Confirmar Nueva Contraseña" value="" required>
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary" type="button" id="togglePasswordConfirmation" onclick="togglePasswordVisibility('password_confirmation', 'togglePasswordConfirmation')">
                                        <i class="tim-icons icon-single-02" id="iconPasswordConfirmation"></i>
                                    </button>
                                </div>
                            </div>
                            <small id="passwordMatchMessage" class="form-text" style="display: none;"></small>
                            @include('alerts.feedback', ['field' => 'password_confirmation'])
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-fill btn-primary">Cambiar Contraseña</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

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
    const passwordInput = document.getElementById('password');
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

        if (password === passwordConfirmation && password.length >= 6) {
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
    if (inputId === 'password') {
        iconId = 'iconPassword';
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
