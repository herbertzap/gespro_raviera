@extends('layouts.app')

@section('title', 'Facturas Pendientes')

@section('content')
    <div class="container-fluid">
        <!-- Header -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Facturas Pendientes - {{ $tipoUsuario }}</h4>
                        <p class="card-category">Facturas pendientes de pago</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tarjetas de Resumen -->
        <div class="row">
            <div class="col-lg-3 col-md-6 col-sm-6">
                <div class="card card-stats">
                    <div class="card-header card-header-danger card-header-icon">
                        <div class="card-icon">
                            <i class="material-icons">receipt</i>
                        </div>
                        <p class="card-category">Total Facturas</p>
                        <h3 class="card-title">{{ $resumen['total_facturas'] ?? 0 }}</h3>
                    </div>
                    <div class="card-footer">
                        <div class="stats">
                            <i class="material-icons text-danger">schedule</i>
                            Por cobrar
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
                        <p class="card-category">Saldo Total</p>
                        <h3 class="card-title">${{ number_format($resumen['total_saldo'] ?? 0, 0) }}</h3>
                    </div>
                    <div class="card-footer">
                        <div class="stats">
                            <i class="material-icons text-success">monetization_on</i>
                            Monto pendiente
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 col-sm-6">
                <div class="card card-stats">
                    <div class="card-header card-header-warning card-header-icon">
                        <div class="card-icon">
                            <i class="material-icons">warning</i>
                        </div>
                        <p class="card-category">Vencidas</p>
                        <h3 class="card-title">{{ ($resumen['por_estado']['VENCIDO']['cantidad'] ?? 0) + ($resumen['por_estado']['MOROSO']['cantidad'] ?? 0) }}</h3>
                    </div>
                    <div class="card-footer">
                        <div class="stats">
                            <i class="material-icons text-warning">priority_high</i>
                            Requiere atención
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 col-sm-6">
                <div class="card card-stats">
                    <div class="card-header card-header-info card-header-icon">
                        <div class="card-icon">
                            <i class="material-icons">check_circle</i>
                        </div>
                        <p class="card-category">Vigentes</p>
                        <h3 class="card-title">{{ $resumen['por_estado']['VIGENTE']['cantidad'] ?? 0 }}</h3>
                    </div>
                    <div class="card-footer">
                        <div class="stats">
                            <i class="material-icons text-info">update</i>
                            Al día
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
                        <form method="GET" action="{{ route('facturas-pendientes.index') }}">
                            <div class="row">
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="buscar">Buscar</label>
                                        <input type="text" class="form-control" id="buscar" name="buscar" 
                                               value="{{ $filtros['buscar'] ?? '' }}" 
                                               placeholder="Cliente o número factura...">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="estado">Estado</label>
                                        <select class="form-control" id="estado" name="estado">
                                            <option value="">Todos</option>
                                            <option value="VIGENTE" {{ $filtros['estado'] == 'VIGENTE' ? 'selected' : '' }}>Vigente</option>
                                            <option value="POR VENCER" {{ $filtros['estado'] == 'POR VENCER' ? 'selected' : '' }}>Por Vencer</option>
                                            <option value="VENCIDO" {{ $filtros['estado'] == 'VENCIDO' ? 'selected' : '' }}>Vencido</option>
                                            <option value="MOROSO" {{ $filtros['estado'] == 'MOROSO' ? 'selected' : '' }}>Moroso</option>
                                            <option value="BLOQUEAR" {{ $filtros['estado'] == 'BLOQUEAR' ? 'selected' : '' }}>Bloquear</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="tipo_documento">Tipo Doc.</label>
                                        <select class="form-control" id="tipo_documento" name="tipo_documento">
                                            <option value="">Todos</option>
                                            <option value="FCV" {{ $filtros['tipo_documento'] == 'FCV' ? 'selected' : '' }}>FCV</option>
                                            <option value="FDV" {{ $filtros['tipo_documento'] == 'FDV' ? 'selected' : '' }}>FDV</option>
                                            <option value="NCV" {{ $filtros['tipo_documento'] == 'NCV' ? 'selected' : '' }}>NCV</option>
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
                                        <label for="saldo_min">Saldo Mín.</label>
                                        <input type="number" class="form-control" id="saldo_min" name="saldo_min" 
                                               value="{{ $filtros['saldo_min'] ?? '' }}" 
                                               placeholder="Saldo mínimo...">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="saldo_max">Saldo Máx.</label>
                                        <input type="number" class="form-control" id="saldo_max" name="saldo_max" 
                                               value="{{ $filtros['saldo_max'] ?? '' }}" 
                                               placeholder="Saldo máximo...">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="fecha_desde">Fecha Desde</label>
                                        <input type="date" class="form-control" id="fecha_desde" name="fecha_desde" 
                                               value="{{ $filtros['fecha_desde'] ?? '' }}">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="fecha_hasta">Fecha Hasta</label>
                                        <input type="date" class="form-control" id="fecha_hasta" name="fecha_hasta" 
                                               value="{{ $filtros['fecha_hasta'] ?? '' }}">
                                    </div>
                                </div>
                                @if($tipoUsuario === 'Administrador' || $tipoUsuario === 'Supervisor' || $tipoUsuario === 'Super Admin')
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="vendedor">Vendedor</label>
                                        <select class="form-control" id="vendedor" name="vendedor">
                                            <option value="">Todos</option>
                                            @if(isset($vendedores) && is_array($vendedores))
                                                @foreach($vendedores as $vend)
                                                    <option value="{{ $vend['codigo'] }}" {{ ($filtros['vendedor'] ?? '') == $vend['codigo'] ? 'selected' : '' }}>
                                                        {{ $vend['codigo'] }} - {{ $vend['nombre'] }}
                                                    </option>
                                                @endforeach
                                            @endif
                                        </select>
                                    </div>
                                </div>
                                @endif
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
                            </div>
                            <div class="row mt-3">
                                <div class="col-md-12">
                                    <div class="form-group d-flex align-items-center">
                                        <button type="submit" class="btn btn-primary mr-2">
                                            <i class="material-icons">search</i> Filtrar
                                        </button>
                                        <a href="{{ route('facturas-pendientes.index') }}" class="btn btn-secondary mr-2">
                                            <i class="material-icons">clear</i> Limpiar
                                        </a>
                                        <button type="button" class="btn btn-success" onclick="exportarFacturas()">
                                            <i class="material-icons">file_download</i> Exportar Excel
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabla de Facturas Pendientes -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Facturas Pendientes</h4>
                        <p class="card-category">Lista de facturas pendientes de pago</p>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead class="text-danger">
                                    <tr>
                                        <th>
                                            <a href="{{ request()->fullUrlWithQuery(['ordenar_por' => 'TIPO_DOCTO', 'orden' => request('ordenar_por') == 'TIPO_DOCTO' && request('orden') == 'asc' ? 'desc' : 'asc']) }}" 
                                               class="text-decoration-none text-danger">
                                                Tipo
                                                @if(request('ordenar_por') == 'TIPO_DOCTO')
                                                    <i class="material-icons">{{ request('orden') == 'asc' ? 'keyboard_arrow_up' : 'keyboard_arrow_down' }}</i>
                                                @else
                                                    <i class="material-icons text-muted">unfold_more</i>
                                                @endif
                                            </a>
                                        </th>
                                        <th>
                                            <a href="{{ request()->fullUrlWithQuery(['ordenar_por' => 'NRO_DOCTO', 'orden' => request('ordenar_por') == 'NRO_DOCTO' && request('orden') == 'asc' ? 'desc' : 'asc']) }}" 
                                               class="text-decoration-none text-danger">
                                                Número
                                                @if(request('ordenar_por') == 'NRO_DOCTO')
                                                    <i class="material-icons">{{ request('orden') == 'asc' ? 'keyboard_arrow_up' : 'keyboard_arrow_down' }}</i>
                                                @else
                                                    <i class="material-icons text-muted">unfold_more</i>
                                                @endif
                                            </a>
                                        </th>
                                        <th>
                                            <a href="{{ request()->fullUrlWithQuery(['ordenar_por' => 'CLIENTE', 'orden' => request('ordenar_por') == 'CLIENTE' && request('orden') == 'asc' ? 'desc' : 'asc']) }}" 
                                               class="text-decoration-none text-danger">
                                                Cliente
                                                @if(request('ordenar_por') == 'CLIENTE')
                                                    <i class="material-icons">{{ request('orden') == 'asc' ? 'keyboard_arrow_up' : 'keyboard_arrow_down' }}</i>
                                                @else
                                                    <i class="material-icons text-muted">unfold_more</i>
                                                @endif
                                            </a>
                                        </th>
                                        <th>
                                            <a href="{{ request()->fullUrlWithQuery(['ordenar_por' => 'SALDO', 'orden' => request('ordenar_por') == 'SALDO' && request('orden') == 'asc' ? 'desc' : 'asc']) }}" 
                                               class="text-decoration-none text-danger">
                                                Saldo
                                                @if(request('ordenar_por') == 'SALDO')
                                                    <i class="material-icons">{{ request('orden') == 'asc' ? 'keyboard_arrow_up' : 'keyboard_arrow_down' }}</i>
                                                @else
                                                    <i class="material-icons text-muted">unfold_more</i>
                                                @endif
                                            </a>
                                        </th>
                                        <th>
                                            <a href="{{ request()->fullUrlWithQuery(['ordenar_por' => 'DIAS', 'orden' => request('ordenar_por') == 'DIAS' && request('orden') == 'asc' ? 'desc' : 'asc']) }}" 
                                               class="text-decoration-none text-danger">
                                                Días
                                                @if(request('ordenar_por') == 'DIAS')
                                                    <i class="material-icons">{{ request('orden') == 'asc' ? 'keyboard_arrow_up' : 'keyboard_arrow_down' }}</i>
                                                @else
                                                    <i class="material-icons text-muted">unfold_more</i>
                                                @endif
                                            </a>
                                        </th>
                                        <th>Estado</th>
                                        <th>Vencimiento</th>
                                        <th>Vendedor</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($facturasPendientes ?? [] as $factura)
                                    <tr>
                                        <td>
                                            <span class="badge badge-{{ 
                                                $factura['TIPO_DOCTO'] == 'FCV' ? 'success' : 
                                                ($factura['TIPO_DOCTO'] == 'FDV' ? 'info' : 'warning') 
                                            }}">
                                                {{ $factura['TIPO_DOCTO'] }}
                                            </span>
                                        </td>
                                        <td>{{ $factura['NRO_DOCTO'] }}</td>
                                        <td>{{ $factura['CLIENTE'] }}</td>
                                        <td>${{ number_format($factura['SALDO'], 0) }}</td>
                                        <td>
                                            <span class="badge badge-{{ 
                                                $factura['DIAS'] < 0 ? 'success' : 
                                                ($factura['DIAS'] < 8 ? 'warning' : 
                                                ($factura['DIAS'] < 31 ? 'danger' : 'dark')) 
                                            }}">
                                                {{ $factura['DIAS'] }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-{{ 
                                                ($factura['ESTADO'] ?? 'VIGENTE') == 'VIGENTE' ? 'success' : 
                                                (($factura['ESTADO'] ?? 'VIGENTE') == 'POR VENCER' ? 'warning' : 
                                                (($factura['ESTADO'] ?? 'VIGENTE') == 'VENCIDO' ? 'danger' : 
                                                (($factura['ESTADO'] ?? 'VIGENTE') == 'MOROSO' ? 'danger' : 'dark'))) 
                                            }}">
                                                {{ $factura['ESTADO'] ?? 'VIGENTE' }}
                                            </span>
                                        </td>
                                        <td>{{ $factura['U_VCMTO'] ?? 'N/A' }}</td>
                                        <td>{{ $factura['VENDEDOR'] ?? 'N/A' }}</td>
                                        <td>
                                            <a href="{{ route('facturas-pendientes.ver', [$factura['TIPO_DOCTO'], $factura['NRO_DOCTO']]) }}" 
                                               class="btn btn-sm btn-info" 
                                               title="Ver detalles">
                                                <i class="material-icons">visibility</i>
                                            </a>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="9" class="text-center">No hay facturas pendientes</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Paginación -->
                        @if($facturasPendientes instanceof \Illuminate\Pagination\LengthAwarePaginator && $facturasPendientes->hasPages())
                            <div class="card-footer">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="text-muted">
                                        Mostrando {{ $facturasPendientes->firstItem() }} a {{ $facturasPendientes->lastItem() }} de {{ $facturasPendientes->total() }} registros
                                    </div>
                                    <div>
                                        {{ $facturasPendientes->appends(request()->query())->links() }}
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

@push('css')
<style>
    /* Estilo para select múltiple personalizado */
    .select-multiple-wrapper {
        position: relative;
    }
    
    select.select-multiple-custom {
        height: 38px !important;
        min-height: 38px !important;
        max-height: 38px !important;
        overflow: hidden !important;
        padding: 8px 12px;
        transition: max-height 0.3s ease;
    }
    
    select.select-multiple-custom:focus {
        height: auto !important;
        max-height: 200px !important;
        overflow-y: auto !important;
        position: absolute;
        z-index: 1000;
        width: 100%;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.3);
    }
    
    select.select-multiple-custom option {
        padding: 8px 12px;
        cursor: pointer;
        white-space: nowrap;
    }
    
    select.select-multiple-custom option:checked {
        background-color: #1e88e5 !important;
        color: #ffffff !important;
    }
    
    /* Placeholder visual cuando está colapsado */
    select.select-multiple-custom[data-selected-count="0"]::before {
        content: attr(data-placeholder);
        color: #999;
    }
    
    /* Estilos para botones - evitar cortes de texto */
    .btn {
        white-space: nowrap !important;
        overflow: visible !important;
        text-overflow: clip !important;
        min-width: auto !important;
        padding-left: 12px !important;
        padding-right: 12px !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
    }
    
    .btn .material-icons {
        margin-right: 4px;
        font-size: 18px;
        line-height: 1;
        vertical-align: middle;
    }
    
    .btn i.material-icons:only-child {
        margin-right: 0;
    }
    
    /* Ajustar botones dentro de col-md-auto */
    .col-md-auto .btn {
        width: auto !important;
        min-width: fit-content;
    }
    
    /* Asegurar que los botones con texto e ícono se vean bien */
    .btn .material-icons + span,
    .btn .material-icons + text {
        margin-left: 4px;
    }
</style>
@endpush

@push('js')
<script>
function exportarFacturas() {
    // Obtener los filtros actuales
    const filtros = {
        buscar: document.getElementById('buscar').value,
        estado: document.getElementById('estado').value,
        tipo_documento: document.getElementById('tipo_documento').value,
        cliente: document.getElementById('cliente').value,
        saldo_min: document.getElementById('saldo_min').value,
        saldo_max: document.getElementById('saldo_max').value,
        fecha_desde: document.getElementById('fecha_desde').value,
        fecha_hasta: document.getElementById('fecha_hasta').value
    };
    
    // Agregar vendedor si es administrador
    const vendedorSelect = document.getElementById('vendedor');
    if (vendedorSelect && vendedorSelect.value) {
        filtros.vendedor = vendedorSelect.value;
    }
    
    // Construir URL con filtros
    const params = new URLSearchParams(filtros);
    const url = '{{ route("facturas-pendientes.export") }}?' + params.toString();
    
    // Mostrar mensaje de carga simple
    const button = event.target;
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="material-icons">hourglass_empty</i> Exportando...';
    button.disabled = true;
    
    // Realizar petición AJAX
    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Descargar el archivo directamente
                window.location.href = data.download_url;
                
                // Mostrar mensaje de éxito
                alert('¡Exportación completada! Se exportaron ' + data.total_registros + ' registros');
            } else {
                alert('Error: No se pudo exportar el archivo');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Ocurrió un error durante la exportación');
        })
        .finally(() => {
            // Restaurar el botón
            button.innerHTML = originalText;
            button.disabled = false;
        });
}
</script>
@endpush

@endsection
