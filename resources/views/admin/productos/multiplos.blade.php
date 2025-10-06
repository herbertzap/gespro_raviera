@extends('layouts.app', ['pageSlug' => $pageSlug ?? 'multiplos-productos'])

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">
                    <i class="material-icons">inventory_2</i>
                    Gestión de Múltiplos de Venta
                </h4>
                <p class="card-category">Carga y administración de cantidades mínimas de venta por producto</p>
            </div>
            <div class="card-body">
                <!-- Alerta de éxito/error -->
                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="material-icons">check_circle</i>
                        {{ session('success') }}
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                @endif

                @if(session('error'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="material-icons">error</i>
                        {{ session('error') }}
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                @endif

                <!-- Sección de Carga de Excel -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h5 class="card-title">
                                    <i class="material-icons">upload_file</i>
                                    Cargar Múltiplos desde Excel
                                </h5>
                                <p class="text-muted">
                                    El archivo Excel debe tener las siguientes columnas: <strong>SKU</strong>, <strong>PRODUCTO</strong>, <strong>MINIMO DE VENTA</strong>
                                </p>
                                
                                <form action="{{ route('admin.productos.multiplos.cargar') }}" method="POST" enctype="multipart/form-data" id="formCargarExcel">
                                    @csrf
                                    <div class="form-row align-items-center">
                                        <div class="col-md-8">
                                            <div class="custom-file">
                                                <input type="file" class="custom-file-input" id="archivo_excel" name="archivo_excel" accept=".xlsx,.xls" required>
                                                <label class="custom-file-label" for="archivo_excel">Seleccionar archivo Excel...</label>
                                            </div>
                                            @error('archivo_excel')
                                                <small class="text-danger">{{ $message }}</small>
                                            @enderror
                                        </div>
                                        <div class="col-md-4">
                                            <button type="submit" class="btn btn-primary btn-block" id="btnCargar">
                                                <i class="material-icons">cloud_upload</i>
                                                Cargar y Procesar
                                            </button>
                                        </div>
                                    </div>
                                </form>

                                <!-- Información adicional -->
                                <div class="mt-3">
                                    <small class="text-muted">
                                        <i class="material-icons" style="font-size: 16px;">info</i>
                                        El proceso actualizará automáticamente el múltiplo de venta de cada producto según su SKU.
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabla de Productos con Múltiplos -->
                <div class="row">
                    <div class="col-md-12">
                        <h5 class="mb-3">
                            <i class="material-icons">list</i>
                            Productos con Múltiplos Configurados ({{ $productosConMultiplo->count() }})
                        </h5>
                        
                        @if($productosConMultiplo->count() > 0)
                            <div class="table-responsive">
                                <table class="table table-striped table-hover" id="tablaMultiplos">
                                    <thead>
                                        <tr>
                                            <th style="width: 15%">SKU</th>
                                            <th style="width: 45%">Producto</th>
                                            <th style="width: 15%" class="text-center">Múltiplo Actual</th>
                                            <th style="width: 25%" class="text-center">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($productosConMultiplo as $producto)
                                            <tr>
                                                <td><code>{{ $producto->KOPR }}</code></td>
                                                <td>{{ $producto->NOKOPR }}</td>
                                                <td class="text-center">
                                                    <span class="badge badge-primary badge-pill" style="font-size: 14px;">
                                                        {{ $producto->multiplo_venta }}
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    <button class="btn btn-sm btn-info" onclick="editarMultiplo({{ $producto->id }}, '{{ $producto->KOPR }}', {{ $producto->multiplo_venta }})" title="Editar">
                                                        <i class="material-icons">edit</i>
                                                    </button>
                                                    <button class="btn btn-sm btn-warning" onclick="restablecerMultiplo({{ $producto->id }}, '{{ $producto->KOPR }}')" title="Restablecer a 1">
                                                        <i class="material-icons">refresh</i>
                                                    </button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="alert alert-info">
                                <i class="material-icons">info</i>
                                No hay productos con múltiplos configurados. Carga un archivo Excel para comenzar.
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Editar Múltiplo -->
<div class="modal fade" id="modalEditarMultiplo" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="material-icons">edit</i>
                    Editar Múltiplo de Venta
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="text-muted">
                    SKU: <strong id="modalSku"></strong>
                </p>
                <div class="form-group">
                    <label for="nuevoMultiplo">Múltiplo de Venta</label>
                    <input type="number" class="form-control" id="nuevoMultiplo" min="1" max="1000" required>
                    <small class="form-text text-muted">
                        Cantidad mínima en la que se puede vender este producto (ej: 1, 3, 6, 12)
                    </small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="guardarMultiplo()">
                    <i class="material-icons">save</i>
                    Guardar
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('js')
<script>
$(document).ready(function() {
    // Actualizar nombre del archivo seleccionado
    $('.custom-file-input').on('change', function() {
        let fileName = $(this).val().split('\\').pop();
        $(this).next('.custom-file-label').html(fileName);
    });

    // Inicializar DataTable
    @if($productosConMultiplo->count() > 0)
    $('#tablaMultiplos').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
        },
        order: [[0, 'asc']],
        pageLength: 25
    });
    @endif

    // Deshabilitar botón al enviar formulario
    $('#formCargarExcel').on('submit', function() {
        $('#btnCargar').prop('disabled', true).html('<i class="material-icons">hourglass_empty</i> Procesando...');
    });
});

let productoIdActual = null;

function editarMultiplo(id, sku, multiploActual) {
    productoIdActual = id;
    $('#modalSku').text(sku);
    $('#nuevoMultiplo').val(multiploActual);
    $('#modalEditarMultiplo').modal('show');
}

function guardarMultiplo() {
    const nuevoMultiplo = $('#nuevoMultiplo').val();
    
    if (!nuevoMultiplo || nuevoMultiplo < 1) {
        alert('Por favor ingresa un múltiplo válido (mayor o igual a 1)');
        return;
    }

    $.ajax({
        url: `/admin/productos/multiplos/${productoIdActual}`,
        method: 'PUT',
        data: {
            _token: '{{ csrf_token() }}',
            multiplo_venta: nuevoMultiplo
        },
        success: function(response) {
            $('#modalEditarMultiplo').modal('hide');
            location.reload();
        },
        error: function(xhr) {
            alert('Error al actualizar: ' + (xhr.responseJSON?.message || 'Error desconocido'));
        }
    });
}

function restablecerMultiplo(id, sku) {
    if (!confirm(`¿Restablecer el múltiplo del producto ${sku} a 1?`)) {
        return;
    }

    $.ajax({
        url: `/admin/productos/multiplos/${id}/restablecer`,
        method: 'POST',
        data: {
            _token: '{{ csrf_token() }}'
        },
        success: function(response) {
            location.reload();
        },
        error: function(xhr) {
            alert('Error al restablecer: ' + (xhr.responseJSON?.message || 'Error desconocido'));
        }
    });
}
</script>
@endpush

