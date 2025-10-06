@extends('layouts.app')

@section('title', 'Nueva Nota de Venta')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header card-header-warning">
                    <h4 class="card-title">
                        <i class="material-icons">add_shopping_cart</i>
                        Nueva Nota de Venta
                    </h4>
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
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label class="bmd-label-floating">RUT/Código Cliente</label>
                                                <input type="text" class="form-control" value="{{ $cliente->codigo ?? '' }}" readonly>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="bmd-label-floating">Nombre/Razón Social</label>
                                                <input type="text" class="form-control" value="{{ $cliente->nombre }}" readonly>
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

                    <!-- Información de Cheques Protestados -->
                    @if($cliente && !$puedeGenerarNotaVenta)
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <div class="card card-header-danger">
                                <div class="card-header">
                                    <h4 class="card-title">
                                        <i class="material-icons">warning</i>
                                        Información de Crédito - Cheques Protestados
                                    </h4>
                                </div>
                                <div class="card-body">
                                    <div class="alert alert-danger" role="alert">
                                        <h5 class="alert-heading">
                                            <i class="material-icons">block</i>
                                            Cliente con Cheques Protestados
                                        </h5>
                                        <p class="mb-0">
                                            <strong>Este cliente tiene cheques protestados y no puede generar Notas de Venta.</strong>
                                        </p>
                                        <hr>
                                        <p class="mb-0">
                                            <strong>Motivo:</strong> {{ $motivoRechazo ?? 'Cliente con cheques protestados' }}
                                        </p>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-12">
                                            <button type="button" class="btn btn-info btn-sm" onclick="mostrarDetalleChequesProtestados()">
                                                <i class="material-icons">info</i> Ver Detalle de Cheques Protestados
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif

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
                                    <p class="card-category">Agregar productos a la nota de venta</p>
                                </div>
                                <div class="card-body">
                                    <!-- Buscador de Productos Mejorado -->
                                    <div class="row mb-3">
                                        <div class="col-md-8">
                                            <div class="input-group">
                                                <input type="text" id="buscarProducto" class="form-control" placeholder="Buscar producto por código o nombre (escriba para buscar automáticamente)..." minlength="3">
                                                <div class="input-group-append">
                                                    <button class="btn btn-primary" type="button" onclick="buscarProductosAjax()">
                                                        <i class="material-icons">search</i> Buscar
                                                    </button>
                                                </div>
                                            </div>
                                            <small class="form-text text-muted">
                                                <i class="material-icons">info</i> 
                                                Búsqueda automática activada. Escriba al menos 3 caracteres para buscar.
                                            </small>
                                        </div>
                                        <div class="col-md-4">
                                            <button type="button" class="btn btn-success btn-block" onclick="limpiarBusqueda()">
                                                <i class="material-icons">clear</i> Limpiar
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Resultados de Búsqueda -->
                                    <div id="resultadosBusqueda" style="display: none;">
                                        <div class="card">
                                            <div class="card-header">
                                                <h5 id="tituloResultados">Productos Encontrados</h5>
                                            </div>
                                            <div class="card-body" id="contenidoResultados">
                                                <!-- Los resultados se cargarán aquí -->
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Tabla de Productos de la Cotización -->
                                    <div class="table-responsive">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <h6 class="mb-0">Productos en Cotización</h6>
                                            <span class="badge badge-info" id="contadorProductos">0/24 productos</span>
                                        </div>
                                        <table class="table" id="tablaProductos">
                                            <thead class="text-primary">
                                                <tr>
                                                    <th>Código</th>
                                                    <th>Producto</th>
                                                    <th>Cantidad</th>
                                                    <th>Precio Unit.</th>
                                                    <th>Descuento (%)</th>
                                                    <th>Subtotal</th>
                                                    <th>Stock</th>
                                                    <th>Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody id="productosCotizacion">
                                                <!-- Los productos se agregarán aquí -->
                                            </tbody>
                                        </table>
                                    </div>

                                    <!-- Totales -->
                                    <div class="row mt-4">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="bmd-label-floating">Observaciones</label>
                                                <textarea class="form-control" id="observaciones" rows="3" placeholder="Observaciones adicionales..."></textarea>
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
                                            <button type="button" id="btnGuardarNotaVenta" class="btn btn-success btn-lg" onclick="guardarNotaVenta()" {{ !$puedeGenerarNotaVenta ? 'disabled' : '' }}>
                                                <i class="material-icons">save</i> Guardar Nota de Venta
                                            </button>
                                            <a href="{{ route('cotizaciones.index') }}" class="btn btn-secondary btn-lg">
                                                <i class="material-icons">cancel</i> Cancelar
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

    let contenido = '<div class="table-responsive">';
    contenido += '<div class="mb-3">';
    contenido += '<button class="btn btn-success btn-sm" onclick="agregarProductosSeleccionados()" id="btnAgregarSeleccionados" disabled>';
    contenido += '<i class="material-icons">add_shopping_cart</i> Agregar Seleccionados (<span id="contadorSeleccionados">0</span>)';
    contenido += '</button>';
    contenido += '</div>';
    contenido += '<table class="table table-striped table-hover">';
    contenido += '<thead class="thead"><tr><th><input type="checkbox" id="selectAllProductos" onchange="toggleAllProductos()"></th><th>Código</th><th>Producto</th><th>Stock</th><th>Precio</th><th>Acción</th></tr></thead><tbody>';
    
    productos.forEach(producto => {
        // Usar información de stock mejorada
        const stockReal = producto.STOCK_DISPONIBLE_REAL !== undefined ? producto.STOCK_DISPONIBLE_REAL : producto.STOCK_DISPONIBLE;
        const stockClass = producto.CLASE_STOCK || (stockReal > 0 ? 'text-success' : 'text-danger');
        const stockText = producto.ESTADO_STOCK || (stockReal > 0 ? 'Disponible' : 'Sin stock');
        
        // Verificar si el producto se puede agregar (precio válido)
        const precioValido = producto.PRECIO_VALIDO !== undefined ? producto.PRECIO_VALIDO : (producto.PRECIO_UD1 > 0);
        const motivoBloqueo = producto.MOTIVO_BLOQUEO || (precioValido ? null : 'Precio no disponible');
        
        // Determinar clases y estilos según el estado del producto
        const rowClass = !precioValido ? 'table-secondary' : '';
        const checkboxDisabled = !precioValido ? 'disabled' : '';
        const buttonClass = precioValido ? 'btn-primary' : 'btn-secondary';
        const buttonDisabled = !precioValido ? 'disabled' : '';
        const buttonText = precioValido ? 'Agregar' : 'Sin precio';
        const buttonIcon = precioValido ? 'add_shopping_cart' : 'block';
        
        contenido += `
            <tr class="${rowClass}">
                <td><input type="checkbox" class="producto-checkbox" value="${producto.CODIGO_PRODUCTO}" onchange="actualizarContadorSeleccionados()" ${checkboxDisabled}></td>
                <td><strong>${producto.CODIGO_PRODUCTO || ''}</strong></td>
                <td>${producto.NOMBRE_PRODUCTO || ''}</td>
                <td class="${stockClass}">
                    <i class="material-icons">${stockReal > 0 ? 'check_circle' : 'warning'}</i>
                    ${stockReal || 0} ${producto.UNIDAD_MEDIDA || 'UN'}
                    ${producto.STOCK_COMPROMETIDO > 0 ? `<br><small class="text-muted">Comprometido: ${producto.STOCK_COMPROMETIDO}</small>` : ''}
                    ${stockReal <= 0 ? '<br><small class="text-warning"><i class="material-icons">info</i> Sin stock - Nota pendiente</small>' : ''}
                </td>
                <td>
                    <strong class="${!precioValido ? 'text-muted' : ''}" data-precio="${producto.PRECIO_UD1 || 0}">$${Math.round(producto.PRECIO_UD1 || 0).toLocaleString()}</strong>
                    ${!precioValido ? '<br><small class="text-danger"><i class="material-icons">warning</i> Precio no disponible</small>' : ''}
                </td>
                <td>
                    <button class="btn btn-sm ${buttonClass}" onclick="${precioValido ? `agregarProductoDesdePHP('${producto.CODIGO_PRODUCTO}', '${producto.NOMBRE_PRODUCTO.replace(/'/g, "\\'")}', ${producto.PRECIO_UD1 || 0}, ${stockReal || 0}, '${producto.UNIDAD_MEDIDA || 'UN'}', ${producto.DESCUENTO_MAXIMO || 0}, ${producto.MULTIPLO_VENTA || 1})` : 'alert(\'Este producto no tiene precio disponible\')'}" ${buttonDisabled} title="${motivoBloqueo || ''}">
                        <i class="material-icons">${buttonIcon}</i> ${buttonText}
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
function agregarProductoDesdePHP(codigo, nombre, precio, stock, unidad, descuentoMaximo = 0, multiplo = 1) {
    console.log('Agregando producto desde PHP:', { codigo, nombre, precio, stock, unidad, descuentoMaximo, multiplo });
    
    // Verificar si el producto ya está en la cotización
    const productoExistente = productosCotizacion.find(p => p.codigo === codigo);
    
    if (productoExistente) {
        // Incrementar cantidad según el múltiplo del producto
        const incremento = Math.max(multiplo, obtenerIncrementoPorUnidad(unidad));
        productoExistente.cantidad += incremento;
        productoExistente.multiplo = multiplo; // Actualizar múltiplo
        actualizarSubtotal(productosCotizacion.indexOf(productoExistente));
    } else {
        // Validar límite máximo de productos diferentes (24)
        if (productosCotizacion.length >= 24) {
            alert('No se pueden agregar más de 24 productos diferentes a la cotización.\n\nProductos actuales: ' + productosCotizacion.length + '/24');
            return;
        }
        
        // Agregar nuevo producto con cantidad inicial según múltiplo
        const cantidadInicial = Math.max(multiplo, obtenerIncrementoPorUnidad(unidad));
        productosCotizacion.push({
            codigo: codigo,
            nombre: nombre,
            cantidad: cantidadInicial,
            precio: parseFloat(precio),
            descuento: 0,
            descuentoMaximo: parseFloat(descuentoMaximo) || 0,
            subtotal: parseFloat(precio) * cantidadInicial,
            stock: parseFloat(stock),
            unidad: unidad,
            multiplo: multiplo // Guardar múltiplo para validaciones posteriores
        });
    }
    
    actualizarTablaProductos();
    calcularTotales();
    
    // Limpiar búsqueda después de agregar el producto
    limpiarBusqueda();
    
    // Mostrar mensaje de confirmación con información de stock y múltiplo
    const stockInfo = parseFloat(stock) <= 0 ? ' (Sin stock - Nota pendiente)' : '';
    const multiploInfo = multiplo > 1 ? ` - Múltiplo: ${multiplo}` : '';
    const productosInfo = productosCotizacion.length > 1 ? `\n\nProductos en cotización: ${productosCotizacion.length}/24` : '';
    alert('Producto agregado: ' + nombre + ' (Cantidad: ' + Math.max(multiplo, obtenerIncrementoPorUnidad(unidad)) + ' ' + unidad + ')' + multiploInfo + stockInfo + productosInfo);
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
    
    // Actualizar contador de productos
    const contador = document.getElementById('contadorProductos');
    if (contador) {
        const cantidad = productosCotizacion.length;
        const maximo = 24;
        contador.textContent = `${cantidad}/${maximo} productos`;
        
        // Cambiar color según el límite
        contador.className = 'badge';
        if (cantidad >= maximo) {
            contador.classList.add('badge-danger');
        } else if (cantidad >= maximo * 0.8) {
            contador.classList.add('badge-warning');
        } else {
            contador.classList.add('badge-info');
        }
    }
    
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
                <td>
                    <input type="number" class="form-control descuento-input" value="${producto.descuento || 0}" 
                           min="0" max="${producto.descuentoMaximo || 0}" step="0.01"
                           onchange="actualizarDescuento(${index}, this.value)" style="width: 80px;">
                    <small class="text-muted">Máx: ${producto.descuentoMaximo || 0}%</small>
                </td>
                <td>$${Math.round(producto.subtotal).toLocaleString()}</td>
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
    const producto = productosCotizacion[index];
    const multiplo = producto.multiplo || 1;
    
    if (cantidad > 0) {
        // Validar que la cantidad sea múltiplo del mínimo de venta
        if (multiplo > 1 && cantidad % multiplo !== 0) {
            const cantidadAjustada = Math.ceil(cantidad / multiplo) * multiplo;
            alert(`Este producto se vende en múltiplos de ${multiplo} unidades.\nLa cantidad se ajustará a ${cantidadAjustada} unidades.`);
            producto.cantidad = cantidadAjustada;
        } else {
            producto.cantidad = cantidad;
        }
        
        actualizarSubtotal(index);
        actualizarTablaProductos();
        calcularTotales();
    }
}

// Función para actualizar descuento
function actualizarDescuento(index, nuevoDescuento) {
    const descuento = parseFloat(nuevoDescuento) || 0;
    const descuentoMaximo = productosCotizacion[index].descuentoMaximo || 0;
    
    if (descuento > descuentoMaximo) {
        alert(`El descuento no puede exceder el máximo permitido: ${descuentoMaximo}%`);
        // Restaurar el valor anterior
        productosCotizacion[index].descuento = productosCotizacion[index].descuento || 0;
        actualizarTablaProductos();
        return;
    }
    
    if (descuento < 0) {
        alert('El descuento no puede ser negativo');
        productosCotizacion[index].descuento = 0;
        actualizarTablaProductos();
        return;
    }
    
    productosCotizacion[index].descuento = descuento;
    actualizarSubtotal(index);
    actualizarTablaProductos();
    calcularTotales();
}

// Función para actualizar subtotal considerando descuento
function actualizarSubtotal(index) {
    const producto = productosCotizacion[index];
    const precioBase = producto.precio * producto.cantidad;
    const descuento = (producto.descuento || 0) / 100;
    producto.subtotal = precioBase * (1 - descuento);
}

// Función para eliminar producto
function eliminarProducto(index) {
    productosCotizacion.splice(index, 1);
    actualizarTablaProductos();
    calcularTotales();
}

// Funciones para selección múltiple de productos
function toggleAllProductos() {
    const selectAll = document.getElementById('selectAllProductos');
    const checkboxes = document.querySelectorAll('.producto-checkbox');
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAll.checked;
    });
    
    actualizarContadorSeleccionados();
}

function actualizarContadorSeleccionados() {
    const checkboxes = document.querySelectorAll('.producto-checkbox:checked');
    const contador = document.getElementById('contadorSeleccionados');
    const btnAgregar = document.getElementById('btnAgregarSeleccionados');
    
    if (contador) {
        contador.textContent = checkboxes.length;
    }
    
    if (btnAgregar) {
        btnAgregar.disabled = checkboxes.length === 0;
        btnAgregar.textContent = `Agregar Seleccionados (${checkboxes.length})`;
    }
}

function agregarProductosSeleccionados() {
    const checkboxes = document.querySelectorAll('.producto-checkbox:checked');
    
    if (checkboxes.length === 0) {
        alert('Selecciona al menos un producto para agregar');
        return;
    }
    
    // Obtener datos de los productos seleccionados
    const productosSeleccionados = [];
    const productosSinPrecio = [];
    
    checkboxes.forEach(checkbox => {
        const row = checkbox.closest('tr');
        const codigo = row.cells[1].textContent.trim();
        const nombre = row.cells[2].textContent.trim();
        // Obtener el precio desde el atributo data-precio para evitar problemas de parsing
        const precioElement = row.cells[4].querySelector('[data-precio]');
        const precio = precioElement ? parseFloat(precioElement.getAttribute('data-precio')) : 0;
        console.log('Precio desde data-precio:', precio);
        const stockText = row.cells[3].textContent;
        const stock = parseFloat(stockText.split(' ')[0]) || 0;
        const unidad = stockText.includes('UN') ? 'UN' : 'UN';
        
        // Verificar si el producto tiene precio válido
        const precioValido = precio > 0;
        
        // Obtener descuento máximo desde el botón (necesitamos extraerlo de los datos del producto)
        const descuentoMaximo = 0; // Por ahora 0, se puede mejorar extrayendo de los datos
        
        if (precioValido) {
            productosSeleccionados.push({
                codigo: codigo,
                nombre: nombre,
                precio: precio,
                stock: stock,
                unidad: unidad,
                descuentoMaximo: descuentoMaximo
            });
        } else {
            productosSinPrecio.push(nombre);
        }
    });
    
    // Mostrar advertencia si hay productos sin precio
    if (productosSinPrecio.length > 0) {
        alert(`Los siguientes productos no se pueden agregar porque no tienen precio disponible:\n\n${productosSinPrecio.join('\n')}\n\nSolo se agregarán los productos con precio válido.`);
    }
    
    // Validar límite de productos antes de agregar
    const productosNuevos = productosSeleccionados.filter(p => !productosCotizacion.find(existente => existente.codigo === p.codigo));
    const totalProductos = productosCotizacion.length + productosNuevos.length;
    
    if (totalProductos > 24) {
        const productosActuales = productosCotizacion.length;
        const productosDisponibles = 24 - productosActuales;
        alert(`No se pueden agregar todos los productos seleccionados.\n\nProductos actuales: ${productosActuales}/24\nProductos seleccionados: ${productosNuevos.length}\nProductos disponibles: ${productosDisponibles}\n\nSolo se agregarán los primeros ${productosDisponibles} productos.`);
        
        // Limitar a los productos disponibles
        productosSeleccionados.splice(productosDisponibles);
    }
    
    // Agregar cada producto válido a la cotización
    let productosAgregados = 0;
    productosSeleccionados.forEach(producto => {
        const productoExistente = productosCotizacion.find(p => p.codigo === producto.codigo);
        if (!productoExistente && productosCotizacion.length < 24) {
            agregarProductoDesdePHP(producto.codigo, producto.nombre, producto.precio, producto.stock, producto.unidad, producto.descuentoMaximo);
            productosAgregados++;
        }
    });
    
    // Limpiar selección
    checkboxes.forEach(checkbox => {
        checkbox.checked = false;
    });
    document.getElementById('selectAllProductos').checked = false;
    actualizarContadorSeleccionados();
    
    // Mostrar resumen
    let mensaje = `${productosAgregados} productos agregados a la cotización`;
    if (productosSinPrecio.length > 0) {
        mensaje += `\n\n${productosSinPrecio.length} productos omitidos (sin precio)`;
    }
    if (totalProductos > 24) {
        mensaje += `\n\nAlgunos productos no se agregaron (límite de 24 productos)`;
    }
    alert(mensaje);
}

// Función para calcular totales
function calcularTotales() {
    const subtotal = productosCotizacion.reduce((sum, producto) => sum + producto.subtotal, 0);
    
    // Calcular descuento (5% si supera $400,000)
    let descuento = 0;
    if (subtotal > 400000) {
        descuento = subtotal * 0.05;
    }
    
    const total = subtotal - descuento;
    
    document.getElementById('subtotal').textContent = '$' + Math.round(subtotal).toLocaleString();
    document.getElementById('descuento').textContent = '$' + Math.round(descuento).toLocaleString();
    document.getElementById('total').textContent = '$' + Math.round(total).toLocaleString();
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

// Función para mostrar detalle de cheques protestados
function mostrarDetalleChequesProtestados() {
    if (!clienteData || !clienteData.codigo) {
        alert('No hay información de cliente disponible');
        return;
    }
    
    // Hacer petición para obtener cheques protestados
    fetch('/cotizacion/cheques-protestados', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({
            codigo_cliente: clienteData.codigo
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.data.tiene_cheques_protestados) {
            let mensaje = `CHEQUES PROTESTADOS - ${clienteData.nombre}\n\n`;
            mensaje += `Total de cheques: ${data.data.cantidad}\n`;
            mensaje += `Valor total: $${data.data.valor_total.toLocaleString()}\n\n`;
            mensaje += `DETALLE:\n`;
            mensaje += `${'='.repeat(50)}\n`;
            
            data.data.cheques.forEach((cheque, index) => {
                mensaje += `${index + 1}. Cheque ${cheque.numero_documento}\n`;
                mensaje += `   Cliente: ${cheque.nombre_cliente}\n`;
                mensaje += `   Valor: $${parseFloat(cheque.valor).toLocaleString()}\n`;
                mensaje += `   Fecha Vencimiento: ${cheque.fecha_vencimiento || 'N/A'}\n`;
                mensaje += `   Fecha Emisión: ${cheque.fecha_emision || 'N/A'}\n`;
                mensaje += `   Sucursal: ${cheque.nombre_sucursal}\n`;
                mensaje += `${'='.repeat(50)}\n`;
            });
            
            alert(mensaje);
        } else {
            alert('No se encontraron cheques protestados para este cliente');
        }
    })
    .catch(error => {
        console.error('Error obteniendo cheques protestados:', error);
        alert('Error al obtener información de cheques protestados');
    });
}

console.log('🔍 Script cargado completamente');
</script> 