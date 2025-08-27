// Variables globales
let productosCotizacion = [];
let clienteData = null;

console.log('🔍 Script de cotizaciones cargándose...');

// Función para buscar productos
function buscarProductos() {
    console.log('🔍 Función buscarProductos llamada');
    
    const busqueda = document.getElementById('buscarProducto').value.trim().toUpperCase();
    
    // Validar mínimo 4 caracteres
    if (busqueda.length < 4) {
        alert('Por favor ingresa al menos 4 caracteres para buscar');
        return;
    }

    console.log('🔍 Buscando productos:', busqueda);

    // Usar la ruta correcta
    const url = '/cotizacion/buscar-productos?busqueda=' + encodeURIComponent(busqueda);
    console.log('🔍 URL de búsqueda:', url);

    // Mostrar indicador de carga
    document.getElementById('contenidoResultados').innerHTML = '<div class="alert alert-info"><i class="material-icons">search</i> Buscando productos...</div>';
    document.getElementById('resultadosBusqueda').style.display = 'block';

    fetch(url)
        .then(response => {
            console.log('🔍 Respuesta del servidor:', response);
            if (!response.ok) {
                throw new Error('Error en la respuesta del servidor: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            console.log('🔍 Datos recibidos:', data);
            if (data.success) {
                mostrarResultadosProductos(data.data);
            } else {
                document.getElementById('contenidoResultados').innerHTML = '<div class="alert alert-danger"><i class="material-icons">error</i> Error: ' + data.message + '</div>';
            }
        })
        .catch(error => {
            console.error('❌ Error en la búsqueda:', error);
            document.getElementById('contenidoResultados').innerHTML = '<div class="alert alert-danger"><i class="material-icons">error</i> Error al buscar productos: ' + error.message + '</div>';
        });
}

// Función de prueba para verificar que el JavaScript se carga
function testJavaScript() {
    console.log('JavaScript cargado correctamente');
    alert('JavaScript funcionando');
}

// Función para mostrar resultados de productos
function mostrarResultadosProductos(productos) {
    console.log('Mostrando resultados:', productos);
    
    if (productos.length === 0) {
        document.getElementById('contenidoResultados').innerHTML = '<div class="alert alert-info">No se encontraron productos</div>';
        document.getElementById('resultadosBusqueda').style.display = 'block';
        return;
    }

    let contenido = '<div class="table-responsive"><table class="table table-striped">';
    contenido += '<thead><tr><th>Código</th><th>Producto</th><th>Stock</th><th>Precio</th><th>Acción</th></tr></thead><tbody>';
    
    productos.forEach(producto => {
        const stockClass = producto.TIENE_STOCK ? 'text-success' : 'text-danger';
        const stockText = producto.TIENE_STOCK ? 'Disponible' : 'Sin stock';
        
        contenido += `
            <tr>
                <td>${producto.CODIGO_PRODUCTO || producto.codigo_producto || ''}</td>
                <td>${producto.NOMBRE_PRODUCTO || producto.nombre_producto || ''}</td>
                <td class="${stockClass}">${producto.STOCK_DISPONIBLE_REAL || producto.stock_disponible || 0} ${producto.UNIDAD_MEDIDA || producto.unidad_medida || 'UN'}</td>
                <td>$${producto.PRECIO_UD1 || producto.precio_ud1 || 0}</td>
                <td>
                    <button class="btn btn-sm btn-primary" onclick="agregarProducto('${producto.CODIGO_PRODUCTO || producto.codigo_producto}', '${producto.NOMBRE_PRODUCTO || producto.nombre_producto}', ${producto.PRECIO_UD1 || producto.precio_ud1 || 0}, ${producto.STOCK_DISPONIBLE_REAL || producto.stock_disponible || 0}, '${producto.UNIDAD_MEDIDA || producto.unidad_medida || 'UN'}')">
                        <i class="material-icons">add</i> Agregar
                    </button>
                </td>
            </tr>
        `;
    });
    
    contenido += '</tbody></table></div>';
    
    document.getElementById('contenidoResultados').innerHTML = contenido;
    document.getElementById('resultadosBusqueda').style.display = 'block';
}

// Función para agregar producto a la cotización
function agregarProducto(codigo, nombre, precio, stock, unidad) {
    console.log('Agregando producto:', { codigo, nombre, precio, stock, unidad });
    
    // Verificar si el producto ya está en la cotización
    const productoExistente = productosCotizacion.find(p => p.codigo === codigo);
    
    if (productoExistente) {
        // Incrementar cantidad
        productoExistente.cantidad += 1;
        productoExistente.subtotal = productoExistente.cantidad * productoExistente.precio;
    } else {
        // Agregar nuevo producto
        productosCotizacion.push({
            codigo: codigo,
            nombre: nombre,
            cantidad: 1,
            precio: precio,
            subtotal: precio,
            stock: stock,
            unidad: unidad
        });
    }
    
    actualizarTablaProductos();
    calcularTotales();
    
    // Ocultar resultados de búsqueda
    document.getElementById('resultadosBusqueda').style.display = 'none';
    document.getElementById('buscarProducto').value = '';
}

// Función para actualizar la tabla de productos
function actualizarTablaProductos() {
    const tbody = document.getElementById('productosCotizacion');
    tbody.innerHTML = '';
    
    productosCotizacion.forEach((producto, index) => {
        const stockClass = producto.cantidad <= producto.stock ? 'text-success' : 'text-danger';
        const stockText = producto.cantidad <= producto.stock ? 'Suficiente' : 'Insuficiente';
        
        const row = `
            <tr>
                <td>${producto.codigo}</td>
                <td>${producto.nombre}</td>
                <td>
                    <input type="number" class="form-control" value="${producto.cantidad}" min="0.01" step="0.01" 
                           onchange="actualizarCantidad(${index}, this.value)" style="width: 80px;">
                </td>
                <td>$${producto.precio.toFixed(2)}</td>
                <td>$${producto.subtotal.toFixed(2)}</td>
                <td class="${stockClass}">${stockText}</td>
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
    const subtotal = productosCotizacion.reduce((sum, producto) => sum + producto.subtotal, 0);
    
    // Calcular descuento (5% si supera $400,000)
    let descuento = 0;
    if (subtotal > 400000) {
        descuento = subtotal * 0.05;
    }
    
    const total = subtotal - descuento;
    
    document.getElementById('subtotal').textContent = '$' + subtotal.toFixed(2);
    document.getElementById('descuento').textContent = '$' + descuento.toFixed(2);
    document.getElementById('total').textContent = '$' + total.toFixed(2);
}

// Función para limpiar búsqueda
function limpiarBusqueda() {
    document.getElementById('buscarProducto').value = '';
    document.getElementById('resultadosBusqueda').style.display = 'none';
}

// Función para guardar cotización
function guardarCotizacion() {
    if (productosCotizacion.length === 0) {
        alert('Debes agregar al menos un producto a la cotización');
        return;
    }

    if (!clienteData) {
        alert('No hay cliente seleccionado');
        return;
    }

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
            alert('Cotización guardada exitosamente');
            window.location.href = '/cotizaciones';
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al guardar la cotización');
    });
}

// Hacer las funciones globales
window.buscarProductos = buscarProductos;
window.testJavaScript = testJavaScript;
window.mostrarResultadosProductos = mostrarResultadosProductos;
window.agregarProducto = agregarProducto;
window.actualizarTablaProductos = actualizarTablaProductos;
window.actualizarCantidad = actualizarCantidad;
window.eliminarProducto = eliminarProducto;
window.calcularTotales = calcularTotales;
window.limpiarBusqueda = limpiarBusqueda;
window.guardarCotizacion = guardarCotizacion;

// Configurar input de búsqueda cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    console.log('🔍 DOM cargado, configurando input de búsqueda...');
    
    // Configurar input de búsqueda
    const buscarInput = document.getElementById('buscarProducto');
    if (buscarInput) {
        console.log('🔍 Configurando input de búsqueda...');
        
        // Convertir a mayúsculas automáticamente
        buscarInput.addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });
        
        // Buscar con Enter
        buscarInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                console.log('🔍 Enter presionado en input de búsqueda');
                buscarProductos();
            }
        });
        
        // Placeholder actualizado
        buscarInput.placeholder = 'Buscar producto por código o nombre (mínimo 4 caracteres)...';
    } else {
        console.error('❌ No se encontró el input de búsqueda');
    }
    
    // Inicializar totales
    calcularTotales();
    
    console.log('✅ Configuración completada');
});

console.log('🔍 Script cargado completamente');
