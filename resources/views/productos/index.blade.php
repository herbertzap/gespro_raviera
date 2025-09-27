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
                                <button type="button" class="btn btn-info btn-sm" onclick="sincronizarProductos()">
                                    <i class="material-icons">sync</i> Sincronizar
                                </button>
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

        <!-- Buscador de Productos -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header card-header-primary">
                        <h4 class="card-title">Buscador de Productos</h4>
                        <p class="card-category">Consulta stock y información de productos</p>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="form-group">
                                    <label for="buscarProducto">Buscar Producto</label>
                                    <input type="text" class="form-control" id="buscarProducto" placeholder="Ingrese código o nombre del producto">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>&nbsp;</label>
                                    <button type="button" class="btn btn-primary btn-block" onclick="buscarProducto()">
                                        <i class="material-icons">search</i> Buscar
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Resultados de búsqueda -->
                        <div id="resultadosBusqueda" style="display: none;">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead class="text-primary">
                                        <tr>
                                            <th>Código</th>
                                            <th>Nombre</th>
                                            <th>Stock Actual</th>
                                            <th>Stock Mínimo</th>
                                            <th>Precio</th>
                                            <th>Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tablaResultados">
                                        <!-- Los resultados se cargarán aquí -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gráficos y Estadísticas -->
        <div class="row">
            <!-- Productos Más Vendidos (Gráfico) -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header card-header-success">
                        <h4 class="card-title">Productos Más Vendidos</h4>
                        <p class="card-category">Últimos 3 meses</p>
                    </div>
                    <div class="card-body">
                        <canvas id="graficoProductosVendidos" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>

            <!-- Stock por Categorías -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header card-header-info">
                        <h4 class="card-title">Stock por Categorías</h4>
                        <p class="card-category">Distribución de inventario</p>
                    </div>
                    <div class="card-body">
                        <canvas id="graficoStockCategorias" width="400" height="200"></canvas>
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
                                        <th>Acción</th>
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
                                        <td>
                                            <button class="btn btn-sm btn-primary" onclick="verDetalleProducto('{{ $producto['codigo'] ?? '' }}')">
                                                <i class="material-icons">visibility</i> Ver
                                            </button>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="7" class="text-center">No hay productos con bajo stock</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Acciones Rápidas -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Acciones Rápidas</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <button class="btn btn-warning btn-block" onclick="exportarReporteStock()">
                                    <i class="material-icons">file_download</i> Exportar Reporte
                                </button>
                            </div>
                            <div class="col-md-3">
                                <button class="btn btn-info btn-block" onclick="actualizarDatos()">
                                    <i class="material-icons">refresh</i> Actualizar Datos
                                </button>
                            </div>
                            <div class="col-md-3">
                                <a href="{{ route('dashboard') }}" class="btn btn-primary btn-block">
                                    <i class="material-icons">dashboard</i> Volver Dashboard
                                </a>
                            </div>
                            <div class="col-md-3">
                                <button class="btn btn-success btn-block" onclick="crearNuevaNVV()">
                                    <i class="material-icons">add_shopping_cart</i> Nueva NVV
                                </button>
                            </div>
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

@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Datos para los gráficos (estos vendrían del controlador)
const productosVendidos = @json($productosVendidos ?? []);
const stockCategorias = @json($stockCategorias ?? []);

// Gráfico de productos más vendidos
const ctxVendidos = document.getElementById('graficoProductosVendidos').getContext('2d');
new Chart(ctxVendidos, {
    type: 'bar',
    data: {
        labels: productosVendidos.map(p => p.nombre),
        datasets: [{
            label: 'Cantidad Vendida',
            data: productosVendidos.map(p => p.cantidad),
            backgroundColor: 'rgba(76, 175, 80, 0.6)',
            borderColor: 'rgba(76, 175, 80, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

// Gráfico de stock por categorías
const ctxCategorias = document.getElementById('graficoStockCategorias').getContext('2d');
new Chart(ctxCategorias, {
    type: 'doughnut',
    data: {
        labels: stockCategorias.map(c => c.categoria),
        datasets: [{
            data: stockCategorias.map(c => c.stock),
            backgroundColor: [
                'rgba(255, 99, 132, 0.6)',
                'rgba(54, 162, 235, 0.6)',
                'rgba(255, 205, 86, 0.6)',
                'rgba(75, 192, 192, 0.6)',
                'rgba(153, 102, 255, 0.6)'
            ]
        }]
    },
    options: {
        responsive: true
    }
});

// Función para buscar productos
function buscarProducto() {
    const termino = document.getElementById('buscarProducto').value;
    
    if (termino.length < 2) {
        alert('Ingrese al menos 2 caracteres para buscar');
        return;
    }
    
    // Simular búsqueda (aquí harías la llamada AJAX real)
    fetch(`/api/productos/buscar?q=${encodeURIComponent(termino)}`)
        .then(response => response.json())
        .then(data => {
            mostrarResultados(data);
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al buscar productos');
        });
}

// Función para mostrar resultados de búsqueda
function mostrarResultados(productos) {
    const tabla = document.getElementById('tablaResultados');
    const contenedor = document.getElementById('resultadosBusqueda');
    
    if (productos.length === 0) {
        tabla.innerHTML = '<tr><td colspan="6" class="text-center">No se encontraron productos</td></tr>';
    } else {
        tabla.innerHTML = productos.map(producto => `
            <tr>
                <td>${producto.codigo}</td>
                <td>${producto.nombre}</td>
                <td><span class="badge badge-${producto.stock_actual <= 0 ? 'danger' : 'success'}">${producto.stock_actual}</span></td>
                <td>${producto.stock_minimo}</td>
                <td>$${producto.precio.toLocaleString()}</td>
                <td><span class="badge badge-${producto.activo ? 'success' : 'danger'}">${producto.activo ? 'Activo' : 'Inactivo'}</span></td>
            </tr>
        `).join('');
    }
    
    contenedor.style.display = 'block';
}

// Función para ver detalle de producto
function verDetalleProducto(codigo) {
    // Simular carga de detalle
    document.getElementById('contenidoDetalleProducto').innerHTML = `
        <div class="text-center">
            <div class="spinner-border" role="status">
                <span class="sr-only">Cargando...</span>
            </div>
        </div>
    `;
    
    $('#modalDetalleProducto').modal('show');
    
    // Aquí harías la llamada AJAX real
    setTimeout(() => {
        document.getElementById('contenidoDetalleProducto').innerHTML = `
            <div class="row">
                <div class="col-md-6">
                    <h6>Código: ${codigo}</h6>
                    <h6>Nombre: Producto de Ejemplo</h6>
                    <h6>Stock Actual: 5</h6>
                    <h6>Stock Mínimo: 10</h6>
                </div>
                <div class="col-md-6">
                    <h6>Precio: $50,000</h6>
                    <h6>Categoría: Categoría A</h6>
                    <h6>Estado: Activo</h6>
                    <h6>Última Venta: 15/01/2025</h6>
                </div>
            </div>
        `;
    }, 1000);
}

// Función para crear NVV con producto
function crearNVVConProducto() {
    // Aquí implementarías la lógica para crear una nueva NVV
    alert('Funcionalidad de crear NVV con producto - Por implementar');
}

// Función para exportar reporte
function exportarReporteStock() {
    alert('Funcionalidad de exportar reporte - Por implementar');
}

// Función para actualizar datos
function actualizarDatos() {
    location.reload();
}

// Función para crear nueva NVV
function crearNuevaNVV() {
    window.location.href = '/cotizaciones/create';
}

// Permitir búsqueda con Enter
document.getElementById('buscarProducto').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        buscarProducto();
    }
});
</script>
<script>
function sincronizarProductos() {
    if (!confirm('¿Deseas sincronizar los productos (precios y descuentos) desde SQL Server?')) {
        return;
    }

    const button = event.target;
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="material-icons">sync</i> Sincronizando...';
    button.disabled = true;

    fetch('/productos/sincronizar', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({ limit: 1000 })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(`Sincronización completada:\n- Nuevos: ${data.nuevos}\n- Actualizados: ${data.actualizados}\n- Total: ${data.total}`);
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'No se pudo sincronizar'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al sincronizar productos');
    })
    .finally(() => {
        button.innerHTML = originalText;
        button.disabled = false;
    });
}
</script>
@endsection
