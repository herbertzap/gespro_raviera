@extends('layouts.app', ['pageSlug' => 'mantenedor-bodegas'])

@section('content')
<div class="content">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Mantenedor de Bodegas y Ubicaciones</h4>
                </div>
                <div class="card-body">
                    <!-- Botón para crear nueva bodega -->
                    <div class="mb-4">
                        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#modalCrearBodega">
                            <i class="tim-icons icon-simple-add"></i> Nueva Bodega
                        </button>
                    </div>

                    <!-- Listado de Bodegas -->
                    <div class="accordion" id="bodegasAccordion">
                        @forelse($bodegas as $bodega)
                        <div class="card mb-2">
                            <div class="card-header" id="heading{{ $bodega->id }}">
                                <h5 class="mb-0">
                                    <button class="btn btn-link" type="button" data-toggle="collapse" data-target="#collapse{{ $bodega->id }}" aria-expanded="false" aria-controls="collapse{{ $bodega->id }}">
                                        <strong>{{ $bodega->nombre_bodega }}</strong> 
                                        <span class="text-muted">({{ $bodega->kobo }})</span>
                                        <span class="badge badge-info ml-2">{{ $bodega->ubicaciones->count() }} ubicaciones</span>
                                    </button>
                                    <div class="float-right">
                                        <button type="button" class="btn btn-sm btn-warning" onclick="editarBodega({{ $bodega->id }})">
                                            <i class="tim-icons icon-pencil"></i> Editar
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger" onclick="eliminarBodega({{ $bodega->id }}, '{{ $bodega->nombre_bodega }}')">
                                            <i class="tim-icons icon-simple-remove"></i> Eliminar
                                        </button>
                                    </div>
                                </h5>
                            </div>
                            <div id="collapse{{ $bodega->id }}" class="collapse" aria-labelledby="heading{{ $bodega->id }}" data-parent="#bodegasAccordion">
                                <div class="card-body">
                                    <div class="mb-3">
                                        <strong>Información de la Bodega:</strong>
                                        <ul class="list-unstyled ml-3">
                                            <li><strong>Empresa:</strong> {{ $bodega->empresa }}</li>
                                            <li><strong>KOSU:</strong> {{ $bodega->kosu }}</li>
                                            <li><strong>KOBO:</strong> {{ $bodega->kobo }}</li>
                                            <li><strong>Centro de Costo:</strong> {{ $bodega->centro_costo ?? '-' }}</li>
                                        </ul>
                                    </div>
                                    
                                    <hr>
                                    
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h6 class="mb-0">Ubicaciones</h6>
                                        <button type="button" class="btn btn-sm btn-primary" onclick="crearUbicacion({{ $bodega->id }})">
                                            <i class="tim-icons icon-simple-add"></i> Nueva Ubicación
                                        </button>
                                    </div>
                                    
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>Código</th>
                                                    <th>Descripción</th>
                                                    <th>Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse($bodega->ubicaciones as $ubicacion)
                                                <tr>
                                                    <td><strong>{{ $ubicacion->codigo }}</strong></td>
                                                    <td>{{ $ubicacion->descripcion ?? '-' }}</td>
                                                    <td>
                                                        <button type="button" class="btn btn-sm btn-warning" onclick="editarUbicacion({{ $ubicacion->id }})">
                                                            <i class="tim-icons icon-pencil"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-danger" onclick="eliminarUbicacion({{ $ubicacion->id }}, '{{ $ubicacion->codigo }}')">
                                                            <i class="tim-icons icon-simple-remove"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                                @empty
                                                <tr>
                                                    <td colspan="3" class="text-center text-muted">No hay ubicaciones registradas</td>
                                                </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @empty
                        <div class="alert alert-info">
                            No hay bodegas registradas. Crea una nueva bodega para comenzar.
                        </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Crear/Editar Bodega -->
<div class="modal fade" id="modalCrearBodega" tabindex="-1" role="dialog" aria-labelledby="modalCrearBodegaLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalCrearBodegaLabel">Nueva Bodega</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="formBodega">
                <div class="modal-body">
                    <input type="hidden" id="bodega_id" name="bodega_id">
                    <div class="form-group">
                        <label for="empresa">Empresa *</label>
                        <input type="text" class="form-control" id="empresa" name="empresa" required maxlength="10">
                    </div>
                    <div class="form-group">
                        <label for="kosu">KOSU *</label>
                        <input type="text" class="form-control" id="kosu" name="kosu" required maxlength="10">
                    </div>
                    <div class="form-group">
                        <label for="kobo">KOBO *</label>
                        <input type="text" class="form-control" id="kobo" name="kobo" required maxlength="10">
                    </div>
                    <div class="form-group">
                        <label for="nombre_bodega">Nombre Bodega *</label>
                        <input type="text" class="form-control" id="nombre_bodega" name="nombre_bodega" required maxlength="200">
                    </div>
                    <div class="form-group">
                        <label for="centro_costo">Centro de Costo</label>
                        <input type="text" class="form-control" id="centro_costo" name="centro_costo" maxlength="10">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Crear/Editar Ubicación -->
<div class="modal fade" id="modalCrearUbicacion" tabindex="-1" role="dialog" aria-labelledby="modalCrearUbicacionLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalCrearUbicacionLabel">Nueva Ubicación</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="formUbicacion">
                <div class="modal-body">
                    <input type="hidden" id="ubicacion_id" name="ubicacion_id">
                    <input type="hidden" id="ubicacion_bodega_id" name="bodega_id">
                    <div class="form-group">
                        <label for="codigo_ubicacion">Código *</label>
                        <input type="text" class="form-control" id="codigo_ubicacion" name="codigo" required maxlength="50">
                    </div>
                    <div class="form-group">
                        <label for="descripcion_ubicacion">Descripción</label>
                        <input type="text" class="form-control" id="descripcion_ubicacion" name="descripcion" maxlength="200">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('js')
<script>
    const crearBodegaUrl = "{{ route('mantenedor.bodegas.crear') }}";
    const actualizarBodegaUrl = "{{ route('mantenedor.bodegas.actualizar', ':id') }}";
    const eliminarBodegaUrl = "{{ route('mantenedor.bodegas.eliminar', ':id') }}";
    const crearUbicacionUrl = "{{ route('mantenedor.ubicaciones.crear') }}";
    const actualizarUbicacionUrl = "{{ route('mantenedor.ubicaciones.actualizar', ':id') }}";
    const eliminarUbicacionUrl = "{{ route('mantenedor.ubicaciones.eliminar', ':id') }}";

    const bodegas = @json($bodegas);

    // Formulario de Bodega
    document.getElementById('formBodega').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const bodegaId = document.getElementById('bodega_id').value;
        const formData = new FormData(this);
        formData.append('_token', '{{ csrf_token() }}');
        
        const url = bodegaId ? actualizarBodegaUrl.replace(':id', bodegaId) : crearBodegaUrl;
        const method = bodegaId ? 'PUT' : 'POST';
        
        fetch(url, {
            method: method,
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                window.location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al guardar la bodega');
        });
    });

    // Formulario de Ubicación
    document.getElementById('formUbicacion').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const ubicacionId = document.getElementById('ubicacion_id').value;
        const formData = new FormData(this);
        formData.append('_token', '{{ csrf_token() }}');
        
        const url = ubicacionId ? actualizarUbicacionUrl.replace(':id', ubicacionId) : crearUbicacionUrl;
        const method = ubicacionId ? 'PUT' : 'POST';
        
        fetch(url, {
            method: method,
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                window.location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al guardar la ubicación');
        });
    });

    function editarBodega(id) {
        const bodega = bodegas.find(b => b.id === id);
        if (!bodega) return;
        
        document.getElementById('bodega_id').value = bodega.id;
        document.getElementById('empresa').value = bodega.empresa;
        document.getElementById('kosu').value = bodega.kosu;
        document.getElementById('kobo').value = bodega.kobo;
        document.getElementById('nombre_bodega').value = bodega.nombre_bodega;
        document.getElementById('centro_costo').value = bodega.centro_costo || '';
        document.getElementById('modalCrearBodegaLabel').textContent = 'Editar Bodega';
        
        $('#modalCrearBodega').modal('show');
    }

    function eliminarBodega(id, nombre) {
        if (!confirm('¿Estás seguro de eliminar la bodega "' + nombre + '"?\n\nEsta acción no se puede deshacer.')) {
            return;
        }
        
        fetch(eliminarBodegaUrl.replace(':id', id), {
            method: 'DELETE',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                window.location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al eliminar la bodega');
        });
    }

    function crearUbicacion(bodegaId) {
        document.getElementById('ubicacion_id').value = '';
        document.getElementById('ubicacion_bodega_id').value = bodegaId;
        document.getElementById('codigo_ubicacion').value = '';
        document.getElementById('descripcion_ubicacion').value = '';
        document.getElementById('modalCrearUbicacionLabel').textContent = 'Nueva Ubicación';
        
        $('#modalCrearUbicacion').modal('show');
    }

    function editarUbicacion(id) {
        let ubicacion = null;
        for (const bodega of bodegas) {
            ubicacion = bodega.ubicaciones.find(u => u.id === id);
            if (ubicacion) break;
        }
        
        if (!ubicacion) return;
        
        document.getElementById('ubicacion_id').value = ubicacion.id;
        document.getElementById('ubicacion_bodega_id').value = ubicacion.bodega_id;
        document.getElementById('codigo_ubicacion').value = ubicacion.codigo;
        document.getElementById('descripcion_ubicacion').value = ubicacion.descripcion || '';
        document.getElementById('modalCrearUbicacionLabel').textContent = 'Editar Ubicación';
        
        $('#modalCrearUbicacion').modal('show');
    }

    function eliminarUbicacion(id, codigo) {
        if (!confirm('¿Estás seguro de eliminar la ubicación "' + codigo + '"?\n\nEsta acción no se puede deshacer.')) {
            return;
        }
        
        fetch(eliminarUbicacionUrl.replace(':id', id), {
            method: 'DELETE',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                window.location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al eliminar la ubicación');
        });
    }

    // Limpiar formulario al cerrar modal
    $('#modalCrearBodega').on('hidden.bs.modal', function () {
        document.getElementById('formBodega').reset();
        document.getElementById('bodega_id').value = '';
        document.getElementById('modalCrearBodegaLabel').textContent = 'Nueva Bodega';
    });

    $('#modalCrearUbicacion').on('hidden.bs.modal', function () {
        document.getElementById('formUbicacion').reset();
        document.getElementById('ubicacion_id').value = '';
        document.getElementById('modalCrearUbicacionLabel').textContent = 'Nueva Ubicación';
    });
</script>
@endpush

