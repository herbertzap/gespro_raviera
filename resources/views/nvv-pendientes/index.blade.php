@extends('layouts.app')

@section('title', 'NVV Pendientes')

@section('content')
<div class="content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">NVV Pendientes - {{ $tipoUsuario }}</h4>
                        <p class="card-category">Notas de venta pendientes de facturación</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tarjetas de Resumen -->
        <div class="row">
            <div class="col-lg-3 col-md-6 col-sm-6">
                <div class="card card-stats">
                    <div class="card-header card-header-warning card-header-icon">
                        <div class="card-icon">
                            <i class="material-icons">pending_actions</i>
                        </div>
                        <p class="card-category">Total NVV</p>
                        <h3 class="card-title">{{ $resumen['total_nvv'] ?? 0 }}</h3>
                    </div>
                    <div class="card-footer">
                        <div class="stats">
                            <i class="material-icons text-warning">schedule</i>
                            Notas pendientes
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 col-sm-6">
                <div class="card card-stats">
                    <div class="card-header card-header-info card-header-icon">
                        <div class="card-icon">
                            <i class="material-icons">inventory</i>
                        </div>
                        <p class="card-category">Unidades Pend.</p>
                        <h3 class="card-title">{{ number_format($resumen['total_pendiente'] ?? 0, 0) }}</h3>
                    </div>
                    <div class="card-footer">
                        <div class="stats">
                            <i class="material-icons text-info">trending_up</i>
                            Por facturar
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 col-sm-6">
                <div class="card card-stats">
                    <div class="card-header card-header-success card-header-icon">
                        <div class="card-icon">
                            <i class="material-icons">attach_money</i>
                        </div>
                        <p class="card-category">Valor Pend.</p>
                        <h3 class="card-title">${{ number_format($resumen['total_valor_pendiente'] ?? 0, 0) }}</h3>
                    </div>
                    <div class="card-footer">
                        <div class="stats">
                            <i class="material-icons text-success">monetization_on</i>
                            Valor total
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 col-sm-6">
                <div class="card card-stats">
                    <div class="card-header card-header-rose card-header-icon">
                        <div class="card-icon">
                            <i class="material-icons">warning</i>
                        </div>
                        <p class="card-category">+60 Días</p>
                        <h3 class="card-title">{{ $resumen['por_rango']['Mas de 60 Días']['cantidad'] ?? 0 }}</h3>
                    </div>
                    <div class="card-footer">
                        <div class="stats">
                            <i class="material-icons text-rose">priority_high</i>
                            Requiere atención
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Filtros de Búsqueda</h4>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="{{ route('nvv-pendientes.index') }}">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="buscar">Buscar</label>
                                        <input type="text" class="form-control" id="buscar" name="buscar" 
                                               value="{{ $filtros['buscar'] ?? '' }}" 
                                               placeholder="Cliente, producto o número NVV...">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="rango_dias">Rango de Días</label>
                                        <select class="form-control" id="rango_dias" name="rango_dias">
                                            <option value="">Todos</option>
                                            <option value="1-7" {{ $filtros['rango_dias'] == '1-7' ? 'selected' : '' }}>1-7 días</option>
                                            <option value="8-30" {{ $filtros['rango_dias'] == '8-30' ? 'selected' : '' }}>8-30 días</option>
                                            <option value="31-60" {{ $filtros['rango_dias'] == '31-60' ? 'selected' : '' }}>31-60 días</option>
                                            <option value="60+" {{ $filtros['rango_dias'] == '60+' ? 'selected' : '' }}>+60 días</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="cliente">Cliente</label>
                                        <select class="form-control" id="cliente" name="cliente">
                                            <option value="">Todos</option>
                                            @if(isset($clientes) && is_array($clientes))
                                                @foreach($clientes as $cli)
                                                    <option value="{{ $cli['codigo'] }}" {{ ($filtros['cliente'] ?? '') == $cli['codigo'] ? 'selected' : '' }}>
                                                        {{ $cli['codigo'] }} - {{ $cli['nombre'] }}
                                                    </option>
                                                @endforeach
                                            @endif
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="producto">Producto</label>
                                        <input type="text" class="form-control" id="producto" name="producto" 
                                               value="{{ $filtros['producto'] ?? '' }}" 
                                               placeholder="Nombre del producto...">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="por_pagina">Por Página</label>
                                        <select class="form-control" id="por_pagina" name="por_pagina">
                                            <option value="10" {{ $filtros['por_pagina'] == 10 ? 'selected' : '' }}>10</option>
                                            <option value="20" {{ $filtros['por_pagina'] == 20 ? 'selected' : '' }}>20</option>
                                            <option value="50" {{ $filtros['por_pagina'] == 50 ? 'selected' : '' }}>50</option>
                                            <option value="100" {{ $filtros['por_pagina'] == 100 ? 'selected' : '' }}>100</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-1">
                                    <div class="form-group">
                                        <label>&nbsp;</label>
                                        <button type="submit" class="btn btn-primary btn-block">
                                            <i class="material-icons">search</i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <a href="{{ route('nvv-pendientes.index') }}" class="btn btn-secondary">
                                        <i class="material-icons">clear</i> Limpiar Filtros
                                    </a>
                                    <button type="button" class="btn btn-success" onclick="exportarNvv()">
                                        <i class="material-icons">file_download</i> Exportar Excel
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabla de NVV Pendientes -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">NVV Pendientes Detalle</h4>
                        <p class="card-category">Lista de notas de venta pendientes de facturación</p>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead class="text-warning">
                                    <tr>
                                        <th>
                                            <a href="{{ request()->fullUrlWithQuery(['ordenar_por' => 'TD', 'orden' => request('ordenar_por') == 'TD' && request('orden') == 'asc' ? 'desc' : 'asc']) }}" 
                                               class="text-decoration-none text-warning">
                                                Número NVV
                                                @if(request('ordenar_por') == 'TD')
                                                    <i class="material-icons">{{ request('orden') == 'asc' ? 'keyboard_arrow_up' : 'keyboard_arrow_down' }}</i>
                                                @else
                                                    <i class="material-icons text-muted">unfold_more</i>
                                                @endif
                                            </a>
                                        </th>
                                        <th>
                                            <a href="{{ request()->fullUrlWithQuery(['ordenar_por' => 'CLIE', 'orden' => request('ordenar_por') == 'CLIE' && request('orden') == 'asc' ? 'desc' : 'asc']) }}" 
                                               class="text-decoration-none text-warning">
                                                Cliente
                                                @if(request('ordenar_por') == 'CLIE')
                                                    <i class="material-icons">{{ request('orden') == 'asc' ? 'keyboard_arrow_up' : 'keyboard_arrow_down' }}</i>
                                                @else
                                                    <i class="material-icons text-muted">unfold_more</i>
                                                @endif
                                            </a>
                                        </th>
                                        <th>
                                            <a href="{{ request()->fullUrlWithQuery(['ordenar_por' => 'CANTIDAD_PRODUCTOS', 'orden' => request('ordenar_por') == 'CANTIDAD_PRODUCTOS' && request('orden') == 'asc' ? 'desc' : 'asc']) }}" 
                                               class="text-decoration-none text-warning">
                                                Productos
                                                @if(request('ordenar_por') == 'CANTIDAD_PRODUCTOS')
                                                    <i class="material-icons">{{ request('orden') == 'asc' ? 'keyboard_arrow_up' : 'keyboard_arrow_down' }}</i>
                                                @else
                                                    <i class="material-icons text-muted">unfold_more</i>
                                                @endif
                                            </a>
                                        </th>
                                        <th>
                                            <a href="{{ request()->fullUrlWithQuery(['ordenar_por' => 'TOTAL_PENDIENTE', 'orden' => request('ordenar_por') == 'TOTAL_PENDIENTE' && request('orden') == 'asc' ? 'desc' : 'asc']) }}" 
                                               class="text-decoration-none text-warning">
                                                Pendiente
                                                @if(request('ordenar_por') == 'TOTAL_PENDIENTE')
                                                    <i class="material-icons">{{ request('orden') == 'asc' ? 'keyboard_arrow_up' : 'keyboard_arrow_down' }}</i>
                                                @else
                                                    <i class="material-icons text-muted">unfold_more</i>
                                                @endif
                                            </a>
                                        </th>
                                        <th>
                                            <a href="{{ request()->fullUrlWithQuery(['ordenar_por' => 'TOTAL_VALOR_PENDIENTE', 'orden' => request('ordenar_por') == 'TOTAL_VALOR_PENDIENTE' && request('orden') == 'asc' ? 'desc' : 'asc']) }}" 
                                               class="text-decoration-none text-warning">
                                                Valor Pend.
                                                @if(request('ordenar_por') == 'TOTAL_VALOR_PENDIENTE')
                                                    <i class="material-icons">{{ request('orden') == 'asc' ? 'keyboard_arrow_up' : 'keyboard_arrow_down' }}</i>
                                                @else
                                                    <i class="material-icons text-muted">unfold_more</i>
                                                @endif
                                            </a>
                                        </th>
                                        <th>
                                            <a href="{{ request()->fullUrlWithQuery(['ordenar_por' => 'DIAS', 'orden' => request('ordenar_por') == 'DIAS' && request('orden') == 'asc' ? 'desc' : 'asc']) }}" 
                                               class="text-decoration-none text-warning">
                                                Días
                                                @if(request('ordenar_por') == 'DIAS')
                                                    <i class="material-icons">{{ request('orden') == 'asc' ? 'keyboard_arrow_up' : 'keyboard_arrow_down' }}</i>
                                                @else
                                                    <i class="material-icons text-muted">unfold_more</i>
                                                @endif
                                            </a>
                                        </th>
                                        <th>Rango</th>
                                        <th>Vendedor</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($nvvPendientes ?? [] as $nvv)
                                    <tr>
                                        <td>
                                            <span class="badge badge-warning">
                                                {{ $nvv['TD'] }}-{{ $nvv['NUM'] }}
                                            </span>
                                        </td>
                                        <td>{{ $nvv['CLIE'] }}</td>
                                        <td>{{ $nvv['CANTIDAD_PRODUCTOS'] ?? 1 }}</td>
                                        <td>{{ number_format($nvv['TOTAL_PENDIENTE'] ?? 0, 0) }}</td>
                                        <td>${{ number_format($nvv['TOTAL_VALOR_PENDIENTE'] ?? 0, 0) }}</td>
                                        <td>
                                            <span class="badge badge-{{ 
                                                $nvv['DIAS'] < 8 ? 'success' : 
                                                ($nvv['DIAS'] < 31 ? 'warning' : 
                                                ($nvv['DIAS'] < 61 ? 'danger' : 'dark')) 
                                            }}">
                                                {{ $nvv['DIAS'] }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-{{ 
                                                $nvv['Rango'] == 'Entre 1 y 7 días' ? 'success' : 
                                                ($nvv['Rango'] == 'Entre 8 y 30 Días' ? 'warning' : 
                                                ($nvv['Rango'] == 'Entre 31 y 60 Días' ? 'danger' : 'dark')) 
                                            }}">
                                                {{ $nvv['Rango'] }}
                                            </span>
                                        </td>
                                        <td>{{ $nvv['VENDEDOR_NOMBRE'] ?? 'N/A' }}</td>
                                        <td>
                                            <a href="{{ route('nvv-pendientes.ver', $nvv['NUM']) }}" 
                                               class="btn btn-sm btn-info" 
                                               title="Ver detalles">
                                                <i class="material-icons">visibility</i>
                                            </a>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="9" class="text-center">No hay NVV pendientes</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Paginación -->
                        @if($nvvPendientes instanceof \Illuminate\Pagination\LengthAwarePaginator && $nvvPendientes->hasPages())
                            <div class="card-footer">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="text-muted">
                                        Mostrando {{ $nvvPendientes->firstItem() }} a {{ $nvvPendientes->lastItem() }} de {{ $nvvPendientes->total() }} registros
                                    </div>
                                    <div>
                                        {{ $nvvPendientes->appends(request()->query())->links() }}
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('js')
<script>
function exportarNvv() {
    // Obtener los filtros actuales
    const filtros = {
        buscar: document.getElementById('buscar').value,
        rango_dias: document.getElementById('rango_dias').value,
        cliente: document.getElementById('cliente').value,
        producto: document.getElementById('producto').value,
        ordenar_por: '{{ request("ordenar_por", "DIAS") }}',
        orden: '{{ request("orden", "desc") }}'
    };
    
    // Construir URL con filtros
    const params = new URLSearchParams(filtros);
    const url = '{{ route("nvv-pendientes.export") }}?' + params.toString();
    
    // Mostrar mensaje simple de carga
    const mensaje = document.createElement('div');
    mensaje.innerHTML = '<div style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: #2b3553; color: white; padding: 20px; border-radius: 5px; z-index: 10000; box-shadow: 0 4px 6px rgba(0,0,0,0.3);"><i class="material-icons" style="vertical-align: middle; margin-right: 10px;">hourglass_empty</i>Generando archivo Excel...</div>';
    document.body.appendChild(mensaje);
    
    // Crear un enlace temporal para descargar el archivo
    const link = document.createElement('a');
    link.href = url;
    link.style.display = 'none';
    document.body.appendChild(link);
    link.click();
    
    // Remover el mensaje y el enlace después de un momento
    setTimeout(() => {
        if (document.body.contains(mensaje)) {
            document.body.removeChild(mensaje);
        }
        if (document.body.contains(link)) {
            document.body.removeChild(link);
        }
    }, 2000);
}
</script>
@endpush

@endsection
