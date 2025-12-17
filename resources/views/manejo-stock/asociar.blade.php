@extends('layouts.app', ['pageSlug' => 'manejo-stock'])

@section('content')
<div class="content">
    <div class="row">
        <div class="col-md-10 offset-md-1">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="card-title mb-0">Asociar código de barras</h4>
                        <small>
                            Código detectado: <strong>{{ $barcode }}</strong>
                            @if($bodega)
                                &nbsp;|&nbsp; Bodega: <strong>{{ $bodega->nombre_bodega }} ({{ $bodega->kobo }})</strong>
                            @endif
                            @if($ubicacion)
                                &nbsp;|&nbsp; Ubicación: <strong>{{ $ubicacion->codigo }}</strong>
                            @endif
                        </small>
                    </div>
                    @if(auth()->user()->hasRole('Barrido') && !auth()->user()->hasRole('Super Admin'))
                        <a href="{{ route('manejo-stock.barrido-simplificado') }}" class="btn btn-link">Volver a barrido</a>
                    @else
                        <a href="{{ route('manejo-stock.contabilidad', ['bodega_id' => $bodega->id, 'ubicacion_id' => $ubicacion->id ?? null]) }}" class="btn btn-link">Volver a contabilidad</a>
                    @endif
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <span class="tim-icons icon-alert-circle-exc mr-2"></span>
                        El código escaneado no existe. Selecciona el producto correcto para asociarlo y luego vuelve a escanear para validar.
                    </div>

                    @if (session('error'))
                        <div class="alert alert-danger">{{ session('error') }}</div>
                    @endif

                    <form id="formAsociar" method="POST" action="{{ route('manejo-stock.asociar.store') }}">
                        @csrf
                        <input type="hidden" name="barcode" value="{{ $barcode }}">
                        <input type="hidden" name="bodega_id" value="{{ $bodega->id }}">
                        <input type="hidden" name="ubicacion_id" value="{{ $ubicacion->id ?? '' }}">
                        <input type="hidden" name="sku" id="inputSkuSeleccionado" value="{{ old('sku') }}">
                        <input type="hidden" name="existing_barcode" id="inputExistingBarcode" value="{{ old('existing_barcode') }}">
                        <input type="hidden" name="accion_codigo" id="inputAccionCodigo" value="{{ old('accion_codigo', 'insert') }}">
                        <input type="hidden" name="origen" value="{{ request()->query('origen', '') }}">

                        <div class="form-group">
                            <label for="inputBarcode">Código de barras</label>
                            <input type="text" id="inputBarcode" class="form-control" value="{{ $barcode }}" readonly>
                            <small class="form-text text-muted">Este campo está bloqueado para evitar cambios accidentales.</small>
                        </div>

                        <div class="form-group">
                            <label for="buscarProducto">Buscar producto</label>
                            <input type="text" id="buscarProducto" class="form-control" placeholder="Ingresa código o nombre de producto" autocomplete="off">
                            <small class="form-text text-muted">Escribe al menos 2 caracteres. Se usa el mismo buscador de cotizaciones.</small>
                        </div>
                    </form>

                    <div id="resultadosAsociar" class="list-group mb-4" style="max-height: 320px; overflow-y: auto;"></div>

                    <div id="productoSeleccionado" class="card" style="display: none;">
                        <div class="card-body">
                            <h5 class="card-title mb-2">Producto seleccionado</h5>
                            <p class="mb-1"><strong>SKU:</strong> <span id="selSku">-</span></p>
                            <p class="mb-1"><strong>Nombre:</strong> <span id="selNombre">-</span></p>
                            <p class="mb-1"><strong>RLUD:</strong> <span id="selRlud">-</span></p>
                            <p class="mb-1"><strong>Unidad 1:</strong> <span id="selUd1">-</span> / <strong>Unidad 2:</strong> <span id="selUd2">-</span></p>
                            <p class="mb-1"><strong>Stock físico:</strong> <span id="selStockFisico">-</span></p>
                            <p class="mb-1"><strong>Stock comprometido:</strong> <span id="selStockComprometido">-</span></p>
                            <p class="mb-2"><strong>Stock disponible:</strong> <span id="selStockDisponible">-</span></p>
                            <div id="estadoCodigoActual" class="alert alert-warning" style="display: none;">
                                <p class="mb-2">
                                    <strong>Código actual:</strong> <span id="textoCodigoActual">-</span>
                                </p>
                                <div class="custom-control custom-radio">
                                    <input class="custom-control-input" type="radio" name="opcionCodigo" id="radioMantener" value="insert" {{ old('accion_codigo', 'insert') !== 'replace' ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="radioMantener">Mantener el código actual y agregar el nuevo</label>
                                </div>
                                <div class="custom-control custom-radio">
                                    <input class="custom-control-input" type="radio" name="opcionCodigo" id="radioReemplazar" value="replace" {{ old('accion_codigo') === 'replace' ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="radioReemplazar">Reemplazar el código actual por el nuevo escaneado</label>
                                </div>
                            </div>
                            <div class="text-right">
                                <button class="btn btn-success" type="submit" form="formAsociar" id="btnGuardarAsociacion" disabled>Guardar asociación</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('js')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const productSearchUrl = "{{ route('productos.buscar') }}";
        const productoDetalleUrl = "{{ route('manejo-stock.producto') }}";
        const bodegaId = {{ $bodega->id }};
        const accionInicial = "{{ old('accion_codigo', 'insert') }}";
        const existingInicial = "{{ old('existing_barcode') }}";

        const buscarInput = document.getElementById('buscarProducto');
        const resultados = document.getElementById('resultadosAsociar');
        const btnGuardar = document.getElementById('btnGuardarAsociacion');
        const cardProducto = document.getElementById('productoSeleccionado');
        const inputSku = document.getElementById('inputSkuSeleccionado');

        const selSku = document.getElementById('selSku');
        const selNombre = document.getElementById('selNombre');
        const selRlud = document.getElementById('selRlud');
        const selUd1 = document.getElementById('selUd1');
        const selUd2 = document.getElementById('selUd2');
        const selStockFisico = document.getElementById('selStockFisico');
        const selStockComprometido = document.getElementById('selStockComprometido');
        const selStockDisponible = document.getElementById('selStockDisponible');
        const estadoCodigoActual = document.getElementById('estadoCodigoActual');
        const textoCodigoActual = document.getElementById('textoCodigoActual');
        const inputExistingBarcode = document.getElementById('inputExistingBarcode');
        const inputAccionCodigo = document.getElementById('inputAccionCodigo');
        const radioMantener = document.getElementById('radioMantener');
        const radioReemplazar = document.getElementById('radioReemplazar');

        let debounceTimer = null;

        function renderResultados(items) {
            resultados.innerHTML = '';
            if (!items.length) {
                resultados.innerHTML = '<div class="list-group-item text-muted">Sin resultados</div>';
                resultados.style.display = 'block';
                return;
            }

            items.forEach(item => {
                const element = document.createElement('div');
                element.className = 'list-group-item d-flex justify-content-between align-items-center';
                element.innerHTML = `
                    <div>
                        <strong>${item.codigo}</strong><br>
                        <small>${item.nombre}</small>
                    </div>
                    <div>
                        <button class="btn btn-sm btn-outline-primary" data-codigo="${item.codigo}">Seleccionar</button>
                    </div>
                `;

                element.querySelector('button').addEventListener('click', function () {
                    cargarDetalleProducto(item.codigo);
                });

                resultados.appendChild(element);
            });
            resultados.style.display = 'block';
        }

        function buscarProductos(query) {
            if (!query || query.length < 2) {
                resultados.innerHTML = '<div class="list-group-item text-muted">Ingresa al menos 2 caracteres</div>';
                resultados.style.display = 'block';
                return;
            }

            fetch(`${productSearchUrl}?q=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(data => renderResultados(data))
                .catch(() => {
                    resultados.innerHTML = '<div class="list-group-item text-danger">Error en la búsqueda</div>';
                    resultados.style.display = 'block';
                });
        }

        function cargarDetalleProducto(sku) {
            fetch(`${productoDetalleUrl}?sku=${encodeURIComponent(sku)}&bodega_id=${bodegaId}`)
                .then(response => response.json())
                .then(data => {
                    if (!data.success || !data.producto) {
                        throw new Error(data.message || 'No se pudo obtener el detalle del producto');
                    }

                    const producto = data.producto;
                    inputSku.value = producto.codigo;
                    selSku.textContent = producto.codigo;
                    selNombre.textContent = producto.nombre;
                    selRlud.textContent = producto.rlud;
                    selUd1.textContent = producto.unidad_1 || '-';
                    selUd2.textContent = producto.unidad_2 || '-';
                    selStockFisico.textContent = producto.stock_fisico ?? 0;
                    selStockComprometido.textContent = producto.stock_comprometido ?? 0;
                    selStockDisponible.textContent = producto.stock_disponible;
                    cardProducto.style.display = 'block';
                    btnGuardar.disabled = false;
                    resultados.style.display = 'none';

                    if (producto.barcode_actual) {
                        textoCodigoActual.textContent = producto.barcode_actual;
                        estadoCodigoActual.style.display = 'block';
                        inputExistingBarcode.value = producto.barcode_actual;
                        if (accionInicial === 'replace' && existingInicial === producto.barcode_actual) {
                            radioMantener.checked = false;
                            radioReemplazar.checked = true;
                        } else {
                            radioMantener.checked = true;
                            radioReemplazar.checked = false;
                        }
                        inputAccionCodigo.value = radioMantener.checked ? radioMantener.value : radioReemplazar.value;
                        actualizarTextoBoton();
                    } else {
                        estadoCodigoActual.style.display = 'none';
                        inputExistingBarcode.value = '';
                        radioMantener.checked = true;
                        radioReemplazar.checked = false;
                        inputAccionCodigo.value = 'insert';
                        btnGuardar.textContent = 'Guardar asociación';
                    }
                })
                .catch(error => {
                    cardProducto.style.display = 'none';
                    btnGuardar.disabled = true;
                    alert(error.message || 'No se pudo obtener el detalle del producto seleccionado.');
                });
        }

        function actualizarTextoBoton() {
            if (estadoCodigoActual.style.display === 'none') {
                btnGuardar.textContent = 'Guardar asociación';
                inputAccionCodigo.value = 'insert';
                return;
            }

            if (radioReemplazar.checked) {
                btnGuardar.textContent = 'Actualizar asociación';
                inputAccionCodigo.value = 'replace';
            } else {
                btnGuardar.textContent = 'Guardar nuevo código';
                inputAccionCodigo.value = 'insert';
            }
        }

        buscarInput.addEventListener('input', function () {
            const query = this.value.trim();
            clearTimeout(debounceTimer);
            if (!query) {
                resultados.innerHTML = '';
                resultados.style.display = 'none';
                cardProducto.style.display = 'none';
                btnGuardar.disabled = true;
                inputSku.value = '';
                return;
            }
            debounceTimer = setTimeout(() => buscarProductos(query), 300);
        });

        radioMantener.addEventListener('change', actualizarTextoBoton);
        radioReemplazar.addEventListener('change', actualizarTextoBoton);

        const skuPrevio = inputSkuSeleccionado.value;
        if (skuPrevio) {
            cargarDetalleProducto(skuPrevio);
        }
    });
</script>
@endpush
