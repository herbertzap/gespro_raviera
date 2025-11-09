<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $cotizacion->tipo_documento === 'nota_venta' ? 'Nota de Venta' : 'Cotización' }} #{{ $cotizacion->id }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #e14eca;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .company-info {
            margin-bottom: 20px;
        }
        .company-name {
            font-size: 24px;
            font-weight: bold;
            color: #e14eca;
        }
        .document-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        .client-info, .document-details {
            width: 48%;
        }
        .section-title {
            font-size: 16px;
            font-weight: bold;
            color: #e14eca;
            margin-bottom: 10px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }
        .info-row {
            margin-bottom: 5px;
        }
        .label {
            font-weight: bold;
            display: inline-block;
            width: 120px;
        }
        .client-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .client-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
            width: 33.33%;
        }
        .products-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .products-table th,
        .products-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .products-table th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        .totals {
            text-align: right;
            margin-top: 20px;
        }
        .total-row {
            margin-bottom: 5px;
        }
        .total-label {
            font-weight: bold;
            display: inline-block;
            width: 150px;
        }
        .total-value {
            display: inline-block;
            width: 100px;
            text-align: right;
        }
        .grand-total {
            font-size: 18px;
            font-weight: bold;
            color: #e14eca;
            border-top: 2px solid #e14eca;
            padding-top: 10px;
        }
        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 12px;
            color: #666;
        }
        .observations {
            margin-top: 30px;
            padding: 15px;
            background-color: #f8f9fa;
            border-left: 4px solid #e14eca;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="company-info">
            <div class="company-name">COMERCIAL HIGUERA</div>
            <div>Sistema de Gestión Logística</div>
        </div>
        <h1>{{ $cotizacion->tipo_documento === 'nota_venta' ? 'NOTA DE VENTA' : 'COTIZACIÓN' }} #{{ $cotizacion->id }}</h1>
    </div>

    <div class="document-info">
        <div class="client-info">
            <div class="section-title">Información del Cliente</div>
            @if($cliente)
                <table class="client-table">
                    <tr>
                        <td><strong>Código:</strong> {{ $cliente->codigo_cliente }}</td>
                        <td><strong>Nombre:</strong> {{ $cliente->nombre_cliente }}</td>
                        <td><strong>Teléfono:</strong> {{ $cliente->telefono ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td colspan="3"><strong>Dirección:</strong> {{ $cliente->direccion ?? 'N/A' }}</td>
                    </tr>
                    @if($cliente->region || $cliente->comuna)
                    <tr>
                        <td><strong>Región:</strong> {{ $cliente->region ?? 'N/A' }}</td>
                        <td><strong>Comuna:</strong> {{ $cliente->comuna ?? 'N/A' }}</td>
                        <td><strong>RUT:</strong> {{ $cliente->rut_cliente ?? 'N/A' }}</td>
                    </tr>
                    @endif
                </table>
            @else
                <table class="client-table">
                    <tr>
                        <td colspan="3">Cliente no encontrado</td>
                    </tr>
                </table>
            @endif
        </div>

        <div class="document-details">
            <div class="section-title">Detalles del Documento</div>
            <div class="info-row">
                <span class="label">Fecha:</span>
                {{ $cotizacion->fecha ? $cotizacion->fecha->format('d/m/Y') : 'N/A' }}
            </div>
            <div class="info-row">
                <span class="label">Vendedor:</span>
                {{ $cotizacion->user->name ?? 'N/A' }}
            </div>
            <div class="info-row">
                <span class="label">Estado:</span>
                {{ ucfirst($cotizacion->estado) }}
            </div>
            @if($cotizacion->tipo_documento === 'nota_venta')
                <div class="info-row">
                    <span class="label">Estado Aprobación:</span>
                    {{ ucfirst(str_replace('_', ' ', $cotizacion->estado_aprobacion ?? 'N/A')) }}
                </div>
            @endif
        </div>
    </div>

    <div class="section-title">Productos</div>
    <table class="products-table">
        <thead>
            <tr>
                <th>Código</th>
                <th>Producto</th>
                <th>Cantidad</th>
                <th>Precio Unit.</th>
                <th>Descuento</th>
                <th>Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($cotizacion->productos as $producto)
                <tr>
                    <td>{{ $producto->codigo_producto }}</td>
                    <td>{{ $producto->nombre_producto }}</td>
                    <td>{{ number_format($producto->cantidad, 0) }} {{ $producto->unidad_medida ?? 'UN' }}</td>
                    <td>${{ number_format($producto->precio_unitario, 0) }}</td>
                    <td>{{ $producto->descuento_porcentaje }}%</td>
                    <td>${{ number_format($producto->subtotal, 0) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <div class="total-row">
            <span class="total-label">Subtotal:</span>
            <span class="total-value">${{ number_format($cotizacion->subtotal, 0) }}</span>
        </div>
        <div class="total-row">
            <span class="total-label">Descuento Global:</span>
            <span class="total-value">${{ number_format($cotizacion->descuento_global, 0) }}</span>
        </div>
        <div class="total-row">
            <span class="total-label">Subtotal Neto:</span>
            <span class="total-value">${{ number_format($cotizacion->subtotal_neto, 0) }}</span>
        </div>
        <div class="total-row">
            <span class="total-label">IVA (19%):</span>
            <span class="total-value">${{ number_format($cotizacion->iva, 0) }}</span>
        </div>
        <div class="total-row grand-total">
            <span class="total-label">TOTAL:</span>
            <span class="total-value">${{ number_format($cotizacion->total, 0) }}</span>
        </div>
    </div>

    @if($cotizacion->observaciones)
        <div class="observations">
            <div class="section-title">Observaciones</div>
            <p>{{ $cotizacion->observaciones }}</p>
        </div>
    @endif

    @if($cotizacion->tipo_documento === 'nota_venta' && ($cotizacion->numero_orden_compra || $cotizacion->observacion_vendedor))
        <div class="observations">
            <div class="section-title">Información Adicional</div>
            @if($cotizacion->numero_orden_compra)
                <div class="info-row">
                    <span class="label">Orden de Compra:</span>
                    {{ $cotizacion->numero_orden_compra }}
                </div>
            @endif
            @if($cotizacion->observacion_vendedor)
                <div class="info-row">
                    <span class="label">Observación Vendedor:</span>
                    {{ $cotizacion->observacion_vendedor }}
                </div>
            @endif
        </div>
    @endif

    <div class="footer">
        <p>Documento generado el {{ now()->format('d/m/Y H:i:s') }}</p>
        <p>Comercial Higuera - Sistema de Gestión Logística</p>
    </div>
</body>
</html>
