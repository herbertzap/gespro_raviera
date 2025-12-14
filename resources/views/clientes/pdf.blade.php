<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ficha Cliente - {{ $cliente->codigo_cliente }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            color: #333;
            font-size: 11px;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #e14eca;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .company-name {
            font-size: 24px;
            font-weight: bold;
            color: #e14eca;
        }
        .section {
            margin-bottom: 25px;
            page-break-inside: avoid;
        }
        .section-title {
            font-size: 14px;
            font-weight: bold;
            color: #e14eca;
            margin-bottom: 10px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-bottom: 15px;
        }
        .info-item {
            margin-bottom: 5px;
        }
        .label {
            font-weight: bold;
            display: inline-block;
            width: 140px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            font-size: 10px;
        }
        table th,
        table td {
            border: 1px solid #ddd;
            padding: 6px;
            text-align: left;
        }
        table th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .badge {
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 9px;
            font-weight: bold;
        }
        .badge-success {
            background-color: #28a745;
            color: white;
        }
        .badge-danger {
            background-color: #dc3545;
            color: white;
        }
        .badge-warning {
            background-color: #ffc107;
            color: #333;
        }
        .badge-info {
            background-color: #17a2b8;
            color: white;
        }
        .totals {
            margin-top: 20px;
            padding: 15px;
            background-color: #f8f9fa;
            border: 1px solid #ddd;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="company-name">COMERCIAL HIGUERA</div>
        <div>Sistema de Gestión Logística</div>
        <h1>FICHA DE CLIENTE</h1>
    </div>

    <!-- Información General del Cliente -->
    <div class="section">
        <div class="section-title">Información General</div>
        <div class="info-grid">
            <div class="info-item">
                <span class="label">Código:</span>
                {{ $cliente->codigo_cliente }}
            </div>
            <div class="info-item">
                <span class="label">Nombre:</span>
                {{ $cliente->nombre_cliente }}
            </div>
            <div class="info-item">
                <span class="label">Dirección:</span>
                {{ $cliente->direccion ?: 'N/A' }}
            </div>
            <div class="info-item">
                <span class="label">Teléfono:</span>
                {{ $cliente->telefono ?: 'N/A' }}
            </div>
            <div class="info-item">
                <span class="label">Región:</span>
                {{ $cliente->region ?: 'N/A' }}
            </div>
            <div class="info-item">
                <span class="label">Comuna:</span>
                {{ $cliente->comuna ?: 'N/A' }}
            </div>
            <div class="info-item">
                <span class="label">Vendedor:</span>
                {{ $cliente->codigo_vendedor ?: 'N/A' }}
            </div>
            <div class="info-item">
                <span class="label">Estado:</span>
                @if($cliente->bloqueado)
                    <span class="badge badge-danger">Bloqueado</span>
                @else
                    <span class="badge badge-success">Activo</span>
                @endif
            </div>
            <div class="info-item">
                <span class="label">Lista de Precios:</span>
                {{ $cliente->lista_precios_nombre ?: 'N/A' }}
            </div>
            <div class="info-item">
                <span class="label">Condición de Pago:</span>
                {{ $cliente->condicion_pago ?: 'N/A' }}
            </div>
        </div>
    </div>

    <!-- Información de Crédito -->
    @if($creditoCliente)
    <div class="section">
        <div class="section-title">Información de Crédito</div>
        <div class="info-grid">
            <div class="info-item">
                <span class="label">Crédito Sin Doc:</span>
                ${{ number_format($creditoCliente['credito_sin_doc'] ?? 0, 0) }}
            </div>
            <div class="info-item">
                <span class="label">Crédito Cheques:</span>
                ${{ number_format($creditoCliente['credito_cheques'] ?? 0, 0) }}
            </div>
            <div class="info-item">
                <span class="label">Crédito Total:</span>
                ${{ number_format($creditoCliente['credito_total'] ?? 0, 0) }}
            </div>
            <div class="info-item">
                <span class="label">Estado:</span>
                <span class="badge badge-{{ $creditoCliente['estado'] == 'BLOQUEADO' ? 'danger' : 'success' }}">
                    {{ $creditoCliente['estado'] }}
                </span>
            </div>
        </div>
    </div>
    @endif

    <!-- Facturas Pendientes -->
    <div class="section">
        <div class="section-title">Facturas Pendientes</div>
        @if(count($facturasPendientes) > 0)
        <table>
            <thead>
                <tr>
                    <th>Tipo</th>
                    <th>Número</th>
                    <th>Emisión</th>
                    <th>Vencimiento</th>
                    <th>Días Venc.</th>
                    <th class="text-right">Valor</th>
                    <th class="text-right">Abonos</th>
                    <th class="text-right">Saldo</th>
                </tr>
            </thead>
            <tbody>
                @foreach($facturasPendientes as $factura)
                <tr>
                    <td>{{ $factura['TIPO_DOCTO'] }}</td>
                    <td>{{ $factura['NRO_DOCTO'] }}</td>
                    <td>{{ $factura['EMISION'] }}</td>
                    <td>{{ $factura['VENCIMIENTO'] }}</td>
                    <td class="text-center">{{ $factura['DIAS_VENCIDO'] }}</td>
                    <td class="text-right">${{ number_format($factura['VALOR'], 0) }}</td>
                    <td class="text-right">${{ number_format($factura['ABONOS'], 0) }}</td>
                    <td class="text-right">${{ number_format($factura['SALDO'], 0) }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="7" class="text-right">Total Saldo:</th>
                    <th class="text-right">${{ number_format(array_sum(array_column($facturasPendientes, 'SALDO')), 0) }}</th>
                </tr>
            </tfoot>
        </table>
        @else
        <p style="color: #666; font-style: italic;">Sin información declarada</p>
        @endif
    </div>

    <!-- Cheques en Cartera -->
    @if(count($chequesEnCarteraDetalle) > 0)
    <div class="section">
        <div class="section-title">Cheques en Cartera</div>
        <table>
            <thead>
                <tr>
                    <th>Número</th>
                    <th class="text-right">Valor</th>
                    <th>Fecha Vencimiento</th>
                    <th>Vendedor</th>
                </tr>
            </thead>
            <tbody>
                @foreach($chequesEnCarteraDetalle as $cheque)
                <tr>
                    <td>{{ $cheque['numero'] ?? '' }}</td>
                    <td class="text-right">${{ number_format($cheque['valor'] ?? 0, 0) }}</td>
                    <td>
                        @if($cheque['fecha_vencimiento'])
                            @php
                                try {
                                    $fecha = \Carbon\Carbon::parse($cheque['fecha_vencimiento']);
                                    echo $fecha->format('d/m/Y');
                                } catch (\Exception $e) {
                                    echo $cheque['fecha_vencimiento'];
                                }
                            @endphp
                        @else
                            N/A
                        @endif
                    </td>
                    <td>{{ $cheque['vendedor'] ?? '' }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <th class="text-right">Total:</th>
                    <th class="text-right">${{ number_format(array_sum(array_column($chequesEnCarteraDetalle, 'valor')), 0) }}</th>
                    <th colspan="2"></th>
                </tr>
            </tfoot>
        </table>
    </div>
    @endif

    <!-- Cheques Protestados -->
    <div class="section">
        <div class="section-title">Cheques Protestados</div>
        @if(count($chequesProtestadosDetalle) > 0)
        <table>
            <thead>
                <tr>
                    <th>Número</th>
                    <th class="text-right">Valor</th>
                    <th>Fecha Vencimiento</th>
                    <th>Vendedor</th>
                </tr>
            </thead>
            <tbody>
                @foreach($chequesProtestadosDetalle as $cheque)
                <tr>
                    <td>{{ $cheque['numero'] ?? '' }}</td>
                    <td class="text-right">${{ number_format($cheque['valor'] ?? 0, 0) }}</td>
                    <td>
                        @if($cheque['fecha_vencimiento'])
                            @php
                                try {
                                    $fecha = \Carbon\Carbon::parse($cheque['fecha_vencimiento']);
                                    echo $fecha->format('d/m/Y');
                                } catch (\Exception $e) {
                                    echo $cheque['fecha_vencimiento'];
                                }
                            @endphp
                        @else
                            N/A
                        @endif
                    </td>
                    <td>{{ $cheque['vendedor'] ?? '' }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <th class="text-right">Total:</th>
                    <th class="text-right">${{ number_format(array_sum(array_column($chequesProtestadosDetalle, 'valor')), 0) }}</th>
                    <th colspan="2"></th>
                </tr>
            </tfoot>
        </table>
        @else
        <p style="color: #666; font-style: italic;">Sin información declarada</p>
        @endif
    </div>

    <!-- NVV del Sistema -->
    <div class="section">
        <div class="section-title">NVV del Sistema</div>
        @if(count($nvvSistema) > 0)
        <table>
            <thead>
                <tr>
                    <th>Número</th>
                    <th>Fecha</th>
                    <th>Vendedor</th>
                    <th>Productos</th>
                    <th class="text-right">Valor Total</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                @foreach($nvvSistema as $nvv)
                <tr>
                    <td>{{ $nvv->numero_cotizacion ?? 'NVV#' . $nvv->id }}</td>
                    <td>{{ $nvv->created_at->format('d/m/Y') }}</td>
                    <td>{{ $nvv->user->name ?? 'N/A' }}</td>
                    <td class="text-center">{{ $nvv->productos->count() }}</td>
                    <td class="text-right">${{ number_format($nvv->total ?? 0, 0) }}</td>
                    <td>{{ ucfirst($nvv->estado_aprobacion ?? 'N/A') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <p style="color: #666; font-style: italic;">Sin información declarada</p>
        @endif
    </div>

    <!-- Pie de Página -->
    <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd; text-align: center; font-size: 9px; color: #666;">
        <p>Generado el {{ now()->format('d/m/Y H:i:s') }}</p>
        <p>COMERCIAL HIGUERA - Sistema de Gestión Logística</p>
    </div>
</body>
</html>

