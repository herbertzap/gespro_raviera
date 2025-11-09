<div class="mt-4">
    <!-- Información del Cliente -->
    @if($cliente)
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card bg-gradient-primary text-white">
                <div class="card-header">
                    <h4 class="card-title text-white">
                        <i class="tim-icons icon-single-02"></i>
                        Información del Cliente
                    </h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <strong>RUT/Código:</strong><br>
                            {{ $cliente->codigo }}
                        </div>
                        <div class="col-md-3">
                            <strong>Nombre/Razón Social:</strong><br>
                            {{ $cliente->nombre }}
                        </div>
                        <div class="col-md-3">
                            <strong>Estado:</strong><br>
                            @if($cliente->bloqueado)
                                <span class="badge badge-danger">Bloqueado</span>
                            @else
                                <span class="badge badge-success">Activo</span>
                            @endif
                        </div>
                        <div class="col-md-3">
                            <strong>Vendedor:</strong><br>
                            {{ $cotizacion->user->name ?? 'N/A' }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Información de la Nota de Venta -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">
                        <i class="tim-icons icon-notes"></i>
                        Información de la Nota de Venta
                    </h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-2">
                            <strong>Fecha:</strong><br>
                            {{ $cotizacion->fecha ? \Carbon\Carbon::parse($cotizacion->fecha)->format('d/m/Y H:i') : 'N/A' }}
                        </div>
                        <div class="col-md-2">
                            <strong>Estado:</strong><br>
                            <span class="badge badge-{{ $cotizacion->estado == 'enviada' ? 'success' : ($cotizacion->estado == 'borrador' ? 'warning' : 'info') }}">
                                {{ ucfirst($cotizacion->estado) }}
                            </span>
                            @if($cotizacion->numero_nvv)
                                <br><small class="text-success"><i class="tim-icons icon-check-2"></i> NVV #{{ $cotizacion->numero_nvv }} en SQL</small>
                            @endif
                        </div>
                        @php
                            // Calcular totales para la información general
                            $totalDescuentoGeneral = 0;
                            $totalSubtotalGeneral = 0;
                            $totalIvaGeneral = 0;
                            $totalGeneralFinal = 0;
                            
                            foreach($productosCotizacion as $producto) {
                                $descuentoPorcentaje = $producto['descuento'] ?? 0;
                                $descuentoValor = $producto['descuento_valor'] ?? (($producto['cantidad'] * $producto['precio']) * ($descuentoPorcentaje / 100));
                                $subtotalConDescuento = $producto['subtotal_con_descuento'] ?? (($producto['cantidad'] * $producto['precio']) - $descuentoValor);
                                $ivaValor = $producto['iva_valor'] ?? ($subtotalConDescuento * 0.19);
                                $totalProducto = $producto['total_producto'] ?? ($subtotalConDescuento + $ivaValor);
                                
                                $totalDescuentoGeneral += $descuentoValor;
                                $totalSubtotalGeneral += $subtotalConDescuento;
                                $totalIvaGeneral += $ivaValor;
                                $totalGeneralFinal += $totalProducto;
                            }
                        @endphp
                        <div class="col-md-2">
                            <strong>Subtotal:</strong><br>
                            ${{ number_format($totalSubtotalGeneral, 0, ',', '.') }}
                        </div>
                        <div class="col-md-2">
                            <strong>Descuento:</strong><br>
                            <span class="text-danger">${{ number_format($totalDescuentoGeneral, 0, ',', '.') }}</span>
                        </div>
                        <div class="col-md-2">
                            <strong>IVA (19%):</strong><br>
                            <span class="text-info">${{ number_format($totalIvaGeneral, 0, ',', '.') }}</span>
                        </div>
                        <div class="col-md-2">
                            <strong>Total (c/IVA):</strong><br>
                            <span class="h5 text-success">${{ number_format($totalGeneralFinal, 0, ',', '.') }}</span>
                        </div>
                    </div>
                    @if($cotizacion->observaciones)
                    <div class="row mt-3">
                        <div class="col-md-12">
                            <strong>Observaciones:</strong><br>
                            <p class="text-muted">{{ $cotizacion->observaciones }}</p>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Productos -->
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                        <h4 class="card-title">
                            <i class="tim-icons icon-basket-simple"></i>
                            Productos ({{ count($productosCotizacion) }})
                        </h4>
                </div>
                <div class="card-body">
                    @if(count($productosCotizacion) > 0)
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Código</th>
                                    <th>Descripción</th>
                                    <th>Cantidad</th>
                                    <th>Precio Unit.</th>
                                    <th>Descuento (%)</th>
                                    <th>Descuento ($)</th>
                                    <th>Subtotal</th>
                                    <th>IVA (19%)</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($productosCotizacion as $producto)
                                @php
                                    // Usar valores de BD si están disponibles, si no calcular
                                    $descuentoPorcentaje = $producto['descuento'] ?? 0;
                                    $descuentoValor = $producto['descuento_valor'] ?? (($producto['cantidad'] * $producto['precio']) * ($descuentoPorcentaje / 100));
                                    $subtotalConDescuento = $producto['subtotal_con_descuento'] ?? (($producto['cantidad'] * $producto['precio']) - $descuentoValor);
                                    $ivaValor = $producto['iva_valor'] ?? ($subtotalConDescuento * 0.19);
                                    $totalProducto = $producto['total_producto'] ?? ($subtotalConDescuento + $ivaValor);
                                @endphp
                                <tr>
                                    <td>{{ $producto['codigo'] }}</td>
                                    <td>{{ $producto['nombre'] }}</td>
                                    <td>{{ number_format($producto['cantidad'], 0, ',', '.') }}</td>
                                    <td>${{ number_format($producto['precio'], 0, ',', '.') }}</td>
                                    <td>{{ number_format($descuentoPorcentaje, 2) }}%</td>
                                    <td class="text-danger">${{ number_format($descuentoValor, 0, ',', '.') }}</td>
                                    <td>${{ number_format($subtotalConDescuento, 0, ',', '.') }}</td>
                                    <td class="text-info">${{ number_format($ivaValor, 0, ',', '.') }}</td>
                                    <td class="text-success font-weight-bold">${{ number_format($totalProducto, 0, ',', '.') }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                @php
                                    // Calcular totales sumando los valores de los productos
                                    $totalDescuento = 0;
                                    $totalSubtotal = 0;
                                    $totalIva = 0;
                                    $totalGeneral = 0;
                                    
                                    foreach($productosCotizacion as $producto) {
                                        $descuentoPorcentaje = $producto['descuento'] ?? 0;
                                        $descuentoValor = $producto['descuento_valor'] ?? (($producto['cantidad'] * $producto['precio']) * ($descuentoPorcentaje / 100));
                                        $subtotalConDescuento = $producto['subtotal_con_descuento'] ?? (($producto['cantidad'] * $producto['precio']) - $descuentoValor);
                                        $ivaValor = $producto['iva_valor'] ?? ($subtotalConDescuento * 0.19);
                                        $totalProducto = $producto['total_producto'] ?? ($subtotalConDescuento + $ivaValor);
                                        
                                        $totalDescuento += $descuentoValor;
                                        $totalSubtotal += $subtotalConDescuento;
                                        $totalIva += $ivaValor;
                                        $totalGeneral += $totalProducto;
                                    }
                                @endphp
                                <tr class="table-info">
                                    <td colspan="5" class="text-right"><strong>TOTALES:</strong></td>
                                    <td class="text-danger"><strong>${{ number_format($totalDescuento, 0, ',', '.') }}</strong></td>
                                    <td><strong>${{ number_format($totalSubtotal, 0, ',', '.') }}</strong></td>
                                    <td class="text-info"><strong>${{ number_format($totalIva, 0, ',', '.') }}</strong></td>
                                    <td class="text-success"><strong>${{ number_format($totalGeneral, 0, ',', '.') }}</strong></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    @else
                    <div class="text-center py-4">
                        <i class="tim-icons icon-basket-simple text-muted" style="font-size: 3rem;"></i>
                        <h4 class="text-muted mt-3">No hay productos</h4>
                        <p class="text-muted">Esta cotización no tiene productos asociados.</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
