@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header card-header-success">
                    <h4 class="card-title">
                        <i class="material-icons">person_add</i>
                        Crear Usuario desde Empleado
                    </h4>
                    <p class="card-category">Seleccionar un empleado para crear su cuenta de usuario</p>
                </div>
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="material-icons">check_circle</i>
                            {{ session('success') }}
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    @endif

                    @if(session('error') || $errors->any())
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="material-icons">error</i>
                            @if(session('error'))
                                {{ session('error') }}
                            @else
                                <ul class="mb-0">
                                    @foreach($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            @endif
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    @endif

                    <form action="{{ route('admin.users.store-from-vendedor') }}" method="POST">
                        @csrf
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="vendedor_id" class="bmd-label-floating">Empleado *</label>
                                    <select name="vendedor_id" id="vendedor_id" class="form-control @error('vendedor_id') is-invalid @enderror" required style="color: #333 !important; background-color: #fff !important; border: 1px solid #ccc !important;">
                                        <option value="">Seleccionar empleado...</option>
                                        @foreach($vendedores as $vendedor)
                                            <option value="{{ $vendedor->id }}" 
                                                    data-nombre="{{ $vendedor->NOKOFU }}" 
                                                    data-email="{{ $vendedor->EMAIL }}"
                                                    data-rut="{{ $vendedor->RTFU }}"
                                                    data-tiene-usuario="{{ $vendedor->tiene_usuario ? 'true' : 'false' }}"
                                                    style="color: #333;">
                                                {{ $vendedor->KOFU }} - {{ $vendedor->NOKOFU }}
                                                @if($vendedor->tiene_usuario)
                                                    (Ya tiene usuario)
                                                @endif
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('vendedor_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="email" class="bmd-label-floating">Email del Sistema *</label>
                                    <input type="email" name="email" id="email" class="form-control @error('email') is-invalid @enderror" required>
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
                                    <input type="email" name="email_alternativo" id="email_alternativo" class="form-control @error('email_alternativo') is-invalid @enderror">
                                    @error('email_alternativo')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="rut" class="bmd-label-floating">RUT (Opcional)</label>
                                    <input type="text" name="rut" id="rut" class="form-control @error('rut') is-invalid @enderror" placeholder="12345678-9" maxlength="11">
                                    <small class="form-text text-muted">Formato: 12345678-9</small>
                                    @error('rut')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="password" class="bmd-label-floating">Contraseña Temporal *</label>
                                    <input type="password" name="password" id="password" class="form-control @error('password') is-invalid @enderror" required minlength="8">
                                    @error('password')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
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
                                                       @if($role->name === 'vendedor') checked @endif>
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
                                <div class="form-group">
                                    <div class="alert alert-info">
                                        <h6><i class="material-icons">info</i> Información del Empleado Seleccionado:</h6>
                                        <div id="vendedor-info">
                                            <p>Seleccione un empleado para ver su información</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12">
                                <button type="submit" class="btn btn-success">
                                    <i class="material-icons">save</i>
                                    Crear Usuario
                                </button>
                                <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">
                                    <i class="material-icons">arrow_back</i>
                                    Volver
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('js')
<style>
/* FORZAR estilos específicos para el select - sobrescribir CSS global */
#vendedor_id.form-control {
    color: #333 !important;
    background-color: #fff !important;
    border: 1px solid #ccc !important;
    border-radius: 0.4285rem !important;
    height: 45px !important;
    line-height: 1.5 !important;
    font-size: 14px !important;
}

#vendedor_id.form-control option {
    color: #333 !important;
    background-color: #fff !important;
    padding: 8px 12px !important;
}

#vendedor_id.form-control:focus {
    color: #333 !important;
    background-color: #fff !important;
    border-color: #9c27b0 !important;
    box-shadow: 0 0 0 0.2rem rgba(156, 39, 176, 0.25) !important;
}

#vendedor_id.form-control:hover {
    color: #333 !important;
    background-color: #fff !important;
}

/* Asegurar que el texto seleccionado sea visible */
#vendedor_id.form-control option:checked {
    color: #333 !important;
    background-color: #e3f2fd !important;
}

/* Estilos adicionales para todos los selects en esta página */
.card-body select.form-control {
    color: #333 !important;
    background-color: #fff !important;
    border: 1px solid #ccc !important;
}

.card-body select.form-control option {
    color: #333 !important;
    background-color: #fff !important;
}

.card-body select.form-control:focus {
    color: #333 !important;
    background-color: #fff !important;
    border-color: #9c27b0 !important;
    box-shadow: 0 0 0 0.2rem rgba(156, 39, 176, 0.25) !important;
}
</style>

<script>
console.log('🔧 Script de create-from-vendedor cargado');
document.addEventListener('DOMContentLoaded', function() {
    console.log('✅ DOM cargado, inicializando script...');
    const vendedorSelect = document.getElementById('vendedor_id');
    const infoDiv = document.getElementById('vendedor-info');
    const emailField = document.getElementById('email');
    const rutField = document.getElementById('rut');
    
    console.log('Elementos encontrados:', {
        vendedorSelect: !!vendedorSelect,
        infoDiv: !!infoDiv,
        emailField: !!emailField,
        rutField: !!rutField
    });
    
    // Función AJAX para obtener datos del vendedor
    function cargarDatosVendedor(vendedorId) {
        console.log('🔄 cargarDatosVendedor llamado con:', vendedorId);
        if (!vendedorId) {
            // Limpiar campos si no hay selección
            if (infoDiv) {
                infoDiv.innerHTML = '<p>Seleccione un empleado para ver su información</p>';
            }
            if (emailField) emailField.value = '';
            if (rutField) rutField.value = '';
            return;
        }

        // Mostrar indicador de carga
        if (infoDiv) {
            infoDiv.innerHTML = '<p><i class="material-icons" style="animation: spin 1s linear infinite;">hourglass_empty</i> Cargando información del empleado...</p>';
        }

        // Obtener token CSRF
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        // Realizar petición AJAX
        const url = `/admin/users/get-vendedor-data/${vendedorId}`;
        console.log('📡 Haciendo petición AJAX a:', url);
        fetch(url, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            credentials: 'same-origin'
        })
        .then(response => {
            console.log('📥 Respuesta recibida:', response.status, response.statusText);
            if (!response.ok) {
                throw new Error('Error en la respuesta del servidor: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            console.log('📊 Datos recibidos:', data);
            if (data.success && data.data) {
                const vendedor = data.data;
                console.log('✅ Datos del vendedor:', vendedor);
                
                // Mostrar información del empleado
                if (infoDiv) {
                    infoDiv.innerHTML = `
                        <p><strong>Nombre:</strong> ${vendedor.nombre || 'No especificado'}</p>
                        <p><strong>Email:</strong> ${vendedor.email || 'No especificado'}</p>
                        <p><strong>RUT:</strong> ${vendedor.rut || 'No especificado'}</p>
                    `;
                }
                
                // Pre-llenar email si existe
                if (emailField && vendedor.email && vendedor.email.trim() !== '') {
                    emailField.value = vendedor.email.trim();
                    emailField.classList.remove('is-invalid');
                    // Resaltar visualmente
                    emailField.style.backgroundColor = '#e8f5e8';
                    setTimeout(() => {
                        emailField.style.backgroundColor = '';
                    }, 2000);
                } else if (emailField) {
                    emailField.value = '';
                }
                
                // Pre-llenar RUT si existe
                if (rutField && vendedor.rut && vendedor.rut.trim() !== '') {
                    // Limpiar el RUT y formatearlo
                    let rutLimpio = vendedor.rut.trim().replace(/[^0-9kK-]/g, '');
                    // Si no tiene guión, agregarlo antes del último carácter
                    if (!rutLimpio.includes('-')) {
                        if (rutLimpio.length >= 2) {
                            const numero = rutLimpio.slice(0, -1);
                            const digitoVerificador = rutLimpio.slice(-1).toUpperCase();
                            rutLimpio = numero + '-' + digitoVerificador;
                        }
                    }
                    rutField.value = rutLimpio;
                    rutField.classList.remove('is-invalid');
                    // Resaltar visualmente
                    rutField.style.backgroundColor = '#e8f5e8';
                    setTimeout(() => {
                        rutField.style.backgroundColor = '';
                    }, 2000);
                } else if (rutField) {
                    rutField.value = '';
                }
            } else {
                // Error al cargar datos
                if (infoDiv) {
                    infoDiv.innerHTML = `<p class="text-danger"><i class="material-icons">error</i> ${data.message || 'Error al cargar información del empleado'}</p>`;
                }
                if (emailField) emailField.value = '';
                if (rutField) rutField.value = '';
            }
        })
        .catch(error => {
            console.error('Error en AJAX:', error);
            if (infoDiv) {
                infoDiv.innerHTML = '<p class="text-danger"><i class="material-icons">error</i> Error al cargar información del empleado. Por favor, intente nuevamente.</p>';
            }
            if (emailField) emailField.value = '';
            if (rutField) rutField.value = '';
        });
    }
    
    // Event listener para el cambio en el select
    if (vendedorSelect) {
        console.log('✅ Agregando event listener al select');
        vendedorSelect.addEventListener('change', function() {
            const vendedorId = this.value;
            console.log('🔄 Select cambió, valor seleccionado:', vendedorId);
            cargarDatosVendedor(vendedorId);
        });
        console.log('✅ Event listener agregado correctamente');
    } else {
        console.error('❌ No se encontró el elemento vendedorSelect');
    }

    // Formatear RUT mientras se escribe (mismo formato que en perfil)
    if (rutField) {
        rutField.addEventListener('input', function(e) {
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
        rutField.addEventListener('input', function(e) {
            if (e.target.value.length > 11) {
                e.target.value = e.target.value.slice(0, 11);
            }
        });
    }
});
</script>
@endpush
