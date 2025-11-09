@extends('layouts.app')

@section('title', 'Dashboard Picking')

@section('content')
@php
    $pageSlug = 'dashboard';
@endphp
<div class="content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Dashboard - Picking</h4>
                        <p class="card-category">Bienvenido {{ auth()->user()->name }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tarjeta Principal -->
        <div class="row">
            <!-- Notas Pendientes por Validar -->
            <div class="col-lg-12 col-md-12 col-sm-12">
                <div class="card card-stats">
                    <div class="card-header card-header-warning card-header-icon">
                        <div class="card-icon">
                            <i class="material-icons">pending_actions</i>
                        </div>
                        <p class="card-category">Por Validar</p>
                        <h3 class="card-title">{{ number_format($resumenCobranza['TOTAL_NOTAS_PENDIENTES_VALIDAR'] ?? 0) }}</h3>
                    </div>
                    <div class="card-footer">
                        <div class="stats">
                            <i class="material-icons text-warning">pending_actions</i>
                            <a href="{{ route('aprobaciones.index') }}" class="text-warning">Ver aprobaciones</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabla de Información -->
        <div class="row">
            <!-- Notas de Venta Pendientes (col-12) -->
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header card-header-warning">
                        <h4 class="card-title">Notas de Venta por Validar</h4>
                        <p class="card-category">Esperando validación de stock por Picking</p>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead class="text-warning">
                                    <tr>
                                        <th>Número</th>
                                        <th>Vendedor</th>
                                        <th>Cliente</th>
                                        <th>Total</th>
                                        <th>Estado</th>
                                        <th>Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($notasPendientes ?? [] as $nota)
                                    <tr>
                                        <td>{{ $nota->numero_nota_venta ?? 'N/A' }}</td>
                                        <td>{{ $nota->user->name ?? 'N/A' }}</td>
                                        <td>{{ $nota->cliente->nombre ?? $nota->nombre_cliente ?? 'N/A' }}</td>
                                        <td>${{ number_format($nota->total ?? 0, 2) }}</td>
                                        <td>
                                            <span class="badge badge-{{ 
                                                $nota->estado_aprobacion == 'pendiente_supervisor' ? 'warning' : 
                                                ($nota->estado_aprobacion == 'pendiente_compras' ? 'info' : 
                                                ($nota->estado_aprobacion == 'pendiente_picking' ? 'primary' : 'secondary'))
                                            }}">
                                                {{ ucfirst(str_replace('_', ' ', $nota->estado_aprobacion ?? 'pendiente')) }}
                                            </span>
                                        </td>
                                        <td>
                                            <a href="{{ route('aprobaciones.show', $nota->id) }}" class="btn btn-sm btn-primary">
                                                <i class="material-icons">visibility</i> Ver
                                            </a>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="6" class="text-center">No hay notas de venta pendientes</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- (Sección de Facturas Pendientes removida para Picking) -->

    </div>
</div>
@endsection
