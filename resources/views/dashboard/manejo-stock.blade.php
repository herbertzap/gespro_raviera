@extends('layouts.app', ['pageSlug' => 'dashboard'])

@section('content')
<div class="content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Dashboard - Manejo Stock</h4>
                        <p class="card-category">Bienvenido {{ auth()->user()->name }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tarjetas de Resumen -->
        <div class="row">
            <!-- Card: Productos Ingresados -->
            <div class="col-lg-6 col-md-6 col-sm-12">
                <div class="card card-stats">
                    <div class="card-header card-header-success card-header-icon">
                        <div class="card-icon">
                            <i class="material-icons">inventory</i>
                        </div>
                        <p class="card-category">Productos Ingresados</p>
                        <h3 class="card-title">{{ number_format($productosIngresados ?? 0) }}</h3>
                    </div>
                    <div class="card-footer">
                        <div class="stats">
                            <i class="material-icons text-success">info</i>
                            Total de capturas de stock realizadas
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card: Productos Modificados -->
            <div class="col-lg-6 col-md-6 col-sm-12">
                <div class="card card-stats">
                    <div class="card-header card-header-info card-header-icon">
                        <div class="card-icon">
                            <i class="material-icons">edit</i>
                        </div>
                        <p class="card-category">Productos Modificados</p>
                        <h3 class="card-title">{{ number_format($productosModificados ?? 0) }}</h3>
                    </div>
                    <div class="card-footer">
                        <div class="stats">
                            <i class="material-icons text-info">info</i>
                            Total de códigos de barras asociados
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Acciones Rápidas -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Acciones Rápidas</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <a href="{{ route('manejo-stock.select') }}" class="btn btn-primary btn-block btn-lg">
                                    <i class="material-icons">inventory_2</i>
                                    <br>
                                    <span style="font-size: 1.2rem;">Contabilidad de Stock</span>
                                </a>
                            </div>
                            <div class="col-md-6">
                                <a href="{{ route('manejo-stock.historial') }}" class="btn btn-info btn-block btn-lg">
                                    <i class="material-icons">history</i>
                                    <br>
                                    <span style="font-size: 1.2rem;">Historial</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

