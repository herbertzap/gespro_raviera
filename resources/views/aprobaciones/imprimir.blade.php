<!DOCTYPE html>
<html>
<head>
    <title>Guía de Despacho - Nota de Venta #{{ $cotizacion->id }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 10px;
            font-size: 10px;
            line-height: 1.2;
        }
        .header-section {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
        }
        .company-left {
            width: 50%;
            text-align: left;
        }
        .company-right {
            width: 50%;
            text-align: right;
        }
        .client-info {
            margin-bottom: 15px;
        }
        .client-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .client-table td {
            border: 1px solid #000;
            padding: 3px;
            text-align: left;
            width: 33.33%;
        }
        .products-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            font-size: 9px;
        }
        .products-table th,
        .products-table td {
            border: 1px solid #000;
            padding: 2px;
            text-align: left;
        }
        .products-table th {
            background-color: #f0f0f0;
            font-weight: bold;
            font-size: 9px;
        }
        .warehouse-section {
            margin-top: 15px;
            padding: 8px;
            border: 1px solid #000;
            background-color: #f9f9f9;
        }
        .warehouse-content {
            display: flex;
            justify-content: space-between;
        }
        .warehouse-left, .warehouse-right {
            width: 48%;
        }
        .warehouse-title {
            text-align: center;
            font-weight: bold;
            margin-bottom: 8px;
        }
        .totals {
            text-align: right;
            margin-top: 15px;
            font-size: 10px;
        }
        .observations {
            margin-top: 15px;
            padding: 8px;
            border: 1px solid #000;
            background-color: #fffacd;
            font-size: 9px;
        }
        .signatures {
            margin-top: 15px;
            display: flex;
            justify-content: space-between;
        }
        .signature-box {
            text-align: center;
            width: 30%;
        }
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="header-section">
        <div class="company-left">
            <h1>HIGUERA COMERCIALIZADORA CLAUDIO ANDRES HIGUERA PAVEZ E.I.R.L.</h1>
            <p><strong>Giro:</strong> Comercialización y Distribución de Art. De Ferretería y Construcción.</p>
            <p><strong>Casa Matriz:</strong> Bernardo O'higgins n° 157 - Colina - Santiago</p>
            <p><strong>Fono:</strong> 26 4656436</p>
            <p><strong>Dirección de Entrega:</strong> Bernardo O'higgins 157 - Colina</p>
        </div>
        <div class="company-right">
            <p><strong>R.U.T.:</strong> 76.426.104-6</p>
            <h2>GUÍA DE DESPACHO</h2>
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
                <td><strong>Dirección:</strong> {{ $cliente->direccion ?? 'No especificada' }}</td>
                <td><strong>RUT:</strong> {{ $cliente->rut_cliente ?? 'No especificado' }}</td>
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
    
    <table class="products-table">
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
                $precioConDescuento = $producto->precio_unitario - $descuentoValor;
                $subtotal = $producto->cantidad * $precioConDescuento;
                $iva = $subtotal * 0.19;
                $total = $subtotal + $iva;
            @endphp
            <tr>
                <td>{{ $producto->codigo_producto }}</td>
                <td>{{ number_format($producto->cantidad, 2, ',', '.') }}</td>
                <td>UN</td>
                <td>{{ $producto->descripcion_producto }}</td>
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
        <div class="warehouse-title">BODEGA</div>
        <div class="warehouse-content">
            <div class="warehouse-left">
                <p><strong>SEPARADO POR:</strong> _________________</p>
                <p><strong>REVISADO POR:</strong> _________________</p>
            </div>
            <div class="warehouse-right">
                <p><strong>N° DE BULTOS:</strong> _________________</p>
                <p><strong>FIRMA PICKING:</strong> _________________</p>
            </div>
        </div>
    </div>
    
    <div class="totals">
        @php
            $totalSinDescuento = $cotizacion->productos->sum(function($producto) {
                return $producto->cantidad * $producto->precio_unitario;
            });
            $totalDescuentos = $cotizacion->productos->sum(function($producto) {
                $descuentoValor = $producto->descuento_valor ?? 0;
                return $producto->cantidad * $descuentoValor;
            });
            $totalNeto = $totalSinDescuento - $totalDescuentos;
            $iva = $totalNeto * 0.19;
            $totalFinal = $totalNeto + $iva;
        @endphp
        <p><strong>DESCTO. GLOBAL:</strong> ${{ number_format($totalDescuentos, 0, ',', '.') }}</p>
        <p><strong>NETO $:</strong> ${{ number_format($totalNeto, 0, ',', '.') }}</p>
        <p><strong>EXENTO $:</strong> $0</p>
        <p><strong>19% I.V.A. $:</strong> ${{ number_format($iva, 0, ',', '.') }}</p>
        <p><strong>TOTAL $:</strong> ${{ number_format($totalFinal, 0, ',', '.') }}</p>
    </div>
    
    <div class="observations">
        <h4>Otras Observaciones:</h4>
        <p>{{ $cotizacion->observaciones ?? 'Sin observaciones' }}</p>
        @if($observacionesExtra)
        <p><strong>Observaciones Extra:</strong> {{ $observacionesExtra }}</p>
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
