@extends('layouts.app', ['page' => __('Editar Producto'), 'pageSlug' => 'productos_editar'])

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">Editar Producto</h4>
            </div>
            <div class="card-body">
                <form action="{{ route('productos.actualizar', $producto->id) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <!-- TIPR -->
                    <div class="form-group">
                        <label for="TIPR">TIPR</label>
                        <input type="text" name="TIPR" class="form-control" value="FPN" readonly>
                    </div>

                    <!-- KOPR -->
                    <div class="form-group">
                        <label for="KOPR">Código Producto (KOPR)</label>
                        <input type="text" id="KOPR" name="KOPR" class="form-control" value="{{ $producto->KOPR }}" readonly>
                    </div>

                    <!-- NOKOPR -->
                    <div class="form-group">
                        <label for="NOKOPR">Descripción del Producto</label>
                        <input type="text" name="NOKOPR" class="form-control" value="{{ $producto->NOKOPR }}" readonly>
                    </div>

                    <!-- KOPRRA -->
                    <div class="form-group">
                        <label for="KOPRRA">Código Producto (KOPRRA)</label>
                        <input type="number" name="KOPRRA" class="form-control" value="{{ $nuevoKOPRRA }}" readonly>
                    </div>

                    <!-- NOKOPRRA -->
                    <div class="form-group">
                        <label for="NOKOPRRA">Descripción Repetida (NOKOPRRA)</label>
                        <input type="text" id="NOKOPRRA" name="NOKOPRRA" class="form-control" value="{{ $producto->NOKOPRRA }}" readonly>
                    </div>

                    <!-- KOPRTE -->
                    <div class="form-group">
                        <label for="KOPRTE">Código Producto Repetido (KOPRTE)</label>
                        <input type="text" id="KOPRTE" name="KOPRTE" class="form-control" value="{{ $producto->KOPRTE }}" readonly>
                    </div>

                    <!-- UD01PR -->
                    <div class="form-group">
                        <label for="UD01PR">Unidad de Medida (UD01PR)</label>
                        <select name="UD01PR" class="form-control">
                            @foreach($unidadMedida1 as $unidad)
                                <option value="{{ $unidad }}">{{ $unidad }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- UD02PR -->
                    <div class="form-group">
                        <label for="UD02PR">Unidad de Medida 2 (UD02PR)</label>
                        <select name="UD02PR" class="form-control">
                            @foreach($unidadMedida2 as $unidad)
                                <option value="{{ $unidad }}">{{ $unidad }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- RLUD -->
                    <div class="form-group">
                        <label for="RLUD">RLUD</label>
                        <input type="number" name="RLUD" class="form-control" min="1" max="9999" value="{{ $producto->RLUD }}">
                    </div>

                    <!-- POIVPR -->
                    <div class="form-group">
                        <label for="POIVPR">POIVPR</label>
                        <input type="number" name="POIVPR" class="form-control" min="1" max="9999" value="{{ $producto->POIVPR }}">
                    </div>

                    <!-- RGPR -->
                    <div class="form-group">
                        <label for="RGPR">RGPR</label>
                        <select name="RGPR" class="form-control">
                            <option value="N" {{ $producto->RGPR == 'N' ? 'selected' : '' }}>N</option>
                            <option value="I" {{ $producto->RGPR == 'I' ? 'selected' : '' }}>I</option>
                        </select>
                    </div>

                    <!-- MRPR -->
                    <div class="form-group">
                        <label for="MRPR">Marca (MRPR)</label>
                        <select name="MRPR" class="form-control">
                            @foreach($marcas as $marca)
                                <option value="{{ $marca->KOMR }}" {{ $producto->MRPR == $marca->KOMR ? 'selected' : '' }}>{{ $marca->NOKOMR }}</option>
                            @endforeach
                        </select>
                    </div>

<div class="form-group">
    <label for="categoriaPadre">Categoría Padre</label>
    <select id="categoriaPadre" name="FMPR" class="form-control">
        <option value="">Seleccione Categoría Padre</option>
        @foreach ($categoriasPadre as $categoria)
            <option value="{{ $categoria->KOFM }}" {{ $producto->FMPR == $categoria->KOFM ? 'selected' : '' }}>
                {{ $categoria->NOKOFM }}
            </option>
        @endforeach
    </select>
</div>


<!-- Subcategoría -->
<div class="form-group">
    <label for="subCategoria">Subcategoría</label>
    <select id="subCategoria" name="PFPR" class="form-control">
        <option value="">Seleccione Sub Categoría</option>
        @foreach ($subCategorias as $subCategoria)
            <option value="{{ $subCategoria->KOPF }}" {{ $producto->PFPR == $subCategoria->KOPF ? 'selected' : '' }}>
                {{ $subCategoria->NOKOPF }}
            </option>
        @endforeach
    </select>
</div>


<!-- Subcategoría Hijo -->
<div class="form-group">
    <label for="subCategoriaHijo">Subcategoría Hijo</label>
    <select id="subCategoriaHijo" name="HFPR" class="form-control">
        <option value="">Seleccione Sub Categoría Hijo</option>
        @foreach ($subCategoriasHijo as $subCategoriaHijo)
            <option value="{{ $subCategoriaHijo->KOHF }}" {{ $producto->HFPR == $subCategoriaHijo->KOHF ? 'selected' : '' }}>
                {{ $subCategoriaHijo->NOKOHF }}
            </option>
        @endforeach
    </select>
</div>



                    <!-- DIVISIBLE -->
                    <div class="form-group">
                        <label for="DIVISIBLE">Divisible</label>
                        <select name="DIVISIBLE" class="form-control">
                            <option value="" {{ $producto->DIVISIBLE == '' ? 'selected' : '' }}>No</option>
                            <option value="S" {{ $producto->DIVISIBLE == 'S' ? 'selected' : '' }}>Sí</option>
                        </select>
                    </div>

                    <!-- DIVISIBLE2 -->
                    <div class="form-group">
                        <label for="DIVISIBLE2">Divisible 2</label>
                        <select name="DIVISIBLE2" class="form-control">
                            <option value="" {{ $producto->DIVISIBLE2 == '' ? 'selected' : '' }}>No</option>
                            <option value="S" {{ $producto->DIVISIBLE2 == 'S' ? 'selected' : '' }}>Sí</option>
                        </select>
                    </div>

                    <!-- FECRPR -->
                    <div class="form-group">
                        <label for="FECRPR">Fecha de Carga (FECRPR)</label>
                        <input type="text" name="FECRPR" class="form-control" value="{{ $producto->FECRPR }}" readonly>
                    </div>

                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                </form>
            </div>
        </div>
    </div>
</div>
<script>
    // Elementos del formulario
    const categoriaPadreSelect = document.getElementById('categoriaPadre');
    const subCategoriaSelect = document.getElementById('subCategoria');
    const subCategoriaHijoSelect = document.getElementById('subCategoriaHijo');
    const descripcionInput = document.getElementsByName('NOKOPR')[0];
    const koprField = document.getElementById('KOPR');
    const koprteField = document.getElementById('KOPRTE');
    const koprraField = document.getElementsByName('KOPRRA')[0];
    const nokoprraField = document.getElementById('NOKOPRRA');

    // Cargar subcategorías dinámicamente al cambiar la categoría padre
    categoriaPadreSelect.addEventListener('change', function () {
        const categoriaPadre = this.value;

        // Limpiar campos dependientes
        subCategoriaSelect.innerHTML = '<option value="">Seleccione Sub Categoría</option>';
        subCategoriaHijoSelect.innerHTML = '<option value="">Seleccione Sub Categoría Hijo</option>';

        if (categoriaPadre) {
            fetch(`/productos/subcategorias/${categoriaPadre}`)
                .then(response => response.json())
                .then(data => {
                    data.forEach(sub => {
                        subCategoriaSelect.innerHTML += `<option value="${sub.KOPF}">${sub.NOKOPF}</option>`;
                    });
                });
        }
    });


    // Cargar subcategorías hijo dinámicamente al cambiar la subcategoría
    subCategoriaSelect.addEventListener('change', function () {
        const subCategoria = this.value;
        const categoriaPadre = categoriaPadreSelect.value;

        subCategoriaHijoSelect.innerHTML = '<option value="">Seleccione Sub Categoría Hijo</option>';

        if (categoriaPadre && subCategoria) {
            fetch(`/productos/subcategorias-hijo/${categoriaPadre}/${subCategoria}`)
                .then(response => response.json())
                .then(data => {
                    data.forEach(hijo => {
                        subCategoriaHijoSelect.innerHTML += `<option value="${hijo.KOHF}">${hijo.NOKOHF}</option>`;
                    });
                });
        }
    });

    // Función para generar el código KOPR y KOPRTE
    function generateKopr() {
        const fmpr = categoriaPadreSelect.value.trim(); // Categoría Padre
        const pfpr = subCategoriaSelect.value.trim(); // Sub Categoría
        const descripcion = descripcionInput.value.trim(); // Descripción del Producto
        const koprra = koprraField.value.trim(); // Código Producto Incremental (KOPRRA)

        // Si falta alguno de los valores, no generamos el código
        if (!fmpr || !pfpr || !descripcion || !koprra) {
            koprField.value = '';
            koprteField.value = '';
            return;
        }

        // Obtenemos las 3 primeras letras de la descripción (removemos espacios extra)
        const descripcionCode = descripcion.replace(/\s+/g, '').substring(0, 3).toUpperCase();

        // Generamos el código
        const newCode = `${fmpr}${pfpr}${descripcionCode}${koprra}`.replace(/\s+/g, '');

        // Actualizamos los campos KOPR y KOPRTE
        koprField.value = newCode;
        koprteField.value = newCode;
    }

    // Función para copiar NOKOPR en NOKOPRRA
    function copyDescripcionToNokoprra() {
        const descripcion = descripcionInput.value.trim(); // Eliminar espacios en blanco
        nokoprraField.value = descripcion; // Copiar descripción
    }

    // Eventos para generar el código dinámicamente
    categoriaPadreSelect.addEventListener('change', generateKopr);
    subCategoriaSelect.addEventListener('change', generateKopr);
    descripcionInput.addEventListener('input', () => {
        generateKopr();
        copyDescripcionToNokoprra();
    });
</script>



@endsection
