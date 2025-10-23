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
                                    <label for="rut" class="bmd-label-floating">RUT</label>
                                    <input type="text" name="rut" id="rut" class="form-control @error('rut') is-invalid @enderror" placeholder="0000000-0" maxlength="10">
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

@section('scripts')
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
// Script directo sin DOMContentLoaded - v3.0
console.log('🔧 Script de precarga de datos iniciado - v3.0');

function initVendedorSelect() {
    const vendedorSelect = document.getElementById('vendedor_id');
    const infoDiv = document.getElementById('vendedor-info');
    const emailField = document.getElementById('email');
    const rutField = document.getElementById('rut');
    
    if (!vendedorSelect) {
        console.error('❌ No se encontró el select vendedor_id');
        return;
    }
    
    console.log('✅ Elementos encontrados:', {
        vendedorSelect: !!vendedorSelect,
        infoDiv: !!infoDiv,
        emailField: !!emailField,
        rutField: !!rutField
    });
    
    vendedorSelect.addEventListener('change', function() {
        console.log('🔄 Cambio detectado en select, valor:', this.value);
        
        const selectedOption = this.options[this.selectedIndex];
        console.log('📋 Opción seleccionada:', selectedOption);
        
        if (this.value && selectedOption) {
            const nombre = selectedOption.getAttribute('data-nombre');
            const email = selectedOption.getAttribute('data-email');
            const rut = selectedOption.getAttribute('data-rut');
            
            console.log('📊 Datos extraídos:', { nombre, email, rut });
            
            // Mostrar información del empleado
            if (infoDiv) {
                infoDiv.innerHTML = `
                    <p><strong>Nombre:</strong> ${nombre || 'No especificado'}</p>
                    <p><strong>Email:</strong> ${email || 'No especificado'}</p>
                    <p><strong>RUT:</strong> ${rut || 'No especificado'}</p>
                `;
            }
            
            // Pre-llenar email si existe
            if (emailField) {
                if (email && email.trim() !== '') {
                    console.log('📧 Precargando email:', email.trim());
                    emailField.value = email.trim();
                    emailField.classList.remove('is-invalid');
                } else {
                    emailField.value = '';
                }
            }
            
            // Pre-llenar RUT si existe
            if (rutField) {
                if (rut && rut.trim() !== '') {
                    console.log('🆔 Precargando RUT:', rut.trim());
                    rutField.value = rut.trim();
                    rutField.classList.remove('is-invalid');
                } else {
                    rutField.value = '';
                }
            }
            
            // Resaltar visualmente que se cargaron datos
            if (email && email.trim() !== '' && emailField) {
                emailField.style.backgroundColor = '#e8f5e8';
                setTimeout(() => {
                    emailField.style.backgroundColor = '';
                }, 2000);
            }
            
            if (rut && rut.trim() !== '' && rutField) {
                rutField.style.backgroundColor = '#e8f5e8';
                setTimeout(() => {
                    rutField.style.backgroundColor = '';
                }, 2000);
            }
        } else {
            console.log('🧹 Limpiando campos');
            if (infoDiv) {
                infoDiv.innerHTML = '<p>Seleccione un empleado para ver su información</p>';
            }
            // Limpiar campos cuando no hay selección
            if (emailField) emailField.value = '';
            if (rutField) rutField.value = '';
        }
    });
    
    console.log('✅ Event listener agregado correctamente');
}

// Intentar inicializar inmediatamente
initVendedorSelect();

// También intentar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', initVendedorSelect);

// Y como respaldo, intentar después de un pequeño delay
setTimeout(initVendedorSelect, 1000);

// Formatear RUT mientras se escribe
document.getElementById('rut').addEventListener('input', function(e) {
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
document.getElementById('rut').addEventListener('blur', function(e) {
    let value = e.target.value.trim();
    if (value && !/^\d{1,8}-[0-9kK]$/.test(value)) {
        e.target.classList.add('is-invalid');
    } else {
        e.target.classList.remove('is-invalid');
    }
});

// Script de prueba simple
console.log('🧪 Script de prueba ejecutándose');
</script>
@endsection
