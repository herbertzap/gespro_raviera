@extends('layouts.app')

@section('title', 'Nueva Nota de Venta / Cotización')

@push('css')
<style>
    /* Fix específico para el problema de scroll en cotizaciones */
    html, body {
        overflow-y: auto !important;
        height: auto !important;
        min-height: 100vh !important;
        max-height: none !important;
        overflow: auto !important;
    }
    
    .wrapper {
        height: auto !important;
        min-height: 100vh !important;
        max-height: none !important;
        overflow: visible !important;
    }
    
    .main-panel {
        height: auto !important;
        min-height: auto !important;
        max-height: none !important;
        overflow: visible !important;
    }
    
    /* Sobrescribir el CSS específico del dashboard que causa el problema */
    .main-panel > .content {
        height: auto !important;
        min-height: auto !important;
        max-height: none !important;
        overflow: visible !important;
        overflow-y: visible !important;
        overflow-x: visible !important;
        /* Mantener padding original del dashboard */
        padding: 78px 30px 30px 280px;
    }
    
    .content {
        height: auto !important;
        min-height: auto !important;
        max-height: none !important;
        overflow: visible !important;
        overflow-y: visible !important;
        overflow-x: visible !important;
    }
    
    .container-fluid {
        height: auto !important;
        overflow: visible !important;
    }
    
    /* Asegurar que las tarjetas no interfieran con el scroll */
    .card {
        overflow: visible !important;
    }
    
    /* Fix para el navbar fijo */
    .navbar {
        position: relative !important;
    }
    
    /* Asegurar que el scroll funcione en todos los elementos */
    * {
        box-sizing: border-box;
    }
    
    /* Fix adicional específico para cotizaciones - Forzar scroll en body */
    body {
        overflow: auto !important;
        overflow-y: auto !important;
        height: auto !important;
    }
    
    html {
        overflow: auto !important;
        overflow-y: auto !important;
        height: auto !important;
    }
    
    /* Forzar que el contenido no tenga scroll interno */
    .main-panel > .content {
        overflow: visible !important;
        overflow-y: visible !important;
        overflow-x: visible !important;
        height: auto !important;
        max-height: none !important;
    }
    
    /* Asegurar que el wrapper permita scroll */
    .wrapper {
        overflow: visible !important;
        height: auto !important;
    }
    
    /* Asegurar que las tarjetas no interfieran con el scroll */
    .card {
        overflow: visible !important;
    }
    
    /* Fix para el navbar fijo */
    .navbar {
        position: relative !important;
    }
    
    /* Asegurar que el scroll funcione en todos los elementos */
    * {
        box-sizing: border-box;
    }
</style>
@endpush

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header card-header-warning">
                    <h4 class="card-title">
                        <i class="material-icons">add_shopping_cart</i>
                        <span id="titulo-documento">{{ request('tipo_documento') === 'nota_venta' ? 'Nueva Nota de Venta' : 'Nueva Cotización' }}</span>
                    </h4>
                </div>
                <div class="card-body">
                    <!-- Selector de Tipo de Documento -->
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <div class="card card-header-info">
                                <div class="card-header">
                                    <h4 class="card-title">
                                        <i class="material-icons">description</i>
                                        Tipo de Documento
                                    </h4>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-check form-check-radio">
                                                <label class="form-check-label">
                                                    <input class="form-check-input" type="radio" name="tipo_documento" id="tipo_nota_venta" value="nota_venta" checked>
                                                    <span class="circle"></span>
                                                    <span class="check"></span>
                                                    <strong>Nota de Venta</strong> - Requiere aprobaciones (Supervisor, Compras, Picking) y genera documento en el sistema
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-check form-check-radio">
                                                <label class="form-check-label">
                                                    <input class="form-check-input" type="radio" name="tipo_documento" id="tipo_cotizacion" value="cotizacion">
                                                    <span class="circle"></span>
                                                    <span class="check"></span>
                                                    <strong>Cotización</strong> - Para enviar al cliente, sin aprobaciones. Se puede convertir a Nota de Venta luego
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

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
                                    <div class="row mt-2" id="filaEntregaSucursal" style="display:none;">
                                        <div class="col-md-12">
                                            <small class="text-muted">Entrega / despacho</small>
                                            <p class="mb-1" id="cliente_entrega_resumen"></p>
                                            <button type="button" class="btn btn-sm btn-info" id="btnCambiarSucursal" style="display:none;">
                                                <i class="material-icons" style="font-size:18px;vertical-align:middle;">swap_horiz</i> Cambiar sucursal
                                            </button>
                                        </div>
                                    </div>
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
                                        {{ request('tipo_documento') === 'nota_venta' ? 'Detalle de la Nota de Venta' : 'Detalle de la Cotización' }}
                                    </h4>
                                    <p class="card-category">Agregar productos a la cotización</p>
                                </div>
                                <div class="card-body">
                                    <!-- Buscador de Productos Mejorado -->
                                    <div class="row mb-3">
                                        <div class="col-md-7">
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
                                        <div class="col-md-2">
                                            <button type="button" class="btn btn-success btn-block" onclick="limpiarBusqueda()">
                                                <i class="material-icons">clear</i> Limpiar
                                            </button>
                                        </div>
                                        <div class="col-md-3">
                                            <form action="{{ route('cotizacion.sincronizar-stock-simple') }}" method="POST" id="formSincronizarStock" style="margin: 0;">
                                                @csrf
                                                <button type="button" class="btn btn-warning btn-block" onclick="sincronizarStock()" id="btnSincronizarStock">
                                                    <i class="material-icons">refresh</i> Sincronizar Productos
                                                </button>
                                            </form>
                                        </div>
                                    </div>

                                    <!-- Modal de Producto Oculto -->
                                    <div class="modal fade" id="modalProductoOculto" tabindex="-1" role="dialog" aria-labelledby="modalProductoOcultoLabel" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered" role="document">
                                            <div class="modal-content">
                                                <div class="modal-header bg-danger">
                                                    <h5 class="modal-title" id="modalProductoOcultoLabel">
                                                        <i class="material-icons">visibility_off</i> Producto Oculto
                                                    </h5>
                                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                        <span aria-hidden="true">&times;</span>
                                                    </button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="alert alert-warning">
                                                        <i class="material-icons">warning</i>
                                                        <strong>El producto <span id="codigoProductoOculto"></span> se encuentra oculto en el sistema.</strong>
                                                    </div>
                                                    <p><strong>Nombre del producto:</strong> <span id="nombreProductoOculto"></span></p>
                                                    <p class="text-muted">Por favor, seleccione otro producto disponible.</p>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                                                        <i class="material-icons">close</i> Cerrar
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Modal de Sincronización -->
                                    <div class="modal fade" id="modalSincronizacion" tabindex="-1" role="dialog" aria-labelledby="modalSincronizacionLabel" aria-hidden="true" data-backdrop="static" data-keyboard="false">
                                        <div class="modal-dialog modal-dialog-centered" role="document">
                                            <div class="modal-content">
                                                <div class="modal-header bg-warning">
                                                    <h5 class="modal-title" id="modalSincronizacionLabel">
                                                        <i class="material-icons">refresh</i> Sincronizando Stock
                                                    </h5>
                                                </div>
                                                <div class="modal-body text-center">
                                                    <div class="spinner-border text-warning" role="status" style="width: 3rem; height: 3rem;">
                                                        <span class="sr-only">Sincronizando...</span>
                                                    </div>
                                                    <h5 class="mt-3" id="mensajeSincronizacion">Sincronizando productos desde SQL Server...</h5>
                                                    <p class="text-muted" id="detalleSincronizacion">Por favor, espere. Esto puede tomar unos minutos.</p>
                                                    <div class="progress mt-3" style="height: 25px;">
                                                        <div class="progress-bar progress-bar-striped progress-bar-animated bg-warning" role="progressbar" style="width: 100%" id="progressBar">
                                                            <span id="progressText">Procesando...</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
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
                                            <span class="badge badge-info" id="contadorProductos">0/20 productos</span>
                                        </div>
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
                                            
                                            <!-- Campo fecha de despacho oculto (no requerido) -->
                                            <input type="hidden" id="fecha_despacho" name="fecha_despacho" value="{{ date('Y-m-d') }}">
                                            <div class="form-group" style="display:none;">
                                                <label for="fecha_despacho">Fecha de Despacho</label>
                                                <input type="date" class="form-control" value="{{ date('Y-m-d') }}" disabled>
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
                                                            <h5 id="subtotal-neto">$0</h5>
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
                                            <button type="button" id="btnGuardarNotaVenta" class="btn btn-success btn-lg" onclick="guardarNotaVenta()" {{ !$puedeGenerarNotaVenta ? 'disabled' : '' }}>
                                                <i class="material-icons">save</i> Guardar Cotización
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

<div class="modal fade" id="modalSucursalCliente" tabindex="-1" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="material-icons" style="vertical-align:middle;">business</i> Sucursal de entrega</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <p class="text-muted">Este cliente tiene más de una sucursal. Elija la dirección de despacho para este documento.</p>
                <div class="form-group">
                    <label for="selectSucursalCliente">Sucursal</label>
                    <select class="form-control" id="selectSucursalCliente"></select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="btnConfirmarSucursalCliente">Confirmar</button>
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
window.__sucursalesClienteListo = false;
window.__requiereElegirSucursal = false;
window.__sucursalElegida = true;
window.__listaSucursalesApi = [];

function codigoClienteActual() {
    if (!clienteData) return '';
    return String(clienteData.codigo_cliente || clienteData.codigo || '').trim();
}
function normalizarClienteDataKeys() {
    if (!clienteData) return;
    if (!clienteData.codigo_cliente && clienteData.codigo) clienteData.codigo_cliente = clienteData.codigo;
    if (!clienteData.nombre_cliente && clienteData.nombre) clienteData.nombre_cliente = clienteData.nombre;
    if (clienteData.direccion === undefined && clienteData.cliente_direccion_entrega) clienteData.direccion = clienteData.cliente_direccion_entrega;
}
function aplicarSucursalSeleccionada(s, desdeModal) {
    if (!clienteData || !s) return;
    clienteData.cliente_suen = (s.suen !== undefined && s.suen !== null) ? String(s.suen) : '';
    const baseDir = clienteData.direccion || '';
    const baseTel = clienteData.telefono || '';
    clienteData.cliente_direccion_entrega = (s.direccion != null && String(s.direccion).trim() !== '') ? s.direccion : baseDir;
    clienteData.cliente_telefono_entrega = (s.telefono != null && String(s.telefono).trim() !== '') ? s.telefono : baseTel;
    let txt = clienteData.cliente_direccion_entrega || '';
    if (s.comuna) txt += (txt ? ' — ' : '') + s.comuna;
    if (s.region) txt += (txt ? ' — ' : '') + s.region;
    const res = document.getElementById('cliente_entrega_resumen');
    const fila = document.getElementById('filaEntregaSucursal');
    if (res) res.textContent = txt || '—';
    if (fila) fila.style.display = 'block';
    window.__sucursalElegida = true;
    if (desdeModal && window.jQuery) {
        jQuery('#modalSucursalCliente').modal('hide');
    }
}
function inicializarSucursalesCliente() {
    const codigo = codigoClienteActual();
    normalizarClienteDataKeys();
    if (!codigo) {
        window.__sucursalesClienteListo = true;
        return;
    }
    fetch('/api/clientes/' + encodeURIComponent(codigo) + '/sucursales', {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            window.__sucursalesClienteListo = true;
            if (!data.success || !data.sucursales || !data.sucursales.length) return;
            window.__listaSucursalesApi = data.sucursales;
            const sel = document.getElementById('selectSucursalCliente');
            const btnCambiar = document.getElementById('btnCambiarSucursal');
            if (sel) {
                sel.innerHTML = '';
                data.sucursales.forEach(function(s, idx) {
                    const opt = document.createElement('option');
                    opt.value = String(idx);
                    opt.textContent = s.etiqueta + (s.direccion ? ' — ' + s.direccion : '');
                    sel.appendChild(opt);
                });
            }
            if (data.multiple) {
                window.__requiereElegirSucursal = true;
                window.__sucursalElegida = false;
                if (btnCambiar) btnCambiar.style.display = 'inline-block';
                if (window.jQuery) jQuery('#modalSucursalCliente').modal('show');
            } else {
                aplicarSucursalSeleccionada(data.sucursales[0], false);
                if (btnCambiar) btnCambiar.style.display = 'none';
            }
        })
        .catch(function() {
            window.__sucursalesClienteListo = true;
        });
}

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
    contenido += '<thead class="thead"><tr><th><input type="checkbox" id="selectAllProductos" onchange="toggleAllProductos()"></th><th>Código</th><th>Producto</th><th>Precio</th><th>Acción</th></tr></thead><tbody>';
    
    productos.forEach(producto => {
        // Usar información de stock mejorada (se usará al agregar, pero no se muestra en la búsqueda)
        const stockReal = producto.STOCK_DISPONIBLE_REAL !== undefined ? producto.STOCK_DISPONIBLE_REAL : producto.STOCK_DISPONIBLE;
        
        // Verificar si el producto se puede agregar (precio válido y no oculto)
        const productoOculto = producto.ES_OCULTO === true || producto.ES_OCULTO === 'true';
        const precioValido = producto.PRECIO_VALIDO !== undefined ? producto.PRECIO_VALIDO : (producto.PRECIO_UD1 > 0);
        const puedeAgregar = precioValido && !productoOculto;
        const motivoBloqueo = productoOculto ? 'Producto oculto en el sistema' : (producto.MOTIVO_BLOQUEO || (precioValido ? null : 'Precio no disponible'));
        
        // Determinar clases y estilos según el estado del producto
        const rowClass = !puedeAgregar ? 'table-secondary' : '';
        const checkboxDisabled = !puedeAgregar ? 'disabled' : '';
        const buttonClass = puedeAgregar ? 'btn-primary' : 'btn-secondary';
        const buttonDisabled = !puedeAgregar ? 'disabled' : '';
        const buttonText = productoOculto ? 'Oculto' : (precioValido ? 'Agregar' : 'Sin precio');
        const buttonIcon = productoOculto ? 'visibility_off' : (precioValido ? 'add_shopping_cart' : 'block');
        
        const multiploVenta = producto.MULTIPLO_VENTA || 1;
        const multiploInfo = multiploVenta > 1 ? `<br><small class="text-info">Múltiplo: ${multiploVenta}</small>` : '';
        
        // Usar data attributes en lugar de onclick para evitar problemas con comillas
        const productoData = {
            codigo: producto.CODIGO_PRODUCTO,
            nombre: producto.NOMBRE_PRODUCTO,
            precio: producto.PRECIO_UD1 || 0,
            stock: stockReal || 0,
            unidad: producto.UNIDAD_MEDIDA || 'UN',
            descuentoMaximo: producto.DESCUENTO_MAXIMO || 0,
            multiplo: multiploVenta || 1,
            oculto: productoOculto
        };
        
        contenido += `
            <tr class="${rowClass}">
                <td><input type="checkbox" class="producto-checkbox" value="${producto.CODIGO_PRODUCTO}" 
                    data-multiplo="${multiploVenta}" 
                    data-descuento-maximo="${producto.DESCUENTO_MAXIMO || 0}"
                    onchange="actualizarContadorSeleccionados()" ${checkboxDisabled}></td>
                <td><strong>${producto.CODIGO_PRODUCTO || ''}</strong></td>
                <td>${producto.NOMBRE_PRODUCTO || ''}${multiploInfo}</td>
                <td>
                    <strong class="${!precioValido ? 'text-muted' : ''}" data-precio="${producto.PRECIO_UD1 || 0}">$${parseFloat(producto.PRECIO_UD1 || 0).toLocaleString('es-CL', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</strong>
                    ${!precioValido ? '<br><small class="text-danger"><i class="material-icons">warning</i> Precio no disponible</small>' : ''}
                    ${productoOculto ? '<br><small class="text-danger"><i class="material-icons">visibility_off</i> Producto oculto</small>' : ''}
                </td>
                <td>
                    <button class="btn btn-sm ${buttonClass} btn-agregar-producto" 
                        data-producto='${JSON.stringify(productoData)}'
                        ${buttonDisabled} 
                        title="${motivoBloqueo || ''}">
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
    
    // Configurar event listeners para los botones de agregar producto (evita problemas con comillas en onclick)
    document.querySelectorAll('.btn-agregar-producto').forEach(button => {
        button.addEventListener('click', function() {
            if (this.disabled) return;
            
            try {
                const productoData = JSON.parse(this.getAttribute('data-producto'));
                
                if (productoData.oculto) {
                    mostrarModalProductoOculto(productoData.codigo, productoData.nombre);
                } else if (productoData.precio > 0) {
                    agregarProductoDesdePHP(
                        productoData.codigo,
                        productoData.nombre,
                        productoData.precio,
                        productoData.stock,
                        productoData.unidad,
                        productoData.descuentoMaximo,
                        productoData.multiplo
                    );
                } else {
                    alert('Este producto no tiene precio disponible');
                }
            } catch (e) {
                console.error('Error al procesar producto:', e);
                alert('Error al agregar el producto');
            }
        });
    });
    
    document.getElementById('resultadosBusqueda').style.display = 'block';
}

// Función para mostrar modal de producto oculto
function mostrarModalProductoOculto(codigo, nombre) {
    document.getElementById('codigoProductoOculto').textContent = codigo;
    document.getElementById('nombreProductoOculto').textContent = nombre;
    $('#modalProductoOculto').modal('show');
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

// Función para sincronizar stock desde SQL Server
function sincronizarStock() {
    const btn = document.getElementById('btnSincronizarStock');
    const form = document.getElementById('formSincronizarStock');
    
    if (!btn || !form) {
        console.error('Error: No se encontró el botón o formulario de sincronización');
        mostrarMensaje('error', 'Error: No se encontró el formulario de sincronización');
        return;
    }
    
    // Confirmar antes de sincronizar
    if (!confirm('¿Está seguro de que desea sincronizar el stock de productos desde SQL Server? Esto puede tomar varios minutos si hay muchos productos.')) {
        return;
    }
    
    const originalText = btn.innerHTML;
    
    // Deshabilitar botón y mostrar estado de carga
    btn.disabled = true;
    btn.innerHTML = '<i class="material-icons">hourglass_empty</i> Sincronizando...';
    
    // Mostrar modal de sincronización
    const modal = document.getElementById('modalSincronizacion');
    const mensajeSincronizacion = document.getElementById('mensajeSincronizacion');
    const detalleSincronizacion = document.getElementById('detalleSincronizacion');
    const progressText = document.getElementById('progressText');
    
    if (modal) {
        mensajeSincronizacion.textContent = 'Sincronizando productos desde SQL Server...';
        detalleSincronizacion.textContent = 'Por favor, espere. Esto puede tomar varios minutos.';
        progressText.textContent = 'Iniciando sincronización...';
        
        // Mostrar el modal y asegurar que aria-hidden esté correcto
        $(modal).modal({
            backdrop: 'static',
            keyboard: false,
            show: true
        });
        
        // Asegurar que aria-hidden esté en false cuando el modal esté visible
        $(modal).on('shown.bs.modal', function() {
            $(this).attr('aria-hidden', 'false');
        });
    }
    
    // Crear form data
    const formData = new FormData(form);
    
    // Iniciar tiempo de sincronización
    const startTime = Date.now();
    
    fetch('/cotizacion/sincronizar-stock', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        }
    })
    .then(response => {
        if (!response.ok) {
            return response.text().then(text => {
                console.error('Error response:', text);
                throw new Error(`HTTP error! status: ${response.status} - ${text}`);
            });
        }
        // Verificar si la respuesta es JSON
        const contentType = response.headers.get('content-type');
        if (contentType && contentType.includes('application/json')) {
            return response.json();
        } else {
            return response.text().then(text => {
                console.error('Response is not JSON:', text);
                throw new Error('La respuesta del servidor no es JSON válido');
            });
        }
    })
    .then(data => {
        const elapsedTime = ((Date.now() - startTime) / 1000).toFixed(0);
        
        // Cerrar modal
        if (modal) {
            $(modal).modal('hide');
            $(modal).attr('aria-hidden', 'true');
        }
        
        if (data.success) {
            // Mostrar mensaje de éxito con detalles
            const productosSync = data.productos_sincronizados || 0;
            const mensaje = `Stock sincronizado exitosamente.\n${productosSync} productos actualizados.\nTiempo: ${elapsedTime} segundos`;
            mostrarMensaje('success', mensaje);
            
            // Recargar la búsqueda si hay un término de búsqueda activo
            const buscarInput = document.getElementById('buscarProducto');
            if (buscarInput) {
                const busqueda = buscarInput.value.trim();
                if (busqueda.length >= 3) {
                    setTimeout(() => {
                        buscarProductosAjax();
                    }, 1000);
                }
            }
        } else {
            mostrarMensaje('error', data.message || 'Error al sincronizar stock');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        
        // Cerrar modal
        if (modal) {
            $(modal).modal('hide');
            $(modal).attr('aria-hidden', 'true');
        }
        
        mostrarMensaje('error', 'Error al sincronizar stock: ' + (error.message || 'Por favor, intente nuevamente.'));
    })
    .finally(() => {
        // Restaurar botón
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    });
}

// Función para mostrar mensajes de éxito o error
function mostrarMensaje(tipo, mensaje) {
    const alertClass = tipo === 'success' ? 'alert-success' : 'alert-danger';
    const icon = tipo === 'success' ? 'check_circle' : 'error';
    
    // Crear elemento de alerta
    const alert = document.createElement('div');
    alert.className = `alert ${alertClass} alert-dismissible fade show`;
    alert.style.position = 'fixed';
    alert.style.top = '20px';
    alert.style.right = '20px';
    alert.style.zIndex = '9999';
    alert.style.minWidth = '300px';
    alert.innerHTML = `
        <i class="material-icons">${icon}</i>
        ${mensaje}
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    `;
    
    document.body.appendChild(alert);
    
    // Auto-eliminar después de 5 segundos
    setTimeout(() => {
        alert.remove();
    }, 5000);
}

// Función de prueba para verificar que el JavaScript se carga
function testJavaScript() {
    console.log('JavaScript cargado correctamente');
    alert('JavaScript funcionando');
}

// Función para mostrar modal de lista de precios
function mostrarModalListaPrecios() {
    const clienteInfo = clienteData ? `Código: ${clienteData.codigo_cliente}<br>Nombre: ${clienteData.nombre_cliente}` : '';
    
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

// ========================================
// SISTEMA DE AUTO-GUARDADO EN LOCALSTORAGE
// ========================================
const STORAGE_KEY = 'nvv_borrador_cliente_{{ $clienteData['CODIGO_CLIENTE'] ?? 'temp' }}';

// Guardar productos en LocalStorage
function guardarBorradorLocal() {
    try {
        const borrador = {
            productos: productosCotizacion,
            observaciones: document.getElementById('observaciones')?.value || '',
            fecha_despacho: document.getElementById('fecha_despacho')?.value || '',
            numero_orden_compra: document.getElementById('numero_orden_compra')?.value || '',
            observacion_vendedor: document.getElementById('observacion_vendedor')?.value || '',
            tipo_documento: document.querySelector('input[name="tipo_documento"]:checked')?.value || 'nota_venta',
            timestamp: new Date().toISOString(),
            cliente_codigo: '{{ $clienteData['CODIGO_CLIENTE'] ?? '' }}',
            cliente_nombre: '{{ $clienteData['NOMBRE_CLIENTE'] ?? '' }}'
        };
        localStorage.setItem(STORAGE_KEY, JSON.stringify(borrador));
        console.log('💾 Borrador guardado automáticamente');
    } catch (error) {
        console.error('Error guardando borrador:', error);
    }
}

// Cargar productos desde LocalStorage
function cargarBorradorLocal() {
    try {
        const borradorStr = localStorage.getItem(STORAGE_KEY);
        if (borradorStr) {
            const borrador = JSON.parse(borradorStr);
            
            // Verificar que sea del mismo cliente
            if (borrador.cliente_codigo === '{{ $clienteData['CODIGO_CLIENTE'] ?? '' }}') {
                // Preguntar al usuario si desea recuperar el borrador
                const fechaBorrador = new Date(borrador.timestamp).toLocaleString('es-CL');
                if (confirm(`📋 Se encontró un borrador guardado el ${fechaBorrador}\n\n¿Deseas recuperarlo?\n\nProductos: ${borrador.productos.length}`)) {
                    productosCotizacion = borrador.productos;
                    
                    // Restaurar observaciones y fecha
                    if (borrador.observaciones) {
                        document.getElementById('observaciones').value = borrador.observaciones;
                    }
                    if (borrador.fecha_despacho) {
                        document.getElementById('fecha_despacho').value = borrador.fecha_despacho;
                    }
                    if (borrador.numero_orden_compra) {
                        document.getElementById('numero_orden_compra').value = borrador.numero_orden_compra;
                    }
                    if (borrador.observacion_vendedor) {
                        document.getElementById('observacion_vendedor').value = borrador.observacion_vendedor;
                    }
                    if (borrador.tipo_documento) {
                        document.getElementById(`tipo_${borrador.tipo_documento}`).checked = true;
                        // Actualizar título del documento
                        const tituloDocumento = document.getElementById('titulo-documento');
                        if (tituloDocumento) {
                            tituloDocumento.textContent = borrador.tipo_documento === 'nota_venta' ? 'Nueva Nota de Venta' : 'Nueva Cotización';
                        }
                    }
                    
                    actualizarTablaProductos();
                    calcularTotales();
                    console.log('✅ Borrador recuperado correctamente');
                } else {
                    // Si no quiere recuperarlo, limpiarlo
                    limpiarBorradorLocal();
                }
            }
        }
    } catch (error) {
        console.error('Error cargando borrador:', error);
    }
}

// Limpiar borrador de LocalStorage
function limpiarBorradorLocal() {
    try {
        localStorage.removeItem(STORAGE_KEY);
        console.log('🗑️ Borrador eliminado');
    } catch (error) {
        console.error('Error limpiando borrador:', error);
    }
}

// Auto-guardar cada 30 segundos
setInterval(function() {
    if (productosCotizacion.length > 0) {
        guardarBorradorLocal();
    }
}, 30000);

// Función para agregar producto a la cotización desde PHP
function agregarProductoDesdePHP(codigo, nombre, precio, stock, unidad, descuentoMaximo = 0, multiplo = 1) {
    console.log('Agregando producto desde PHP:', { codigo, nombre, precio, stock, unidad, descuentoMaximo, multiplo });
    
    // Verificar si el producto ya está en la cotización
    const productoExistente = productosCotizacion.find(p => p.codigo === codigo);
    
    if (productoExistente) {
        // Incrementar cantidad según el múltiplo del producto (no usar Math.max)
        const incremento = multiplo > 0 ? multiplo : 1;
        productoExistente.cantidad += incremento;
        // Refrescar stock/precio por si el borrador viene viejo
        productoExistente.stock = parseFloat(stock) || 0;
        productoExistente.unidad = unidad || 'UN';
        productoExistente.precio = parseFloat(precio) || 0;
        productoExistente.descuentoMaximo = parseFloat(descuentoMaximo) || 0;
        productoExistente.multiplo = multiplo; // Actualizar múltiplo
        actualizarSubtotal(productosCotizacion.indexOf(productoExistente));
    } else {
        // Validar límite máximo de productos diferentes (24)
        if (productosCotizacion.length >= 24) {
            alert('No se pueden agregar más de 24 productos diferentes a la cotización.\n\nProductos actuales: ' + productosCotizacion.length + '/24');
            return;
        }
        
        // Agregar nuevo producto con cantidad inicial = múltiplo
        const cantidadInicial = multiplo > 0 ? multiplo : 1;
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
    guardarBorradorLocal(); // Auto-guardar después de agregar producto
    
    // Limpiar búsqueda después de agregar el producto
    limpiarBusqueda();
    
    // Mostrar mensaje de confirmación con información de stock y múltiplo
    const cantidadAgregada = multiplo > 0 ? multiplo : 1;
    const stockInfo = parseFloat(stock) <= 0 ? '\n⚠️ Sin stock - Se generará nota pendiente' : '';
    const multiploInfo = multiplo > 1 ? `\n📦 Se vende en múltiplos de ${multiplo} unidades` : '';
    const productosInfo = productosCotizacion.length > 1 ? `\n\n📋 Productos en cotización: ${productosCotizacion.length}/20` : '';
    alert('✅ Producto agregado\n\n' + nombre + '\nCantidad: ' + cantidadAgregada + ' ' + unidad + multiploInfo + stockInfo + productosInfo);
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
        const maximo = 20;
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
        
        // Determinar el step según el múltiplo del producto
        const multiplo = producto.multiplo || 1;
        const step = multiplo;
        
        // Agregar información del múltiplo si es > 1
        const multiploInfo = multiplo > 1 ? `<br><small class="text-info">Múltiplo: ${multiplo}</small>` : '';
        
        // Calcular valores para mostrar
        const precioBase = producto.precio * producto.cantidad;
        const descuentoPorcentaje = (producto.descuento || 0) / 100;
        const descuentoValor = precioBase * descuentoPorcentaje;
        const subtotalConDescuento = precioBase - descuentoValor;
        const ivaValor = subtotalConDescuento * 0.19;
        const totalConIva = subtotalConDescuento + ivaValor;

        const row = `
            <tr>
                <td>${producto.codigo}</td>
                <td>${producto.nombre}</td>
                <td>
                    <input type="number" class="form-control" value="${producto.cantidad}" step="${step}" min="${multiplo}"
                           onchange="actualizarCantidad(${index}, this.value)" style="width: 80px;">
                    <small class="text-muted">${producto.unidad}${multiploInfo}</small>
                </td>
                <td>$${Math.round(producto.precio).toLocaleString()}</td>
                <td>
                    <input type="number" class="form-control descuento-input" value="${producto.descuento || 0}" 
                           min="0" max="${producto.descuentoMaximo || 0}" step="0.01"
                           onchange="actualizarDescuento(${index}, this.value)" style="width: 80px;">
                    <small class="text-muted">Máx: ${producto.descuentoMaximo || 0}%</small>
                </td>
                <td class="text-danger">$${Math.round(descuentoValor).toLocaleString()}</td>
                <td>$${Math.round(subtotalConDescuento).toLocaleString()}</td>
                <td class="text-info">$${Math.round(ivaValor).toLocaleString()}</td>
                <td class="text-success font-weight-bold">$${Math.round(totalConIva).toLocaleString()}</td>
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
        guardarBorradorLocal(); // Auto-guardar después de actualizar cantidad
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
    guardarBorradorLocal(); // Auto-guardar después de actualizar descuento
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
    guardarBorradorLocal(); // Auto-guardar después de eliminar producto
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
        // Obtener el precio desde el atributo data-precio (está en cells[3], no cells[4])
        const precioElement = row.cells[3].querySelector('[data-precio]');
        const precio = precioElement ? parseFloat(precioElement.getAttribute('data-precio')) : 0;
        console.log('Precio desde data-precio:', precio, 'Elemento encontrado:', precioElement !== null);
        // El stock no está en la tabla de búsqueda, se obtiene del data-producto del botón o se usa 0
        const botonAgregar = row.cells[4].querySelector('[data-producto]');
        let stock = 0;
        let unidad = 'UN';
        if (botonAgregar) {
            try {
                const productoData = JSON.parse(botonAgregar.getAttribute('data-producto'));
                stock = productoData.stock || 0;
                unidad = productoData.unidad || 'UN';
            } catch(e) {
                console.error('Error parseando data-producto:', e);
            }
        }
        
        // Obtener múltiplo y descuento máximo desde los data attributes del checkbox
        const multiplo = parseInt(checkbox.getAttribute('data-multiplo')) || 1;
        const descuentoMaximo = parseFloat(checkbox.getAttribute('data-descuento-maximo')) || 0;
        
        // Verificar si el producto tiene precio válido
        const precioValido = precio > 0;
        
        if (precioValido) {
            productosSeleccionados.push({
                codigo: codigo,
                nombre: nombre,
                precio: precio,
                stock: stock,
                unidad: unidad,
                descuentoMaximo: descuentoMaximo,
                multiplo: multiplo
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
        if (productoExistente) {
            // Si ya existe, igual agregamos para refrescar stock/precio y subir cantidad
            agregarProductoDesdePHP(producto.codigo, producto.nombre, producto.precio, producto.stock, producto.unidad, producto.descuentoMaximo, producto.multiplo);
            productosAgregados++;
        } else if (productosCotizacion.length < 24) {
            agregarProductoDesdePHP(producto.codigo, producto.nombre, producto.precio, producto.stock, producto.unidad, producto.descuentoMaximo, producto.multiplo);
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
    if (totalProductos > 20) {
        mensaje += `\n\nAlgunos productos no se agregaron (límite de 20 productos)`;
    }
    alert(mensaje);
}

// Función para calcular totales
function calcularTotales() {
    // Calcular subtotal sin descuentos (precio base * cantidad)
    const subtotalSinDescuentos = productosCotizacion.reduce((sum, producto) => {
        return sum + (producto.precio * producto.cantidad);
    }, 0);
    
    // Calcular descuento total aplicado a todos los productos
    const descuentoTotal = productosCotizacion.reduce((sum, producto) => {
        const precioBase = producto.precio * producto.cantidad;
        const descuentoPorcentaje = (producto.descuento || 0) / 100;
        return sum + (precioBase * descuentoPorcentaje);
    }, 0);
    
    // Calcular subtotal final (con descuentos aplicados)
    const subtotalFinal = subtotalSinDescuentos - descuentoTotal;
    
    // Calcular IVA (19% sobre el subtotal con descuentos)
    const ivaTotal = subtotalFinal * 0.19;
    
    // Calcular total final (subtotal + IVA)
    const totalFinal = subtotalFinal + ivaTotal;
    
    document.getElementById('subtotal').textContent = '$' + Math.round(subtotalSinDescuentos).toLocaleString();
    document.getElementById('descuento').textContent = '$' + Math.round(descuentoTotal).toLocaleString();
    document.getElementById('subtotal-neto').textContent = '$' + Math.round(subtotalFinal).toLocaleString();
    document.getElementById('iva').textContent = '$' + Math.round(ivaTotal).toLocaleString();
    document.getElementById('total').textContent = '$' + Math.round(totalFinal).toLocaleString();
}

// Función para limpiar búsqueda
function limpiarBusqueda() {
    document.getElementById('buscarProducto').value = '';
    document.getElementById('resultadosBusqueda').style.display = 'none';
}

// Variable para controlar si ya se está procesando una solicitud
let guardandoNotaVenta = false;

// Función para obtener un token CSRF fresco
async function obtenerTokenCSRF() {
    try {
        const response = await fetch('/cotizacion/nueva', {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        if (response.ok) {
            const html = await response.text();
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newToken = doc.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            
            if (newToken) {
                // Actualizar el token en la página actual
                const currentToken = document.querySelector('meta[name="csrf-token"]');
                if (currentToken) {
                    currentToken.setAttribute('content', newToken);
                }
                return newToken;
            }
        }
    } catch (error) {
        console.error('Error obteniendo token CSRF:', error);
    }
    
    // Fallback al token actual
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
}

// Función para guardar nota de venta (con validación anti-doble clic)
async function guardarNotaVenta() {
    // Verificar si ya se está procesando
    if (guardandoNotaVenta) {
        alert('Ya se está procesando el documento, por favor espere...');
        return;
    }

    if (!window.__sucursalesClienteListo) {
        alert('Espere un momento, cargando datos del cliente...');
        return;
    }

    if (productosCotizacion.length === 0) {
        alert('Debes agregar al menos un producto');
        return;
    }

    if (!clienteData) {
        alert('No hay cliente seleccionado');
        return;
    }
    normalizarClienteDataKeys();
    if (clienteData.cliente_direccion_entrega === undefined) {
        clienteData.cliente_direccion_entrega = clienteData.direccion || '';
    }
    if (clienteData.cliente_telefono_entrega === undefined) {
        clienteData.cliente_telefono_entrega = clienteData.telefono || '';
    }
    if (window.__requiereElegirSucursal && !window.__sucursalElegida) {
        alert('Seleccione la sucursal de entrega.');
        if (window.jQuery) jQuery('#modalSucursalCliente').modal('show');
        return;
    }

    // Obtener tipo de documento seleccionado
    const tipoDocumentoElement = document.querySelector('input[name="tipo_documento"]:checked');
    if (!tipoDocumentoElement) {
        alert('Error: No se pudo determinar el tipo de documento');
        guardandoNotaVenta = false;
        btn.disabled = false;
        btn.innerHTML = originalText;
        return;
    }
    const tipoDocumento = tipoDocumentoElement.value;
    const esCotizacion = (tipoDocumento === 'cotizacion');
    
    // Marcar como procesando y deshabilitar botón
    guardandoNotaVenta = true;
    const btn = document.getElementById('btnGuardarNotaVenta');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="material-icons">hourglass_empty</i> Guardando...';

    const observaciones = document.getElementById('observaciones')?.value || '';
    const fechaDespacho = document.getElementById('fecha_despacho')?.value || '{{ date('Y-m-d') }}';
    const numeroOrdenCompra = document.getElementById('numero_orden_compra')?.value || '';
    const observacionVendedor = document.getElementById('observacion_vendedor')?.value || '';
    const solicitarDescuentoExtra = document.getElementById('solicitar_descuento_extra')?.checked || false;
    
    // Fecha de despacho no requiere validación (se usa fecha de creación)
    
    // Obtener token CSRF fresco
    const csrfToken = await obtenerTokenCSRF();
    
    const cotizacionData = {
        tipo_documento: tipoDocumento,
        cliente_codigo: clienteData.codigo_cliente,
        cliente_nombre: clienteData.nombre_cliente,
        productos: productosCotizacion,
        observaciones: observaciones,
        fecha_despacho: fechaDespacho,
        numero_orden_compra: numeroOrdenCompra,
        observacion_vendedor: observacionVendedor,
        solicitar_descuento_extra: solicitarDescuentoExtra,
        _token: csrfToken
    };
    if (clienteData.cliente_suen !== undefined && clienteData.cliente_suen !== null) {
        cotizacionData.cliente_suen = clienteData.cliente_suen;
    }
    cotizacionData.cliente_direccion_entrega = clienteData.cliente_direccion_entrega;
    cotizacionData.cliente_telefono_entrega = clienteData.cliente_telefono_entrega;

    fetch('/cotizacion/guardar', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(cotizacionData)
    })
    .then(response => {
        // Detectar error 419 (CSRF token expirado)
        if (response.status === 419) {
            alert('⚠️ Tu sesión ha expirado. La página se recargará automáticamente.');
            window.location.reload();
            return;
        }
        return response.json();
    })
    .then(data => {
        if (!data) return; // Si hubo error 419, ya se manejó arriba
        
        if (data.success) {
            const mensaje = esCotizacion 
                ? 'Cotización guardada exitosamente' 
                : 'Nota de venta guardada exitosamente';
            limpiarBorradorLocal(); // Limpiar borrador después de guardar exitosamente
            alert(mensaje);
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
        alert('Error al guardar el documento. Por favor, recarga la página e intenta nuevamente.');
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
    
    // Fix para el scroll - asegurar que funcione correctamente
    document.body.style.overflow = 'auto';
    document.body.style.height = 'auto';
    document.body.style.minHeight = '100vh';
    document.body.style.maxHeight = 'none';
    
    document.documentElement.style.overflow = 'auto';
    document.documentElement.style.height = 'auto';
    document.documentElement.style.minHeight = '100vh';
    document.documentElement.style.maxHeight = 'none';
    
    // Remover cualquier altura fija que pueda estar causando problemas
    const mainPanel = document.querySelector('.main-panel');
    if (mainPanel) {
        mainPanel.style.height = 'auto';
        mainPanel.style.minHeight = 'auto';
        mainPanel.style.maxHeight = 'none';
        mainPanel.style.overflow = 'visible';
    }
    
    const content = document.querySelector('.content');
    if (content) {
        content.style.height = 'auto';
        content.style.minHeight = 'auto';
        content.style.maxHeight = 'none';
        content.style.overflow = 'visible';
    }
    
    // Forzar que el wrapper no tenga altura fija (solo en páginas de cotizaciones)
    if (window.location.pathname.includes('/cotizacion/') || window.location.pathname.includes('/nota-venta/')) {
        const wrapper = document.querySelector('.wrapper');
        if (wrapper) {
            wrapper.style.height = 'auto';
            wrapper.style.minHeight = '100vh';
            wrapper.style.maxHeight = 'none';
            wrapper.style.overflow = 'visible';
        }
    }
    
    // Aplicar estilos después de un pequeño delay para asegurar que se apliquen
    setTimeout(() => {
        document.body.style.overflow = 'auto';
        document.documentElement.style.overflow = 'auto';
        if (mainPanel) mainPanel.style.overflow = 'visible';
        if (content) content.style.overflow = 'visible';
        if (window.location.pathname.includes('/cotizacion/') || window.location.pathname.includes('/nota-venta/')) {
            const wrapperElement = document.querySelector('.wrapper');
            if (wrapperElement) {
                wrapperElement.style.overflow = 'visible';
            }
        }
    }, 100);
    
    // Cargar borrador guardado si existe
    cargarBorradorLocal();
    
    // Verificar si el cliente tiene lista de precios
    if (clienteData && (!clienteData.lista_precios_codigo || clienteData.lista_precios_codigo === '00' || clienteData.lista_precios_codigo === '0')) {
        console.warn('⚠️ Cliente sin lista de precios asignada, usando lista por defecto');
        // Asignar lista por defecto
        clienteData.lista_precios_codigo = '01P';
        clienteData.lista_precios_nombre = 'Lista Precios 01P';
    }
    
    console.log('🔍 Lista de precios final:', clienteData?.lista_precios_codigo);

    const btnConfirmSuc = document.getElementById('btnConfirmarSucursalCliente');
    if (btnConfirmSuc) {
        btnConfirmSuc.addEventListener('click', function() {
            const sel = document.getElementById('selectSucursalCliente');
            const idx = sel ? parseInt(sel.value, 10) : 0;
            const lista = window.__listaSucursalesApi || [];
            if (lista[idx]) aplicarSucursalSeleccionada(lista[idx], true);
        });
    }
    const btnCambiarSuc = document.getElementById('btnCambiarSucursal');
    if (btnCambiarSuc) {
        btnCambiarSuc.addEventListener('click', function() {
            if (window.jQuery) jQuery('#modalSucursalCliente').modal('show');
        });
    }
    inicializarSucursalesCliente();
    
    // Configurar cambio de tipo de documento
    const radioNotaVenta = document.getElementById('tipo_nota_venta');
    const radioCotizacion = document.getElementById('tipo_cotizacion');
    const tituloDocumento = document.getElementById('titulo-documento');
    const btnGuardar = document.getElementById('btnGuardarNotaVenta');
    
    if (radioNotaVenta && radioCotizacion && tituloDocumento) {
        radioNotaVenta.addEventListener('change', function() {
            if (this.checked) {
                tituloDocumento.textContent = 'Nueva Nota de Venta';
                if (btnGuardar) btnGuardar.innerHTML = '<i class="material-icons">save</i> Guardar Nota de Venta';
            }
        });
        
        radioCotizacion.addEventListener('change', function() {
            if (this.checked) {
                tituloDocumento.textContent = (new URLSearchParams(window.location.search).get('tipo_documento') === 'nota_venta') ? 'Nueva Nota de Venta' : 'Nueva Cotización';
                if (btnGuardar) btnGuardar.innerHTML = '<i class="material-icons">save</i> Guardar Cotización';
            }
        });
    }
    
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
    if (!clienteData || !clienteData.codigo_cliente) {
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
            codigo_cliente: clienteData.codigo_cliente
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.data.tiene_cheques_protestados) {
            let mensaje = `CHEQUES PROTESTADOS - ${clienteData.nombre_cliente}\n\n`;
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