@extends('layouts.app', ['page' => __('Log'), 'pageSlug' => 'log'])

@section('content')
<div class="row">
    <div class="col-md-12">
        <form method="GET" action="{{ route('logs.index') }}">
            <div class="row">
                <!-- Filtro por Usuario -->
                <div class="col-md-3">
                    <label for="user_id">Usuario</label>
                    <input type="text" name="user_id" id="user_id" class="form-control" value="{{ request('user_id') }}" placeholder="Buscar por usuario">
                </div>

                <!-- Filtro por Tipo de Acción -->
                <div class="col-md-3">
                    <label for="action_type">Tipo de Acción</label>
                    <select name="action_type" id="action_type" class="form-control">
                        <option value="">Seleccione Tipo de Acción</option>
                        <option value="insert" {{ request('action_type') == 'insert' ? 'selected' : '' }}>Insertar</option>
                        <option value="update" {{ request('action_type') == 'update' ? 'selected' : '' }}>Modificar</option>
                        <option value="delete" {{ request('action_type') == 'delete' ? 'selected' : '' }}>Eliminar</option>
                        <option value="error" {{ request('action_type') == 'error' ? 'selected' : '' }}>Error</option>
                    </select>
                </div>

                <!-- Filtro por Tabla -->
                <div class="col-md-3">
                    <label for="table_name">Tabla</label>
                    <input type="text" name="table_name" id="table_name" class="form-control" value="{{ request('table_name') }}" placeholder="Buscar por tabla">
                </div>

                <!-- Filtro por Fecha -->
                <div class="col-md-3">
                    <label for="created_at">Fecha</label>
                    <input type="date" name="created_at" id="created_at" class="form-control" value="{{ request('created_at') }}">
                </div>
            </div>

            <div class="row mt-3">
                <div class="col-md-12 d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary mr-2">Buscar</button>
                    <a href="{{ route('logs.index') }}" class="btn btn-secondary">Limpiar Filtros</a>
                </div>
            </div>
        </form>
    </div>

    <!-- Tabla de Logs -->
    <div class="col-md-12 mt-4">
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead class="thead-light">
                    <tr>
                        <th>Usuario</th>
                        <th>Tipo de Acción</th>
                        <th>Tabla</th>
                        <th>Datos</th>
                        <th>Errores</th>
                        <th>Fecha</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td>{{ $log->user_id }}</td>
                            <td>{{ $log->action_type }}</td>
                            <td>{{ $log->table_name }}</td>
                            <td><pre>{{ json_encode(json_decode($log->data), JSON_PRETTY_PRINT) }}</pre></td>
                            <td>
                                @if($log->errors)
                                    <pre>{{ json_encode(json_decode($log->errors), JSON_PRETTY_PRINT) }}</pre>
                                @else
                                    N/A
                                @endif
                            </td>
                            <td>{{ $log->created_at }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center">No se encontraron registros</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <!-- Paginación -->
        <div class="mt-3">
            {{ $logs->withQueryString()->links() }}
        </div>
    </div>
</div>
@endsection
