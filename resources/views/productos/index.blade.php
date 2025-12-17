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

@endsection

@section('scripts')
<script>
// Función para crear NVV con producto
function crearNVVConProducto() {
    // Aquí implementarías la lógica para crear una nueva NVV
    alert('Funcionalidad de crear NVV con producto - Por implementar');
}
</script>
@endsection
