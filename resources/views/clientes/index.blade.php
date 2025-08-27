@extends('layouts.app')

@section('title', 'Gestión de Clientes')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header card-header-primary">
                    <div class="row">
                        <div class="col-md-8">
                            <h4 class="card-title">
                                <i class="fas fa-users"></i> Gestión de Clientes
                            </h4>
                            <p class="card-category">Clientes asignados al vendedor</p>
                        </div>
                        <div class="col-md-4 text-right">
                            <button type="button" class="btn btn-info btn-sm" onclick="sincronizarClientes()">
                                <i class="fas fa-sync-alt"></i> Sincronizar
                            </button>
                            <button type="button" class="btn btn-success btn-sm" onclick="mostrarEstadisticas()">
                                <i class="fas fa-chart-bar"></i> Estadísticas
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="card-body">
                    <!-- Información de sincronización automática -->
                    <div class="alert alert-info" role="alert">
                        <i class="fas fa-info-circle"></i>
                        <strong>Sincronización Automática:</strong> Los datos de clientes se sincronizan automáticamente 
                        cuando ingresas por primera vez en el día. Esto garantiza que siempre tengas información actualizada.
                    </div>

                    <!-- Buscador de clientes -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="input-group">
                                <input type="text" id="buscarCliente" class="form-control" placeholder="Buscar cliente por código...">
                                <div class="input-group-append">
                                    <button class="btn btn-primary" type="button" onclick="buscarCliente()">
                                        <i class="fas fa-search"></i> Buscar
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group">
                                <input type="text" id="buscarPorNombre" class="form-control" placeholder="Buscar cliente por nombre...">
                                <div class="input-group-append">
                                    <button class="btn btn-secondary" type="button" onclick="buscarPorNombre()">
                                        <i class="fas fa-search"></i> Buscar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Resultados de búsqueda -->
                    <div id="resultadosBusqueda" style="display: none;">
                        <div class="card">
                            <div class="card-header">
                                <h5>Resultados de Búsqueda</h5>
                            </div>
                            <div class="card-body" id="contenidoResultados">
                                <!-- Los resultados se cargarán aquí -->
                            </div>
                        </div>
                    </div>

                    <!-- Lista de clientes -->
                    <div class="table-responsive">
                        <table class="table">
                            <thead class="text-info">
                                <tr>
                                    <th>Código</th>
                                    <th>Cliente</th>
                                    <th>Teléfono</th>
                                    <th>Región</th>
                                    <th>Comuna</th>
                                    <th>Estado</th>
                                    <th>Última Sincronización</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($clientes ?? [] as $cliente)
                                <tr>
                                    <td>{{ $cliente->codigo_cliente }}</td>
                                    <td>{{ $cliente->nombre_cliente }}</td>
                                    <td>{{ $cliente->telefono ?: 'N/A' }}</td>
                                    <td>{{ $cliente->region ?: 'N/A' }}</td>
                                    <td>{{ $cliente->comuna ?: 'N/A' }}</td>
                                    <td>
                                        @if($cliente->bloqueado)
                                            <span class="badge badge-danger">Bloqueado</span>
                                        @else
                                            <span class="badge badge-success">Activo</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($cliente->ultima_sincronizacion)
                                            {{ $cliente->ultima_sincronizacion->diffForHumans() }}
                                        @else
                                            <span class="text-muted">Nunca</span>
                                        @endif
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-primary" onclick="seleccionarCliente('{{ $cliente->codigo_cliente }}', '{{ $cliente->nombre_cliente }}')">
                                            <i class="fas fa-check"></i> Seleccionar
                                        </button>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="8" class="text-center">
                                        <div class="alert alert-warning">
                                            <i class="fas fa-exclamation-triangle"></i>
                                            No hay clientes disponibles. 
                                            <button type="button" class="btn btn-sm btn-info" onclick="sincronizarClientes()">
                                                Sincronizar ahora
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Estadísticas -->
<div class="modal fade" id="modalEstadisticas" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Estadísticas de Clientes</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body" id="contenidoEstadisticas">
                <!-- Las estadísticas se cargarán aquí -->
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
function buscarCliente() {
    const codigo = document.getElementById('buscarCliente').value.trim();
    if (!codigo) {
        alert('Por favor ingresa un código de cliente');
        return;
    }

    fetch('/clientes/buscar', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({ codigo_cliente: codigo })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            mostrarResultadoCliente(data.cliente);
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al buscar cliente');
    });
}

function buscarPorNombre() {
    const nombre = document.getElementById('buscarPorNombre').value.trim();
    if (nombre.length < 3) {
        alert('Por favor ingresa al menos 3 caracteres');
        return;
    }

    fetch('/clientes/buscar-por-nombre', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({ nombre: nombre })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            mostrarResultadosMultiples(data.clientes);
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al buscar clientes');
    });
}

function mostrarResultadoCliente(cliente) {
    const contenido = `
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5><i class="fas fa-user"></i> ${cliente.nombre}</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Código:</strong> ${cliente.codigo}</p>
                        <p><strong>Dirección:</strong> ${cliente.direccion || 'N/A'}</p>
                        <p><strong>Teléfono:</strong> ${cliente.telefono || 'N/A'}</p>
                        <p><strong>Región:</strong> ${cliente.region || 'N/A'}</p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Comuna:</strong> ${cliente.comuna || 'N/A'}</p>
                        <p><strong>Lista de Precios:</strong> ${cliente.lista_precios_nombre || 'N/A'}</p>
                        <p><strong>Estado:</strong> 
                            <span class="badge badge-${cliente.bloqueado ? 'danger' : 'success'}">
                                ${cliente.bloqueado ? 'Bloqueado' : 'Activo'}
                            </span>
                        </p>
                        <p><strong>Puede Vender:</strong> 
                            <span class="badge badge-${cliente.puede_vender ? 'success' : 'warning'}">
                                ${cliente.puede_vender ? 'Sí' : 'No'}
                            </span>
                        </p>
                    </div>
                </div>
                ${!cliente.puede_vender ? `<div class="alert alert-warning"><strong>Motivo:</strong> ${cliente.motivo_rechazo}</div>` : ''}
                <div class="text-center">
                    <button class="btn btn-primary" onclick="seleccionarCliente('${cliente.codigo}', '${cliente.nombre}')">
                        <i class="fas fa-check"></i> Seleccionar Cliente
                    </button>
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('contenidoResultados').innerHTML = contenido;
    document.getElementById('resultadosBusqueda').style.display = 'block';
}

function mostrarResultadosMultiples(clientes) {
    if (clientes.length === 0) {
        document.getElementById('contenidoResultados').innerHTML = '<div class="alert alert-info">No se encontraron clientes</div>';
        document.getElementById('resultadosBusqueda').style.display = 'block';
        return;
    }

    let contenido = '<div class="table-responsive"><table class="table table-striped">';
    contenido += '<thead><tr><th>Código</th><th>Nombre</th><th>Teléfono</th><th>Estado</th><th>Acción</th></tr></thead><tbody>';
    
    clientes.forEach(cliente => {
        contenido += `
            <tr>
                <td>${cliente.codigo}</td>
                <td>${cliente.nombre}</td>
                <td>${cliente.telefono || 'N/A'}</td>
                <td>
                    <span class="badge badge-${cliente.bloqueado ? 'danger' : 'success'}">
                        ${cliente.bloqueado ? 'Bloqueado' : 'Activo'}
                    </span>
                </td>
                <td>
                    <button class="btn btn-sm btn-primary" onclick="seleccionarCliente('${cliente.codigo}', '${cliente.nombre}')">
                        <i class="fas fa-check"></i> Seleccionar
                    </button>
                </td>
            </tr>
        `;
    });
    
    contenido += '</tbody></table></div>';
    
    document.getElementById('contenidoResultados').innerHTML = contenido;
    document.getElementById('resultadosBusqueda').style.display = 'block';
}

function sincronizarClientes() {
    if (!confirm('¿Deseas sincronizar los clientes desde SQL Server? Esto puede tomar unos momentos.')) {
        return;
    }

    const button = event.target;
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sincronizando...';
    button.disabled = true;

    fetch('/clientes/sincronizar', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(`Sincronización completada:\n- Nuevos: ${data.nuevos}\n- Actualizados: ${data.actualizados}\n- Total: ${data.total}`);
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al sincronizar clientes');
    })
    .finally(() => {
        button.innerHTML = originalText;
        button.disabled = false;
    });
}

function mostrarEstadisticas() {
    fetch('/clientes/estadisticas')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const stats = data.estadisticas;
            const contenido = `
                <div class="row text-center">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <h3>${stats.total_clientes}</h3>
                                <p>Total Clientes</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <h3>${stats.clientes_activos}</h3>
                                <p>Clientes Activos</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-danger text-white">
                            <div class="card-body">
                                <h3>${stats.clientes_bloqueados}</h3>
                                <p>Bloqueados</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body">
                                <h3>${stats.clientes_sin_lista_precios}</h3>
                                <p>Sin Lista Precios</p>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            document.getElementById('contenidoEstadisticas').innerHTML = contenido;
            $('#modalEstadisticas').modal('show');
        } else {
            alert('Error al cargar estadísticas');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al cargar estadísticas');
    });
}

function seleccionarCliente(codigo, nombre) {
    // Redirigir a la página de nueva cotización con el cliente seleccionado
    window.location.href = `/cotizacion/nueva?cliente=${codigo}&nombre=${encodeURIComponent(nombre)}`;
}

// Buscar con Enter
document.getElementById('buscarCliente').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        buscarCliente();
    }
});

document.getElementById('buscarPorNombre').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        buscarPorNombre();
    }
});
</script>
@endpush 