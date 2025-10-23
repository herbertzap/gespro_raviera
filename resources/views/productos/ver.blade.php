@extends('layouts.app')

@section('title', 'Detalle Producto - ' . $producto->KOPR)

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
                    <div class="card-header card-header-primary">
                        <div class="row">
                            <div class="col-md-8">
                                <h4 class="card-title">
                                    <i class="material-icons">inventory</i>
                                    {{ $producto->NOKOPR }}
                                </h4>
                                <p class="card-category">Código: {{ $producto->KOPR }}</p>
                            </div>
                            <div class="col-md-4 text-right">
                                <a href="{{ route('productos.index') }}" class="btn btn-secondary">
                                    <i class="material-icons">arrow_back</i> Volver
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Estadísticas de Ventas -->
        <div class="row">
            <div class="col-lg-3 col-md-6">
                <div class="card card-stats">
                    <div class="card-header card-header-success card-header-icon">
                        <div class="card-icon">
                            <i class="material-icons">shopping_cart</i>
                        </div>
                        <p class="card-category">Total NVV</p>
                        <h3 class="card-title">{{ $estadisticas->total_nvv ?? 0 }}</h3>
                    </div>
                    <div class="card-footer">
                        <div class="stats">
                            <i class="material-icons text-success">receipt</i>
                            Últimos 6 meses
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="card card-stats">
                    <div class="card-header card-header-info card-header-icon">
                        <div class="card-icon">
                            <i class="material-icons">all_inbox</i>
                        </div>
                        <p class="card-category">Unidades Vendidas</p>
                        <h3 class="card-title">{{ number_format($estadisticas->total_unidades ?? 0, 0) }}</h3>
                    </div>
                    <div class="card-footer">
                        <div class="stats">
                            <i class="material-icons text-info">trending_up</i>
                            Total vendido
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="card card-stats">
                    <div class="card-header card-header-warning card-header-icon">
                        <div class="card-icon">
                            <i class="material-icons">attach_money</i>
                        </div>
                        <p class="card-category">Precio Promedio</p>
                        <h3 class="card-title">${{ number_format($estadisticas->precio_promedio ?? 0, 0) }}</h3>
                    </div>
                    <div class="card-footer">
                        <div class="stats">
                            <i class="material-icons text-warning">show_chart</i>
                            En NVV
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="card card-stats">
                    <div class="card-header card-header-danger card-header-icon">
                        <div class="card-icon">
                            <i class="material-icons">inventory_2</i>
                        </div>
                        <p class="card-category">Stock Actual</p>
                        <h3 class="card-title">{{ number_format($producto->stock_disponible ?? 0, 0) }}</h3>
                    </div>
                    <div class="card-footer">
                        <div class="stats">
                            <i class="material-icons {{ ($producto->stock_disponible ?? 0) > 0 ? 'text-success' : 'text-danger' }}">
                                {{ ($producto->stock_disponible ?? 0) > 0 ? 'check_circle' : 'warning' }}
                            </i>
                            {{ ($producto->stock_disponible ?? 0) > 0 ? 'Disponible' : 'Sin stock' }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gráfico de Ventas por Mes -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header card-header-info">
                        <h4 class="card-title">
                            <i class="material-icons">show_chart</i>
                            Ventas por Mes
                        </h4>
                        <p class="card-category">Últimos 6 meses</p>
                    </div>
                    <div class="card-body">
                        <canvas id="graficoVentasMes" height="80"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabla de NVV con este Producto -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header card-header-success">
                        <h4 class="card-title">
                            <i class="material-icons">receipt_long</i>
                            NVV con este Producto
                        </h4>
                        <p class="card-category">Últimas 20 notas de venta</p>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="text-success">
                                    <tr>
                                        <th>NVV #</th>
                                        <th>Cliente</th>
                                        <th>Vendedor</th>
                                        <th>Cantidad</th>
                                        <th>Precio</th>
                                        <th>Subtotal</th>
                                        <th>Fecha</th>
                                        <th>Estado</th>
                                        <th>Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($nvvConProducto as $nvv)
                                    <tr>
                                        <td>
                                            <span class="badge badge-info">{{ $nvv['nvv_numero'] }}</span>
                                        </td>
                                        <td>{{ $nvv['cliente'] }}</td>
                                        <td>{{ $nvv['vendedor'] }}</td>
                                        <td>
                                            <span class="badge badge-primary">{{ $nvv['cantidad'] }}</span>
                                        </td>
                                        <td>${{ number_format($nvv['precio'], 0) }}</td>
                                        <td><strong>${{ number_format($nvv['subtotal'], 0) }}</strong></td>
                                        <td>{{ $nvv['fecha'] }}</td>
                                        <td>
                                            @if($nvv['facturada'])
                                                <span class="badge badge-success">Facturada</span>
                                            @else
                                                <span class="badge badge-warning">Pendiente</span>
                                            @endif
                                        </td>
                                        <td>
                                            <a href="{{ route('aprobaciones.show', $nvv['nvv_id']) }}" class="btn btn-sm btn-primary">
                                                <i class="material-icons">visibility</i> Ver NVV
                                            </a>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="9" class="text-center">
                                            No hay NVV registradas con este producto
                                        </td>
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

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Gráfico de ventas por mes
const ventasPorMes = @json($ventasPorMes);

const ctx = document.getElementById('graficoVentasMes').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: ventasPorMes.map(v => {
            const [year, month] = v.mes.split('-');
            const meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
            return meses[parseInt(month) - 1] + ' ' + year;
        }),
        datasets: [{
            label: 'Unidades Vendidas',
            data: ventasPorMes.map(v => v.cantidad),
            borderColor: 'rgb(0, 188, 212)',
            backgroundColor: 'rgba(0, 188, 212, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                display: true,
                position: 'top'
            },
            title: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    precision: 0
                }
            }
        }
    }
});
</script>
@endsection

