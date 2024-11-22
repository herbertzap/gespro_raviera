@extends('layouts.app', ['page' => __('Productos Publicados'), 'pageSlug' => 'productos-publicados'])

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title"> Productos Publicados</h4>
                <form method="GET" action="{{ route('productos.publicados') }}" class="form-inline">
                    <input type="text" name="nombre" placeholder="Buscar por Nombre" class="form-control mr-2" value="{{ request()->nombre }}">
                    <input type="text" name="codigo" placeholder="Código KOPR" class="form-control mr-2" value="{{ request()->codigo }}">
                    <input type="date" name="fecha_inicio" placeholder="Fecha Inicio" class="form-control mr-2" value="{{ request()->fecha_inicio }}">
                    <input type="date" name="fecha_fin" placeholder="Fecha Fin" class="form-control mr-2" value="{{ request()->fecha_fin }}">
                    <select name="marca" class="form-control mr-2">
                        <option value="">Seleccione Marca</option>
                        @foreach($marcas as $marca)
                            <option value="{{ $marca->MRPR }}" {{ request()->marca == $marca->MRPR ? 'selected' : '' }}>
                                {{ $marca->MRPR }}
                            </option>
                        @endforeach
                    </select>
                    <select name="categoria" class="form-control mr-2">
                        <option value="">Categoría Padre</option>
                        @foreach($categorias as $categoria)
                            <option value="{{ $categoria->FMPR }}" {{ request()->categoria == $categoria->FMPR ? 'selected' : '' }}>
                                {{ $categoria->NOKOFM }}
                            </option>
                        @endforeach
                    </select>
                    <select name="sub_categoria" class="form-control mr-2">
                        <option value="">Sub Categoría</option>
                        @foreach($subCategorias as $subCategoria)
                            <option value="{{ $subCategoria->PFPR }}" {{ request()->sub_categoria == $subCategoria->PFPR ? 'selected' : '' }}>
                                {{ $subCategoria->NOKOPF }}
                            </option>
                        @endforeach
                    </select>
                    <select name="sub_categoria_hijo" class="form-control mr-2">
                        <option value="">Sub Categoría Hijo</option>
                        @foreach($subCategoriasHijo as $subCategoriaHijo)
                            <option value="{{ $subCategoriaHijo->HFPR }}" {{ request()->sub_categoria_hijo == $subCategoriaHijo->HFPR ? 'selected' : '' }}>
                                {{ $subCategoriaHijo->NOKOHF }}
                            </option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                    <a href="{{ route('productos.publicados') }}" class="btn btn-secondary">Limpiar Filtros</a>
    
                </form>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table tablesorter" id="">
                        <thead class="text-primary">
                            <tr>
                                <th>TIPR</th>
                                <th>KOPR</th>
                                <th>Nombre</th>
                                <th>KOPRRA</th>
                                <th>Marca</th>
                                <th>Categoría</th>
                                <th>Sub Categoría</th>
                                <th>Sub Categoría Hijo</th>
                                <th>Fecha</th>
                                <th>Divisible</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($productos as $producto)
                            <tr>
                                <td>{{ $producto->TIPR }}</td>
                                <td>{{ $producto->KOPR }}</td>
                                <td>{{ $producto->NOKOPR }}</td>
                                <td>{{ $producto->KOPRRA }}</td>
                                <td>{{ $producto->MRPR }}</td>
                                <td>{{ $producto->FMPR }}</td>
                                <td>{{ $producto->PFPR }}</td>
                                <td>{{ $producto->HFPR }}</td>
                                <td>{{ $producto->FECRPR }}</td>
                                <td>{{ $producto->DIVISIBLE }}</td>
                                <td>
                                    <a href="{{ route('productos.editar-precios', $producto->KOPR) }}" class="btn btn-warning btn-sm">Editar Precios</a>
                                    <a href="{{ route('productos.editar-bodegas', $producto->KOPR) }}" class="btn btn-info btn-sm">Editar Bodegas</a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    {{ $productos->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection