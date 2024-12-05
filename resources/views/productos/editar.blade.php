@extends('layouts.app', ['page' => __('Editar Producto'), 'pageSlug' => 'productos_editar'])

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">Editar Producto</h4>
            </div>
            <div class="card-body">
            <!-- Formulario Principal para Editar Producto -->
            <form action="{{ route('productos.actualizar', $producto->sku) }}" method="POST">
                @csrf
                @method('PUT')
                    <!-- Información General -->
                    <div class="mb-4">
                        <h5>Información General</h5>
                        <table class="table table-bordered">
                            <tr>
                                <th>SKU</th>
                                <th>Nombre del Producto</th>
                                <th>Marca</th>
                                <th>RLUD</th>
                                <th>unidad2</th>
                                <th>unidad2</th>
                                <th>Divisible UD1</th>
                                <th>Divisible UD2</th>
                                <th>Régimen</th>
                            </tr>
                            <tr>
                                <td>{{ $producto->sku }}</td>
                                <td>{{ $producto->nombre }}</td>
                                <td>{{ $producto->marca }}</td>
                                <td>{{ number_format($producto->RLUD, 7, '.', '') }} </td>
                                <td>{{ $producto->unidad1 }}</td>
                                <td>{{ $producto->unidad2 }}</td>
                                <td>{{ $producto->divisible_ud1 === 'S' ? 'Sí' : 'No' }}</td>
                                <td>{{ $producto->divisible_ud2 === 'S' ? 'Sí' : 'No' }}</td>
                                <td>{{ $producto->regimen === 'N' ? 'Nacional' : ($producto->regimen === 'I' ? 'Internacional' : 'Otro') }}</td>
                            

                            </tr>
                        </table>
                    </div>

                    <!-- Inventario por Bodega -->
                    <div class="mb-4">
        <h5>Inventario por Bodega</h5>
        <!--<button type="button" class="btn btn-success mb-2" data-toggle="modal" data-target="#modalAgregarBodega">Agregar Bodega</button>-->
        <table class="table table-bordered">
            <tr>
                <th>Bodega</th>
                <th>Stock UD1</th>
                <th>Stock UD2</th>
                <!--<th>Acciones</th>-->
            </tr>
            @forelse ($bodegas as $bodega)
            <tr>
                <td>{{ $bodega->nombre_bodega }}</td>
                <td>{{ $bodega->stock_ud1 }}</td>
                <td>{{ $bodega->stock_ud2 }}</td>
                <!--
                <td>
                <button type="button" class="btn btn-danger btn-sm" data-toggle="modal" data-target="#modalEliminarBodega" data-bodega-id="{{ $bodega->bodega_id }}">
                        Eliminar
                    </button>
                    </form>
                </td>
                -->
            </tr>
            @empty
            <tr>
                <td colspan="4" class="text-center text-muted">Producto aún no se encuentra asociado a una bodega.</td>
            </tr>
            @endforelse
        </table>
    </div>

<!-- Costos -->
    <div class="mb-4">
                        <h5>Costos</h5>
                        <table class="table table-bordered">
                            <tr>
                                <th>Costo</th>
                                <th>PMP</th>
                                <th>Última Compra</th>
                                <th>Fecha Última Compra</th>
                            </tr>
                            @forelse ($listas_costo as $lista)
                            <tr>
                                <td><input type="number" class="form-control" name="costo_{{ $lista->lista }}" id="costo_{{ $lista->lista }}" value="{{ $lista->precio_ud1 }}"></td>
                                <td><input type="text" class="form-control" name="pmp" value="{{ $lista->PM }}"></td>
                                <td><input type="text" class="form-control" name="ultima_compra" value="{{ $lista->PPUL01 }}"></td>
                                <td><input type="date" class="form-control" name="fecha_ultima_compra" value="{{ $lista->FEUL }}"></td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted">Producto aún no tiene listas de precios asociadas.</td>
                            </tr>
                            @endforelse
                        </table>
                    </div>



                    <!-- Listas de Precios -->
                    <div class="mb-4">
    <h5>Listas de Precios</h5>
    <button type="button" class="btn btn-success mb-2" data-toggle="modal" data-target="#modalAgregarLista">Agregar Lista de Precios</button>
    <table class="table table-bordered">
        <tr>
            <th>Lista</th>
            <th>Nombre lista</th>
            <th>Precio Venta UD1</th>
            <th>Margen UD1</th>
            <th>Descuento Máx UD1</th>
            <th>Precio Venta UD2</th>
            <th>Margen UD2</th>
            <th>Descuento Máx UD2</th>
            <th>Flete</th>
            <th>Acciones</th>
        </tr>
        @forelse ($listas as $index => $lista)
        <tr>
            <td>{{ $lista->lista }}</td>
            <td>{{ $lista->nombre_lista }}</td>
            <td>
                <input type="text" class="form-control" id="precio_ud1_{{ $lista->lista }}" value="{{ $lista->precio_ud1 }}" disabled>
            </td>
            <td>
                <input type="number" class="form-control" id="margen_ud1_{{ $lista->lista }}" value="{{ $lista->margen_ud1 }}" data-lista="{{ $lista->lista }}">
            </td>
            <td>
            <input type="number" class="form-control" id="descuento_ud1_{{ $lista->lista }}" value="{{ $lista->descuento_ud1 }}" data-lista="{{ $lista->lista }}">
            <td>
                <input type="text" class="form-control" id="precio_ud2_{{ $lista->lista }}" value="{{ $lista->precio_ud2 }}" disabled>
            </td>
            <td>
                <input type="number" class="form-control" id="margen_ud2_{{ $lista->lista }}" value="{{ $lista->margen_ud2 }}" data-lista="{{ $lista->lista }}">
            </td>
            <td>
                <input type="number" class="form-control" id="descuento_ud2_{{ $lista->lista }}" value="{{ $lista->descuento_ud2 }}" data-lista="{{ $lista->lista }}">    
            </td>
            <td id="flete_{{ $lista->lista }}">{{ $lista->flete ?? '--' }}</td>
            <td>
            <button type="button" class="btn btn-danger btn-sm" data-toggle="modal" data-target="#modalEliminarLista" data-lista-id="{{ $lista->lista }}">
                        Eliminar
                    </button>
            </td>
        </tr>
        @empty
        <tr>
            <td colspan="8" class="text-center text-muted">Producto aún no tiene listas de precios asociadas.</td>
        </tr>
        @endforelse
    </table>
</div>



                    <!-- Botón Guardar -->
                    <div class="text-right">
                        <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Confirmar Eliminación de Lista -->
<div class="modal fade" id="modalEliminarLista" tabindex="-1" aria-labelledby="modalEliminarListaLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('listas.eliminar') }}" method="POST">
                @csrf
                @method('DELETE')
                <div class="modal-header">
                    <h5 class="modal-title" id="modalEliminarListaLabel">Eliminar Lista de Precios</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>¿Está seguro de que desea eliminar esta lista de precios?</p>
                    <input type="hidden" name="lista_id" id="listaId">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Eliminar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para Confirmar Eliminación de Bodega -->
<div class="modal fade" id="modalEliminarBodega" tabindex="-1" aria-labelledby="modalEliminarBodegaLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('bodegas.eliminar') }}" method="POST">
                @csrf
                @method('DELETE')
                <div class="modal-header">
                    <h5 class="modal-title" id="modalEliminarBodegaLabel">Eliminar Bodega</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>¿Está seguro de que desea eliminar esta bodega?</p>
                    <input type="hidden" name="bodega_id" id="bodegaId">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Eliminar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Agregar Bodega -->
<div class="modal fade" id="modalAgregarBodega" tabindex="-1" aria-labelledby="modalAgregarBodegaLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('bodegas.agregar') }}" method="POST">
                @csrf
                <input type="hidden" name="sku" value="{{ $producto->sku }}">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalAgregarBodegaLabel">Agregar Bodega</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="bodega">Seleccionar Bodega</label>
                        <select class="form-control" id="bodega" name="bodega_id" required>
                            <option value="">Seleccione una bodega</option>
                            @foreach ($bodegasDisponibles as $bodega)
                                <option value="{{ $bodega->bodega_id }}">{{ $bodega->nombre_bodega }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="stockUd1">Stock UD1</label>
                        <input type="number" class="form-control" id="stockUd1" name="stock_ud1" value="0" required>
                    </div>
                    <div class="form-group">
                        <label for="stockUd2">Stock UD2</label>
                        <input type="number" class="form-control" id="stockUd2" name="stock_ud2" value="0" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Agregar</button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- Modal Agregar Lista de Precios -->
<div class="modal fade" id="modalAgregarLista" tabindex="-1" aria-labelledby="modalAgregarListaLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('listas.agregar') }}" method="POST">
                @csrf
                <input type="hidden" name="sku" value="{{ $producto->sku }}"> 
                <input type="hidden" name="koprra" value="{{ $producto->KOPRRA }}"> <!-- KOPRRA del producto -->
                <!-- SKU del producto -->
                <div class="modal-header">
                    <h5 class="modal-title" id="modalAgregarListaLabel">Agregar Lista de Precios</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="nombreLista">Seleccionar Lista</label>
                        <select class="form-control" id="nombreLista" name="lista" required>
                            <option value="">Seleccione una lista</option>
                            @foreach ($listasDisponibles as $lista)
                                <option value="{{ $lista->KOLT }}">{{ $lista->NOKOLT }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="precioUd1">Precio Venta UD1</label>
                        <input type="text" class="form-control" id="precioUd1" name="pp01ud" value="0" disabled>
                    </div>
                    <div class="form-group">
                        <label for="precioUd2">Precio Venta UD2</label>
                        <input type="text" class="form-control" id="precioUd2" name="pp02ud" value="0" disabled>
                    </div>
                    <div class="form-group">
                        <label for="margenUd1">Margen UD1</label>
                        <input type="number" class="form-control" id="margenUd1" name="mg01ud" required>
                    </div>
                    <div class="form-group">
                        <label for="margenUd2">Margen UD2</label>
                        <input type="number" class="form-control" id="margenUd2" name="mg02ud" required>
                    </div>
                    <div class="form-group">
                        <label for="descuentoUd1">Descuento Máx UD1</label>
                        <input type="number" class="form-control" id="descuentoUd1" name="dtma01ud" required>
                    </div>
                    <div class="form-group">
                        <label for="descuentoUd2">Descuento Máx UD2</label>
                        <input type="number" class="form-control" id="descuentoUd2" name="dtma02ud" required>
                    </div>
                    <div class="form-group">
                        <label for="ecuacion">Ecuación</label>
                        <input type="text" class="form-control" id="ecuacion" name="ecuacion" value="(<01c>pp01ud*(1+mg01ud/100))#3" disabled>
                    </div>
                    <div class="form-group">
                        <label for="ecuacionU2">Ecuación UD2</label>
                        <textarea class="form-control" id="ecuacionU2" name="ecuacionu2" rows="3" disabled>((<01c>pp01ud*(1+mg02ud/100))*rlud)#3</textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Agregar</button>
                </div>
            </form>
        </div>
    </div>
</div>




<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var modalEliminarLista = document.getElementById('modalEliminarLista');
    modalEliminarLista.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget; // Botón que abrió el modal
        var listaId = button.getAttribute('data-lista-id'); // Extraer data-lista-id
        var inputListaId = modalEliminarLista.querySelector('#listaId');
        inputListaId.value = listaId; // Asignar el valor al input oculto
    });
});

document.addEventListener('DOMContentLoaded', function () {
    var modalEliminarBodega = document.getElementById('modalEliminarBodega');
    modalEliminarBodega.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget; // Botón que abrió el modal
        var bodegaId = button.getAttribute('data-bodega-id'); // Extraer data-bodega-id
        var inputBodegaId = modalEliminarBodega.querySelector('#bodegaId');
        inputBodegaId.value = bodegaId; // Asignar el valor al input oculto
    });
});
$(document).ready(function () {
    const rlud = parseFloat('{{ $producto->RLUD }}') || 1; // RLUD del producto

    // Recalcular los valores de ecuación y precios dinámicamente
    function recalcularValores() {
        const lista = $('#nombreLista').val();
        const margenUd1 = parseFloat($('#margenUd1').val()) || 0;
        const margenUd2 = parseFloat($('#margenUd2').val()) || 0;
        const costoBase = parseFloat($('#costo_01C').val()) || 0;

        // Calcular Precio UD1 y UD2
        const precioUd1 = costoBase * (1 + margenUd1 / 100);
        const precioUd2 = (costoBase * (1 + margenUd2 / 100)) * rlud;

        // Actualizar campos de precio
        $('#precioUd1').val(precioUd1.toFixed(2));
        $('#precioUd2').val(precioUd2.toFixed(2));

        // Calcular ecuaciones dinámicamente
        if (['13P', '14P', '15P'].includes(lista)) {
            $('#ecuacion').val(`(<01c>pp01ud*(1+mg01ud/100)+(zflete3*(1/rlud)))#3`);
            $('#ecuacionU2').val(`(((<01c>pp01ud*(1+mg02ud/100))*rlud)+zflete3)#3`);
        } else {
            $('#ecuacion').val(`(<01c>pp01ud*(1+mg01ud/100))#3`);
            $('#ecuacionU2').val(`((<01c>pp01ud*(1+mg02ud/100))*rlud)#3`);
        }
    }

    // Escuchar cambios en los campos y recalcular valores
    $('#nombreLista, #margenUd1, #margenUd2').on('input change', function () {
        recalcularValores();
    });

    // Recalcular valores al cargar la página si ya hay valores
    recalcularValores();
});


$(document).ready(function () {
    const rlud = parseFloat('{{ $producto->RLUD }}') || 1; // Obtener RLUD del producto

    // Función para calcular precio UD1 (existente)
    function calcularPrecioUD1(listaId) {
        const costo = parseFloat($('#costo_01C').val()) || 0;
        const margen = parseFloat($(`#margen_ud1_${listaId}`).val()) || 0;
        const flete = parseFloat($(`#flete_${listaId}`).text()) || 0;

        let precioUD1;
        if (['13P', '14P', '15P'].includes(listaId)) {
            precioUD1 = costo * (1 + margen / 100) + (flete * (1 / rlud));
        } else {
            precioUD1 = costo * (1 + margen / 100);
        }

        $(`#precio_ud1_${listaId}`).val(precioUD1.toFixed(2));
    }

    // Nueva función para calcular precio UD2
    function calcularPrecioUD2(listaId) {
        const costo = parseFloat($('#costo_01C').val()) || 0;
        const margen = parseFloat($(`#margen_ud2_${listaId}`).val()) || 0;
        const flete = parseFloat($(`#flete_${listaId}`).text()) || 0;

        // Calcular precio UD2
        let precioUD2;
        if (['13P', '14P', '15P'].includes(listaId)) {
            precioUD2 = costo * (1 + margen / 100) + (flete * (1 / rlud));
        } else {
            precioUD2 = costo * (1 + margen / 100) * rlud;;
        }

        // Actualizar el campo de precio UD2
        $(`#precio_ud2_${listaId}`).val(precioUD2.toFixed(2));
    }

    // Escuchar cambios en el costo base y recalcular ambos precios
    $('#costo_01C').on('input', function () {
        $('input[id^="margen_ud1_"]').each(function () {
            const listaId = $(this).attr('id').split('_').pop();
            calcularPrecioUD1(listaId);
        });

        $('input[id^="margen_ud2_"]').each(function () {
            const listaId = $(this).attr('id').split('_').pop();
            calcularPrecioUD2(listaId);
        });
    });

    // Escuchar cambios en los márgenes UD1 y UD2
    $('input[id^="margen_ud1_"]').on('input', function () {
        const listaId = $(this).attr('id').split('_').pop();
        calcularPrecioUD1(listaId);
    });

    $('input[id^="margen_ud2_"]').on('input', function () {
        const listaId = $(this).attr('id').split('_').pop();
        calcularPrecioUD2(listaId);
    });
});





</script>
@endsection
