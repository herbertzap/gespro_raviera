// Función para actualizar el stock de productos en la cotización dinámicamente
function actualizarStockProductos() {
    console.log('🔄 Actualizando stock de productos dinámicamente...');
    
    // Obtener todos los productos en la cotización
    productosCotizacion.forEach((producto, index) => {
        // Hacer petición AJAX para obtener stock actual
        fetch(`/cotizaciones/stock-producto/${producto.codigo}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Actualizar el stock del producto
                    producto.stock = data.stock_disponible;
                    producto.stock_fisico = data.stock_fisico;
                    producto.stock_comprometido = data.stock_comprometido;
                    
                    console.log(`✅ Stock actualizado para ${producto.codigo}: ${data.stock_disponible}`);
                    
                    // Actualizar la tabla si estamos en la vista de cotización
                    if (typeof actualizarTablaProductos === 'function') {
                        actualizarTablaProductos();
                    }
                } else {
                    console.error(`❌ Error actualizando stock para ${producto.codigo}:`, data.message);
                }
            })
            .catch(error => {
                console.error(`❌ Error en petición AJAX para ${producto.codigo}:`, error);
            });
    });
}

// Función mejorada para actualizar la tabla de productos con stock dinámico
function actualizarTablaProductos() {
    const tbody = document.getElementById('productosCotizacion');
    if (!tbody) return;
    
    tbody.innerHTML = '';
    
    productosCotizacion.forEach((producto, index) => {
        let stockClass, stockText;
        
        // Usar el stock actualizado del producto
        const stockActual = producto.stock || 0;
        
        if (stockActual <= 0) {
            stockClass = 'text-warning';
            stockText = 'Sin stock';
        } else if (producto.cantidad <= stockActual) {
            stockClass = 'text-success';
            stockText = 'Suficiente';
        } else {
            stockClass = 'text-danger';
            stockText = 'Insuficiente';
        }
        
        // Determinar el step según la unidad
        const step = obtenerStepPorUnidad(producto.unidad);
        
        // Calcular valores para mostrar
        const precioBase = producto.precio * producto.cantidad;
        const descuentoPorcentaje = (producto.descuento || 0) / 100;
        const descuentoValor = precioBase * descuentoPorcentaje;
        const subtotalConDescuento = precioBase - descuentoValor;
        const ivaValor = subtotalConDescuento * 0.19;
        const totalConIva = subtotalConDescuento + ivaValor;
        
        const row = `
            <tr>
                <td>
                    <button class="btn btn-sm btn-danger" onclick="eliminarProducto(${index})" title="Eliminar producto">
                        <i class="material-icons">delete</i>
                    </button>
                </td>
                <td><strong>${producto.codigo}</strong></td>
                <td>${producto.nombre}</td>
                <td class="${stockClass}">
                    <i class="material-icons">${stockActual > 0 ? 'check_circle' : 'warning'}</i>
                    ${stockActual} ${producto.unidad}
                    ${producto.stock_comprometido > 0 ? `<br><small class="text-muted">Comprometido: ${producto.stock_comprometido}</small>` : ''}
                    ${stockActual <= 0 ? '<br><small class="text-warning"><i class="material-icons">info</i> Sin stock - Nota pendiente</small>' : ''}
                </td>
                <td>
                    <input type="number" 
                           class="form-control form-control-sm" 
                           value="${producto.cantidad}" 
                           min="0" 
                           step="${step}"
                           onchange="actualizarCantidad(${index}, this.value)"
                           style="width: 80px;">
                </td>
                <td>
                    <div class="input-group input-group-sm">
                        <input type="number" 
                               class="form-control" 
                               value="${producto.descuento || 0}" 
                               min="0" 
                               max="100" 
                               step="0.01"
                               onchange="actualizarDescuento(${index}, this.value)"
                               style="width: 80px;">
                        <div class="input-group-append">
                            <span class="input-group-text">%</span>
                        </div>
                    </div>
                </td>
                <td><strong>$${Math.round(producto.precio).toLocaleString()}</strong></td>
                <td><strong>$${Math.round(descuentoValor).toLocaleString()}</strong></td>
                <td><strong>$${Math.round(subtotalConDescuento).toLocaleString()}</strong></td>
                <td><strong>$${Math.round(ivaValor).toLocaleString()}</strong></td>
                <td><strong>$${Math.round(totalConIva).toLocaleString()}</strong></td>
            </tr>
        `;
        
        tbody.innerHTML += row;
    });
    
    // Actualizar totales
    actualizarTotales();
}

// Función para obtener el stock de un producto específico
function obtenerStockProducto(codigo) {
    return fetch(`/cotizaciones/stock-producto/${codigo}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                return data.stock_disponible;
            } else {
                console.error(`Error obteniendo stock para ${codigo}:`, data.message);
                return 0;
            }
        })
        .catch(error => {
            console.error(`Error en petición AJAX para ${codigo}:`, error);
            return 0;
        });
}

// Función para actualizar el stock de un producto específico en la cotización
function actualizarStockProducto(codigo) {
    obtenerStockProducto(codigo).then(stock => {
        const producto = productosCotizacion.find(p => p.codigo === codigo);
        if (producto) {
            producto.stock = stock;
            actualizarTablaProductos();
        }
    });
}
