<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Producto;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\ProductosImport;
use Illuminate\Support\Facades\DB;

class ProductoController extends Controller
{
    public function cargarVista()
    {
        return view('productos.cargar'); // Vista para cargar el archivo Excel
    }

    public function cargarExcel(Request $request)
    {
        $request->validate([
            'archivo' => 'required|file|mimes:xlsx,xls'
        ]);

        Excel::import(new ProductosImport, $request->file('archivo'));

        return redirect()->route('productos.cargar')->with('success', 'Productos cargados exitosamente.');
    }

    // Mostrar la lista de productos para validación
    public function validarVista()
    {
        $productos = Producto::where('estado', 0)->get(); // Filtra productos "ingresados"
        return view('productos.validar', compact('productos'));
    }

    // Mostrar el formulario de edición de un producto específico
    public function editarProducto($id)
    {
        // Obtener el producto temporal que se va a editar
        $producto = Producto::findOrFail($id);

        // Consultar el máximo valor de KOPRRA y sumar 1
        $nuevoKOPRRA = DB::connection('sqlsrv')->table('MAEPR')
        ->max('KOPRRA') + 1;

        // Consultar las unidades de medida (UD01PR y UD02PR)
        $unidadMedida1 = DB::connection('sqlsrv')->table('MAEPR')
            ->select('UD01PR')
            ->groupBy('UD01PR')
            ->pluck('UD01PR');

        $unidadMedida2 = DB::connection('sqlsrv')->table('MAEPR')
            ->select('UD02PR')
            ->groupBy('UD02PR')
            ->pluck('UD02PR');

        // Consultar las marcas (MRPR)
        $marcas = DB::connection('sqlsrv')->table('TABMR')
            ->select('KOMR', 'NOKOMR')
            ->get();

        // Consultar categorías padre (FMPR)
        $categoriasPadre = DB::connection('sqlsrv')->table('TABFM')
            ->select('KOFM', 'NOKOFM')
            ->get();

        // Consultar subcategorías (PFPR) y subcategorías hijo (HFPR)
        // Inicialmente vacías para manejarlas con AJAX
        // Subcategorías (PFPR) basadas en la categoría padre del producto
        $subCategorias = [];
        if ($producto->FMPR) {
            $subCategorias = DB::connection('sqlsrv')->table('TABPF')
            ->select('KOPF', 'NOKOPF')
            ->where('KOFM', $producto->FMPR)
            ->get();
        }
        // Subcategorías Hijo (HFPR) basadas en la categoría padre y subcategoría del producto
        $subCategoriasHijo = [];
        if ($producto->FMPR && $producto->PFPR) {
        $subCategoriasHijo = DB::connection('sqlsrv')->table('TABHF')
            ->select('KOHF', 'NOKOHF')
            ->where('KOFM', $producto->FMPR)
            ->where('KOPF', $producto->PFPR)
            ->get();
        }

        // Pasar los datos necesarios a la vista
        return view('productos.editar', compact(
            'producto',
            'unidadMedida1',
            'unidadMedida2',
            'marcas',
            'categoriasPadre',
            'subCategorias',
            'subCategoriasHijo',
            'nuevoKOPRRA'
        ));
    }
// Actualizar los datos del producto en la base de datos
    public function actualizarProducto(Request $request, $id)
    {
        // Validar datos recibidos (puedes agregar reglas más específicas)
        $request->validate([
            'TIPR' => 'required|string|max:3', // Siempre será "FPN"
            'KOPR' => 'required|string|max:20', // Código dinámico generado
            'KOPRRA' => 'required|numeric', // Código incremental
            'KOPRTE' => 'required|string|max:20', // Igual a KOPR
            'NOKOPR' => 'required|string|max:50',
            'NOKOPRRA' => 'required|string|max:50',
            'UD01PR' => 'nullable|string|max:2',
            'UD02PR' => 'nullable|string|max:2',
            'RLUD' => 'nullable|numeric|min:1|max:9999',
            'POIVPR' => 'nullable|numeric|min:1|max:9999',
            'RGPR' => 'nullable|string|max:1',
            'MRPR' => 'nullable|string|max:20',
            'FMPR' => 'required|string|max:3',
            'PFPR' => 'required|string|max:3',
            'HFPR' => 'nullable|string|max:3',
            'DIVISIBLE' => 'nullable|string|max:1',
            'DIVISIBLE2' => 'nullable|string|max:1',
            'FECRPR' => 'nullable|date',
        ]);

        // Obtener el producto temporal
        $producto = Producto::findOrFail($id);

        // Actualizar los datos del producto
        $producto->update([
            'TIPR' => $request->input('TIPR', 'FPN'), // Siempre "FPN"
            'KOPR' => $request->input('KOPR'), // Código dinámico generado
            'KOPRRA' => $request->input('KOPRRA'), // Código incremental
            'KOPRTE' => $request->input('KOPRTE'), // Igual a KOPR
            'NOKOPR' => $request->input('NOKOPR'),
            'NOKOPRRA' => $request->input('NOKOPRRA'),
            'UD01PR' => $request->input('UD01PR'),
            'UD02PR' => $request->input('UD02PR'),
            'RLUD' => $request->input('RLUD', 1),
            'POIVPR' => $request->input('POIVPR', 1),
            'RGPR' => $request->input('RGPR', 'N'),
            'MRPR' => $request->input('MRPR'),
            'FMPR' => $request->input('FMPR'),
            'PFPR' => $request->input('PFPR'),
            'HFPR' => $request->input('HFPR'),
            'DIVISIBLE' => $request->input('DIVISIBLE'),
            'DIVISIBLE2' => $request->input('DIVISIBLE2'),
            'FECRPR' => $request->input('FECRPR', now()), // Fecha actual por defecto
            'estado' => 1, // Actualizar estado a validado
    
        ]);

        // Insertar el producto en SQL Server
        DB::connection('sqlsrv')->table('MAEPR')->insert([
            'TIPR' => $producto->TIPR,
            'KOPR' => $producto->KOPR,
            'NOKOPR' => $producto->NOKOPR,
            'KOPRRA' => $producto->KOPRRA,
            'NOKOPRRA' => $producto->NOKOPRRA,
            'KOPRTE' => $producto->KOPRTE,
            'UD01PR' => $producto->UD01PR ?? '',
            'UD02PR' => $producto->UD02PR ?? '',
            'RLUD' => $producto->RLUD ?? 0,
            'POIVPR' => $producto->POIVPR ?? 0,
            'RGPR' => $producto->RGPR ?? '',
            'MRPR' => $producto->MRPR ?? '',
            'FMPR' => $producto->FMPR,
            'PFPR' => $producto->PFPR,
            'HFPR' => $producto->HFPR ?? '',
            'DIVISIBLE' => $producto->DIVISIBLE ?? '',
            'DIVISIBLE2' => $producto->DIVISIBLE2 ?? '',
            'FECRPR' => $producto->FECRPR,
            // Resto de los campos faltantes
            'KOGE' => '',
            'NMARCA' => '',
            'NUIMPR' => 0,
            'STMIPR' => 0,
            'STMAPR' => 0,
            'ATPR' => '',
            'RUPR' => '',
            'STFI1' => 0,
            'STDV1' => 0,
            'STOCNV1' => 0,
            'STFI2' => 0,
            'STDV2' => 0,
            'STOCNV2' => 0,
            'PPUL01' => 0,
            'PPUL02' => 0,
            'MOUL' => '',
            'TIMOUL' => '',
            'TAUL' => 0,
            'FEUL' => Carbon::now(),
            'PM' => 0,
            'FEPM' => Carbon::now(),
            'VALI' => 0,
            'FEVALI' => Carbon::now(),
            'TTREPR' => 0,
            'PRRG' => 0,
            'NIPRRG' => '',
            'NFPRRG' => '',
            'PMIN' => 0,
            // Agregar más campos según sea necesario...
        ]);

        return redirect()->route('productos.validar')->with('success', 'Producto actualizado correctamente.');
    }


    public function obtenerSubcategorias($categoriaPadre)
    {
    $subCategorias = DB::connection('sqlsrv')->table('TABPF')
        ->select('KOPF', 'NOKOPF')
        ->where('KOFM', $categoriaPadre)
        ->get();
        return response()->json($subCategorias);
    }
    
    public function obtenerSubcategoriasHijo($categoriaPadre, $subCategoria)
    {
        $subCategoriasHijo = DB::connection('sqlsrv')->table('TABHF')
        ->select('KOHF', 'NOKOHF')
        ->where('KOFM', $categoriaPadre)
        ->where('KOPF', $subCategoria)
        ->get();
        return response()->json($subCategoriasHijo);
    }

    public function productosPublicados(Request $request)
    {
        // Filtros de búsqueda
        $nombre = $request->input('nombre');
        $codigo = $request->input('codigo');
        $fechaInicio = $request->input('fecha_inicio');
        $fechaFin = $request->input('fecha_fin');
        $marca = $request->input('marca');
        $categoria = $request->input('categoria');
        $subCategoria = $request->input('sub_categoria');
        $subCategoriaHijo = $request->input('sub_categoria_hijo');

        // Consulta a la base de datos SQL Server
        $query = DB::connection('sqlsrv')->table('MAEPR')->select(
            'TIPR', 'KOPR', 'NOKOPR', 'KOPRRA', 'NOKOPRRA', 'KOPRTE',
            'UD01PR', 'UD02PR', 'RLUD', 'POIVPR', 'RGPR', 'MRPR',
            'FMPR', 'PFPR', 'HFPR', 'DIVISIBLE', 'FECRPR', 'DIVISIBLE2'
        );

        // Aplicar los filtros
        if ($nombre) {
            $query->where('NOKOPR', 'like', "%$nombre%");
        }
        if ($codigo) {
            $query->where('KOPR', $codigo);
        }
        if ($fechaInicio && $fechaFin) {
            $query->whereBetween('FECRPR', [$fechaInicio, $fechaFin]);
        }
        if ($marca) {
            $query->where('MRPR', $marca);
        }
        if ($categoria) {
            $query->where('FMPR', $categoria);
        }
        if ($subCategoria) {
            $query->where('PFPR', $subCategoria);
        }
        if ($subCategoriaHijo) {
            $query->where('HFPR', $subCategoriaHijo);
        }

        // Obtener datos paginados
        $productos = $query->orderBy('KOPRRA', 'DESC')->paginate(20);

        // Obtener datos auxiliares para filtros
        $marcas = DB::connection('sqlsrv')->table('MAEPR')->select('MRPR')->distinct()->get();
        $categorias = DB::connection('sqlsrv')->table('TABFM')->select('KOFM as FMPR', 'NOKOFM')->get();
        $subCategorias = DB::connection('sqlsrv')->table('TABPF')->select('KOPF as PFPR', 'NOKOPF')->get();
        $subCategoriasHijo = DB::connection('sqlsrv')->table('TABHF')->select('KOHF as HFPR', 'NOKOHF')->get();

        return view('productos.productosPublicados', compact('productos', 'marcas', 'categorias', 'subCategorias', 'subCategoriasHijo'));
    }

    public function listaPrecios(Request $request)
    {
        // Filtros de búsqueda
        $codigo = $request->input('codigo');
        $listaPrecio = $request->input('lista_precio');
    
        // Consulta a la base de datos SQL Server
        $query = DB::connection('sqlsrv')->table('TABPRE')->select(
            'KOLT', 'KOPR', 'KOPRRA', 'KOPRTE', 'ECUACION', 'RLUD',
            'PP01UD', 'MG01UD', 'DTMA01UD', 'PP02UD', 'MG02UD', 'DTMA02UD', 'ECUACIONU2'
        );
    
        // Aplicar filtros
        if ($codigo) {
            $query->where('KOPR', 'like', "%$codigo%");
        }
    
        if ($listaPrecio) {
            $query->where('KOLT', $listaPrecio);
        }
    
        // Obtener datos paginados
        $productos = $query->orderBy('KOLT', 'ASC')->paginate(20);
    
        // Obtener listas de precios únicas para el filtro
        $listasPrecios = DB::connection('sqlsrv')->table('TABPRE')->select('KOLT')->distinct()->get();
    
        // Enviar datos a la vista
        return view('productos.listaPrecios', compact('productos', 'listasPrecios'));
    }



    public function editarPrecios($codigo)
    {
        // Obtener todas las listas de precios asociadas al producto
        $listasPrecios = DB::connection('sqlsrv')->table('TABPRE')
            ->where('KOPR', $codigo)
            ->select('KOLT', 'PP01UD', 'MG01UD', 'DTMA01UD', 'PP02UD', 'MG02UD', 'DTMA02UD', 'RLUD')
            ->get();
    
        if ($listasPrecios->isEmpty()) {
            abort(404, 'No se encontraron listas de precios asociadas al producto.');
        }
    
        // Identificar el valor base de KOLT = '01C' si existe
        $baseLista = $listasPrecios->firstWhere('KOLT', '01C');
        $basePrecio = $baseLista ? $baseLista->PP01UD : null;
    
        return view('productos.editarPrecios', compact('codigo', 'listasPrecios', 'basePrecio'));
    }
    

    public function actualizarPrecios(Request $request)
    {
        $validated = $request->validate([
            'codigo' => 'required',
            'listas' => 'required|array',
            'listas.*.KOLT' => 'required',
            'listas.*.MG01UD' => 'nullable|numeric',
            'listas.*.DTMA01UD' => 'nullable|numeric',
            'listas.*.MG02UD' => 'nullable|numeric',
            'listas.*.DTMA02UD' => 'nullable|numeric',
        ]);
    
        $codigo = $request->codigo;
        $listas = $request->listas;
    
        foreach ($listas as $lista) {
            // Calcular PP01UD y PP02UD
            $pp01ud = null;
            if ($lista['KOLT'] !== '01C') {
                $basePrecio = DB::connection('sqlsrv')->table('TABPRE')
                    ->where('KOPR', $codigo)
                    ->where('KOLT', '01C')
                    ->value('PP01UD');
    
                $pp01ud = $basePrecio ? $basePrecio * ($lista['MG01UD'] ?? 1) : null;
            }
    
            $pp02ud = isset($lista['RLUD']) && isset($lista['MG02UD']) 
                ? $lista['RLUD'] * $lista['MG02UD'] 
                : null;
    
            // Actualizar la lista de precios
            DB::connection('sqlsrv')->table('TABPRE')
                ->where('KOPR', $codigo)
                ->where('KOLT', $lista['KOLT'])
                ->update([
                    'MG01UD' => $lista['MG01UD'],
                    'DTMA01UD' => $lista['DTMA01UD'],
                    'PP01UD' => $pp01ud,
                    'MG02UD' => $lista['MG02UD'],
                    'DTMA02UD' => $lista['DTMA02UD'],
                    'PP02UD' => $pp02ud,
                ]);
        }
    
        return redirect()->route('productos.productosPublicados')->with('success', 'Precios actualizados correctamente.');
    }
    


public function editarBodegas($codigo)
{
    // Consultar los datos de bodega relacionados al producto
    $producto = DB::connection('sqlsrv')->table('MAEPR')->where('KOPR', $codigo)->first();

    if (!$producto) {
        abort(404, 'Producto no encontrado');
    }

    return view('productos.editarBodegas', compact('producto'));
}

public function actualizarBodegas(Request $request)
{
    $validated = $request->validate([
        'KOPR' => 'required',
        'bodega' => 'nullable|string|max:255',
        'cantidad' => 'nullable|numeric|min:0',
    ]);

    // Actualizar datos de bodega
    DB::connection('sqlsrv')->table('MAEPR')->where('KOPR', $request->KOPR)->update([
        'bodega' => $request->bodega,
        'cantidad' => $request->cantidad,
    ]);

    return redirect()->route('productos.productosPublicados')->with('success', 'Bodegas actualizadas correctamente');
}


    
    


    


}
