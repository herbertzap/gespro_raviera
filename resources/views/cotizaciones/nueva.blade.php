@extends('layouts.app')

@section('title', 'Nueva Cotización')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header card-header-warning">
                    <h4 class="card-title">
                        <i class="material-icons">add_shopping_cart</i>
                        Nueva Cotización
                    </h4>
                    <p class="card-category">Crear una nueva cotización para el cliente</p>
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
                                        Información del Cliente - Cotización
                                    </h4>
                                    <p class="card-category">Verificar que los datos del cliente sean correctos</p>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="bmd-label-floating">Código/RUT Cliente</label>
                                                <input type="text" class="form-control" value="{{ $cliente->codigo ?? '' }}" readonly>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="bmd-label-floating">Nombre/Razón Social</label>
                                                <input type="text" class="form-control" value="{{ $cliente->nombre }}" readonly>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="bmd-label-floating">Teléfono</label>
                                                <input type="text" class="form-control" value="{{ $cliente->telefono ?? 'No disponible' }}" readonly>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="bmd-label-floating">Email</label>
                                                <input type="text" class="form-control" value="{{ $cliente->email ?? 'No disponible' }}" readonly>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="bmd-label-floating">Dirección</label>
                                                <input type="text" class="form-control" value="{{ $cliente->direccion ?? 'No disponible' }}" readonly>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="bmd-label-floating">Lista de Precios</label>
                                                <input type="text" class="form-control" value="{{ $cliente->lista_precios_nombre ?? 'Lista General' }}" readonly>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="bmd-label-floating">Estado</label>
                                                <input type="text" class="form-control" value="{{ $cliente->bloqueado ? 'BLOQUEADO' : 'ACTIVO' }}" readonly style="color: {{ $cliente->bloqueado ? 'red' : 'green' }}; font-weight: bold;">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="bmd-label-floating">Código Lista</label>
                                                <input type="text" class="form-control" value="{{ $cliente->lista_precios_codigo ?? '01' }}" readonly>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- INFORMACIÓN COMPLETA DEL CLIENTE PARA DEBUG -->
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <div class="card card-header-info">
                                <div class="card-header">
                                    <h4 class="card-title">
                                        <i class="material-icons">info</i>
                                        Información Completa del Cliente (Debug)
                                    </h4>
                                </div>
                                <div class="card-body">
                                    <p><strong>Datos completos del cliente:</strong></p>
                                    <p>
                                        <strong>Código:</strong> {{ $cliente->codigo ?? 'N/A' }}<br>
                                        <strong>Nombre:</strong> {{ $cliente->nombre ?? 'N/A' }}<br>
                                        <strong>Dirección:</strong> {{ $cliente->direccion ?? 'N/A' }}<br>
                                        <strong>Teléfono:</strong> {{ $cliente->telefono ?? 'N/A' }}<br>
                                        <strong>Email:</strong> {{ $cliente->email ?? 'N/A' }}<br>
                                        <strong>Región:</strong> {{ $cliente->region ?? 'N/A' }}<br>
                                        <strong>Comuna:</strong> {{ $cliente->comuna ?? 'N/A' }}<br>
                                        <strong>Vendedor:</strong> {{ $cliente->vendedor ?? 'N/A' }}<br>
                                        <strong>Lista de Precios Código:</strong> {{ $cliente->lista_precios_codigo ?? 'N/A' }}<br>
                                        <strong>Lista de Precios Nombre:</strong> {{ $cliente->lista_precios_nombre ?? 'N/A' }}<br>
                                        <strong>Bloqueado:</strong> {{ $cliente->bloqueado ? 'SÍ' : 'NO' }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Alertas del Cliente -->
                    @if(!empty($alertas))
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <div class="alert alert-warning">
                                <h5><i class="material-icons">warning</i> Alertas del Cliente</h5>
                                <div class="row">
                                    @foreach($alertas as $alerta)
                                    <div class="col-md-12 mb-2">
                                        <div class="alert alert-{{ $alerta['tipo'] }} mb-0">
                                            <strong>{{ $alerta['titulo'] }}:</strong> {{ $alerta['mensaje'] }}
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif
                    @endif

                    <!-- Búsqueda de Productos -->
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5><i class="material-icons">search</i> Buscar Productos</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <div class="form-group">
                                                <label for="buscarProducto">Buscar por código, nombre o marca:</label>
                                                <input type="text" class="form-control" id="buscarProducto" 
                                                       placeholder="Escribe para buscar productos..." 
                                                       style="text-transform: uppercase;"
                                                       oninput="this.value = this.value.toUpperCase();">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <button type="button" class="btn btn-primary mt-4" onclick="buscarProductos()">
                                                <i class="material-icons">search</i> Buscar
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- Resultados de búsqueda -->
                                    <div id="resultadosBusqueda" class="mt-3" style="display: none;">
                                        <h6>Resultados de búsqueda:</h6>
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Código</th>
                                                        <th>Nombre</th>
                                                        <th>Stock</th>
                                                        <th>Bodega</th>
                                                        <th>Unidad</th>
                                                        <th>Acciones</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="tablaResultados">
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Lista de Productos Seleccionados -->
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5><i class="material-icons">shopping_cart</i> Productos de la Cotización</h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table" id="tablaProductos">
                                            <thead>
                                                <tr>
                                                    <th>Código</th>
                                                    <th>Producto</th>
                                                    <th>Cantidad</th>
                                                    <th>Precio Unit.</th>
                                                    <th>Subtotal</th>
                                                    <th>Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody id="productosSeleccionados">
                                                <!-- Los productos se agregarán dinámicamente aquí -->
                                            </tbody>
                                            <tfoot>
                                                <tr>
                                                    <td colspan="4" class="text-right"><strong>Subtotal:</strong></td>
                                                    <td><strong id="subtotalCotizacion">$0</strong></td>
                                                    <td></td>
                                                </tr>
                                                <tr>
                                                    <td colspan="4" class="text-right">
                                                        <span class="text-success">Descuento (<span id="porcentajeDescuento">0</span>%):</span>
                                                    </td>
                                                    <td><span class="text-success" id="descuentoCotizacion">$0</span></td>
                                                    <td></td>
                                                </tr>
                                                <tr class="table-primary">
                                                    <td colspan="4" class="text-right"><strong>TOTAL:</strong></td>
                                                    <td><strong id="totalCotizacion">$0</strong></td>
                                                    <td></td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                    
                                    <!-- Observaciones -->
                                    <div class="form-group mt-3">
                                        <label for="observaciones">Observaciones:</label>
                                        <textarea class="form-control" id="observaciones" rows="3" 
                                                  placeholder="Observaciones adicionales..."></textarea>
                                    </div>
                                    
                                    <!-- Botones de acción -->
                                    <div class="text-right mt-3">
                                        <button type="button" class="btn btn-secondary" onclick="window.history.back()">
                                            <i class="material-icons">arrow_back</i> Cancelar
                                        </button>
                                        <button type="button" class="btn btn-success" onclick="guardarCotizacion()">
                                            <i class="material-icons">save</i> Guardar Cotización
                                        </button>
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

<!-- Modal para seleccionar precio -->
<div class="modal fade" id="modalPrecios" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Seleccionar Precio</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="listaPrecios">
                    <!-- Los precios se cargarán aquí -->
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('js')
<script>
// Verificar si las variables ya están declaradas para evitar redeclaración
if (typeof productosSeleccionados === 'undefined') {
    var productosSeleccionados = [];
}
if (typeof productoActual === 'undefined') {
    var productoActual = null;
}

// Búsqueda de productos
function buscarProductos() {
    const busqueda = $('#buscarProducto').val();
    if (!busqueda) {
        alert('Por favor ingresa un término de búsqueda');
        return;
    }
    
    $.ajax({
        url: '{{ route("cotizacion.buscar-productos") }}',
        method: 'GET',
        data: { q: busqueda, limit: 10 },
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        xhrFields: {
            withCredentials: true
        },
        success: function(response) {
            if (response.success) {
                mostrarResultados(response.data);
            } else {
                alert('Error al buscar productos: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error AJAX:', xhr.responseText);
            alert('Error al conectar con el servidor: ' + error);
        }
    });
}

function mostrarResultados(productos) {
    const tabla = $('#tablaResultados');
    tabla.empty();
    
    if (productos.length === 0) {
        tabla.html('<tr><td colspan="6" class="text-center">No se encontraron productos</td></tr>');
    } else {
        productos.forEach(function(producto) {
            const stockClass = producto.alerta_stock === 'danger' ? 'text-danger' : 
                              producto.alerta_stock === 'warning' ? 'text-warning' : 'text-success';
            const stockIcon = producto.alerta_stock === 'danger' ? 'error' : 
                             producto.alerta_stock === 'warning' ? 'warning' : 'check_circle';
            
            const fila = `
                <tr>
                    <td><strong>${producto.codigo}</strong></td>
                    <td>${producto.nombre}</td>
                    <td>
                        <span class="${stockClass}">
                            <i class="material-icons" style="font-size: 16px;">${stockIcon}</i>
                            ${producto.stock_disponible}
                        </span>
                        <br>
                        <small class="text-muted">Físico: ${producto.stock_fisico} | Comprometido: ${producto.stock_comprometido}</small>
                    </td>
                    <td>
                        <span class="badge badge-info">
                            <i class="material-icons" style="font-size: 14px;">warehouse</i>
                            ${producto.nombre_bodega || 'N/A'}
                        </span>
                    </td>
                    <td>${producto.unidad || 'N/A'}</td>
                    <td>
                        <button type="button" class="btn btn-sm btn-primary" 
                                onclick="seleccionarProducto('${producto.codigo}', '${producto.nombre}', ${producto.stock_disponible || 0})">
                            <i class="material-icons">add</i> Agregar
                        </button>
                    </td>
                </tr>
            `;
            tabla.append(fila);
        });
    }
    
    $('#resultadosBusqueda').show();
}

function seleccionarProducto(codigo, nombre, stockDisponible) {
    // Validar que stockDisponible sea un número válido
    const stock = parseFloat(stockDisponible) || 0;
    
    productoActual = { codigo: codigo, nombre: nombre, stock: stock };
    
    // Verificar si el stock es 0
    if (stock === 0) {
        mostrarModalStockCero(codigo, nombre);
        return;
    }
    
    // Si hay stock, agregar automáticamente con el precio de la lista del cliente
    agregarProductoAutomatico(codigo, nombre);
}

function agregarProductoAutomatico(codigo, nombre) {
    // Obtener precios del producto automáticamente
    $.ajax({
        url: '{{ route("cotizacion.obtener-precios") }}',
        method: 'GET',
        data: { 
            codigo: codigo,
            cliente: '{{ $cliente->codigo ?? "" }}'
        },
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        xhrFields: {
            withCredentials: true
        },
        success: function(response) {
            if (response.success && response.data.length > 0) {
                // Usar el primer precio disponible (de la lista del cliente)
                const precio = response.data[0].precio_ud1;
                agregarProductoACotizacion(codigo, nombre, precio);
            } else {
                // Si no hay precios, agregar con precio 0
                agregarProductoACotizacion(codigo, nombre, 0);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error AJAX precios:', xhr.responseText);
            // Si hay error, agregar con precio 0
            agregarProductoACotizacion(codigo, nombre, 0);
        }
    });
}

function mostrarModalStockCero(codigo, nombre) {
    const modal = `
        <div class="modal fade" id="modalStockCero" tabindex="-1" role="dialog">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header bg-warning">
                        <h5 class="modal-title">
                            <i class="material-icons">warning</i> Producto sin Stock
                        </h5>
                        <button type="button" class="close" data-dismiss="modal">
                            <span>&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <h6><strong>Producto: ${nombre}</strong></h6>
                            <p class="mb-2">Este producto tiene <strong>stock 0</strong>.</p>
                            <p class="mb-0"><strong>Al agregarlo:</strong></p>
                            <ul class="mb-0">
                                <li>Se notificará al supervisor de la nota de venta</li>
                                <li>Se notificará a bodega</li>
                                <li>La nota de venta quedará con estado <strong>PENDIENTE - STOCK</strong></li>
                            </ul>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">
                            <i class="material-icons">cancel</i> Cancelar
                        </button>
                        <button type="button" class="btn btn-warning" onclick="confirmarAgregarStockCero('${codigo}', '${nombre}')">
                            <i class="material-icons">add</i> Agregar Producto
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Remover modal anterior si existe
    $('#modalStockCero').remove();
    
    // Agregar nuevo modal al body
    $('body').append(modal);
    
    // Mostrar modal
    $('#modalStockCero').modal('show');
}

function confirmarAgregarStockCero(codigo, nombre) {
    $('#modalStockCero').modal('hide');
    
    // Obtener precios del producto automáticamente
    $.ajax({
        url: '{{ route("cotizacion.obtener-precios") }}',
        method: 'GET',
        data: { 
            codigo: codigo,
            cliente: '{{ $cliente->codigo ?? "" }}'
        },
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        xhrFields: {
            withCredentials: true
        },
        success: function(response) {
            if (response.success && response.data.length > 0) {
                // Usar el primer precio disponible (de la lista del cliente)
                const precio = response.data[0].precio_ud1;
                agregarProductoACotizacion(codigo, nombre, precio, true); // Marcar como stock 0
            } else {
                // Si no hay precios, agregar con precio 0
                agregarProductoACotizacion(codigo, nombre, 0, true); // Marcar como stock 0
            }
        },
        error: function(xhr, status, error) {
            console.error('Error AJAX precios:', xhr.responseText);
            // Si hay error, agregar con precio 0
            agregarProductoACotizacion(codigo, nombre, 0, true); // Marcar como stock 0
        }
    });
}

// Función para mostrar precios (mantenida por compatibilidad, pero ya no se usa)
function mostrarPrecios(precios) {
    // Esta función ya no se usa, pero la mantenemos por compatibilidad
    console.log('Función mostrarPrecios llamada pero no implementada');
}

function seleccionarPrecio(precio) {
    // Esta función ya no se usa, pero la mantenemos por compatibilidad
    console.log('Función seleccionarPrecio llamada pero no implementada');
}

function agregarProductoACotizacion(codigo, nombre, precio, stockCero = false) {
    const producto = {
        codigo: codigo,
        nombre: nombre,
        cantidad: 1,
        precio: precio,
        subtotal: precio,
        stock_cero: stockCero
    };
    
    productosSeleccionados.push(producto);
    actualizarTablaProductos();
    calcularTotal();
}

function actualizarTablaProductos() {
    const tabla = $('#productosSeleccionados');
    tabla.empty();
    
    productosSeleccionados.forEach(function(producto, index) {
        const stockCeroClass = producto.stock_cero ? 'table-warning' : '';
        const stockCeroIcon = producto.stock_cero ? '<i class="material-icons text-warning" style="font-size: 16px;">warning</i> ' : '';
        
        const fila = `
            <tr class="${stockCeroClass}">
                <td>${producto.codigo}</td>
                <td>
                    ${stockCeroIcon}${producto.nombre}
                    ${producto.stock_cero ? '<br><small class="text-warning"><strong>Stock 0 - Pendiente</strong></small>' : ''}
                </td>
                <td>
                    <input type="number" class="form-control form-control-sm" 
                           value="${producto.cantidad}" min="0.01" step="0.01"
                           onchange="actualizarCantidad(${index}, this.value)">
                </td>
                <td>
                    <input type="number" class="form-control form-control-sm" 
                           value="${producto.precio}" min="0" step="0.01"
                           onchange="actualizarPrecio(${index}, this.value)">
                </td>
                <td>$${producto.subtotal.toFixed(2)}</td>
                <td>
                    <button type="button" class="btn btn-sm btn-danger" 
                            onclick="eliminarProducto(${index})">
                        <i class="material-icons">delete</i>
                    </button>
                </td>
            </tr>
        `;
        tabla.append(fila);
    });
}

function actualizarCantidad(index, cantidad) {
    productosSeleccionados[index].cantidad = parseFloat(cantidad);
    productosSeleccionados[index].subtotal = productosSeleccionados[index].cantidad * productosSeleccionados[index].precio;
    actualizarTablaProductos();
    calcularTotal();
}

function actualizarPrecio(index, precio) {
    productosSeleccionados[index].precio = parseFloat(precio);
    productosSeleccionados[index].subtotal = productosSeleccionados[index].cantidad * productosSeleccionados[index].precio;
    actualizarTablaProductos();
    calcularTotal();
}

function eliminarProducto(index) {
    productosSeleccionados.splice(index, 1);
    actualizarTablaProductos();
    calcularTotal();
}

function calcularTotal() {
    const subtotal = productosSeleccionados.reduce((sum, producto) => sum + producto.subtotal, 0);
    
    // Calcular descuentos (simulación del algoritmo del servidor)
    let descuentoGlobal = 0;
    let porcentajeDescuento = 0;
    
    // Descuento del 5% si pedido > $400,000
    if (subtotal > 400000) {
        descuentoGlobal += subtotal * 0.05;
        porcentajeDescuento += 5;
    }
    
    // Descuento adicional por promedio de compras (simulado)
    if (subtotal > 300000) {
        descuentoGlobal += subtotal * 0.05;
        porcentajeDescuento += 5;
    }
    
    const total = subtotal - descuentoGlobal;
    
    // Actualizar la interfaz
    $('#subtotalCotizacion').text('$' + subtotal.toFixed(2));
    $('#descuentoCotizacion').text('$' + descuentoGlobal.toFixed(2));
    $('#porcentajeDescuento').text(porcentajeDescuento);
    $('#totalCotizacion').text('$' + total.toFixed(2));
    
    // Mostrar información de descuentos
    if (descuentoGlobal > 0) {
        mostrarInfoDescuentos(descuentoGlobal, porcentajeDescuento);
    }
}

function mostrarInfoDescuentos(descuento, porcentaje) {
    // Crear o actualizar alerta de descuentos
    let alertaDescuentos = $('#alertaDescuentos');
    if (alertaDescuentos.length === 0) {
        alertaDescuentos = $('<div id="alertaDescuentos" class="alert alert-success mt-3"></div>');
        $('.card-body').append(alertaDescuentos);
    }
    
    alertaDescuentos.html(`
        <i class="material-icons">local_offer</i>
        <strong>¡Descuentos aplicados!</strong><br>
        Descuento del ${porcentaje}%: $${descuento.toFixed(2)}<br>
        <small>Descuento aplicado por volumen de compra y promedio histórico</small>
    `);
}

function guardarCotizacion() {
    if (productosSeleccionados.length === 0) {
        alert('Debes agregar al menos un producto a la cotización');
        return;
    }
    
    const datos = {
        cliente_codigo: '{{ $cliente->codigo ?? "" }}',
        cliente_nombre: '{{ $cliente->nombre ?? "" }}',
        productos: productosSeleccionados,
        observaciones: $('#observaciones').val()
    };
    
    $.ajax({
        url: '{{ route("cotizacion.guardar") }}',
        method: 'POST',
        data: datos,
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
            if (response.success) {
                let mensaje = response.message;
                if (response.estado === 'pendiente') {
                    mensaje += '\n\nLa cotización requiere aprobación debido a alertas del cliente.';
                } else if (response.estado === 'aprobado') {
                    mensaje += '\n\nLa cotización fue aprobada automáticamente.';
                }
                
                if (response.descuentos && response.descuentos.descuento_global > 0) {
                    mensaje += `\n\nDescuentos aplicados: $${response.descuentos.descuento_global.toFixed(2)} (${response.descuentos.porcentaje_descuento}%)`;
                }
                
                alert(mensaje);
                window.location.href = '{{ route("dashboard") }}';
            } else {
                alert('Error al guardar: ' + response.message);
            }
        },
        error: function() {
            alert('Error al conectar con el servidor');
        }
    });
}

// Búsqueda automática al presionar Enter
$('#buscarProducto').keypress(function(e) {
    if (e.which == 13) {
        buscarProductos();
    }
});
</script>
@endpush 