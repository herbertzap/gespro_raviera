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
                                        <div>
                                            <button type="button" class="btn btn-sm btn-primary" onclick="crearUbicacion({{ $bodega->id }})">
                                                <i class="tim-icons icon-simple-add"></i> Nueva Ubicación
                                            </button>
                                            <button type="button" class="btn btn-sm btn-success" onclick="cargaMasivaUbicaciones({{ $bodega->id }}, '{{ $bodega->kobo }}')">
                                                <i class="tim-icons icon-upload"></i> Carga Masiva
                                            </button>
                                        </div>
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

<!-- Modal Carga Masiva de Ubicaciones -->
<div class="modal fade" id="modalCargaMasiva" tabindex="-1" role="dialog" aria-labelledby="modalCargaMasivaLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalCargaMasivaLabel">Carga Masiva de Ubicaciones</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="formCargaMasiva" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" id="carga_masiva_bodega_id" name="bodega_id">
                    <input type="hidden" id="carga_masiva_kobo" name="kobo">
                    
                    <div class="alert alert-info">
                        <strong>Instrucciones:</strong>
                        <ul class="mb-0">
                            <li>El archivo Excel debe tener las siguientes columnas: <strong>KOBO</strong>, <strong>UBICACION</strong> (código), <strong>DESCRIPCION</strong></li>
                            <li>El KOBO debe ser igual a: <strong id="kobo_bodega_seleccionada"></strong></li>
                            <li>Las ubicaciones que ya existan serán omitidas automáticamente</li>
                            <li>La primera fila debe contener los encabezados</li>
                        </ul>
                    </div>
                    
                    <div class="form-group">
                        <label for="archivo_excel">Archivo Excel (.xlsx, .xls) *</label>
                        <input type="file" class="form-control-file" id="archivo_excel" name="archivo_excel" accept=".xlsx,.xls" required>
                        <small class="form-text text-muted">Tamaño máximo: 10MB</small>
                    </div>
                    
                    <div class="form-group">
                        <a href="#" id="descargarPlantilla" class="btn btn-sm btn-outline-primary">
                            <i class="tim-icons icon-download"></i> Descargar Plantilla Excel
                        </a>
                    </div>
                    
                    <div id="resultadoCarga" style="display: none;">
                        <hr>
                        <div id="mensajeResultado"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success" id="btnProcesarCarga">
                        <i class="tim-icons icon-upload"></i> Procesar Archivo
                    </button>
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
    const cargaMasivaUbicacionesUrl = "{{ route('mantenedor.ubicaciones.carga-masiva') }}";
    const descargarPlantillaUrl = "{{ route('mantenedor.ubicaciones.descargar-plantilla') }}";

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

    function cargaMasivaUbicaciones(bodegaId, kobo) {
        document.getElementById('carga_masiva_bodega_id').value = bodegaId;
        document.getElementById('carga_masiva_kobo').value = kobo;
        document.getElementById('kobo_bodega_seleccionada').textContent = kobo;
        document.getElementById('archivo_excel').value = '';
        document.getElementById('resultadoCarga').style.display = 'none';
        document.getElementById('mensajeResultado').innerHTML = '';
        $('#modalCargaMasiva').modal('show');
    }

    // Formulario de Carga Masiva
    document.getElementById('formCargaMasiva').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('_token', '{{ csrf_token() }}');
        
        const btnProcesar = document.getElementById('btnProcesarCarga');
        btnProcesar.disabled = true;
        btnProcesar.innerHTML = '<i class="tim-icons icon-refresh-02"></i> Procesando...';
        
        fetch(cargaMasivaUbicacionesUrl, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            btnProcesar.disabled = false;
            btnProcesar.innerHTML = '<i class="tim-icons icon-upload"></i> Procesar Archivo';
            
            const resultadoDiv = document.getElementById('resultadoCarga');
            const mensajeDiv = document.getElementById('mensajeResultado');
            resultadoDiv.style.display = 'block';
            
            if (data.success) {
                let mensaje = '<div class="alert alert-success"><h6><i class="tim-icons icon-check-2"></i> Proceso completado con éxito</h6>';
                mensaje += '<p><strong>Ubicaciones creadas:</strong> ' + data.creadas + '</p>';
                
                if (data.omitidas && data.omitidas.length > 0) {
                    mensaje += '<p><strong>Ubicaciones omitidas (ya existían):</strong> ' + data.omitidas.length + '</p>';
                    mensaje += '<ul class="mb-0">';
                    data.omitidas.slice(0, 10).forEach(function(codigo) {
                        mensaje += '<li>Ubicación <strong>' + codigo + '</strong> ya está ingresada, se omitió</li>';
                    });
                    if (data.omitidas.length > 10) {
                        mensaje += '<li>... y ' + (data.omitidas.length - 10) + ' más</li>';
                    }
                    mensaje += '</ul>';
                }
                
                if (data.errores && data.errores.length > 0) {
                    mensaje += '<p class="mt-2"><strong>Errores:</strong> ' + data.errores.length + '</p>';
                    mensaje += '<ul class="mb-0">';
                    data.errores.slice(0, 5).forEach(function(error) {
                        mensaje += '<li class="text-danger">' + error + '</li>';
                    });
                    if (data.errores.length > 5) {
                        mensaje += '<li>... y ' + (data.errores.length - 5) + ' más</li>';
                    }
                    mensaje += '</ul>';
                }
                
                mensaje += '</div>';
                mensajeDiv.innerHTML = mensaje;
                
                // Recargar la página después de 3 segundos si todo salió bien
                if (data.creadas > 0) {
                    setTimeout(function() {
                        window.location.reload();
                    }, 3000);
                }
            } else {
                mensajeDiv.innerHTML = '<div class="alert alert-danger"><h6><i class="tim-icons icon-alert-circle"></i> Error al procesar el archivo</h6><p>' + data.message + '</p></div>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            btnProcesar.disabled = false;
            btnProcesar.innerHTML = '<i class="tim-icons icon-upload"></i> Procesar Archivo';
            
            const resultadoDiv = document.getElementById('resultadoCarga');
            const mensajeDiv = document.getElementById('mensajeResultado');
            resultadoDiv.style.display = 'block';
            mensajeDiv.innerHTML = '<div class="alert alert-danger"><h6><i class="tim-icons icon-alert-circle"></i> Error</h6><p>Error al procesar el archivo. Por favor, intente nuevamente.</p></div>';
        });
    });

    // Descargar plantilla
    document.getElementById('descargarPlantilla').addEventListener('click', function(e) {
        e.preventDefault();
        window.location.href = descargarPlantillaUrl;
    });

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

    $('#modalCargaMasiva').on('hidden.bs.modal', function () {
        document.getElementById('formCargaMasiva').reset();
        document.getElementById('resultadoCarga').style.display = 'none';
        document.getElementById('mensajeResultado').innerHTML = '';
    });
</script>
@endpush

