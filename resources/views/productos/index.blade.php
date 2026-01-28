@extends('layouts.app')

@section('title', 'Gestión de Productos - Compras')

@section('content')
@php
    $pageSlug = 'productos';
@endphp
<div class="content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <div class="row">
                            <div class="col-md-8">
                                <h4 class="card-title">Gestión de Productos - Compras</h4>
                                <p class="card-category">Análisis de productos, stock y ventas</p>
                            </div>
                            <div class="col-md-4 text-right">
                                <a href="{{ route('productos.descargar-informe-compras') }}" class="btn btn-success btn-sm">
                                    <i class="material-icons">file_download</i> Descargar Informe de Compras
                                </a>
                                <button type="button" class="btn btn-warning btn-sm ml-2" onclick="iniciarSincronizacionProductos()">
                                    <i class="material-icons">sync</i> Sincronizar Productos General
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Buscador de Productos con NVV Pendientes -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header card-header-info">
                        <h4 class="card-title">
                            <i class="material-icons">search</i> Buscar Productos con NVV Pendientes
                        </h4>
                        <p class="card-category">Información de stock y cantidades pendientes en NVV</p>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label>Buscar producto (código o nombre)</label>
                                    <input type="text" id="buscarProductoInput" class="form-control" 
                                           placeholder="Ej: SINS001500000 o nombre del producto..." 
                                           onkeyup="buscarProductosConNvv(this.value)">
                                </div>
                            </div>
                        </div>
                        <div id="resultadosBusqueda" style="display:none;">
                            <hr>
                            <h5>Resultados de la búsqueda</h5>
                            <div class="table-responsive">
                                <table class="table table-hover" id="tablaResultadosBusqueda">
                                    <thead class="text-info">
                                        <tr>
                                            <th>Código</th>
                                            <th>Nombre</th>
                                            <th>Stock Físico</th>
                                            <th>Stock Comprometido (SQL)</th>
                                            <th>Stock Comprometido (Local)</th>
                                            <th>Stock Disponible</th>
                                            <th>Cantidad NVV Pendiente</th>
                                            <th>N° NVV</th>
                                            <th>Detalle NVV</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbodyResultadosBusqueda">
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tarjetas de Resumen -->
        <div class="row">
            <!-- Productos con Bajo Stock -->
            <div class="col-lg-3 col-md-6 col-sm-6">
                <div class="card card-stats">
                    <div class="card-header card-header-danger card-header-icon">
                        <div class="card-icon">
                            <i class="material-icons">warning</i>
                        </div>
                        <p class="card-category">Bajo Stock</p>
                        <h3 class="card-title">{{ $productosBajoStock ?? 0 }}</h3>
                    </div>
                    <div class="card-footer">
                        <div class="stats">
                            <i class="material-icons text-danger">warning</i>
                            Productos con stock crítico
                        </div>
                    </div>
                </div>
            </div>

            <!-- Total Productos -->
            <div class="col-lg-3 col-md-6 col-sm-6">
                <div class="card card-stats">
                    <div class="card-header card-header-info card-header-icon">
                        <div class="card-icon">
                            <i class="material-icons">inventory</i>
                        </div>
                        <p class="card-category">Total Productos</p>
                        <h3 class="card-title">{{ $totalProductos ?? 0 }}</h3>
                    </div>
                    <div class="card-footer">
                        <div class="stats">
                            <i class="material-icons text-info">inventory</i>
                            Productos en sistema
                        </div>
                    </div>
                </div>
            </div>

            <!-- Productos Más Vendidos -->
            <div class="col-lg-3 col-md-6 col-sm-6">
                <div class="card card-stats">
                    <div class="card-header card-header-success card-header-icon">
                        <div class="card-icon">
                            <i class="material-icons">trending_up</i>
                        </div>
                        <p class="card-category">Top Ventas</p>
                        <h3 class="card-title">{{ $productosMasVendidos ?? 0 }}</h3>
                    </div>
                    <div class="card-footer">
                        <div class="stats">
                            <i class="material-icons text-success">trending_up</i>
                            Este mes
                        </div>
                    </div>
                </div>
            </div>

            <!-- Valor Total Stock -->
            <div class="col-lg-3 col-md-6 col-sm-6">
                <div class="card card-stats">
                    <div class="card-header card-header-warning card-header-icon">
                        <div class="card-icon">
                            <i class="material-icons">attach_money</i>
                        </div>
                        <p class="card-category">Valor Stock</p>
                        <h3 class="card-title">${{ number_format($valorTotalStock ?? 0, 0) }}</h3>
                    </div>
                    <div class="card-footer">
                        <div class="stats">
                            <i class="material-icons text-warning">attach_money</i>
                            Valor total inventario
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Productos Más Vendidos -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header card-header-success">
                        <h4 class="card-title">
                            <i class="material-icons">trending_up</i> Productos Más Vendidos
                        </h4>
                        <p class="card-category">Top 50 - Últimos 3 meses (paginación de 5)</p>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="tablaProductosVendidos">
                                <thead class="text-success">
                                    <tr>
                                        <th>#</th>
                                        <th>Código</th>
                                        <th>Producto</th>
                                        <th>Cantidad Vendida</th>
                                        <th>N° Ventas</th>
                                        <th>Precio Promedio</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $paginaActual = request()->get('pagina_vendidos', 1);
                                        $porPagina = 5;
                                        $inicio = ($paginaActual - 1) * $porPagina;
                                        $productosVendidosPaginados = array_slice($productosVendidos->toArray(), $inicio, $porPagina);
                                        $totalPaginas = ceil(count($productosVendidos) / $porPagina);
                                    @endphp
                                    @forelse($productosVendidosPaginados as $index => $producto)
                                    <tr>
                                        <td>{{ $inicio + $index + 1 }}</td>
                                        <td><strong>{{ $producto['codigo'] }}</strong></td>
                                        <td>{{ $producto['nombre'] }}</td>
                                        <td>
                                            <span class="badge badge-success">{{ number_format($producto['cantidad'], 0) }}</span>
                                        </td>
                                        <td>{{ $producto['total_ventas'] }}</td>
                                        <td>${{ number_format($producto['precio_promedio'], 0) }}</td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="6" class="text-center">No hay datos de productos vendidos</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Paginación manual -->
                        @if(count($productosVendidos) > $porPagina)
                        <div class="card-footer">
                            <nav aria-label="Paginación productos vendidos">
                                <ul class="pagination justify-content-center">
                                    <li class="page-item {{ $paginaActual == 1 ? 'disabled' : '' }}">
                                        <a class="page-link" href="?pagina_vendidos={{ $paginaActual - 1 }}">Anterior</a>
                                    </li>
                                    @for($i = 1; $i <= $totalPaginas; $i++)
                                        <li class="page-item {{ $paginaActual == $i ? 'active' : '' }}">
                                            <a class="page-link" href="?pagina_vendidos={{ $i }}">{{ $i }}</a>
                                        </li>
                                    @endfor
                                    <li class="page-item {{ $paginaActual == $totalPaginas ? 'disabled' : '' }}">
                                        <a class="page-link" href="?pagina_vendidos={{ $paginaActual + 1 }}">Siguiente</a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabla de Productos con Bajo Stock -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header card-header-warning">
                        <h4 class="card-title">Productos con Bajo Stock</h4>
                        <p class="card-category">Productos que requieren reposición</p>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead class="text-warning">
                                    <tr>
                                        <th>Código</th>
                                        <th>Nombre</th>
                                        <th>Stock Actual</th>
                                        <th>Stock Mínimo</th>
                                        <th>Diferencia</th>
                                        <th>Precio</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($productosBajoStockLista ?? [] as $producto)
                                    <tr>
                                        <td>{{ $producto['codigo'] ?? 'N/A' }}</td>
                                        <td>{{ $producto['nombre'] ?? 'N/A' }}</td>
                                        <td>
                                            <span class="badge badge-{{ $producto['stock_actual'] <= 0 ? 'danger' : 'warning' }}">
                                                {{ $producto['stock_actual'] ?? 0 }}
                                            </span>
                                        </td>
                                        <td>{{ $producto['stock_minimo'] ?? 0 }}</td>
                                        <td>
                                            <span class="badge badge-danger">
                                                {{ ($producto['stock_actual'] ?? 0) - ($producto['stock_minimo'] ?? 0) }}
                                            </span>
                                        </td>
                                        <td>${{ number_format($producto['precio'] ?? 0, 0) }}</td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="6" class="text-center">No hay productos con bajo stock</td>
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
</div>

<!-- Modal para detalle de producto -->
<div class="modal fade" id="modalDetalleProducto" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalle del Producto</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body" id="contenidoDetalleProducto">
                <!-- El contenido se cargará aquí -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-primary" onclick="crearNVVConProducto()">Crear NVV</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Sincronización de Productos -->
<div class="modal fade" id="modalSincronizacionProductos" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="material-icons text-warning">sync</i> Sincronización de Productos
                </h5>
                <button type="button" class="close" data-dismiss="modal" id="btnCerrarModal" style="display:none;">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="sincronizacionIniciando" class="text-center">
                    <div class="spinner-border text-warning" role="status">
                        <span class="sr-only">Cargando...</span>
                    </div>
                    <p class="mt-3">Iniciando sincronización...</p>
                </div>
                
                <div id="sincronizacionProgreso" style="display:none;">
                    <h6 class="text-info">
                        <i class="material-icons">info</i> Sincronizando productos desde SQL Server
                    </h6>
                    <p class="text-muted small">
                        Esto puede tomar varios minutos. Se procesan en lotes de 1000 productos.
                        <br>Estimado: 5,000 - 11,000 productos totales
                    </p>
                    
                    <!-- Barra de progreso -->
                    <div class="progress mb-3" style="height: 30px;">
                        <div id="barraProgreso" class="progress-bar progress-bar-striped progress-bar-animated bg-warning" 
                             role="progressbar" style="width: 0%">
                            <span id="textoProgreso">0%</span>
                        </div>
                    </div>
                    
                    <!-- Estadísticas -->
                    <div class="row">
                        <div class="col-md-4">
                            <div class="card card-stats">
                                <div class="card-body text-center">
                                    <h6 class="card-title">Productos Procesados</h6>
                                    <h3 id="totalProcesados" class="text-info">0</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card card-stats">
                                <div class="card-body text-center">
                                    <h6 class="card-title">Productos Creados</h6>
                                    <h3 id="totalCreados" class="text-success">0</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card card-stats">
                                <div class="card-body text-center">
                                    <h6 class="card-title">Productos Actualizados</h6>
                                    <h3 id="totalActualizados" class="text-warning">0</h3>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Estado actual -->
                    <div class="mt-3">
                        <p class="mb-1"><strong>Estado actual:</strong></p>
                        <p id="estadoActual" class="text-muted">Preparando sincronización...</p>
                    </div>
                </div>
                
                <div id="sincronizacionCompletada" style="display:none;">
                    <div class="alert alert-success">
                        <h5><i class="material-icons">check_circle</i> Sincronización Completada</h5>
                        <hr>
                        <div class="row">
                            <div class="col-md-4">
                                <strong>Total Procesados:</strong>
                                <h4 id="resumenProcesados" class="text-info">0</h4>
                            </div>
                            <div class="col-md-4">
                                <strong>Productos Creados:</strong>
                                <h4 id="resumenCreados" class="text-success">0</h4>
                            </div>
                            <div class="col-md-4">
                                <strong>Productos Actualizados:</strong>
                                <h4 id="resumenActualizados" class="text-warning">0</h4>
                            </div>
                        </div>
                        <p class="mt-3 mb-0">
                            <i class="material-icons">update</i> 
                            Los productos han sido sincronizados con stock, precios y datos actualizados.
                        </p>
                    </div>
                </div>
                
                <div id="sincronizacionError" style="display:none;">
                    <div class="alert alert-danger">
                        <h5><i class="material-icons">error</i> Error en la Sincronización</h5>
                        <p id="mensajeError"></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal" id="btnCerrarModalFooter">Cerrar</button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('js')
<script>
// Función para crear NVV con producto
function crearNVVConProducto() {
    // Aquí implementarías la lógica para crear una nueva NVV
    alert('Funcionalidad de crear NVV con producto - Por implementar');
}

// Función para buscar productos con información de NVV pendientes
let timeoutBusqueda = null;
function buscarProductosConNvv(termino) {
    // Limpiar timeout anterior
    if (timeoutBusqueda) {
        clearTimeout(timeoutBusqueda);
    }
    
    // Si el término es muy corto, ocultar resultados
    if (termino.length < 2) {
        $('#resultadosBusqueda').hide();
        return;
    }
    
    // Esperar 500ms antes de buscar (debounce)
    timeoutBusqueda = setTimeout(function() {
        $.ajax({
            url: '{{ route("productos.buscar-nvv") }}',
            method: 'GET',
            data: {
                q: termino
            },
            beforeSend: function() {
                $('#tbodyResultadosBusqueda').html('<tr><td colspan="9" class="text-center"><i class="material-icons">hourglass_empty</i> Buscando...</td></tr>');
                $('#resultadosBusqueda').show();
            },
            success: function(response) {
                if (response.length === 0) {
                    $('#tbodyResultadosBusqueda').html('<tr><td colspan="9" class="text-center text-muted">No se encontraron productos</td></tr>');
                    return;
                }
                
                let html = '';
                response.forEach(function(producto) {
                    // Determinar clase de stock disponible
                    let stockClass = 'text-success';
                    if (producto.stock_disponible <= 0) {
                        stockClass = 'text-danger';
                    } else if (producto.stock_disponible < 10) {
                        stockClass = 'text-warning';
                    }
                    
                    // Formatear detalle de NVV
                    let detalleNvvHtml = '';
                    if (producto.detalle_nvv && producto.detalle_nvv.length > 0) {
                        detalleNvvHtml = '<ul class="list-unstyled mb-0">';
                        producto.detalle_nvv.forEach(function(nvv) {
                            detalleNvvHtml += `<li><small>NVV ${nvv.nvv}: ${nvv.cantidad.toLocaleString()}</small></li>`;
                        });
                        detalleNvvHtml += '</ul>';
                    } else {
                        detalleNvvHtml = '<span class="text-muted">-</span>';
                    }
                    
                    html += `
                        <tr>
                            <td><strong>${producto.codigo}</strong></td>
                            <td>${producto.nombre}</td>
                            <td>${producto.stock_fisico.toLocaleString()}</td>
                            <td class="text-warning">${producto.stock_comprometido_sql.toLocaleString()}</td>
                            <td class="text-info">${producto.stock_comprometido_local.toLocaleString()}</td>
                            <td class="${stockClass}"><strong>${producto.stock_disponible.toLocaleString()}</strong></td>
                            <td class="text-danger">${producto.cantidad_nvv_pendiente.toLocaleString()}</td>
                            <td>${producto.numero_nvv}</td>
                            <td>${detalleNvvHtml}</td>
                        </tr>
                    `;
                });
                
                $('#tbodyResultadosBusqueda').html(html);
            },
            error: function(xhr) {
                console.error('Error buscando productos:', xhr);
                $('#tbodyResultadosBusqueda').html('<tr><td colspan="9" class="text-center text-danger">Error al buscar productos</td></tr>');
            }
        });
    }, 500);
}

// Variables globales para la sincronización
let sincronizacionEnProceso = false;
let estadisticasTotales = {
    procesados: 0,
    creados: 0,
    actualizados: 0
};
const ESTIMADO_TOTAL = 8000; // Valor medio estimado para cálculo de porcentaje

// Función para iniciar la sincronización
function iniciarSincronizacionProductos() {
    if (sincronizacionEnProceso) {
        alert('Ya hay una sincronización en proceso. Por favor, espere a que termine.');
        return;
    }
    
    // Reiniciar estadísticas
    estadisticasTotales = {
        procesados: 0,
        creados: 0,
        actualizados: 0
    };
    
    // Mostrar modal
    $('#modalSincronizacionProductos').modal('show');
    
    // Mostrar sección de inicio
    $('#sincronizacionIniciando').show();
    $('#sincronizacionProgreso').hide();
    $('#sincronizacionCompletada').hide();
    $('#sincronizacionError').hide();
    $('#btnCerrarModal').hide();
    $('#btnCerrarModalFooter').prop('disabled', true);
    
    // Iniciar sincronización después de un breve delay para mostrar el modal
    setTimeout(() => {
        procesarSincronizacion(0);
    }, 500);
}

// Variable para controlar si ya se sincronizó el stock
let stockSincronizado = false;

// Función recursiva para procesar la sincronización por lotes
function procesarSincronizacion(batch) {
    sincronizacionEnProceso = true;
    
    // Actualizar estado
    $('#sincronizacionIniciando').hide();
    $('#sincronizacionProgreso').show();
    $('#estadoActual').text(`Procesando lote ${batch + 1} (productos ${batch * 1000} - ${(batch + 1) * 1000})...`);
    
    // Hacer petición AJAX
    $.ajax({
        url: '{{ route("productos.sincronizar-general") }}',
        method: 'POST',
        data: {
            batch: batch,
            _token: '{{ csrf_token() }}'
        },
        timeout: 120000, // 2 minutos por lote
        success: function(response) {
            if (response.success) {
                // Acumular estadísticas
                estadisticasTotales.procesados += response.procesados || 0;
                estadisticasTotales.creados += response.nuevos || 0;
                estadisticasTotales.actualizados += response.actualizados || 0;
                
                // Actualizar UI
                $('#totalProcesados').text(estadisticasTotales.procesados.toLocaleString());
                $('#totalCreados').text(estadisticasTotales.creados.toLocaleString());
                $('#totalActualizados').text(estadisticasTotales.actualizados.toLocaleString());
                
                // Actualizar barra de progreso (estimado)
                const porcentaje = Math.min(100, Math.round((estadisticasTotales.procesados / ESTIMADO_TOTAL) * 100));
                $('#barraProgreso').css('width', porcentaje + '%');
                $('#textoProgreso').text(porcentaje + '%');
                
                // Verificar si hay más lotes de productos
                if (response.hasMore && response.nextBatch !== null) {
                    // Continuar con el siguiente lote de productos
                    setTimeout(() => {
                        procesarSincronizacion(response.nextBatch);
                    }, 500); // Pequeño delay entre lotes
                } else if (response.syncStock && !stockSincronizado) {
                    // Si terminamos productos, sincronizar stock y precios
                    stockSincronizado = true;
                    $('#estadoActual').text('Productos sincronizados. Sincronizando stock y precios desde bodega LIB...');
                    setTimeout(() => {
                        sincronizarStock();
                    }, 500);
                } else {
                    // Sincronización completada
                    sincronizacionCompletada();
                }
            } else {
                mostrarError(response.message || 'Error desconocido en la sincronización');
            }
        },
        error: function(xhr) {
            let mensajeError = 'Error al comunicarse con el servidor';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                mensajeError = xhr.responseJSON.message;
            }
            mostrarError(mensajeError);
        }
    });
}

// Función para sincronizar stock y precios
function sincronizarStock() {
    $.ajax({
        url: '{{ route("productos.sincronizar-stock") }}',
        method: 'POST',
        data: {
            _token: '{{ csrf_token() }}'
        },
        timeout: 300000, // 5 minutos para sincronizar stock
        success: function(response) {
            if (response.success) {
                $('#estadoActual').html(`
                    <strong>✅ Sincronización completa finalizada</strong><br>
                    Productos procesados: ${estadisticasTotales.procesados.toLocaleString()}<br>
                    Productos nuevos: ${estadisticasTotales.creados.toLocaleString()}<br>
                    Productos actualizados: ${estadisticasTotales.actualizados.toLocaleString()}<br>
                    Stock actualizado: ${response.stock_actualizado || 0} productos
                `);
                sincronizacionCompletada();
            } else {
                mostrarError(response.message || 'Error al sincronizar stock');
            }
        },
        error: function(xhr) {
            let mensajeError = 'Error al sincronizar stock';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                mensajeError = xhr.responseJSON.message;
            }
            mostrarError(mensajeError);
        }
    });
}

// Función para mostrar que la sincronización está completa
function sincronizacionCompletada() {
    sincronizacionEnProceso = false;
    stockSincronizado = false; // Reset para próxima sincronización
    
    // Actualizar barra de progreso al 100%
    $('#barraProgreso').removeClass('progress-bar-animated').css('width', '100%');
    $('#textoProgreso').text('100%');
    
    // Mostrar resumen
    $('#sincronizacionProgreso').hide();
    $('#sincronizacionCompletada').show();
    
    $('#resumenProcesados').text(estadisticasTotales.procesados.toLocaleString());
    $('#resumenCreados').text(estadisticasTotales.creados.toLocaleString());
    $('#resumenActualizados').text(estadisticasTotales.actualizados.toLocaleString());
    
    // Habilitar botón de cerrar
    $('#btnCerrarModal').show();
    $('#btnCerrarModalFooter').prop('disabled', false);
    
    // Recargar la página después de 3 segundos para mostrar datos actualizados
    setTimeout(() => {
        window.location.reload();
    }, 3000);
}

// Función para mostrar errores
function mostrarError(mensaje) {
    sincronizacionEnProceso = false;
    
    $('#sincronizacionProgreso').hide();
    $('#sincronizacionIniciando').hide();
    $('#sincronizacionError').show();
    $('#mensajeError').text(mensaje);
    
    // Habilitar botón de cerrar
    $('#btnCerrarModal').show();
    $('#btnCerrarModalFooter').prop('disabled', false);
}

// Prevenir cierre del modal durante la sincronización
$('#modalSincronizacionProductos').on('hide.bs.modal', function(e) {
    if (sincronizacionEnProceso) {
        e.preventDefault();
        e.stopPropagation();
        alert('La sincronización está en proceso. Por favor, espere a que termine.');
        return false;
    }
});

// Limpiar al cerrar el modal
$('#modalSincronizacionProductos').on('hidden.bs.modal', function() {
    // Reiniciar estado si se cerró sin completar
    if (sincronizacionEnProceso) {
        sincronizacionEnProceso = false;
    }
});
</script>
@endpush
