@extends('layouts.app')

@section('title', 'Error del Sistema')

@section('content')
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header card-header-danger">
                    <h4 class="card-title">
                        <i class="material-icons">error</i>
                        Error del Sistema
                    </h4>
                    <p class="card-category">Se ha producido un error inesperado</p>
                </div>
                <div class="card-body">
                    <div class="alert alert-danger">
                        <h5><strong>Código de Error:</strong> {{ $errorData['code'] }}</h5>
                        <h5><strong>Mensaje:</strong> {{ $errorData['message'] }}</h5>
                        <p><strong>Timestamp:</strong> {{ $errorData['timestamp'] }}</p>
                        @if($errorData['user_id'])
                        <p><strong>Usuario:</strong> {{ $errorData['user_email'] }} (ID: {{ $errorData['user_id'] }})</p>
                        @endif
                        <p><strong>URL:</strong> {{ $errorData['url'] }}</p>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="card-title">¿Qué hacer ahora?</h6>
                                </div>
                                <div class="card-body">
                                    <ul>
                                        <li>Intenta recargar la página</li>
                                        <li>Verifica que tengas los permisos necesarios</li>
                                        <li>Si el problema persiste, contacta al administrador</li>
                                        <li>El error ha sido registrado automáticamente</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="card-title">Acciones Rápidas</h6>
                                </div>
                                <div class="card-body">
                                    <a href="{{ route('dashboard') }}" class="btn btn-primary btn-sm">
                                        <i class="material-icons">home</i> Ir al Dashboard
                                    </a>
                                    <button onclick="history.back()" class="btn btn-secondary btn-sm">
                                        <i class="material-icons">arrow_back</i> Volver Atrás
                                    </button>
                                    <button onclick="location.reload()" class="btn btn-info btn-sm">
                                        <i class="material-icons">refresh</i> Recargar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    @if(config('app.debug'))
                    <div class="card mt-3">
                        <div class="card-header">
                            <h6 class="card-title">Información de Debug (Solo en desarrollo)</h6>
                        </div>
                        <div class="card-body">
                            <pre class="bg-light p-3">{{ json_encode($errorData, JSON_PRETTY_PRINT) }}</pre>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

