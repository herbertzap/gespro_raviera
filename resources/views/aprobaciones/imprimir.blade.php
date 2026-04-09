<!DOCTYPE html>
<html>
<head>
    <title>Guía de Picking - Nota de Venta #{{ $cotizacion->id }}</title>
    <style>
        /* Compactación para imprimir la guía en 1 página A4 (timbres arriba) */
        html { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            margin: 0;
            padding: 4px 6px;
            font-size: 8.5pt;
            line-height: 1.15;
        }
        body p { margin: 0 0 2px 0; }
        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 4px;
            border-bottom: 1px solid #000;
            padding-bottom: 4px;
        }
        .header-brand-table { width: 100%; border-collapse: collapse; margin: 0; }
        .header-brand-table td { vertical-align: top; padding: 0 6px 0 0; border: none; }
        .header-brand-table .logo-cell { width: 100px; }
        .header-section h1 {
            font-size: 8.5pt;
            font-weight: bold;
            margin: 0 0 3px 0;
            line-height: 1.15;
        }
        .header-section h2 {
            font-size: 11pt;
            margin: 0 0 3px 0;
            line-height: 1.1;
        }
        .company-left { width: 52%; text-align: left; }
        .company-right { width: 48%; text-align: right; }
        .client-info { margin-bottom: 4px; }
        .client-info h3 {
            font-size: 9pt;
            margin: 0 0 2px 0;
            font-weight: bold;
        }
        .client-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
        }
        .client-table td {
            border: 1px solid #000;
            padding: 1px 3px;
            text-align: left;
            width: 33.33%;
            font-size: 8pt;
            line-height: 1.15;
        }
        .products-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
            font-size: 7.5pt;
        }
        .products-table th,
        .products-table td {
            border: 1px solid #000;
            padding: 1px 2px;
            text-align: left;
            line-height: 1.1;
        }
        .products-table th {
            background-color: #f0f0f0;
            font-weight: bold;
            font-size: 7.5pt;
        }
        /* Muchas líneas: aún más compacto para una sola hoja */
        .products-table--many { font-size: 6.5pt; }
        .products-table--many th,
        .products-table--many td { padding: 0 1px; font-size: 6.5pt; }
        .warehouse-section {
            margin-top: 4px;
            padding: 3px 5px;
            border: 1px solid #000;
            background-color: #f9f9f9;
        }
        .warehouse-section p { margin: 0 0 1px 0; font-size: 8pt; }
        .warehouse-content {
            display: flex;
            justify-content: space-between;
        }
        .warehouse-left, .warehouse-right { width: 49%; }
        .warehouse-title {
            text-align: center;
            font-weight: bold;
            margin-bottom: 2px;
            font-size: 8.5pt;
        }
        .totals {
            text-align: right;
            margin-top: 3px;
            font-size: 8pt;
        }
        .totals p { margin: 0; line-height: 1.2; }
        .observations {
            margin-top: 3px;
            padding: 3px 5px;
            border: 1px solid #000;
            background-color: #fffacd;
            font-size: 7.5pt;
        }
        .observations h4 {
            margin: 0 0 2px 0;
            font-size: 8pt;
        }
        .observations .obs-block { margin-bottom: 3px !important; padding: 2px 4px !important; }
        .observations .obs-block p { margin: 0; }
        .signatures {
            margin-top: 4px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            page-break-inside: avoid;
        }
        .signature-box {
            text-align: center;
            width: 31%;
            font-size: 7.5pt;
        }
        .signature-box p { margin: 0 0 1px 0; }
        @media print {
            @page { size: A4 portrait; margin: 6mm; }
            body { margin: 0; padding: 0; font-size: 8.2pt; }
            .no-print { display: none; }
            .signatures { page-break-inside: avoid; break-inside: avoid; }
            .warehouse-section { page-break-inside: avoid; }
        }
    </style>
</head>
<body>
    <div class="header-section">
        <div class="company-left">
            <table class="header-brand-table">
                <tr>
                    <td class="logo-cell">@include('pdf.partials.logo-empresa')</td>
                    <td>
                        <h1>HIGUERA COMERCIALIZADORA CLAUDIO ANDRES HIGUERA PAVEZ E.I.R.L.</h1>
                        <p><strong>Giro:</strong> Comercialización y Distribución de Art. De Ferretería y Construcción.</p>
                        <p><strong>Casa Matriz:</strong> Bernardo O'higgins n° 157 - Colina - Santiago</p>
                        <p><strong>Fono:</strong> 26 4656436</p>
                        <p><strong>Dirección de Entrega (doc.):</strong>
                            @php
                                $__dirEnt = trim((string) ($cotizacion->cliente_direccion ?? ''));
                            @endphp
                            {{ $__dirEnt !== '' ? $__dirEnt : 'Bernardo O\'higgins 157 - Colina' }}
                        </p>
                    </td>
                </tr>
            </table>
        </div>
        <div class="company-right">
            <p><strong>R.U.T.:</strong> 76.426.104-6</p>
            <h2>GUÍA DE PICKING</h2>
            <p><strong>Nro.:</strong> {{ str_pad($cotizacion->id, 10, '0', STR_PAD_LEFT) }}</p>
            <p><strong>Fecha:</strong> {{ date('d/m/Y', strtotime($cotizacion->created_at)) }}</p>
            <p><strong>Hora:</strong> {{ date('H:i:s', strtotime($cotizacion->created_at)) }}</p>
        </div>
    </div>
    
    <div class="client-info">
        <h3>DATOS DEL CLIENTE</h3>
        @php
            $cliente = \App\Models\Cliente::where('codigo_cliente', $cotizacion->cliente_codigo)->first();
            $vendedor = null;
            if ($cliente && $cliente->codigo_vendedor) {
                $vendedor = \App\Models\User::where('codigo_vendedor', $cliente->codigo_vendedor)->first();
            }
        @endphp
        <table class="client-table">
            <tr>
                <td><strong>Señor(es):</strong> {{ $cliente->nombre_cliente ?? $cotizacion->cliente_nombre }}</td>
                <td><strong>Dirección entrega:</strong> {{ !empty(trim((string) ($cotizacion->cliente_direccion ?? ''))) ? $cotizacion->cliente_direccion : ($cliente->direccion ?? 'No especificada') }}</td>
                <td><strong>RUT:</strong> {{ !empty($cliente->rut_cliente) ? $cliente->rut_cliente : ($cotizacion->cliente_codigo ?? 'No especificado') }}</td>
            </tr>
            <tr>
                <td><strong>Teléfono:</strong> {{ $cliente->telefono ?? 'No especificado' }}</td>
                <td><strong>Email:</strong> {{ $cliente->email ?? 'No especificado' }}</td>
                <td><strong>Cond. Pago:</strong> CREDITO 30 DIAS</td>
            </tr>
            <tr>
                <td><strong>Región:</strong> {{ $cliente->region ?? 'No especificada' }}</td>
                <td><strong>Comuna:</strong> {{ $cliente->comuna ?? 'No especificada' }}</td>
                <td><strong>Vendedor:</strong> {{ $vendedor ? $vendedor->name . ' (' . $cliente->codigo_vendedor . ')' : ($cotizacion->vendedor_nombre ?? 'No especificado') }}</td>
            </tr>
            <tr>
                <td><strong>Vencimiento:</strong> {{ date('d/m/Y', strtotime('+30 days', strtotime($cotizacion->created_at))) }}</td>
                <td></td>
                <td></td>
            </tr>
        </table>
    </div>
    
    <table class="products-table{{ $cotizacion->productos->count() > 10 ? ' products-table--many' : '' }}">
        <thead>
            <tr>
                <th>Código</th>
                <th>Cantidad</th>
                <th>UD</th>
                <th>Descripción</th>
                <th>Precio</th>
                <th>Descto.</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($cotizacion->productos as $producto)
            @php
                $descuentoPorcentaje = $producto->descuento_porcentaje ?? 0;
                $descuentoValor = $producto->descuento_valor ?? 0;
                // Usar los valores ya calculados y guardados en la BD
                $subtotalConDescuento = $producto->subtotal_con_descuento ?? ($producto->cantidad * $producto->precio_unitario - $descuentoValor);
                $total = $producto->total_producto ?? ($subtotalConDescuento * 1.19);
            @endphp
            <tr>
                <td>{{ $producto->codigo_producto }}</td>
                <td>{{ number_format($producto->cantidad, 2, ',', '.') }}</td>
                <td>UN</td>
                <td>{{ $producto->nombre_producto }}</td>
                <td>${{ number_format($producto->precio_unitario, 0, ',', '.') }}</td>
                <td>
                    @if($descuentoPorcentaje > 0)
                        {{ $descuentoPorcentaje }}%
                    @else
                        -
                    @endif
                </td>
                <td>${{ number_format($total, 0, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    
    <div class="warehouse-section">
        <div class="warehouse-title">BODEGA{{ $cotizacion->guia_picking_bodega ? ': ' . $cotizacion->guia_picking_bodega : '' }}</div>
        <div class="warehouse-content">
            <div class="warehouse-left">
                <p><strong>SEPARADO POR:</strong> {{ $cotizacion->guia_picking_separado_por ? $cotizacion->guia_picking_separado_por : '_________________' }}</p>
                <p><strong>REVISADO POR:</strong> {{ $cotizacion->guia_picking_revisado_por ? $cotizacion->guia_picking_revisado_por : '_________________' }}</p>
            </div>
            <div class="warehouse-right">
                <p><strong>N° DE BULTOS:</strong> {{ $cotizacion->guia_picking_numero_bultos ? $cotizacion->guia_picking_numero_bultos : '_________________' }}</p>
                <p><strong>FIRMA PICKING:</strong> {{ $cotizacion->guia_picking_firma ? $cotizacion->guia_picking_firma : '_________________' }}</p>
            </div>
        </div>
    </div>
    
    <div class="totals">
        @php
            // Calcular subtotal sin descuentos
            $totalSinDescuento = $cotizacion->productos->sum(function($producto) {
                return $producto->cantidad * $producto->precio_unitario;
            });
            // descuento_valor ya es el valor total del descuento para esa línea (no multiplicar por cantidad)
            $totalDescuentos = $cotizacion->productos->sum(function($producto) {
                return $producto->descuento_valor ?? 0;
            });
            // Usar subtotal_con_descuento si está disponible, sino calcular
            $totalNeto = $cotizacion->productos->sum(function($producto) {
                return $producto->subtotal_con_descuento ?? ($producto->cantidad * $producto->precio_unitario - ($producto->descuento_valor ?? 0));
            });
            // Calcular IVA sobre el neto
            $iva = $totalNeto * 0.19;
            // Total final con IVA
            $totalFinal = $totalNeto + $iva;
        @endphp
        <p><strong>DESCTO. GLOBAL:</strong> ${{ number_format($totalDescuentos, 0, ',', '.') }}</p>
        <p><strong>NETO $:</strong> ${{ number_format($totalNeto, 0, ',', '.') }}</p>
        <p><strong>EXENTO $:</strong> $0</p>
        <p><strong>19% I.V.A. $:</strong> ${{ number_format($iva, 0, ',', '.') }}</p>
        <p><strong>TOTAL $:</strong> ${{ number_format($totalFinal, 0, ',', '.') }}</p>
    </div>
    
    <div class="observations">
        <h4>Observaciones:</h4>
        
        @if($cotizacion->observacion_vendedor)
        <div class="obs-block" style="background-color: #e3f2fd; border-left: 3px solid #2196F3;">
            <p style="font-weight: bold; color: #1976D2;">Observaciones del Vendedor:</p>
            <p>{{ $cotizacion->observacion_vendedor }}</p>
        </div>
        @endif
        
        @if($cotizacion->observaciones)
        <div class="obs-block">
            <p style="font-weight: bold;">Observaciones Generales:</p>
            <p>{{ $cotizacion->observaciones }}</p>
        </div>
        @endif
        
        @if(isset($observacionesExtra) && $observacionesExtra)
        <div class="obs-block">
            <p style="font-weight: bold;">Observaciones Extra:</p>
            <p>{{ $observacionesExtra }}</p>
        </div>
        @endif
        
        @if($cotizacion->observaciones_picking)
        <div class="obs-block" style="background-color: #fff3cd; border-left: 3px solid #ffc107;">
            <p style="font-weight: bold; color: #856404;">Observaciones de Picking:</p>
            <p>{{ $cotizacion->observaciones_picking }}</p>
        </div>
        @endif
        
        @if(!$cotizacion->observacion_vendedor && !$cotizacion->observaciones && (!isset($observacionesExtra) || !$observacionesExtra) && !$cotizacion->observaciones_picking)
        <p style="margin: 0; font-style: italic; color: #666;">Sin observaciones</p>
        @endif
    </div>
    
    <!-- Espacio para firmas -->
    <div class="signatures">
        <div class="signature-box">
            <p>_________________________</p>
            <p><strong>TIMBRE 1</strong></p>
        </div>
        <div class="signature-box">
            <p>_________________________</p>
            <p><strong>TIMBRE 2</strong></p>
        </div>
        <div class="signature-box">
            <p>_________________________</p>
            <p><strong>Firma y Fecha</strong></p>
        </div>
    </div>
    
    <script>
        // Imprimir automáticamente al cargar
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        };
    </script>
</body>
</html>
