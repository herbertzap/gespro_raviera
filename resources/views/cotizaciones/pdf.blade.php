<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $cotizacion->tipo_documento === 'nota_venta' ? 'Nota de Venta' : 'Cotización' }} #{{ $cotizacion->id }}</title>
    <style>
        html { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            margin: 0;
            padding: 4px 6px;
            font-size: 10pt;
            line-height: 1.15;
            color: #000;
        }
        body p { margin: 0 0 2px 0; }
        .header-section {
            width: 100%;
            margin-bottom: 6px;
            border-bottom: 1px solid #000;
            padding-bottom: 6px;
        }
        .header-section-inner {
            width: 100%;
            border-collapse: collapse;
        }
        .header-section-inner td {
            vertical-align: top;
            padding: 0 4px 0 0;
        }
        .company-left { width: 52%; }
        .company-right { width: 48%; text-align: right; }
        .header-brand-table { width: 100%; border-collapse: collapse; margin: 0; }
        .header-brand-table td { vertical-align: top; padding: 0 6px 0 0; border: none; }
        .header-brand-table .logo-cell { width: 100px; }
        .company-left h1 {
            font-size: 10pt;
            font-weight: bold;
            margin: 0 0 3px 0;
            line-height: 1.15;
        }
        .company-right h2 {
            font-size: 12.5pt;
            margin: 0 0 3px 0;
            line-height: 1.1;
            font-weight: bold;
        }
        .client-info { margin-bottom: 6px; }
        .client-info h3 {
            font-size: 10.5pt;
            margin: 0 0 4px 0;
            font-weight: bold;
        }
        .client-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
        }
        .client-table td {
            border: 1px solid #000;
            padding: 2px 4px;
            text-align: left;
            width: 33.33%;
            font-size: 9.5pt;
            line-height: 1.15;
        }
        .products-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
            font-size: 9pt;
        }
        .products-table th,
        .products-table td {
            border: 1px solid #000;
            padding: 2px 3px;
            text-align: left;
            line-height: 1.1;
        }
        .products-table th {
            background-color: #f0f0f0;
            font-weight: bold;
            font-size: 9pt;
        }
        .products-table--many { font-size: 8pt; }
        .products-table--many th,
        .products-table--many td { padding: 1px 2px; font-size: 8pt; }
        .totals {
            text-align: right;
            margin-top: 6px;
            font-size: 9.5pt;
        }
        .totals p { margin: 0 0 2px 0; line-height: 1.2; }
        .totals .grand-line {
            font-size: 11pt;
            font-weight: bold;
            margin-top: 4px;
            padding-top: 4px;
            border-top: 1px solid #000;
        }
        .observations {
            margin-top: 8px;
            padding: 4px 6px;
            border: 1px solid #000;
            background-color: #fffacd;
            font-size: 9pt;
        }
        .observations h4 {
            margin: 0 0 4px 0;
            font-size: 9.5pt;
        }
        .footer {
            margin-top: 16px;
            text-align: center;
            font-size: 8.5pt;
            color: #333;
        }
        .footer-legal {
            margin-top: 8px;
            font-size: 8.5pt;
            font-style: italic;
            color: #222;
            line-height: 1.3;
        }
        @media print {
            @page { size: A4 portrait; margin: 8mm; }
            body { margin: 0; padding: 0; }
        }
    </style>
</head>
<body>
    @php
        $docDir = $cotizacion->cliente_direccion ?? null;
        $docTel = $cotizacion->cliente_telefono ?? null;
        $direccionPdf = ($docDir !== null && trim((string) $docDir) !== '') ? $docDir : ($cliente ? ($cliente->direccion ?? 'N/A') : 'N/A');
        $telefonoPdf = ($docTel !== null && trim((string) $docTel) !== '') ? $docTel : ($cliente ? ($cliente->telefono ?? 'N/A') : 'N/A');
        $sEntrega = null;
        if ($cliente && $cotizacion->cliente_suen !== null) {
            $sEntrega = \App\Models\ClienteSucursal::where('codigo_cliente', $cotizacion->cliente_codigo)
                ->where('suen', (string) $cotizacion->cliente_suen)
                ->first();
        }
        $regionPdf = ($sEntrega && !empty($sEntrega->region)) ? $sEntrega->region : ($cliente ? ($cliente->region ?? null) : null);
        $comunaPdf = ($sEntrega && !empty($sEntrega->comuna)) ? $sEntrega->comuna : ($cliente ? ($cliente->comuna ?? null) : null);
        $tituloTipo = $cotizacion->tipo_documento === 'nota_venta' ? 'NOTA DE VENTA' : 'COTIZACIÓN';
        $fechaDoc = ($cotizacion->fecha ?? $cotizacion->created_at)?->timezone(config('app.timezone'));

        // Totales: subtotal_neto e iva no se persistían si faltaban en $fillable del modelo (corregido). Recalcular si vienen en 0.
        $subtotalBruto = (float) ($cotizacion->subtotal ?? 0);
        $descGlobal = (float) ($cotizacion->descuento_global ?? 0);
        $subtotalNetoPdf = (float) ($cotizacion->subtotal_neto ?? 0);
        $ivaPdf = (float) ($cotizacion->iva ?? 0);
        $totalPdf = (float) ($cotizacion->total ?? 0);
        if (abs($subtotalNetoPdf) < 0.01 && ($subtotalBruto > 0 || $descGlobal > 0)) {
            $subtotalNetoPdf = $subtotalBruto - $descGlobal;
        }
        if (abs($ivaPdf) < 0.01 && $subtotalNetoPdf > 0) {
            $ivaPdf = round($subtotalNetoPdf * 0.19, 2);
        }
        if (abs($totalPdf) < 0.01 && ($subtotalNetoPdf > 0 || $ivaPdf > 0)) {
            $totalPdf = round($subtotalNetoPdf + $ivaPdf, 2);
        }
    @endphp

    <div class="header-section">
        <table class="header-section-inner">
            <tr>
                <td class="company-left">
                    <table class="header-brand-table">
                        <tr>
                            <td class="logo-cell">@include('pdf.partials.logo-empresa')</td>
                            <td>
                                <h1>HIGUERA COMERCIALIZADORA CLAUDIO ANDRES HIGUERA PAVEZ E.I.R.L.</h1>
                                <p><strong>Giro:</strong> Comercialización y Distribución de Art. De Ferretería y Construcción.</p>
                                <p><strong>Casa Matriz:</strong> Bernardo O'higgins n° 157 - Colina - Santiago</p>
                                <p><strong>Fono:</strong> 26 4656436</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td class="company-right">
                    <p><strong>R.U.T.:</strong> 76.426.104-6</p>
                    <h2>{{ $tituloTipo }}</h2>
                    <p><strong>Nro.:</strong> {{ str_pad((string) $cotizacion->id, 10, '0', STR_PAD_LEFT) }}</p>
                    <p><strong>Fecha:</strong> {{ $fechaDoc ? $fechaDoc->format('d/m/Y') : '' }}</p>
                    <p><strong>Hora:</strong> {{ $fechaDoc ? $fechaDoc->format('H:i:s') : '' }}</p>
                </td>
            </tr>
        </table>
    </div>

    <div class="client-info">
        <h3>DATOS DEL CLIENTE</h3>
        @if($cliente)
            <table class="client-table">
                <tr>
                    <td><strong>Código:</strong> {{ $cliente->codigo_cliente }}</td>
                    <td><strong>Señor(es):</strong> {{ $cotizacion->cliente_nombre ?? $cliente->nombre_cliente }}</td>
                    <td><strong>Teléfono:</strong> {{ $telefonoPdf }}</td>
                </tr>
                @if($cotizacion->cliente_suen !== null)
                <tr>
                    <td colspan="3"><strong>Sucursal (entrega):</strong>
                        {{ trim((string) $cotizacion->cliente_suen) === '' ? 'Casa matriz' : 'Sucursal ' . $cotizacion->cliente_suen }}
                    </td>
                </tr>
                @endif
                <tr>
                    <td colspan="3"><strong>Dirección de entrega:</strong> {{ $direccionPdf }}</td>
                </tr>
                <tr>
                    <td><strong>Región:</strong> {{ $regionPdf ?? 'N/A' }}</td>
                    <td><strong>Comuna:</strong> {{ $comunaPdf ?? 'N/A' }}</td>
                    <td><strong>RUT:</strong> {{ $cliente->rut_cliente ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td><strong>Vendedor:</strong> {{ optional($cotizacion->user)->name ?? 'N/A' }}</td>
                    <td><strong>Estado doc.:</strong> {{ ucfirst($cotizacion->estado) }}</td>
                    <td>
                        @if($cotizacion->tipo_documento === 'nota_venta')
                            <strong>Estado aprobación:</strong> {{ ucfirst(str_replace('_', ' ', $cotizacion->estado_aprobacion ?? 'N/A')) }}
                        @else
                            &nbsp;
                        @endif
                    </td>
                </tr>
            </table>
        @else
            <table class="client-table">
                <tr><td colspan="3">Cliente no encontrado</td></tr>
            </table>
        @endif
    </div>

    <h3 style="font-size: 10.5pt; margin: 8px 0 4px 0;">DETALLE DE PRODUCTOS</h3>
    <table class="products-table{{ $cotizacion->productos->count() > 10 ? ' products-table--many' : '' }}">
        <thead>
            <tr>
                <th>Código</th>
                <th>Producto</th>
                <th>Cantidad</th>
                <th>Precio unit.</th>
                <th>Desc. %</th>
                <th>Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($cotizacion->productos as $producto)
                <tr>
                    <td>{{ $producto->codigo_producto }}</td>
                    <td>{{ $producto->nombre_producto }}</td>
                    <td>{{ number_format($producto->cantidad, 0, ',', '.') }} {{ $producto->unidad_medida ?? 'UN' }}</td>
                    <td>${{ number_format($producto->precio_unitario, 0, ',', '.') }}</td>
                    <td>{{ $producto->descuento_porcentaje ?? 0 }}%</td>
                    <td>${{ number_format($producto->subtotal, 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <p><strong>Subtotal:</strong> ${{ number_format($subtotalBruto, 0, ',', '.') }}</p>
        <p><strong>Descuento global:</strong> ${{ number_format($descGlobal, 0, ',', '.') }}</p>
        <p><strong>Subtotal neto:</strong> ${{ number_format($subtotalNetoPdf, 0, ',', '.') }}</p>
        <p><strong>IVA (19%):</strong> ${{ number_format($ivaPdf, 0, ',', '.') }}</p>
        <p class="grand-line"><strong>TOTAL:</strong> ${{ number_format($totalPdf, 0, ',', '.') }}</p>
    </div>

    @if($cotizacion->observaciones || ($cotizacion->tipo_documento === 'nota_venta' && ($cotizacion->numero_orden_compra || $cotizacion->observacion_vendedor)))
        <div class="observations">
            <h4>Observaciones e información adicional</h4>
            @if($cotizacion->observaciones)
                <p><strong>Observaciones:</strong> {{ $cotizacion->observaciones }}</p>
            @endif
            @if($cotizacion->tipo_documento === 'nota_venta' && $cotizacion->numero_orden_compra)
                <p><strong>Orden de compra:</strong> {{ $cotizacion->numero_orden_compra }}</p>
            @endif
            @if($cotizacion->tipo_documento === 'nota_venta' && $cotizacion->observacion_vendedor)
                <p><strong>Obs. vendedor:</strong> {{ $cotizacion->observacion_vendedor }}</p>
            @endif
        </div>
    @endif

    <div class="footer">
        <p>Documento generado el {{ now()->timezone(config('app.timezone'))->format('d/m/Y H:i') }} — Comercial Higuera</p>
        @if($cotizacion->tipo_documento === 'cotizacion')
            <p class="footer-legal">Cotización válida por 30 días desde la fecha de creación del documento.</p>
        @endif
    </div>
</body>
</html>
