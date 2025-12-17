@extends('layouts.app', ['pageSlug' => 'manejo-stock-barrido-simplificado'])

@section('content')
<div class="content">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="card-title mb-0">
                            <i class="tim-icons icon-tablet-2"></i> Barrido Simplificado - Asociación de Códigos de Barras
                        </h4>
                        <div>
                            <small>
                                Bodega: <strong>{{ $bodega->nombre_bodega }} ({{ $bodega->kobo }})</strong>
                            </small>
                            @if($ubicacion)
                                <br>
                                <small>
                                    Ubicación: <strong>{{ $ubicacion->codigo }}</strong>
                                </small>
                            @endif
                        </div>
                    </div>
                    <div>
                        <a href="{{ route('manejo-stock.historial') }}" class="btn btn-info btn-sm">
                            <i class="tim-icons icon-notes"></i> Ver Historial
                        </a>
                    </div>
                </div>
                <div class="card-body">
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
                        <div class="col-lg-12">
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
                                </small>
                            </div>
                            <div id="barcodeResult" class="alert alert-secondary" style="display: none;"></div>
                        </div>
                    </div>

                    <div id="productoDetalle" class="card mt-4" style="display: none;">
                        <div class="card-header bg-info">
                            <h5 class="mb-0 text-white">Información del Producto</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-12">
                                    <p class="mb-1"><strong>SKU:</strong> <span id="detalleSku">-</span></p>
                                    <p class="mb-1"><strong>Nombre:</strong> <span id="detalleNombre">-</span></p>
                                    <p class="mb-1"><strong>Código de barras:</strong> <span id="detalleBarcode">-</span></p>
                                    <p class="mb-1"><strong>RLUD:</strong> <span id="detalleRlud">-</span></p>
                                    <p class="mb-1"><strong>Unidad 1:</strong> <span id="detalleUd1">-</span> / <strong>Unidad 2:</strong> <span id="detalleUd2">-</span></p>
                                    <p class="mb-1"><strong>Stock físico:</strong> <span id="detalleStockFisico">-</span></p>
                                    <p class="mb-1"><strong>Stock comprometido:</strong> <span id="detalleStockComprometido">-</span></p>
                                    <p class="mb-0"><strong>Stock disponible:</strong> <span id="detalleStock">-</span></p>
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

@endsection

@push('js')
<script src="https://unpkg.com/html5-qrcode" defer></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const barcodeLookupUrl = "{{ route('manejo-stock.barcode') }}";
        const productoDetalleUrl = "{{ route('manejo-stock.producto') }}";
        const asociarFormUrl = "{{ route('manejo-stock.asociar') }}";
        const bodegaId = {{ $bodega->id }};
        const ubicacionId = {{ $ubicacion?->id ?? 'null' }};

        const btnIniciar = document.getElementById('btnIniciarScanner');
        const btnDetener = document.getElementById('btnDetenerScanner');
        const cameraView = document.getElementById('camera-view');
        const barcodeResult = document.getElementById('barcodeResult');
        const btnBuscarManual = document.getElementById('btnBuscarManual');
        const inputCodigoManual = document.getElementById('codigo_manual');

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

        const modalCodigo = $('#modalCodigoNoEncontrado');
        const modalCodigoValor = document.getElementById('modalCodigoValor');
        const btnConfirmarAsociacion = document.getElementById('btnConfirmarAsociacion');

        let html5Scanner = null;
        let scanning = false;
        let productoActual = null;
        let codigoPendiente = null;

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

        async function iniciarScanner() {
            if (typeof Html5Qrcode === 'undefined') {
                mostrarMensajeResultado('El lector no está disponible en este navegador. Usa la entrada manual.', 'warning');
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
                fps: 10,
                qrbox: function(viewfinderWidth, viewfinderHeight) {
                    const minSize = Math.min(viewfinderWidth, viewfinderHeight);
                    const qrboxSize = Math.min(minSize * 0.8, 400);
                    return {
                        width: qrboxSize,
                        height: qrboxSize * 0.4
                    };
                },
                aspectRatio: 1.0,
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
                scanning = true;
                btnIniciar.disabled = true;
                btnDetener.disabled = false;
                mostrarMensajeResultado('Escanea un código alineándolo dentro del recuadro.', 'info');
            }).catch(err => {
                console.error('Error iniciando cámara:', err);
                mostrarMensajeResultado('No se pudo iniciar la cámara: ' + err, 'danger');
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
            // Ignorar errores de escaneo continuo
        }

        function procesarCodigo(code) {
            if (!code) {
                mostrarMensajeResultado('El código recibido está vacío.', 'warning');
                return;
            }

            limpiarMensajeResultado();
            mostrarMensajeResultado('Buscando código <strong>' + code + '</strong>...', 'info');

            fetch(`${barcodeLookupUrl}?code=${encodeURIComponent(code)}&bodega_id=${bodegaId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.found) {
                        mostrarMensajeResultado(`Código <strong>${data.barcode}</strong> asociado a <strong>${data.producto.codigo}</strong> - ${data.producto.nombre}`, 'success');
                        mostrarDetalleProducto(data.producto);
                    } else {
                        mostrarMensajeResultado(data.message + ' Puedes asociarlo ahora.', 'danger');
                        ocultarDetalleProducto();
                        codigoPendiente = code;
                        modalCodigoValor.textContent = code;
                        modalCodigo.modal('show');
                    }
                })
                .catch(() => {
                    mostrarMensajeResultado('Error consultando el código de barras.', 'danger');
                });
        }

        btnIniciar.addEventListener('click', iniciarScanner);
        btnDetener.addEventListener('click', detenerScanner);

        // Detectar cuando se escanea un código con pistola Bluetooth (modo HID)
        inputCodigoManual.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const code = this.value.trim();
                if (code) {
                    procesarCodigo(code);
                    this.value = '';
                }
            }
        });

        // También detectar cuando se completa la escritura
        let codigoTimeout = null;
        inputCodigoManual.addEventListener('input', function() {
            const code = this.value.trim();
            if (code.length >= 8) {
                clearTimeout(codigoTimeout);
                codigoTimeout = setTimeout(() => {
                    if (this.value.trim().length >= 8) {
                        procesarCodigo(this.value.trim());
                        this.value = '';
                    }
                }, 500);
            }
        });

        btnBuscarManual.addEventListener('click', () => {
            const code = inputCodigoManual.value.trim();
            if (!code) {
                mostrarMensajeResultado('Ingresa un código antes de buscar.', 'warning');
                return;
            }
            procesarCodigo(code);
            inputCodigoManual.value = '';
        });

        btnConfirmarAsociacion.addEventListener('click', () => {
            if (!codigoPendiente) {
                modalCodigo.modal('hide');
                return;
            }
            const url = new URL("{{ route('manejo-stock.asociar-barrido') }}", window.location.origin);
            url.searchParams.set('barcode', codigoPendiente);
            url.searchParams.set('bodega_id', bodegaId);
            url.searchParams.set('origen', 'barrido-simplificado');
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

