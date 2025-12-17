<div class="mt-4">
    <div class="row">
        <!-- Acciones principales -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">
                        <i class="tim-icons icon-settings"></i>
                        Acciones Principales
                    </h4>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <!-- Ver cliente -->
                        <a href="{{ route('clientes.show', $cotizacion->cliente_codigo) }}" 
                           class="btn btn-info btn-block">
                            <i class="tim-icons icon-single-02"></i>
                            Ver Cliente
                        </a>

                        <!-- Editar cotización (si es posible) -->
                        @if(in_array($cotizacion->estado, ['borrador', 'enviada', 'pendiente_stock']))
                        <a href="{{ route('cotizacion.editar', $cotizacion->id) }}" 
                           class="btn btn-warning btn-block">
                            <i class="tim-icons icon-pencil"></i>
                            Editar Cotización
                        </a>
                        @endif

                        <!-- Generar nota de venta (si está aprobada) -->
                        @if($cotizacion->estado === 'aprobada')
                        <button type="button" class="btn btn-success btn-block" 
                                onclick="generarNotaVenta({{ $cotizacion->id }})">
                            <i class="tim-icons icon-send"></i>
                            Generar Nota de Venta
                        </button>
                        @endif

                        <!-- Ir a aprobaciones (si está pendiente) -->
                        @if(in_array($cotizacion->estado_aprobacion, ['pendiente', 'pendiente_picking']))
                        <a href="{{ route('aprobaciones.show', $cotizacion->id) }}" 
                           class="btn btn-primary btn-block">
                            <i class="tim-icons icon-check-2"></i>
                            Ver Aprobaciones
                        </a>
                        @endif

                        <!-- Descargar PDF -->
                        <button type="button" class="btn btn-success btn-block" 
                                onclick="descargarPDF({{ $cotizacion->id }})">
                            <i class="tim-icons icon-paper"></i>
                            Descargar PDF
                        </button>

                        <!-- Descargar Guía Picking (solo para NVV aprobadas por picking) -->
                        @if($cotizacion->tipo_documento === 'nota_venta' && ($cotizacion->aprobado_por_picking || $cotizacion->estado_aprobacion === 'aprobada_picking' || $cotizacion->estado_aprobacion === 'pendiente_entrega'))
                        <a href="{{ route('aprobaciones.descargar-guia-picking', $cotizacion->id) }}" 
                           class="btn btn-primary btn-block" target="_blank">
                            <i class="tim-icons icon-delivery-fast"></i>
                            Descargar Guía Picking
                        </a>
                        @endif

                        <!-- Sincronizar Stock -->
                        <form action="{{ route('cotizacion.sincronizar-stock', $cotizacion->id) }}" method="POST" style="display: inline-block; width: 100%;">
                            @csrf
                            <button type="submit" class="btn btn-warning btn-block" onclick="return confirmarSincronizacion()">
                                <i class="tim-icons icon-refresh-02"></i>
                                Sincronizar Productos
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Comentarios y comunicación -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">
                        <i class="tim-icons icon-chat-33"></i>
                        Comentarios y Comunicación
                    </h4>
                </div>
                <div class="card-body">
                    <!-- Formulario para agregar comentario -->
                    <div class="alert alert-info">
                        <i class="tim-icons icon-info"></i>
                        <strong>Funcionalidad en desarrollo:</strong> Los comentarios estarán disponibles próximamente.
                    </div>
                    <!-- TODO: Implementar funcionalidad de comentarios
                    <form id="comentarioForm">
                        @csrf
                        <input type="hidden" name="cotizacion_id" value="{{ $cotizacion->id }}">
                        <div class="form-group">
                            <label for="comentario">Agregar Comentario:</label>
                            <textarea class="form-control" id="comentario" name="comentario" rows="3" 
                                      placeholder="Escribe un comentario sobre esta cotización..."></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="tim-icons icon-send"></i>
                            Enviar Comentario
                        </button>
                    </form>
                    -->

                    <!-- Lista de comentarios existentes -->
                    <div class="mt-4">
                        <h6>Comentarios Recientes:</h6>
                        <div id="comentariosList">
                            @if($cotizacion->observaciones)
                            <div class="alert alert-info">
                                <strong>Sistema:</strong> {{ $cotizacion->observaciones }}
                                <small class="text-muted d-block">{{ $cotizacion->updated_at->diffForHumans() }}</small>
                            </div>
                            @endif
                            
                            <!-- Aquí se cargarían los comentarios del historial -->
                            @if(isset($historial) && $historial)
                                @foreach($historial->where('comentarios', '!=', null) as $registro)
                                <div class="alert alert-light">
                                    <strong>{{ $registro->usuario_nombre ?? 'Sistema' }}:</strong> {{ $registro->comentarios }}
                                    <small class="text-muted d-block">{{ $registro->fecha_accion->diffForHumans() }}</small>
                                </div>
                                @endforeach
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Información adicional -->
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">
                        <i class="tim-icons icon-info"></i>
                        Información Adicional
                    </h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <strong>Fecha de Creación:</strong><br>
                            {{ $cotizacion->created_at->format('d/m/Y H:i:s') }}
                        </div>
                        <div class="col-md-4">
                            <strong>Última Actualización:</strong><br>
                            {{ $cotizacion->updated_at->format('d/m/Y H:i:s') }}
                        </div>
                        <div class="col-md-4">
                            <strong>Estado de Aprobación:</strong><br>
                            @if($cotizacion->estado_aprobacion)
                                <span class="badge badge-{{ $cotizacion->estado_aprobacion == 'aprobada_picking' ? 'success' : 'warning' }}">
                                    {{ ucfirst(str_replace('_', ' ', $cotizacion->estado_aprobacion)) }}
                                </span>
                            @else
                                <span class="badge badge-secondary">Sin aprobación</span>
                            @endif
                        </div>
                    </div>

                    @if($cotizacion->tiene_problemas_credito || $cotizacion->tiene_problemas_stock)
                    <div class="row mt-3">
                        <div class="col-md-12">
                            <h6>Problemas Identificados:</h6>
                            @if($cotizacion->tiene_problemas_credito)
                            <div class="alert alert-warning">
                                <i class="tim-icons icon-alert-triangle"></i>
                                <strong>Problemas de Crédito:</strong> {{ $cotizacion->detalle_problemas_credito ?? 'Cliente con problemas de crédito' }}
                            </div>
                            @endif
                            @if($cotizacion->tiene_problemas_stock)
                            <div class="alert alert-warning">
                                <i class="tim-icons icon-box-2"></i>
                                <strong>Problemas de Stock:</strong> {{ $cotizacion->detalle_problemas_stock ?? 'Productos con problemas de stock' }}
                            </div>
                            @endif
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('comentarioForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('#', { // TODO: Implementar ruta para comentarios
        method: 'POST',
        body: formData,
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Agregar el comentario a la lista
            const comentariosList = document.getElementById('comentariosList');
            const nuevoComentario = document.createElement('div');
            nuevoComentario.className = 'alert alert-light';
            nuevoComentario.innerHTML = `
                <strong>${data.usuario}:</strong> ${data.comentario}
                <small class="text-muted d-block">Ahora</small>
            `;
            comentariosList.insertBefore(nuevoComentario, comentariosList.firstChild);
            
            // Limpiar el formulario
            document.getElementById('comentario').value = '';
            
            // Mostrar notificación de éxito
            showNotification('Comentario agregado exitosamente', 'success');
        } else {
            showNotification('Error al agregar comentario', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error al agregar comentario', 'error');
    });
});

function showNotification(message, type) {
    // Implementar notificación (puede usar SweetAlert2 o similar)
    if (type === 'success') {
        alert('✅ ' + message);
    } else {
        alert('❌ ' + message);
    }
}

// Función para descargar PDF
function descargarPDF(cotizacionId) {
    window.open(`/cotizacion/pdf/${cotizacionId}`, '_blank');
}

// Función para confirmar sincronización
function confirmarSincronizacion() {
    return confirm('¿Estás seguro de que deseas sincronizar el stock de productos desde SQL Server?\n\nEsto actualizará los datos de stock en el sistema.');
}
</script>
