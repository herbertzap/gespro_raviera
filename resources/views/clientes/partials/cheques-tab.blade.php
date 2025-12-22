<!-- Cheques Asociados al Cliente -->
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">Cheques Asociados al Cliente</h4>
            </div>
            <div class="card-body">
                <ul class="nav nav-tabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="chequesCartera-tab-ajax" data-toggle="tab" href="#chequesCarteraAjax" role="tab">
                            <i class="material-icons">account_balance</i> Cheques en Cartera
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="chequesProtestados-tab-ajax" data-toggle="tab" href="#chequesProtestadosAjax" role="tab">
                            <i class="material-icons">warning</i> Cheques Protestados
                        </a>
                    </li>
                </ul>
                
                <!-- Tab Content -->
                <div class="tab-content">
                    <!-- Tab: Cheques en Cartera -->
                    <div class="tab-pane fade show active" id="chequesCarteraAjax" role="tabpanel">
                        <div class="table-responsive mt-3">
                            <table class="table">
                                <thead class="text-primary">
                                    <tr>
                                        <th>Número</th>
                                        <th>Valor</th>
                                        <th>Fecha Vencimiento</th>
                                        <th>Vendedor</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($chequesEnCarteraDetalle ?? [] as $cheque)
                                    <tr>
                                        <td><strong>{{ $cheque['numero'] ?? '' }}</strong></td>
                                        <td>${{ number_format($cheque['valor'] ?? 0, 0) }}</td>
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
                                    @empty
                                    <tr>
                                        <td colspan="4" class="text-center">No hay cheques en cartera</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Tab: Cheques Protestados -->
                    <div class="tab-pane fade" id="chequesProtestadosAjax" role="tabpanel">
                        <div class="table-responsive mt-3">
                            <table class="table">
                                <thead class="text-danger">
                                    <tr>
                                        <th>Número</th>
                                        <th>Valor</th>
                                        <th>Fecha Vencimiento</th>
                                        <th>Vendedor</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($chequesProtestadosDetalle ?? [] as $cheque)
                                    <tr>
                                        <td><strong>{{ $cheque['numero'] ?? '' }}</strong></td>
                                        <td>${{ number_format($cheque['valor'] ?? 0, 0) }}</td>
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
                                    @empty
                                    <tr>
                                        <td colspan="4" class="text-center">No hay cheques protestados</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
