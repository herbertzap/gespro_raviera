@extends('layouts.app', ['pageSlug' => 'cotizaciones'])

@section('title', 'Ver ' . ($cotizacion->tipo_documento === 'nota_venta' ? 'Nota de Venta' : 'Cotización'))

@section('content')
<div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header {{ $cotizacion->tipo_documento === 'nota_venta' ? 'card-header-danger' : 'card-header-info' }}">
                        <div class="row">
                            <div class="col-md-8">
                                <h4 class="card-title">
                                    <i class="material-icons">visibility</i>
                                    Ver {{ $cotizacion->tipo_documento === 'nota_venta' ? 'Nota de Venta' : 'Cotización' }} #{{ $cotizacion->id }}
                                </h4>
                                <p class="card-category">Visualizar {{ $cotizacion->tipo_documento === 'nota_venta' ? 'nota de venta' : 'cotización' }} (solo lectura)</p>
                            </div>
                            <div class="col-md-4 text-right">
                                <a href="{{ route('cotizaciones.index') }}" class="btn btn-secondary">
                                    <i class="material-icons">arrow_back</i> Volver
                                </a>
                                @if($cotizacion->tipo_documento === 'cotizacion')
                                <button type="button" class="btn btn-warning ml-2" onclick="convertirANotaVenta({{ $cotizacion->id }})">
                                    <i class="material-icons">transform</i> Convertir a NVV
                                </button>
                                @endif
                                <button type="button" class="btn btn-success ml-2" onclick="descargarPDF({{ $cotizacion->id }})" style="display: inline-block !important;">
                                    <i class="material-icons">picture_as_pdf</i> Descargar PDF
                                </button>
                                <a href="{{ route('aprobaciones.historial', $cotizacion->id) }}" class="btn btn-info ml-2">
                                    <i class="material-icons">history</i> Historial
                                </a>
                            </div>
                        </div>
                    </div>
                <div class="card-body">
                    <!-- Información del Cliente -->
                    @if($cliente)
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <div class="card card-header-success">
                                <div class="card-header">
                                    <h4 class="card-title">
                                        <i class="material-icons">person</i>
                                        Información del Cliente
                                    </h4>
                                    <p class="card-category">Datos del cliente seleccionado</p>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label class="bmd-label-floating">RUT/Código Cliente</label>
                                                <input type="text" class="form-control" value="{{ $cliente->codigo_cliente ?? '' }}" readonly>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="bmd-label-floating">Nombre/Razón Social</label>
                                                <input type="text" class="form-control" value="{{ $cliente->nombre_cliente ?? '' }}" readonly>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label class="bmd-label-floating">Estado</label>
                                                <input type="text" class="form-control" value="{{ $cliente->bloqueado ? 'BLOQUEADO' : 'ACTIVO' }}" readonly style="color: {{ $cliente->bloqueado ? 'red' : 'green' }}; font-weight: bold;">
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Campos ocultos para mantener la funcionalidad -->
                                    <input type="hidden" name="cliente_telefono" value="{{ $cliente->telefono ?? '' }}">
                                    <input type="hidden" name="cliente_email" value="{{ $cliente->email ?? '' }}">
                                    <input type="hidden" name="cliente_lista_precios" value="{{ $cliente->lista_precios_nombre ?? 'Lista General' }}">
                                    <input type="hidden" name="cliente_direccion" value="{{ $cliente->direccion ?? '' }}">
                                    <input type="hidden" name="cliente_region" value="{{ $cliente->region ?? '' }}">
                                    <input type="hidden" name="cliente_comuna" value="{{ $cliente->comuna ?? '' }}">
                                </div>
                            </div>
                        </div>
                    </div>


                    @endif

                    <!-- Formulario de Cotización -->
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header card-header-primary">
                                    <h4 class="card-title">
                                        <i class="material-icons">shopping_cart</i>
                                        Detalle de la Nota de Venta
                                    </h4>
                                    <p class="card-category">Productos de la nota de venta (solo lectura)</p>
                                </div>
                                <div class="card-body">
                                    <!-- Buscador de Productos (OCULTO en modo vista) -->
                                    <!-- En modo vista solo lectura, no se pueden agregar productos -->

                                    <!-- Tabla de Productos de la Cotización -->
                                    <div class="table-responsive">
                                        <table class="table" id="tablaProductos">
                                            <thead class="text-primary">
                                                <tr>
                                                    <th>Código</th>
                                                    <th>Producto</th>
                                                    <th>Cantidad</th>
                                                    <th>Precio Unit.</th>
                                                    <th>Descuento (%)</th>
                                                    <th>Descuento ($)</th>
                                                    <th>Subtotal</th>
                                                    <th>IVA (19%)</th>
                                                    <th>Total</th>
                                                    <th>Stock</th>
                                                </tr>
                                            </thead>
                                            <tbody id="productosCotizacion">
                                                @foreach($cotizacion->productos as $producto)
                                                @php
                                                    $descuentoPorcentaje = $producto->descuento_porcentaje ?? 0;
                                                    $descuentoValor = $producto->descuento_valor ?? 0;
                                                    $subtotalConDescuento = $producto->subtotal_con_descuento ?? 0;
                                                    $iva = $producto->iva_valor ?? 0;
                                                    $total = $producto->total_producto ?? 0;
                                                @endphp
                                                <tr>
                                                    <td>{{ $producto->codigo_producto ?? '' }}</td>
                                                    <td>{{ $producto->nombre_producto ?? '' }}</td>
                                                    <td>{{ $producto->cantidad ?? 0 }}</td>
                                                    <td>${{ number_format($producto->precio_unitario ?? 0, 0, ',', '.') }}</td>
                                                    <td>{{ number_format($descuentoPorcentaje, 2) }}%</td>
                                                    <td>${{ number_format($descuentoValor, 0, ',', '.') }}</td>
                                                    <td>${{ number_format($subtotalConDescuento, 0, ',', '.') }}</td>
                                                    <td>${{ number_format($iva, 0, ',', '.') }}</td>
                                                    <td>${{ number_format($total, 0, ',', '.') }}</td>
                                                    <td>
                                                        @if($producto->stock_suficiente ?? true)
                                                            <span class="badge badge-success">Suficiente</span>
                                                        @else
                                                            <span class="badge badge-warning">Insuficiente</span>
                                                            <br><small class="text-muted">Nota pendiente de stock</small>
                                                        @endif
                                                    </td>
                                                </tr>
                                                @endforeach
                                            </tbody>
                                            <tfoot>
                                                <tr class="font-weight-bold">
                                                    <td colspan="6" class="text-right">TOTALES:</td>
                                                    <td id="totalSubtotal">$0</td>
                                                    <td id="totalIva">$0</td>
                                                    <td id="totalGeneral">$0</td>
                                                    <td></td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>

                                    <!-- Totales -->
                                    <script>
                                        // Calcular totales automáticamente
                                        document.addEventListener('DOMContentLoaded', function() {
                                            let subtotal = 0;
                                            let descuentoTotal = 0;
                                            let subtotalNeto = 0;
                                            let ivaTotal = 0;
                                            let totalGeneral = 0;
                                            
                                            @foreach($cotizacion->productos as $producto)
                                                @php
                                                    $descuentoValor = $producto['descuento_valor'] ?? 0;
                                                    $subtotalConDescuento = $producto['subtotal'] - $descuentoValor;
                                                    $iva = $subtotalConDescuento * 0.19;
                                                    $total = $subtotalConDescuento + $iva;
                                                @endphp
                                                subtotal += {{ $producto['subtotal'] }};
                                                descuentoTotal += {{ $descuentoValor }};
                                                subtotalNeto += {{ $subtotalConDescuento }};
                                                ivaTotal += {{ $iva }};
                                                totalGeneral += {{ $total }};
                                            @endforeach
                                            
                                            document.getElementById('subtotal').textContent = '$' + Math.round(subtotal).toLocaleString('es-CL');
                                            document.getElementById('descuento').textContent = '$' + Math.round(descuentoTotal).toLocaleString('es-CL');
                                            document.getElementById('subtotalNeto').textContent = '$' + Math.round(subtotalNeto).toLocaleString('es-CL');
                                            document.getElementById('iva').textContent = '$' + Math.round(ivaTotal).toLocaleString('es-CL');
                                            document.getElementById('total').textContent = '$' + Math.round(totalGeneral).toLocaleString('es-CL');
                                            
                                            // Actualizar totales de la tabla
                                            document.getElementById('totalSubtotal').textContent = '$' + Math.round(subtotalNeto).toLocaleString('es-CL');
                                            document.getElementById('totalIva').textContent = '$' + Math.round(ivaTotal).toLocaleString('es-CL');
                                            document.getElementById('totalGeneral').textContent = '$' + Math.round(totalGeneral).toLocaleString('es-CL');
                                        });
                                    </script>
                                    <div class="row mt-4">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="bmd-label-floating">Observaciones</label>
                                                <textarea class="form-control" id="observaciones" rows="3" placeholder="Observaciones adicionales..." readonly>{{ $cotizacion->observaciones ?? '' }}</textarea>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="card card-header-info">
                                                <div class="card-body">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <h5>Subtotal:</h5>
                                                        </div>
                                                        <div class="col-md-6 text-right">
                                                            <h5 id="subtotal">$0</h5>
                                                        </div>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <h5>Descuento:</h5>
                                                        </div>
                                                        <div class="col-md-6 text-right">
                                                            <h5 id="descuento">$0</h5>
                                                        </div>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <h5>Subtotal Neto:</h5>
                                                        </div>
                                                        <div class="col-md-6 text-right">
                                                            <h5 id="subtotalNeto">$0</h5>
                                                        </div>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <h5>IVA (19%):</h5>
                                                        </div>
                                                        <div class="col-md-6 text-right">
                                                            <h5 id="iva">$0</h5>
                                                        </div>
                                                    </div>
                                                    <hr>
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <h4>Total:</h4>
                                                        </div>
                                                        <div class="col-md-6 text-right">
                                                            <h4 id="total">$0</h4>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Botones de Acción -->
                                    <div class="row mt-4">
                                        <div class="col-md-12 text-center">
                                            <a href="{{ route('cotizacion.editar', $cotizacion->id) }}" class="btn btn-warning btn-lg">
                                                <i class="material-icons">edit</i> Editar
                                            </a>
                                            <a href="{{ route('cotizaciones.index') }}" class="btn btn-secondary btn-lg">
                                                <i class="material-icons">arrow_back</i> Volver
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para mostrar detalles del producto -->
<div class="modal fade" id="modalProducto" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalles del Producto</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body" id="modalProductoBody">
                <!-- Contenido del modal -->
            </div>
        </div>
    </div>
</div>

@endsection

<script>
// Versión: {{ time() }} - Forzar recarga del cache
// Variables globales
let productosCotizacion = [];
let clienteData = @json($cliente ?? null);
let searchTimeout = null;
let searchCache = new Map();
let lastSearchTerm = '';

console.log('🔍 Script de cotizaciones cargándose...');
console.log('🔍 Cliente data:', clienteData);

// Función para buscar productos con AJAX optimizada
function buscarProductosAjax() {
    const busqueda = document.getElementById('buscarProducto').value.trim().toUpperCase();
    
    // Validar mínimo 3 caracteres
    if (busqueda.length < 3) {
        if (busqueda.length > 0) {
            document.getElementById('contenidoResultados').innerHTML = '<div class="alert alert-warning"><i class="material-icons">info</i> Escriba al menos 3 caracteres para buscar</div>';
            document.getElementById('resultadosBusqueda').style.display = 'block';
        } else {
            document.getElementById('resultadosBusqueda').style.display = 'none';
        }
        return;
    }

    // Obtener lista de precios del cliente
            const listaPrecios = clienteData ? (clienteData.lista_precios_codigo || '01P') : '01P';


    document.getElementById('contenidoResultados').innerHTML = '<div class="alert alert-info"><i class="material-icons">search</i> Buscando productos...</div>';
    document.getElementById('resultadosBusqueda').style.display = 'block';

    // Usar la ruta AJAX
    const url = '/cotizacion/buscar-productos?busqueda=' + encodeURIComponent(busqueda) + '&lista_precios=' + encodeURIComponent(listaPrecios);

    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                mostrarResultadosProductosAjax(data.data);
            } else {
                if (data.error_type === 'no_lista_precios') {
                    mostrarModalListaPrecios();
                } else {
                    document.getElementById('contenidoResultados').innerHTML = '<div class="alert alert-danger"><i class="material-icons">error</i> Error: ' + data.message + '</div>';
                }
            }
        })
        .catch(error => {
            hideLoadingIndicator();
            document.getElementById('contenidoResultados').innerHTML = '<div class="alert alert-danger"><i class="material-icons">error</i> Error al buscar productos</div>';
        });
}

// Función para búsqueda automática simplificada
function buscarProductosAuto() {
    const busqueda = document.getElementById('buscarProducto').value.trim().toUpperCase();
    
    // Limpiar timeout anterior
    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }
    
    // Esperar 300ms antes de buscar (debounce más rápido)
    searchTimeout = setTimeout(() => {
        if (busqueda.length >= 3) {
            buscarProductosAjax();
        }
    }, 300);
}



// Función para mostrar resultados de productos con AJAX mejorada
function mostrarResultadosProductosAjax(productos) {
    console.log('Mostrando resultados AJAX:', productos);
    
    if (productos.length === 0) {
        document.getElementById('contenidoResultados').innerHTML = '<div class="alert alert-info"><i class="material-icons">search_off</i> No se encontraron productos</div>';
        document.getElementById('resultadosBusqueda').style.display = 'block';
        return;
    }

    let contenido = '<div class="table-responsive"><table class="table table-striped table-hover">';
    contenido += '<thead class="thead"><tr><th>Código</th><th>Producto</th><th>Stock</th><th>Precio</th><th>Acción</th></tr></thead><tbody>';
    
    productos.forEach(producto => {
        // Usar información de stock mejorada
        const stockReal = producto.STOCK_DISPONIBLE_REAL !== undefined ? producto.STOCK_DISPONIBLE_REAL : producto.STOCK_DISPONIBLE;
        const stockClass = producto.CLASE_STOCK || (stockReal > 0 ? 'text-success' : 'text-danger');
        const stockText = producto.ESTADO_STOCK || (stockReal > 0 ? 'Disponible' : 'Sin stock');
        
        contenido += `
            <tr>
                <td><strong>${producto.CODIGO_PRODUCTO || ''}</strong></td>
                <td>${producto.NOMBRE_PRODUCTO || ''}</td>
                <td class="${stockClass}">
                    <i class="material-icons">${stockReal > 0 ? 'check_circle' : 'warning'}</i>
                    ${stockReal || 0} ${producto.UNIDAD_MEDIDA || 'UN'}
                    ${producto.STOCK_COMPROMETIDO > 0 ? `<br><small class="text-muted">Comprometido: ${producto.STOCK_COMPROMETIDO}</small>` : ''}
                    ${stockReal <= 0 ? '<br><small class="text-warning"><i class="material-icons">info</i> Sin stock - Nota pendiente</small>' : ''}
                </td>
                <td><strong>$${Math.round(producto.PRECIO_UD1 || 0).toLocaleString()}</strong></td>
                <td>
                    <button class="btn btn-sm btn-primary" onclick="agregarProductoDesdePHP('${producto.CODIGO_PRODUCTO}', '${producto.NOMBRE_PRODUCTO.replace(/'/g, "\\'")}', ${producto.PRECIO_UD1 || 0}, ${stockReal || 0}, '${producto.UNIDAD_MEDIDA || 'UN'}')">
                        <i class="material-icons">add_shopping_cart</i> Agregar
                    </button>
                </td>
            </tr>
        `;
    });
    
    contenido += '</tbody></table></div>';
    
    // Agregar información de búsqueda rápida
    const searchTime = Date.now() - window.lastSearchStart;
    contenido += `<div class="alert alert-success mt-2">
        <i class="material-icons">speed</i> 
        Búsqueda en ${searchTime}ms - ${productos.length} productos
    </div>`;
    
    document.getElementById('contenidoResultados').innerHTML = contenido;
    document.getElementById('tituloResultados').textContent = `Productos Encontrados (${productos.length})`;
    document.getElementById('resultadosBusqueda').style.display = 'block';
}

// Función para limpiar búsqueda
function limpiarBusqueda() {
    document.getElementById('buscarProducto').value = '';
    document.getElementById('resultadosBusqueda').style.display = 'none';
    lastSearchTerm = '';
    
    // Limpiar timeout si existe
    if (searchTimeout) {
        clearTimeout(searchTimeout);
        searchTimeout = null;
    }
    
    hideLoadingIndicator();
}

// Función para buscar productos (mantener para compatibilidad)
function buscarProductos() {
    buscarProductosAjax();
}

// Función de prueba para verificar que el JavaScript se carga
function testJavaScript() {
    console.log('JavaScript cargado correctamente');
    alert('JavaScript funcionando');
}

// Función para mostrar modal de lista de precios
function mostrarModalListaPrecios() {
    const clienteInfo = clienteData ? `Código: ${clienteData.codigo}<br>Nombre: ${clienteData.nombre}` : '';
    
    const modalHtml = `
        <div class="modal fade" id="modalListaPrecios" tabindex="-1" role="dialog" aria-labelledby="modalListaPreciosLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title" id="modalListaPreciosLabel">
                            <i class="material-icons">warning</i>
                            Cliente Sin Lista de Precios
                        </h5>
                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-danger">
                            <i class="material-icons">error</i>
                            <strong>No es posible generar notas de venta para este cliente.</strong>
                        </div>
                        <p>El cliente no tiene una lista de precios asignada, por lo que no se pueden mostrar productos con precios.</p>
                        <div class="card">
                            <div class="card-body">
                                <h6>Información del Cliente:</h6>
                                <div class="text-muted">
                                    ${clienteInfo}
                                </div>
                            </div>
                        </div>
                        <p class="mt-3">
                            <strong>Solución:</strong> Contacte al administrador para asignar una lista de precios al cliente.
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                        <button type="button" class="btn btn-primary" onclick="window.location.href='/cotizaciones'">
                            <i class="material-icons">arrow_back</i>
                            Volver a Cotizaciones
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Remover modal existente si hay uno
    const modalExistente = document.getElementById('modalListaPrecios');
    if (modalExistente) {
        modalExistente.remove();
    }
    
    // Agregar modal al body
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    // Mostrar modal
    $('#modalListaPrecios').modal('show');
}

// Función para mostrar resultados de productos (ya no se usa - ahora se hace con PHP)
function mostrarResultadosProductos(productos) {
    console.log('🔍 Mostrar resultados ahora se hace con PHP directamente');
}

// Función para agregar producto a la cotización desde PHP
function agregarProductoDesdePHP(codigo, nombre, precio, stock, unidad) {
    console.log('Agregando producto desde PHP:', { codigo, nombre, precio, stock, unidad });
    
    // Verificar si el producto ya está en la cotización
    const productoExistente = productosCotizacion.find(p => p.codigo === codigo);
    
    if (productoExistente) {
        // Incrementar cantidad según la unidad
        const incremento = obtenerIncrementoPorUnidad(unidad);
        productoExistente.cantidad += incremento;
        productoExistente.subtotal = productoExistente.cantidad * productoExistente.precio;
    } else {
        // Agregar nuevo producto con cantidad inicial según unidad
        const cantidadInicial = obtenerIncrementoPorUnidad(unidad);
        productosCotizacion.push({
            codigo: codigo,
            nombre: nombre,
            cantidad: cantidadInicial,
            precio: parseFloat(precio),
            subtotal: parseFloat(precio) * cantidadInicial,
            stock: parseFloat(stock),
            unidad: unidad
        });
    }
    
    actualizarTablaProductos();
    calcularTotales();
    
    // Limpiar búsqueda después de agregar el producto
    limpiarBusqueda();
    
    // Mostrar mensaje de confirmación con información de stock
    const stockInfo = parseFloat(stock) <= 0 ? ' (Sin stock - Nota pendiente)' : '';
    alert('Producto agregado: ' + nombre + ' (Cantidad: ' + obtenerIncrementoPorUnidad(unidad) + ' ' + unidad + ')' + stockInfo);
}

// Función para obtener el incremento de cantidad según la unidad
function obtenerIncrementoPorUnidad(unidad) {
    // Por defecto, incrementar de 1 en 1 para todas las unidades
    return 1;
}

// Función para agregar producto a la cotización (mantener para compatibilidad)
function agregarProducto(codigo, nombre, precio, stock, unidad) {
    agregarProductoDesdePHP(codigo, nombre, precio, stock, unidad);
}

// Función para actualizar la tabla de productos
function actualizarTablaProductos() {
    const tbody = document.getElementById('productosCotizacion');
    tbody.innerHTML = '';
    
    productosCotizacion.forEach((producto, index) => {
        let stockClass, stockText;
        
        if (producto.stock <= 0) {
            stockClass = 'text-warning';
            stockText = 'Sin stock';
        } else if (producto.cantidad <= producto.stock) {
            stockClass = 'text-success';
            stockText = 'Suficiente';
        } else {
            stockClass = 'text-danger';
            stockText = 'Insuficiente';
        }
        
        // Determinar el step según la unidad
        const step = obtenerStepPorUnidad(producto.unidad);
        
        const descuentoValor = producto.descuento_valor || 0;
        const subtotalConDescuento = producto.subtotal - descuentoValor;
        const iva = subtotalConDescuento * 0.19;
        const total = subtotalConDescuento + iva;
        const descuentoPorcentaje = producto.descuento_porcentaje || 0;
        
        const row = `
            <tr>
                <td>${producto.codigo}</td>
                <td>${producto.nombre}</td>
                <td>
                    <input type="number" class="form-control" value="${producto.cantidad}" step="${step}" 
                           onchange="actualizarCantidad(${index}, this.value)" style="width: 80px;">
                    <small class="text-muted">${producto.unidad}</small>
                </td>
                <td>$${Math.round(producto.precio).toLocaleString()}</td>
                <td>${descuentoPorcentaje.toFixed(2)}%</td>
                <td>$${Math.round(descuentoValor).toLocaleString()}</td>
                <td>$${Math.round(subtotalConDescuento).toLocaleString()}</td>
                <td>$${Math.round(iva).toLocaleString()}</td>
                <td>$${Math.round(total).toLocaleString()}</td>
                <td class="${stockClass}">
                    ${stockText}
                    ${producto.stock > 0 ? `<br><small>Disponible: ${producto.stock} ${producto.unidad}</small>` : '<br><small>Nota pendiente de stock</small>'}
                </td>
                <td>
                    <button class="btn btn-sm btn-danger" onclick="eliminarProducto(${index})">
                        <i class="material-icons">delete</i>
                    </button>
                </td>
            </tr>
        `;
        tbody.innerHTML += row;
    });
}

// Función para obtener el step del input según la unidad
function obtenerStepPorUnidad(unidad) {
    // Por defecto, step de 1 para todas las unidades
    return 1;
}

// Función para actualizar cantidad
function actualizarCantidad(index, nuevaCantidad) {
    const cantidad = parseFloat(nuevaCantidad);
    if (cantidad > 0) {
        productosCotizacion[index].cantidad = cantidad;
        productosCotizacion[index].subtotal = cantidad * productosCotizacion[index].precio;
        actualizarTablaProductos();
        calcularTotales();
    }
}

// Función para eliminar producto
function eliminarProducto(index) {
    productosCotizacion.splice(index, 1);
    actualizarTablaProductos();
    calcularTotales();
}

// Función para calcular totales
function calcularTotales() {
    let subtotal = 0;
    let descuentoTotal = 0;
    let subtotalNeto = 0;
    let ivaTotal = 0;
    let totalGeneral = 0;
    
    productosCotizacion.forEach(producto => {
        const descuentoValor = producto.descuento_valor || 0;
        const subtotalConDescuento = producto.subtotal - descuentoValor;
        const iva = subtotalConDescuento * 0.19;
        const total = subtotalConDescuento + iva;
        
        subtotal += producto.subtotal;
        descuentoTotal += descuentoValor;
        subtotalNeto += subtotalConDescuento;
        ivaTotal += iva;
        totalGeneral += total;
    });
    
    document.getElementById('subtotal').textContent = '$' + Math.round(subtotal).toLocaleString();
    document.getElementById('descuento').textContent = '$' + Math.round(descuentoTotal).toLocaleString();
    document.getElementById('subtotalNeto').textContent = '$' + Math.round(subtotalNeto).toLocaleString();
    document.getElementById('iva').textContent = '$' + Math.round(ivaTotal).toLocaleString();
    document.getElementById('total').textContent = '$' + Math.round(totalGeneral).toLocaleString();
    
    // Actualizar totales de la tabla
    document.getElementById('totalSubtotal').textContent = '$' + Math.round(subtotalNeto).toLocaleString();
    document.getElementById('totalIva').textContent = '$' + Math.round(ivaTotal).toLocaleString();
    document.getElementById('totalGeneral').textContent = '$' + Math.round(totalGeneral).toLocaleString();
}

// Función para limpiar búsqueda
function limpiarBusqueda() {
    document.getElementById('buscarProducto').value = '';
    document.getElementById('resultadosBusqueda').style.display = 'none';
}

// Variable para controlar si ya se está procesando una solicitud
let guardandoNotaVenta = false;

// Función para guardar nota de venta (con validación anti-doble clic)
function guardarNotaVenta() {
    // Verificar si ya se está procesando
    if (guardandoNotaVenta) {
        alert('Ya se está procesando la nota de venta, por favor espere...');
        return;
    }

    if (productosCotizacion.length === 0) {
        alert('Debes agregar al menos un producto a la nota de venta');
        return;
    }

    if (!clienteData) {
        alert('No hay cliente seleccionado');
        return;
    }

    // Marcar como procesando y deshabilitar botón
    guardandoNotaVenta = true;
    const btn = document.getElementById('btnGuardarNotaVenta');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="material-icons">hourglass_empty</i> Guardando...';

    const observaciones = document.getElementById('observaciones').value;
    
    const cotizacionData = {
        cliente_codigo: clienteData.codigo,
        cliente_nombre: clienteData.nombre,
        productos: productosCotizacion,
        observaciones: observaciones,
        _token: document.querySelector('meta[name="csrf-token"]').getAttribute('content')
    };

    fetch('/cotizacion/guardar', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify(cotizacionData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Nota de venta guardada exitosamente');
            window.location.href = '/cotizaciones';
        } else {
            alert('Error: ' + data.message);
            // Restaurar botón en caso de error
            guardandoNotaVenta = false;
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al guardar la nota de venta');
        // Restaurar botón en caso de error
        guardandoNotaVenta = false;
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
}

// Mantener función original para compatibilidad
function guardarCotizacion() {
    guardarNotaVenta();
}

// Configurar input de búsqueda cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    console.log('🔍 DOM cargado, configurando input de búsqueda...');
    
    // Verificar si el cliente tiene lista de precios
    if (clienteData && (!clienteData.lista_precios_codigo || clienteData.lista_precios_codigo === '00' || clienteData.lista_precios_codigo === '0')) {
        console.warn('⚠️ Cliente sin lista de precios asignada, usando lista por defecto');
        // Asignar lista por defecto
        clienteData.lista_precios_codigo = '01P';
        clienteData.lista_precios_nombre = 'Lista Precios 01P';
    }
    
    console.log('🔍 Lista de precios final:', clienteData?.lista_precios_codigo);
    
    // Configurar input de búsqueda
    const buscarInput = document.getElementById('buscarProducto');
    if (buscarInput) {
        console.log('🔍 Configurando input de búsqueda...');
        
        // Convertir a mayúsculas automáticamente y búsqueda automática
        buscarInput.addEventListener('input', function() {
            this.value = this.value.toUpperCase();
            // Búsqueda automática con debounce
            buscarProductosAuto();
        });
        
        // Buscar con Enter
        buscarInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                console.log('🔍 Enter presionado en input de búsqueda');
                buscarProductos();
            }
        });
        
        // Placeholder actualizado
        buscarInput.placeholder = 'Buscar producto por código o nombre (búsqueda automática)...';
    } else {
        console.error('❌ No se encontró el input de búsqueda');
    }
    
    // Inicializar totales
    calcularTotales();
    
    console.log('✅ Configuración completada');
});

// Función para convertir cotización a NVV
function convertirANotaVenta(cotizacionId) {
    if (!confirm('¿Estás seguro de convertir esta cotización a Nota de Venta?\n\nUna vez convertida, entrará al flujo de aprobaciones (Supervisor, Compras, Picking).')) {
        return;
    }
    
    // Abrir modal de conversión
    $('#modalConvertirNVV').data('cotizacion-id', cotizacionId);
    $('#modalConvertirNVV').modal('show');
}

function confirmarConversionNVV() {
    const cotizacionId = $('#modalConvertirNVV').data('cotizacion-id');
    const numeroOrdenCompra = $('#numero_orden_compra_nvv').val();
    const observacionVendedor = $('#observacion_vendedor_nvv').val();
    const solicitarDescuentoExtra = $('#solicitar_descuento_extra_nvv').is(':checked');
    
    $.ajax({
        url: `/cotizacion/convertir-a-nota-venta/${cotizacionId}`,
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        data: {
            numero_orden_compra: numeroOrdenCompra,
            observacion_vendedor: observacionVendedor,
            solicitar_descuento_extra: solicitarDescuentoExtra
        },
        success: function(response) {
            $('#modalConvertirNVV').modal('hide');
            alert('Cotización convertida exitosamente a Nota de Venta');
            location.reload();
        },
        error: function(xhr) {
            const errorMsg = xhr.responseJSON?.message || 'Error al convertir la cotización';
            alert('Error: ' + errorMsg);
        }
    });
}

// Función para descargar PDF
function descargarPDF(cotizacionId) {
    window.open(`/cotizacion/pdf/${cotizacionId}`, '_blank');
}

console.log('🔍 Script cargado completamente');
</script>

<!-- Modal de Conversión a NVV -->
<div class="modal fade" id="modalConvertirNVV" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="material-icons">description</i>
                    Convertir a Nota de Venta
                </h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="material-icons">info</i>
                    <strong>Información:</strong> Una vez convertida, la cotización entrará al flujo de aprobaciones (Supervisor, Compras, Picking).
                </div>
                
                <div class="form-group">
                    <label for="numero_orden_compra_nvv">Número de Orden de Compra</label>
                    <input type="text" 
                           class="form-control" 
                           id="numero_orden_compra_nvv" 
                           maxlength="40"
                           placeholder="Número de orden de compra del cliente (opcional)">
                    <small class="form-text text-muted">
                        <i class="material-icons" style="font-size: 14px; vertical-align: middle;">info</i>
                        Campo opcional - Máximo 40 caracteres
                    </small>
                </div>
                
                <div class="form-group">
                    <label for="observacion_vendedor_nvv">Observación del Vendedor</label>
                    <textarea class="form-control" 
                              id="observacion_vendedor_nvv" 
                              rows="3" 
                              maxlength="250"
                              placeholder="Observación personal del vendedor (opcional)"></textarea>
                    <small class="form-text text-muted">
                        <i class="material-icons" style="font-size: 14px; vertical-align: middle;">info</i>
                        Campo opcional - Máximo 250 caracteres
                    </small>
                </div>
                
                <div class="form-group">
                    <div class="form-check">
                        <label class="form-check-label">
                            <input class="form-check-input" type="checkbox" id="solicitar_descuento_extra_nvv">
                            <span class="form-check-sign"><span class="check"></span></span>
                            <strong>Solicitar descuento extra</strong>
                        </label>
                    </div>
                    <small class="form-text text-muted">
                        Si está marcado, la NVV requerirá aprobación de Supervisor aunque el cliente no tenga problemas de crédito.
                    </small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="confirmarConversionNVV()">
                    <i class="material-icons">check</i> Convertir a NVV
                </button>
            </div>
        </div>
    </div>
</div> 