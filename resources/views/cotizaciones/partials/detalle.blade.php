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

    <!-- Información de la Cotización -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">
                        <i class="tim-icons icon-notes"></i>
                        Información de la Cotización
                    </h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <strong>Fecha:</strong><br>
                            {{ $cotizacion->fecha ? \Carbon\Carbon::parse($cotizacion->fecha)->format('d/m/Y H:i') : 'N/A' }}
                        </div>
                        <div class="col-md-3">
                            <strong>Estado:</strong><br>
                            <span class="badge badge-{{ $cotizacion->estado == 'enviada' ? 'success' : ($cotizacion->estado == 'borrador' ? 'warning' : 'info') }}">
                                {{ ucfirst($cotizacion->estado) }}
                            </span>
                        </div>
                        <div class="col-md-3">
                            <strong>Subtotal:</strong><br>
                            ${{ number_format($cotizacion->subtotal ?? 0, 0, ',', '.') }}
                        </div>
                        <div class="col-md-3">
                            <strong>Total:</strong><br>
                            <span class="h5 text-success">${{ number_format($cotizacion->total ?? 0, 0, ',', '.') }}</span>
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
                                    <th>Descuento</th>
                                    <th>Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($productosCotizacion as $producto)
                                <tr>
                                    <td>{{ $producto['codigo'] }}</td>
                                    <td>{{ $producto['nombre'] }}</td>
                                    <td>{{ number_format($producto['cantidad'], 0, ',', '.') }}</td>
                                    <td>${{ number_format($producto['precio'], 0, ',', '.') }}</td>
                                    <td>0%</td>
                                    <td>${{ number_format($producto['subtotal'], 0, ',', '.') }}</td>
                                </tr>
                                @endforeach
                            </tbody>
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
