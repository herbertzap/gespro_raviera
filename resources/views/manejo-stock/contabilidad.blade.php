@extends('layouts.app', ['pageSlug' => 'manejo-stock'])

@section('content')
<div class="content">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="card-title mb-0">Contabilidad de Stock</h4>
                        <div>
                            <small>
                                Bodega: <strong>{{ $bodega->nombre_bodega }} ({{ $bodega->kobo }})</strong>
                            </small>
                            <br>
                            <small>
                                Ubicación: 
                                @if($ubicacion)
                                    <strong id="ubicacion-actual">{{ $ubicacion->codigo }}</strong>
                                @else
                                    <strong id="ubicacion-actual" class="text-danger">No seleccionada</strong>
                                @endif
                                <button type="button" class="btn btn-sm btn-link p-0 ml-2" id="btnCambiarUbicacion" style="font-size: 0.875rem;">
                                    <i class="tim-icons icon-pencil"></i> Cambiar
                                </button>
                            </small>
                        </div>
                    </div>
                    <a href="{{ route('manejo-stock.select') }}" class="btn btn-primary">Cambiar Bodega</a>
                </div>
                <div class="card-body">
                    @if (session('status'))
                        <div class="alert alert-success">{{ session('status') }}</div>
                    @endif

                    @if (session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            {{ session('success') }}
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    @endif

                    @if (session('error'))
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            {{ session('error') }}
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    @endif

                    <div class="row">
                        <div class="col-lg-12
                            <h5 class="mb-3">Lectura de Código de Barras</h5>
                            <div class="mb-2">
                                <button class="btn btn-sm btn-primary" id="btnIniciarScanner">Iniciar cámara</button>
                                <button class="btn btn-sm btn-outline-secondary" id="btnDetenerScanner" disabled>Detener</button>
                            </div>
                            <div id="camera-view" class="border rounded mb-3" style="min-height: 260px; display: none; align-items: center; justify-content: center; background: #f7f7f7;">
                                <span class="text-muted">La vista de la cámara aparecerá aquí.</span>
                            </div>
                            <div class="form-group">
                                <label for="codigo_manual">Ingresar código manualmente o con pistola Bluetooth</label>
                                <div class="input-group">
                                    <input type="text" id="codigo_manual" class="form-control" placeholder="Escanea con pistola Bluetooth o escribe el código" autocomplete="off">
                                    <div class="input-group-append">
                                        <button class="btn btn-info" id="btnBuscarManual">Buscar</button>
                                    </div>
                                </div>
                                <small class="form-text text-muted">
                                    <i class="tim-icons icon-bluetooth"></i> 
                                    Conecta tu pistola Bluetooth en modo HID (teclado) y enfoca este campo. El código se leerá automáticamente.
                                    <button type="button" class="btn btn-link btn-sm p-0 ml-2" id="btnConectarBluetooth" style="font-size: 0.875rem; display: none;">
                                        <i class="tim-icons icon-bluetooth"></i> Conectar pistola Bluetooth (SPP)
                                    </button>
                                </small>
                            </div>
                            <div id="barcodeResult" class="alert alert-secondary" style="display: none;"></div>
                        </div>
                        <div class="col-lg-12
                            <h5 class="mb-3">
                                Buscador de productos
                            </h5>
                            <form id="buscadorProductos" class="mb-3" onsubmit="return false;">
                                <div class="form-group mb-2">
                                    <input type="text" class="form-control" id="buscar" placeholder="Ingresa código o nombre de producto">
                                    <small class="form-text text-muted">
                                        Búsqueda dinámica (igual que en cotizaciones). Escribe al menos 2 caracteres.
                                    </small>
                                </div>
                            </form>
                            <div id="resultados" class="list-group" style="max-height: 360px; overflow-y: auto; display: none;"></div>
                        </div>
                    </div>

                    <div id="productoDetalle" class="card mt-4" style="display: none;">
                        <div class="card-header">
                            <h5 class="mb-0">Detalle del producto seleccionado</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>SKU:</strong> <span id="detalleSku">-</span></p>
                                    <p class="mb-1"><strong>Nombre:</strong> <span id="detalleNombre">-</span></p>
                                    <p class="mb-1"><strong>Código de barras:</strong> <span id="detalleBarcode">-</span></p>
                                    <p class="mb-1"><strong>RLUD:</strong> <span id="detalleRlud">-</span></p>
                                    <p class="mb-1"><strong>Unidad 1:</strong> <span id="detalleUd1">-</span> / <strong>Unidad 2:</strong> <span id="detalleUd2">-</span></p>
                                    <p class="mb-1"><strong>Stock físico:</strong> <span id="detalleStockFisico">-</span></p>
                                    <p class="mb-1"><strong>Stock comprometido:</strong> <span id="detalleStockComprometido">-</span></p>
                                    <p class="mb-0"><strong>Stock disponible:</strong> <span id="detalleStock">-</span></p>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="inputCaptura1">Captura 1 (cantidad total contada)</label>
                                        <input type="number" min="0" step="1" id="inputCaptura1" class="form-control" placeholder="Ej: 1000">
                                    </div>
                                    <div class="form-group">
                                        <label for="inputCaptura2">Captura 2 (bultos)</label>
                                        <input type="number" min="0" step="0.01" id="inputCaptura2" class="form-control" placeholder="RLUD automático" readonly>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group col-md-6">
                                            <label for="inputStfi1">STFI1 (diferencia unidades)</label>
                                            <input type="number" step="0.01" id="inputStfi1" class="form-control" readonly>
                                        </div>
                                        <div class="form-group col-md-6">
                                            <label for="inputStfi2">STFI2 (diferencia bultos)</label>
                                            <input type="number" step="0.01" id="inputStfi2" class="form-control" readonly>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="inputFuncionario">Codigo funcionario</label>
                                        <input type="text" id="inputFuncionario" class="form-control" placeholder="Ej: PZZ" data-funcionario="{{ auth()->user()->codigo_vendedor ?? '' }}">
                                    </div>
                                    <div class="form-group mt-3">
                                        <button type="button" id="btnGuardarCaptura" class="btn btn-primary btn-block" disabled>
                                            <i class="tim-icons icon-check-2"></i> Guardar Captura
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

<div class="modal fade" id="modalCodigoNoEncontrado" tabindex="-1" role="dialog" aria-labelledby="modalCodigoNoEncontradoLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalCodigoNoEncontradoLabel">Código no encontrado</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>El código de barras <strong id="modalCodigoValor"></strong> no está asociado a ningún producto.</p>
                <p>¿Deseas asociarlo ahora?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="btnCancelarAsociacion" data-dismiss="modal">No, cancelar</button>
                <button type="button" class="btn btn-primary" id="btnConfirmarAsociacion">Sí, asociar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalCambiarUbicacion" tabindex="-1" role="dialog" aria-labelledby="modalCambiarUbicacionLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalCambiarUbicacionLabel">Cambiar Ubicación</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="buscarUbicacion">Buscar ubicación</label>
                    <input type="text" id="buscarUbicacion" class="form-control" placeholder="Escribe para buscar por código o descripción">
                    <small class="form-text text-muted">Escribe al menos 2 caracteres para buscar</small>
                </div>
                <div id="resultadosUbicaciones" class="list-group" style="max-height: 300px; overflow-y: auto; display: none;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de éxito al guardar -->
<div class="modal fade" id="modalExitoGuardado" tabindex="-1" role="dialog" aria-labelledby="modalExitoGuardadoLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="modalExitoGuardadoLabel">
                    <i class="tim-icons icon-check-2"></i> Guardado exitoso
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="text-center">
                    <i class="tim-icons icon-check-2 text-success" style="font-size: 3rem;"></i>
                    <h5 class="mt-3">Producto agregado con éxito</h5>
                    <p class="mb-2"><strong>Producto:</strong> <span id="modalExitoProducto"></span></p>
                    <p class="mb-0"><strong>Cantidad:</strong> <span id="modalExitoCantidad"></span></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-success" data-dismiss="modal">Aceptar</button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('js')
<script src="https://unpkg.com/html5-qrcode" defer></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const barcodeLookupUrl = "{{ route('manejo-stock.barcode') }}";
        const productSearchUrl = "{{ route('productos.buscar') }}";
        const productoDetalleUrl = "{{ route('manejo-stock.producto') }}";
        const asociarFormUrl = "{{ route('manejo-stock.asociar') }}";
        const ubicacionesUrl = "{{ route('manejo-stock.ubicaciones') }}";
        const guardarCapturaUrl = "{{ route('manejo-stock.contabilidad.guardar') }}";
        const bodegaId = {{ $bodega->id }};
        let ubicacionId = {{ $ubicacion?->id ?? 'null' }};
        
        // Debug: verificar que ubicacionId se inicializó correctamente
        console.log('Ubicación ID inicial:', ubicacionId);

        const btnIniciar = document.getElementById('btnIniciarScanner');
        const btnDetener = document.getElementById('btnDetenerScanner');
        const cameraView = document.getElementById('camera-view');
        const barcodeResult = document.getElementById('barcodeResult');
        const buscarInput = document.getElementById('buscar');
        const resultados = document.getElementById('resultados');
        const btnBuscarManual = document.getElementById('btnBuscarManual');
        const inputCodigoManual = document.getElementById('codigo_manual');
        const btnConectarBluetooth = document.getElementById('btnConectarBluetooth');
        
        // Variable para almacenar el dispositivo Bluetooth conectado
        let bluetoothDevice = null;
        let bluetoothCharacteristic = null;

        const detalleCard = document.getElementById('productoDetalle');
        const detalleSku = document.getElementById('detalleSku');
        const detalleNombre = document.getElementById('detalleNombre');
        const detalleBarcode = document.getElementById('detalleBarcode');
        const detalleRlud = document.getElementById('detalleRlud');
        const detalleUd1 = document.getElementById('detalleUd1');
        const detalleUd2 = document.getElementById('detalleUd2');
        const detalleStockFisico = document.getElementById('detalleStockFisico');
        const detalleStockComprometido = document.getElementById('detalleStockComprometido');
        const detalleStock = document.getElementById('detalleStock');
        const inputCaptura1 = document.getElementById('inputCaptura1');
        const inputCaptura2 = document.getElementById('inputCaptura2');
        const inputStfi1 = document.getElementById('inputStfi1');
        const inputStfi2 = document.getElementById('inputStfi2');
        const inputFuncionario = document.getElementById('inputFuncionario');
        const defaultFuncionario = inputFuncionario.dataset.funcionario;

        const modalCodigo = $('#modalCodigoNoEncontrado');
        const modalCodigoValor = document.getElementById('modalCodigoValor');
        const btnConfirmarAsociacion = document.getElementById('btnConfirmarAsociacion');
        const btnCambiarUbicacion = document.getElementById('btnCambiarUbicacion');
        const modalCambiarUbicacion = $('#modalCambiarUbicacion');
        const buscarUbicacion = document.getElementById('buscarUbicacion');
        const resultadosUbicaciones = document.getElementById('resultadosUbicaciones');
        const btnGuardarCaptura = document.getElementById('btnGuardarCaptura');
        const ubicacionActual = document.getElementById('ubicacion-actual');
        const modalExitoGuardado = $('#modalExitoGuardado');
        const modalExitoProducto = document.getElementById('modalExitoProducto');
        const modalExitoCantidad = document.getElementById('modalExitoCantidad');

        let html5Scanner = null;
        let scanning = false;
        let productoActual = null;
        let debounceTimer = null;
        let codigoPendiente = null;
        let debounceUbicaciones = null;

        function mostrarMensajeResultado(message, type = 'secondary') {
            barcodeResult.className = `alert alert-${type}`;
            barcodeResult.style.display = 'block';
            barcodeResult.innerHTML = message;
        }

        function limpiarMensajeResultado() {
            barcodeResult.style.display = 'none';
            barcodeResult.innerHTML = '';
        }

        function ocultarDetalleProducto() {
            productoActual = null;
            detalleCard.style.display = 'none';
            detalleSku.textContent = '-';
            detalleNombre.textContent = '-';
            detalleBarcode.textContent = '-';
            detalleRlud.textContent = '-';
            detalleUd1.textContent = '-';
            detalleUd2.textContent = '-';
            detalleStockFisico.textContent = '-';
            detalleStockComprometido.textContent = '-';
            detalleStock.textContent = '-';
            inputCaptura1.value = '';
            inputCaptura2.value = '';
            inputStfi1.value = '';
            inputStfi2.value = '';
            validarFormulario();
        }

        function mostrarDetalleProducto(producto) {
            productoActual = producto;
            detalleSku.textContent = producto.codigo;
            detalleNombre.textContent = producto.nombre;
            // Mostrar código de barras si existe
            if (producto.barcode_actual || producto.barcode) {
                detalleBarcode.textContent = producto.barcode_actual || producto.barcode;
            } else if (producto.barcodes && producto.barcodes.length > 0) {
                detalleBarcode.textContent = producto.barcodes[0];
            } else {
                detalleBarcode.textContent = 'No asociado';
            }
            detalleRlud.textContent = producto.rlud;
            detalleUd1.textContent = producto.unidad_1 || '-';
            detalleUd2.textContent = producto.unidad_2 || '-';
            detalleStockFisico.textContent = producto.stock_fisico ?? 0;
            detalleStockComprometido.textContent = producto.stock_comprometido ?? 0;
            detalleStock.textContent = producto.stock_disponible;
            detalleCard.style.display = 'block';
            inputCaptura1.focus();
            recalcularCapturas();

            if (defaultFuncionario) {
                inputFuncionario.value = defaultFuncionario;
                inputFuncionario.readOnly = true;
            } else if (producto.funcionario) {
                inputFuncionario.value = producto.funcionario;
                inputFuncionario.readOnly = true;
            } else {
                inputFuncionario.value = '';
                inputFuncionario.readOnly = false;
            }

            validarFormulario();
        }

        function cargarProductoPorSku(sku) {
            if (!sku) {
                return Promise.resolve();
            }

            return fetch(`${productoDetalleUrl}?sku=${encodeURIComponent(sku)}&bodega_id=${bodegaId}`)
                .then(response => {
                    // Verificar si la respuesta es exitosa
                    if (!response.ok) {
                        // Intentar parsear JSON para obtener el mensaje de error
                        return response.json().then(data => {
                            throw new Error(data.message || `Error ${response.status}: ${response.statusText}`);
                        }).catch(() => {
                            throw new Error(`Error ${response.status}: No se pudo obtener el producto.`);
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    if (!data.success || !data.producto) {
                        throw new Error(data.message || 'No se encontró el producto.');
                    }
                    mostrarDetalleProducto(data.producto);
                })
                .catch(error => {
                    console.error('Error cargando producto:', error);
                    mostrarMensajeResultado(error.message, 'danger');
                });
        }

        function recalcularCapturas() {
            if (!productoActual) {
                inputCaptura2.value = '';
                inputStfi1.value = '';
                inputStfi2.value = '';
                validarFormulario();
                return;
            }

            const rlud = Number(productoActual.rlud || 1) || 1;
            const stockActual = Number(productoActual.stock_disponible || 0);
            const captura1 = Number(inputCaptura1.value || 0);

            const captura2 = rlud > 0 ? (captura1 / rlud) : 0;
            const diferenciaUnidades = captura1 - stockActual;
            const diferenciaBultos = rlud > 0 ? (diferenciaUnidades / rlud) : 0;

            inputCaptura2.value = captura2.toFixed(2);
            inputStfi1.value = diferenciaUnidades.toFixed(2);
            inputStfi2.value = diferenciaBultos.toFixed(2);
            validarFormulario();
        }

        function validarFormulario() {
            const tieneProducto = productoActual !== null;
            const tieneCaptura = Number(inputCaptura1.value || 0) > 0;
            const tieneUbicacion = ubicacionId !== null && ubicacionId !== undefined;

            const puedeGuardar = tieneProducto && tieneCaptura && tieneUbicacion;
            btnGuardarCaptura.disabled = !puedeGuardar;
            
            // Debug: mostrar estado de validación
            console.log('Validación:', {
                tieneProducto,
                tieneCaptura,
                tieneUbicacion,
                ubicacionId,
                puedeGuardar
            });
        }

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

                element.querySelector('button').addEventListener('click', () => {
                    cargarProductoPorSku(item.codigo).then(() => {
                        resultados.innerHTML = '';
                        resultados.style.display = 'none';
                    });
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
                    resultados.style.display = 'block';
                    resultados.innerHTML = '<div class="list-group-item text-danger">Error en la búsqueda</div>';
                });
        }

        function detenerScanner() {
            if (html5Scanner && scanning) {
                html5Scanner.stop().then(() => {
                    scanning = false;
                    btnIniciar.disabled = false;
                    btnDetener.disabled = true;
                    cameraView.style.display = 'none';
                }).catch(() => {});
            }
        }

        // Función para verificar y recordar permiso de cámara
        async function verificarPermisoCamara() {
            const CAMERA_PERMISSION_KEY = 'camera_permission_granted';
            
            // Verificar si ya tenemos el permiso guardado
            const permisoGuardado = localStorage.getItem(CAMERA_PERMISSION_KEY);
            if (permisoGuardado === 'true') {
                return true;
            }

            // Intentar usar la Permissions API si está disponible
            if (navigator.permissions && navigator.permissions.query) {
                try {
                    const result = await navigator.permissions.query({ name: 'camera' });
                    if (result.state === 'granted') {
                        localStorage.setItem(CAMERA_PERMISSION_KEY, 'true');
                        return true;
                    } else if (result.state === 'prompt') {
                        // El navegador pedirá permiso, pero lo guardaremos después
                        return null;
                    } else {
                        // Denegado
                        return false;
                    }
                } catch (e) {
                    // La API no está disponible o no soporta 'camera'
                    console.log('Permissions API no disponible:', e);
                }
            }

            return null; // No se puede determinar, el navegador pedirá permiso
        }

        // Función para guardar permiso de cámara
        function guardarPermisoCamara() {
            localStorage.setItem('camera_permission_granted', 'true');
        }

        async function iniciarScanner() {
            if (typeof Html5Qrcode === 'undefined') {
                mostrarMensajeResultado('El lector no está disponible en este navegador. Usa la entrada manual.', 'warning');
                return;
            }

            // Verificar permiso antes de iniciar
            const tienePermiso = await verificarPermisoCamara();
            if (tienePermiso === false) {
                mostrarMensajeResultado('El permiso de cámara fue denegado. Por favor, habilítalo en la configuración del navegador.', 'warning');
                return;
            }

            cameraView.style.display = 'flex';
            if (!html5Scanner) {
                html5Scanner = new Html5Qrcode('camera-view');
            }

            const formats = typeof Html5QrcodeSupportedFormats !== 'undefined'
                ? [
                    Html5QrcodeSupportedFormats.EAN_13,
                    Html5QrcodeSupportedFormats.EAN_8,
                    Html5QrcodeSupportedFormats.CODE_128,
                    Html5QrcodeSupportedFormats.UPC_A,
                    Html5QrcodeSupportedFormats.CODE_39,
                    Html5QrcodeSupportedFormats.CODE_93,
                    Html5QrcodeSupportedFormats.QR_CODE,
                ]
                : undefined;

            const config = {
                fps: 10, // Reducido para mejor compatibilidad y estabilidad
                qrbox: function(viewfinderWidth, viewfinderHeight) {
                    // Calcular tamaño basado en el viewport disponible
                    const minSize = Math.min(viewfinderWidth, viewfinderHeight);
                    const qrboxSize = Math.min(minSize * 0.8, 400);
                    return {
                        width: qrboxSize,
                        height: qrboxSize * 0.4 // Más bajo para códigos de barras horizontales
                    };
                },
                aspectRatio: 1.0, // Aspecto cuadrado más compatible
                experimentalFeatures: { 
                    useBarCodeDetectorIfSupported: true 
                },
                rememberLastUsedCamera: true,
                disableFlip: false,
            };

            if (formats) {
                config.formatsToSupport = formats;
            }

            html5Scanner.start(
                { facingMode: 'environment' },
                config,
                onBarcodeSuccess,
                onBarcodeError
            ).then(() => {
                // Guardar permiso cuando se inicia exitosamente
                guardarPermisoCamara();
                scanning = true;
                btnIniciar.disabled = true;
                btnDetener.disabled = false;
                mostrarMensajeResultado('Escanea un código alineándolo dentro del recuadro.', 'info');
            }).catch(err => {
                console.error('Error iniciando cámara:', err);
                
                // Si el error es por permiso denegado, limpiar el permiso guardado
                if (err.toString().includes('Permission denied') || err.toString().includes('NotAllowedError')) {
                    localStorage.removeItem('camera_permission_granted');
                    mostrarMensajeResultado('Permiso de cámara denegado. Por favor, permite el acceso a la cámara e intenta nuevamente.', 'warning');
                } else {
                    mostrarMensajeResultado('No se pudo iniciar la cámara: ' + err, 'danger');
                }
                cameraView.style.display = 'none';
            });
        }

        function onBarcodeSuccess(decodedText, decodedResult) {
            if (!decodedText) {
                return;
            }
            console.log('Código detectado:', decodedText);
            detenerScanner();
            procesarCodigo(decodedText.trim());
        }

        function onBarcodeError(errorMessage) {
            // Ignorar errores de escaneo continuo, solo loguear si es necesario
            // console.log('Error de escaneo:', errorMessage);
        }

        function procesarCodigo(code) {
            if (!code) {
                mostrarMensajeResultado('El código recibido está vacío.', 'warning');
                return;
            }

            limpiarMensajeResultado();
            mostrarMensajeResultado('Buscando código <strong>' + code + '</strong>...', 'info');

            fetch(`${barcodeLookupUrl}?code=${encodeURIComponent(code)}&bodega_id=${bodegaId}`)
                .then(response => {
                    // Verificar si la respuesta es exitosa
                    if (!response.ok) {
                        // Intentar parsear JSON para obtener el mensaje de error
                        return response.json().then(data => {
                            // Si el código de barras no se encuentra, es un caso válido
                            if (response.status === 404 && data.found === false) {
                                return data; // Retornar data para procesarlo normalmente
                            }
                            throw new Error(data.message || `Error ${response.status}: ${response.statusText}`);
                        }).catch(() => {
                            throw new Error(`Error ${response.status}: No se pudo consultar el código de barras.`);
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.found) {
                        mostrarMensajeResultado(`Código <strong>${data.barcode}</strong> asociado a <strong>${data.producto.codigo}</strong> - ${data.producto.nombre}`, 'success');
                        mostrarDetalleProducto(data.producto);
                    } else {
                        // Cambiar a 'danger' para mejor legibilidad del mensaje de código inexistente
                        mostrarMensajeResultado(data.message + ' Puedes asociarlo ahora.', 'danger');
                        ocultarDetalleProducto();
                        codigoPendiente = code;
                        modalCodigoValor.textContent = code;
                        modalCodigo.modal('show');
                    }
                })
                .catch(error => {
                    console.error('Error consultando código de barras:', error);
                    mostrarMensajeResultado(error.message || 'Error consultando el código de barras.', 'danger');
                });
        }

        btnIniciar.addEventListener('click', iniciarScanner);
        btnDetener.addEventListener('click', detenerScanner);

        // Detectar cuando se escanea un código con pistola Bluetooth (modo HID)
        // Las pistolas HID envían el código seguido de Enter
        inputCodigoManual.addEventListener('keypress', function(e) {
            // Si se presiona Enter, procesar el código
            if (e.key === 'Enter') {
                e.preventDefault();
                const code = this.value.trim();
                if (code) {
                    procesarCodigo(code);
                    this.value = ''; // Limpiar después de procesar
                }
            }
        });

        // También detectar cuando se completa la escritura (útil para pistolas que no envían Enter)
        let codigoTimeout = null;
        inputCodigoManual.addEventListener('input', function() {
            const code = this.value.trim();
            if (code.length >= 8) { // Los códigos de barras suelen tener al menos 8 caracteres
                clearTimeout(codigoTimeout);
                codigoTimeout = setTimeout(() => {
                    if (this.value.trim().length >= 8) {
                        procesarCodigo(this.value.trim());
                        this.value = '';
                    }
                }, 500); // Esperar 500ms después de dejar de escribir
            }
        });

        btnBuscarManual.addEventListener('click', () => {
            const code = inputCodigoManual.value.trim();
            if (!code) {
                mostrarMensajeResultado('Ingresa un código antes de buscar.', 'warning');
                return;
            }
            procesarCodigo(code);
            inputCodigoManual.value = ''; // Limpiar después de buscar
        });

        // Funcionalidad para conectar pistola Bluetooth en modo SPP (Serial Port Profile)
        // Solo disponible en navegadores que soporten Web Bluetooth API
        if (navigator.bluetooth && btnConectarBluetooth) {
            btnConectarBluetooth.style.display = 'inline-block';
            
            btnConectarBluetooth.addEventListener('click', async () => {
                try {
                    mostrarMensajeResultado('Buscando dispositivos Bluetooth...', 'info');
                    
                    // Solicitar dispositivo Bluetooth
                    // Nota: Necesitarás ajustar el filtro según el modelo de tu pistola
                    bluetoothDevice = await navigator.bluetooth.requestDevice({
                        filters: [
                            { services: ['00001101-0000-1000-8000-00805f9b34fb'] }, // Serial Port Profile
                        ],
                        optionalServices: ['battery_service']
                    });

                    mostrarMensajeResultado('Conectando a ' + (bluetoothDevice.name || 'dispositivo') + '...', 'info');

                    // Conectar al servidor GATT
                    const server = await bluetoothDevice.gatt.connect();
                    
                    // Obtener el servicio Serial Port
                    const service = await server.getPrimaryService('00001101-0000-1000-8000-00805f9b34fb');
                    
                    // Obtener la característica para recibir datos
                    bluetoothCharacteristic = await service.getCharacteristic('00001101-0000-1000-8000-00805f9b34fb');
                    
                    // Escuchar datos
                    await bluetoothCharacteristic.startNotifications();
                    bluetoothCharacteristic.addEventListener('characteristicvaluechanged', handleBluetoothData);
                    
                    mostrarMensajeResultado('Pistola Bluetooth conectada. Escanea códigos de barras.', 'success');
                    btnConectarBluetooth.textContent = '✓ Conectado';
                    btnConectarBluetooth.disabled = true;

                    // Manejar desconexión
                    bluetoothDevice.addEventListener('gattserverdisconnected', () => {
                        mostrarMensajeResultado('Pistola Bluetooth desconectada.', 'warning');
                        btnConectarBluetooth.textContent = 'Conectar pistola Bluetooth (SPP)';
                        btnConectarBluetooth.disabled = false;
                        bluetoothDevice = null;
                        bluetoothCharacteristic = null;
                    });

                } catch (error) {
                    console.error('Error conectando Bluetooth:', error);
                    if (error.name === 'NotFoundError') {
                        mostrarMensajeResultado('No se encontró ningún dispositivo Bluetooth compatible.', 'warning');
                    } else if (error.name === 'SecurityError') {
                        mostrarMensajeResultado('Error de seguridad. Asegúrate de estar en HTTPS.', 'danger');
                    } else {
                        mostrarMensajeResultado('Error conectando Bluetooth: ' + error.message, 'danger');
                    }
                }
            });
        }

        function handleBluetoothData(event) {
            const value = event.target.value;
            const decoder = new TextDecoder('utf-8');
            const codigo = decoder.decode(value).trim();
            
            if (codigo) {
                procesarCodigo(codigo);
            }
        }

        buscarInput.addEventListener('input', function () {
            const query = this.value.trim();
            clearTimeout(debounceTimer);
            if (!query) {
                resultados.innerHTML = '';
                resultados.style.display = 'none';
                return;
            }
            debounceTimer = setTimeout(() => buscarProductos(query), 300);
        });

        inputCaptura1.addEventListener('input', recalcularCapturas);
        inputCaptura1.addEventListener('input', validarFormulario);

        btnConfirmarAsociacion.addEventListener('click', () => {
            if (!codigoPendiente) {
                modalCodigo.modal('hide');
                return;
            }
            const url = new URL(asociarFormUrl, window.location.origin);
            url.searchParams.set('barcode', codigoPendiente);
            url.searchParams.set('bodega_id', bodegaId);
            if (ubicacionId) {
                url.searchParams.set('ubicacion_id', ubicacionId);
            }
            modalCodigo.modal('hide');
            window.location.href = url.toString();
        });

        modalCodigo.on('hidden.bs.modal', function () {
            codigoPendiente = null;
        });

        ocultarDetalleProducto();

        if (defaultFuncionario) {
            inputFuncionario.value = defaultFuncionario;
            inputFuncionario.readOnly = true;
        }

        // Funcionalidad de cambio de ubicación
        btnCambiarUbicacion.addEventListener('click', () => {
            modalCambiarUbicacion.modal('show');
            buscarUbicacion.value = '';
            resultadosUbicaciones.innerHTML = '';
            resultadosUbicaciones.style.display = 'none';
        });

        buscarUbicacion.addEventListener('input', function () {
            const query = this.value.trim();
            clearTimeout(debounceUbicaciones);
            if (!query || query.length < 2) {
                resultadosUbicaciones.innerHTML = '';
                resultadosUbicaciones.style.display = 'none';
                return;
            }
            debounceUbicaciones = setTimeout(() => buscarUbicaciones(query), 300);
        });

        function buscarUbicaciones(query) {
            fetch(`${ubicacionesUrl}?bodega_id=${bodegaId}&q=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(data => renderResultadosUbicaciones(data))
                .catch(() => {
                    resultadosUbicaciones.style.display = 'block';
                    resultadosUbicaciones.innerHTML = '<div class="list-group-item text-danger">Error en la búsqueda</div>';
                });
        }

        function renderResultadosUbicaciones(items) {
            resultadosUbicaciones.innerHTML = '';
            if (!items.length) {
                resultadosUbicaciones.innerHTML = '<div class="list-group-item text-muted">Sin resultados</div>';
                resultadosUbicaciones.style.display = 'block';
                return;
            }

            items.forEach(item => {
                const element = document.createElement('div');
                element.className = 'list-group-item list-group-item-action';
                element.style.cursor = 'pointer';
                element.innerHTML = `
                    <div>
                        <strong>${item.codigo}</strong>
                        ${item.descripcion ? '<br><small>' + item.descripcion + '</small>' : ''}
                    </div>
                `;

                element.addEventListener('click', () => {
                    ubicacionId = item.id;
                    ubicacionActual.textContent = item.codigo;
                    ubicacionActual.classList.remove('text-danger');
                    modalCambiarUbicacion.modal('hide');
                    validarFormulario();
                });

                resultadosUbicaciones.appendChild(element);
            });
            resultadosUbicaciones.style.display = 'block';
        }

        // Funcionalidad de guardar captura
        btnGuardarCaptura.addEventListener('click', () => {
            if (!productoActual || !ubicacionId) {
                alert('Debes seleccionar un producto y una ubicación antes de guardar.');
                return;
            }

            const captura1 = Number(inputCaptura1.value || 0);
            if (captura1 <= 0) {
                alert('Debes ingresar una cantidad en Captura 1.');
                return;
            }

            const formData = new FormData();
            formData.append('_token', '{{ csrf_token() }}');
            formData.append('bodega_id', bodegaId);
            formData.append('ubicacion_id', ubicacionId);
            formData.append('sku', productoActual.codigo);
            formData.append('nombre_producto', productoActual.nombre);
            formData.append('rlud', productoActual.rlud || 1);
            formData.append('unidad_medida_1', productoActual.unidad_1 || '');
            formData.append('unidad_medida_2', productoActual.unidad_2 || '');
            formData.append('captura_1', captura1);
            formData.append('captura_2', inputCaptura2.value || '');
            formData.append('stfi1', inputStfi1.value || '');
            formData.append('stfi2', inputStfi2.value || '');
            formData.append('funcionario', inputFuncionario.value || '');

            btnGuardarCaptura.disabled = true;
            btnGuardarCaptura.innerHTML = '<i class="tim-icons icon-refresh-02"></i> Guardando...';

            fetch(guardarCapturaUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => {
                if (response.redirected) {
                    window.location.href = response.url;
                    return;
                }
                return response.json();
            })
            .then(data => {
                if (data && data.success) {
                    // Mostrar modal de éxito
                    modalExitoProducto.textContent = data.data.producto + ' (' + data.data.sku + ')';
                    modalExitoCantidad.textContent = data.data.cantidad;
                    modalExitoGuardado.modal('show');
                    
                    // Limpiar formulario después de guardar
                    ocultarDetalleProducto();
                    
                    // Restaurar botón
                    btnGuardarCaptura.disabled = false;
                    btnGuardarCaptura.innerHTML = '<i class="tim-icons icon-check-2"></i> Guardar Captura';
                } else {
                    throw new Error(data?.message || 'Error al guardar');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al guardar la captura: ' + (error.message || 'Intente nuevamente.'));
                btnGuardarCaptura.disabled = false;
                btnGuardarCaptura.innerHTML = '<i class="tim-icons icon-check-2"></i> Guardar Captura';
            });
        });

        // Validar formulario al cargar
        validarFormulario();
        
        // Mensaje de confirmación al cargar
        if (ubicacionId) {
            console.log('✅ Ubicación seleccionada:', ubicacionId);
            console.log('Para habilitar el botón "Guardar Captura", necesitas:');
            console.log('1. Seleccionar un producto (buscar o escanear)');
            console.log('2. Ingresar una cantidad en "Captura 1"');
            console.log('3. Ubicación ya está seleccionada ✓');
        } else {
            console.log('⚠️ No hay ubicación seleccionada. Usa el botón "Cambiar" para seleccionar una.');
        }
    });
</script>

<style>
    #camera-view {
        width: 100%;
        min-height: 320px;
        display: none;
        background: #f7f7f7;
        border: 1px dashed #ccc;
        border-radius: 8px;
        align-items: center;
        justify-content: center;
        text-align: center;
        color: #666;
    }
</style>
@endpush

