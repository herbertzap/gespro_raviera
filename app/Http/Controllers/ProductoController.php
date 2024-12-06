<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Producto;
use App\Imports\ProductosImport;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\LogController;


class ProductoController extends Controller
{
    public function cargarVista()
    {
        return view('productos.cargar'); // Vista para cargar el archivo Excel
    }

/////////////////////////////////    
/*
carga de excel y funcioncionesss
*/
/////////////////////////////////
public function cargarExcel(Request $request)
{
    if (!$request->hasFile('archivo_excel')) {
        Log::error('No se encontró un archivo en la solicitud.');
        return redirect()->back()->with('error', 'No se ha seleccionado ningún archivo.');
    }

    $file = $request->file('archivo_excel');

    if (!$file->isValid()) {
        Log::error('El archivo no es válido.');
        return redirect()->back()->with('error', 'El archivo no es válido.');
    }

    try {
        $filePath = $file->getRealPath();
        Log::info('Archivo recibido correctamente: ' . $file->getClientOriginalName());
        $this->procesarExcelPorPestanas($filePath);
        return redirect()->back()->with('success', 'Archivo procesado correctamente.');
    } catch (\Exception $e) {
        Log::error('Error al procesar el archivo: ' . $e->getMessage());
        return redirect()->back()->with('error', 'Error al procesar el archivo.');
    }
}


protected function procesarExcelPorPestanas($filePath)
{
    // Crear un lector para el archivo Excel y permitir lectura de fórmulas evaluadas
    $reader = IOFactory::createReader('Xlsx');
    $reader->setReadDataOnly(false);
    $spreadsheet = $reader->load($filePath);

    $hojas = $spreadsheet->getSheetNames();

    foreach ($hojas as $pestana) {
        $sheet = $spreadsheet->getSheetByName($pestana);
        if (!$sheet) {
            Log::warning("La pestaña {$pestana} no existe en el archivo.");
            continue;
        }

        Log::info("Procesando pestaña: {$pestana}");

        if ($pestana == 'Creación Productos') {
            $this->procesarCreacionProductos($sheet);
        } else {
           
        }
    }
}


protected function procesarCreacionProductos($sheet)
{
    $datos = $sheet->toArray(null, true, true, true);
    $userId = auth()->user()->id;

    $resultadosPorTabla = [
        'MAEPR' => [],
        'MAEPREM' => [],
        'TABPRE' => [],
        'TABBOPR' => [],
        'PDIMEN' => [],
        
    ];

    // Obtener el último correlativo desde la base de datos
    $ultimoCorrelativo = DB::connection('sqlsrv')->table('MAEPR')->max('KOPRRA');
    if (!$ultimoCorrelativo) {
        $ultimoCorrelativo = 0; // Si no hay registros, empezar desde 0
    }

    foreach ($datos as $index => $fila) {
        // Saltar encabezados
        if ($index == 1) {
            Log::info("Encabezados detectados y omitidos.");
            continue;
        }

        // Validar si el campo NOKOPR (fila['I']) está vacío
        if (empty($fila['I']) || trim($fila['I']) === '#N/A') {
            Log::warning("Fila {$index} saltada: NOKOPR vacío o inválido.");
            continue;
        }

        // Generar un nuevo correlativo
        $nuevoCorrelativo = str_pad($ultimoCorrelativo + 1, 6, '0', STR_PAD_LEFT);

        // Reemplazar valores en los campos relevantes
        if (isset($fila['H']) && strpos($fila['H'], '999999') !== false) {
            $fila['H'] = str_replace('999999', $nuevoCorrelativo, $fila['H']); // Reemplazo en KOPR
        }
        if (isset($fila['G']) && strpos($fila['G'], '999999') !== false) {
            $fila['G'] = str_replace('999999', $nuevoCorrelativo, $fila['G']); // Reemplazo en KOPRRA
            $ultimoCorrelativo++; // Incrementar el correlativo
        }

        // Truncar NOKOPRRA a un máximo de 20 caracteres
        $nokoprra = $fila['I'] ?? null;
        if (!empty($nokoprra) && strlen($nokoprra) > 20) {
            $nokoprra = substr($nokoprra, 0, 20);
        }

        $rlud = $fila['N'] ?? 0; // Obtenemos el valor original
        if (is_string($rlud)) {
            // Reemplazamos la coma por un punto y convertimos a float
            $rlud = floatval(str_replace(',', '.', $rlud));
        }
        // Eliminar el punto y convertir a entero costo producto
        $costoBase = $fila['O'] ?? 0;
        $costoBase = intval(str_replace('.', '', $costoBase));

        $pp02ud = $costoBase; // Obtenemos el valor original
        if (is_string($pp02ud)) {
            // Reemplazamos la coma por un punto y convertimos a float
            $pp02ud = ($pp02ud*$rlud);
        }

        // Construir el código del producto
        $primerasTresLetrasD = substr($fila['I'], 0, 3);
        $codigoProducto = $fila['B'] . $fila['D'] . $primerasTresLetrasD . ltrim($fila['G'], '0');
        $codigoProducto = (str_replace(' ', '', $codigoProducto));
        Log::info("Código de producto generado: {$codigoProducto}");



        // Preparar datos para MAEPR
        $maepr = [
            'TIPR' => 'FPN',
            'KOPR' => $codigoProducto,
            'NOKOPR' => $fila['I'] ?? null,
            'KOPRRA' => $fila['G'] ?? null,
            'NOKOPRRA' => $nokoprra,
            'KOPRTE' => $codigoProducto,
            'UD01PR' => $fila['L'] ?? null,
            'UD02PR' => $fila['M'] ?? null,
            'RLUD' => $rlud,
            'POIVPR' => '19',
            'RGPR' => $fila['BR'] ?? null,
            'FMPR' => $fila['B'] ?? null,
            'PFPR' => $fila['D'] ?? null,
            'DIVISIBLE' => $fila['BP'] ?? null,
            'DIVISIBLE2' => $fila['BQ'] ?? null,
            'MRPR' => $fila['K'] ?? null,
        ];
        // Validar longitud de campos para evitar truncados
        $camposConLongitud = [
            'KOPR' => 13,
            'NOKOPR' => 50,
            'KOPRRA' => 6,
            'NOKOPRRA' => 20,
            'UD01PR' => 2,
            'UD02PR' => 2,
            'MRPR' => 20,
        ];
        foreach ($camposConLongitud as $campo => $longitud) {
            if (isset($maepr[$campo]) && strlen($maepr[$campo]) > $longitud) {
                Log::error("Fila {$index} saltada: {$campo} excede la longitud permitida ({$longitud}).");
                continue 2; // Saltar esta fila
            }
        }
        

        $maeprem = [
            'EMPRESA' => '01',
            'KOPR' => $codigoProducto,
            'METRCO' => 'PPX',
            'CUENTACON' => '11090050',
            'CUENTAVEN' => '41900010',
            'CUENTASIA' => '51300026',
            'CUENTARIA' => '51300026',
            'CTACOSVTA' => '51100200',
            'CTAIMPAB' => '11090040',
            'CUENTAGTI' => '11090041',
            'CTAVPREST' => '11090042',
            'CTACPREST' => '11090040',
            'CTACPRESTH' => '11090042',
            'STTR1' => '0',
            'STTR2' => '0',
            'CTAAJUSAL' => '51100900',
            'CTAAJUING' => '51100900',
            'CTAFXRCOM' => '21200300',
        ];


        
        $tabpre01C = [
            'KOLT' => '01C',
            'KOPR' => $codigoProducto ,
            'KOPRRA' => $fila['G'] ?? null,
            'KOPRTE' => $codigoProducto,
            'RLUD' => $rlud,
            'MG01UD' => '0',
            'MG02UD' => '0',
            'DTMA01UD' => '0',
            'DTMA02UD' => '0',
            'PP01UD' => $costoBase,
            'PP02UD' => $pp02ud,
            'ECUACION' => '0',
            'ECUACIONU2' => '<01c>pp01ud * rlud#3',
        ];

        $tabpre02C = [
            'KOLT' => '02C',
            'KOPR' => $codigoProducto,
            'KOPRRA' => $fila['G'] ?? null,
            'KOPRTE' => $codigoProducto,
            'RLUD' => $rlud,
            'MG01UD' => '0',
            'MG02UD' => '0',
            'DTMA01UD' => '0',
            'DTMA02UD' => '0',
            'PP01UD' => '0',
            'PP02UD' => '0',
            'ECUACION' => ' ',
            'ECUACIONU2' => ' ',
        ];

        $tabpre03C = [
            'KOLT' => '03C',
            'KOPR' => $codigoProducto,
            'KOPRRA' => $fila['G'] ?? null,
            'KOPRTE' => $codigoProducto,
            'RLUD' => $rlud,
            'MG01UD' => '0',
            'MG02UD' => '0',
            'DTMA01UD' => '0',
            'DTMA02UD' => '0',
            'PP01UD' => '0',
            'PP02UD' => '0',
            'ECUACION' => ' ',
            'ECUACIONU2' => ' ',
        ];

        $pdimen = [
            'EMPRESA' => '',
            'CODIGO' => $codigoProducto,
            'NUMOT' => '',
            'NREGOTL' => '',
            'NOMBRE' => $fila['I'] ?? null, 
            'UDAD' => $fila['L'] ?? null,
            'HORAS_HH' => '0',
            'CANT_KMTS' => '0',
            'ZFLETE1' => '100.0',
            'ZFLETE2' => '200.0',
            'ZFLETE3' => '300.0',
        ];


        // Insertar en la base de datos
        try {
            DB::connection('sqlsrv')->table('MAEPR')->insert($maepr);
            Log::info("Fila {$index} insertada correctamente en MAEPR.");
            LogController::registrarLog($userId, 'insert', 'MAEPR', $maepr);
            
            DB::connection('sqlsrv')->table('MAEPREM')->insert($maeprem);
            Log::info("Fila {$index} insertada correctamente en maeprem.");
            LogController::registrarLog($userId, 'insert', 'MAEPREM', $maeprem);
            
            DB::connection('sqlsrv')->table('TABPRE')->insert($tabpre01C);
            Log::info("Fila {$index} insertada correctamente en TABPRE 01c.");
            LogController::registrarLog($userId, 'insert', 'TABPRE', $tabpre01C);
            
            DB::connection('sqlsrv')->table('TABPRE')->insert($tabpre02C);
            Log::info("Fila {$index} insertada correctamente en TABPRE 02c.");
            
            DB::connection('sqlsrv')->table('TABPRE')->insert($tabpre03C);
            Log::info("Fila {$index} insertada correctamente en TABPRE 03c.");

            DB::connection('sqlsrv')->table('PDIMEN')->insert($pdimen);
            Log::info("Fila {$index} insertada correctamente en PDIMEN.");
            
        } catch (\Exception $e) {
            Log::error("Error al insertar la fila {$index} en MAEPR: " . $e->getMessage());
            LogController::registrarLog($userId, 'error', 'MAEPR', $maepr, ['message' => $e->getMessage()]);
          
       
        }

        // Array de configuraciones para las tablas TABPRE
        $tabpreConfig = [
            '01P' => ['MG01UD' => 'AF', 'MG02UD' => 'AG', 'DTMA01UD' => 'AH', 'DTMA02UD' => 'AH'],
            '02P' => ['MG01UD' => 'AI', 'MG02UD' => 'AJ', 'DTMA01UD' => 'AK', 'DTMA02UD' => 'AK'],
            '03P' => ['MG01UD' => 'AL', 'MG02UD' => 'AM', 'DTMA01UD' => 'AN', 'DTMA02UD' => 'AN'],
            '04P' => ['MG01UD' => 'AO', 'MG02UD' => 'AP', 'DTMA01UD' => 'AQ', 'DTMA02UD' => 'AQ'],
            '05P' => ['MG01UD' => 'AR', 'MG02UD' => 'AS', 'DTMA01UD' => 'AT', 'DTMA02UD' => 'AT'],
            '10P' => ['MG01UD' => 'AU', 'MG02UD' => 'AV', 'DTMA01UD' => 'AW', 'DTMA02UD' => 'AW'],
            '11P' => ['MG01UD' => 'AX', 'MG02UD' => 'AY', 'DTMA01UD' => 'AZ', 'DTMA02UD' => 'AZ'],
            '12P' => ['MG01UD' => 'BA', 'MG02UD' => 'BB', 'DTMA01UD' => 'BC', 'DTMA02UD' => 'BC'],  
            '13P' => ['MG01UD' => 'BD', 'MG02UD' => 'BE', 'DTMA01UD' => 'BF', 'DTMA02UD' => 'BF'],
            '14P' => ['MG01UD' => 'BA', 'MG02UD' => 'BB', 'DTMA01UD' => 'BC', 'DTMA02UD' => 'BC'],
            '15P' => ['MG01UD' => 'BA', 'MG02UD' => 'BB', 'DTMA01UD' => 'BC', 'DTMA02UD' => 'BC'],
        ];

        // Procesar cada configuración
        foreach ($tabpreConfig as $kolt => $fields) {
            // Obtener los valores de los campos
            $mg01ud = $fila[$fields['MG01UD']] ?? null;
            $mg02ud = $fila[$fields['MG02UD']] ?? null;
            $dtma01ud = $fila[$fields['DTMA01UD']] ?? null;
            $dtma02ud = $fila[$fields['DTMA02UD']] ?? null;

            // Validar si todos los campos relevantes están vacíos
            if (
                (is_null($mg01ud) || $mg01ud == 0) &&
                (is_null($mg02ud) || $mg02ud == 0) &&
                (is_null($dtma01ud) || $dtma01ud == 0) &&
                (is_null($dtma02ud) || $dtma02ud == 0)
            ) {
                Log::info("Tabla TABPRE {$kolt} omitida porque todos los campos están vacíos o en 0.");
                continue; // Saltar esta tabla
            }

            
            // Asignar valores de ecuaciones según el código (KOLT)
            if (in_array($kolt, ['13P', '14P', '15P'])) {
                $ecuacion = "(<01c>pp01ud*(1+mg01ud/100)+(zflete3*(1/rlud)))#3";
                $ecuacionU2 = "(((<01c>pp01ud*(1+mg02ud/100))*rlud)+zflete3)#3";
                // Calcular PP01UD y PP02UD con el ajuste específico para 13P, 14P, y 15P
                $adjustment = ($kolt === '13P') ? 100 : (($kolt === '14P') ? 200 : 300);
                $pp01ud = (($costoBase * (1 + ($mg01ud / 100))) + $adjustment);
                $pp02ud = ((($costoBase * (1 + ($mg02ud / 100))) * $rlud) + $adjustment);
            } else {
                $ecuacion = "<01C>pp01ud*(1+mg01ud/100)#3";
                $ecuacionU2 = "(<01C>pp01ud*(1+mg02ud/100))*rlud#3";
                $pp01ud = $costoBase * (1 + ($mg01ud / 100));
                $pp02ud = (($costoBase * (1 + ($mg02ud / 100))) * $rlud);
            }

            // Preparar datos para el insert
            $tabpre = [
                'KOLT' => $kolt,
                'KOPR' => $codigoProducto,
                'KOPRRA' => $fila['G'] ?? null,
                'KOPRTE' => $codigoProducto,
                'RLUD' => $rlud,
                'MG01UD' => $mg01ud,
                'MG02UD' => $mg02ud,
                'DTMA01UD' => $dtma01ud,
                'DTMA02UD' => $dtma02ud,
                'PP01UD' => $pp01ud,
                'PP02UD' => $pp02ud,
                'ECUACION' => $ecuacion,
                'ECUACIONU2' => $ecuacionU2,
            ];

            try {
                // Insertar en la base de datos
                DB::connection('sqlsrv')->table('TABPRE')->insert($tabpre);
                Log::info("Fila {$index} insertada correctamente en TABPRE {$kolt}.");
                LogController::registrarLog($userId, 'insert', 'TABPRE', $tabpre);
                
            } catch (\Exception $e) {
                LogController::registrarLog($userId, 'error', 'TABPRE', $tabpre, ['message' => $e->getMessage()]);
       
            }
        }

        // Configuración para procesar bodegas y sucursales
        // Configuración para las columnas de KOBO y KOSU
        $bodegaConfig = [
            'KOBO' => ['P', 'R', 'T', 'V', 'X', 'Z', 'AB', 'AD'], // Columnas de KOBO
            'KOSU' => ['Q', 'S', 'U', 'W', 'Y', 'AA', 'AC', 'AE'], // Columnas asociadas de KOSU
        ];
        
        // Procesar cada fila del archivo Excel
        foreach ($datos as $index => $fila) {
            // Omitir encabezados (primera fila)
            
            if ($index == 1) {
                Log::info("Encabezados detectados y omitidos.");
                continue;
            }

            // Asegurarse de que KOBO y KOSU tengan el mismo número de columnas
            $koboColumns = $bodegaConfig['KOBO'];
            $kosuColumns = $bodegaConfig['KOSU'];
            if (count($koboColumns) !== count($kosuColumns)) {
                Log::error("Configuración inconsistente: KOBO y KOSU tienen diferente número de columnas.");
                break;
            }
            
            // Validar y procesar cada columna de KOBO y KOSU
            for ($i = 0; $i < count($koboColumns); $i++) {
                $columnaKOBO = $koboColumns[$i];
                $columnaKOSU = $kosuColumns[$i];

                // Obtener valores de KOBO y KOSU desde las columnas correspondientes
                $kobo = $fila[$columnaKOBO] ?? null;
                $kosu = $fila[$columnaKOSU] ?? null;

                // Validar que KOBO no sea "NO" o vacío y que KOSU no esté vacío
                if (is_null($kobo) || strtoupper(trim($kobo)) === 'NO' || is_null($kosu)) {
                    
                    continue;
                }

                // Preparar datos para el INSERT
                $bodega = [
                    'KOPR' => $codigoProducto, // Código del producto
                    'EMPRESA' => '01', // Empresa predeterminada
                    'KOSU' => substr($kosu, 0, 10), // Truncar a 10 caracteres si es necesario
                    'KOBO' => substr($kobo, 0, 10), // Truncar a 10 caracteres si es necesario
                ];

                $maest = [
                    'KOPR' => $codigoProducto, // Código del producto
                    'EMPRESA' => '01', // Empresa predeterminada
                    'KOSU' => substr($kosu, 0, 10), 
                    'KOBO' => substr($kobo, 0, 10),
                    'STFI1' => '0',
                    'STFI2' => '0', 
                ];

                try {
                    // Verificar duplicados antes de insertar
                    $existe = DB::connection('sqlsrv')->table('TABBOPR')
                        ->where('KOPR', $codigoProducto)
                        ->where('EMPRESA', '01')
                        ->where('KOSU', $bodega['KOSU'])
                        ->where('KOBO', $bodega['KOBO'])
                        ->exists();

                    if ($existe) {
                        
                        continue;
                    }

                    // Insertar en la base de datos
                    DB::connection('sqlsrv')->table('TABBOPR')->insert($bodega);
                    DB::connection('sqlsrv')->table('MAEST')->insert($maest);
                    Log::info("Fila {$index}, KOBO {$bodega['KOBO']}, KOSU {$bodega['KOSU']} insertados correctamente en TABBOPR.");
                    LogController::registrarLog($userId, 'insert', 'TABBOPR', $bodega);
                } catch (\Exception $e) {
                    LogController::registrarLog($userId, 'error', 'TABBOPR', $bodega, ['message' => $e->getMessage()]);
                }
            }
        }
    }

    // Guardar los datos procesados en un archivo JSON para análisis
    file_put_contents(storage_path('app/public/datos_maepr.json'), json_encode($resultadosPorTabla['MAEPR'], JSON_PRETTY_PRINT));
    Log::info("Datos de MAEPR exportados a storage/app/public/datos_maepr.json");
}













/////////////////////////////////    
/*
Fin carga de excel y funcioncionesss
*/
///////////////////////////////// 

    // Mostrar la lista de productos para validación
    public function validarVista()
    {
        $productos = Producto::where('estado', 0)->get(); // Filtra productos "ingresados"
        return view('productos.validar', compact('productos'));
    }

// Mostrar el formulario de edición de un producto específico
    public function editarProducto($sku)
    {
        // Consulta principal usando conexión SQL Server
        $producto = DB::connection('sqlsrv')->table('MAEPR')
            ->join('TABMR', 'MAEPR.MRPR', '=', 'TABMR.KOMR') // INNER JOIN con TABMR para la marca
            ->select(
                'MAEPR.KOPR as sku',               // SKU
                'MAEPR.KOPRRA as KOPRRA',          //KOPRRA
                'MAEPR.NOKOPR as nombre',          // Nombre del producto
                'MAEPR.UD01PR as unidad1',         // Unidad 1
                'MAEPR.UD02PR as unidad2',         // Unidad 2
                'MAEPR.DIVISIBLE as divisible_ud1',// Divisible UD1
                'MAEPR.DIVISIBLE2 as divisible_ud2',// Divisible UD2
                'MAEPR.RGPR as regimen',           // Régimen
                'TABMR.NOKOMR as marca',            // Marca (desde TABMR)
                'MAEPR.RLUD as RLUD'
                
            )
            ->where('MAEPR.KOPR', $sku)
            ->first(); // Retorna solo un producto
    
        // Validar si el producto no existe
        if (!$producto) {
            session()->flash('showModal', true);
            return redirect()->route('productos.publicados');
        }

        // Consulta de inventario por bodega
        $bodegas = DB::connection('sqlsrv')->table('MAEST')
        ->join('TABBO', 'MAEST.KOBO', '=', 'TABBO.KOBO') // JOIN con TABBO
        ->select(
            'MAEST.KOBO as bodega_id',
            'TABBO.NOKOBO as nombre_bodega', // Nombre de la bodega
            'MAEST.STFI1 as stock_ud1',      // Stock UD1
            'MAEST.STFI2 as stock_ud2'       // Stock UD2
        )
        ->where('MAEST.KOPR', $sku)
        ->get();

        


        // Consulta de listas de precios asociadas al producto
        $listas = DB::connection('sqlsrv')->table('TABPRE')
        ->join('TABPP', 'TABPRE.KOLT', '=', 'TABPP.KOLT') // JOIN para obtener el nombre de la lista
        ->leftJoin('PDIMEN', 'TABPRE.KOPR', '=', 'PDIMEN.CODIGO') // JOIN con PDIMEN para obtener flete
        ->select(
            'TABPRE.KOLT as lista',           // Lista
            'TABPP.NOKOLT as nombre_lista',   // Nombre de la lista
            'TABPRE.PP01UD as precio_ud1',    // Precio UD1
            'TABPRE.MG01UD as margen_ud1',    // Margen UD1
            'TABPRE.DTMA01UD as descuento_ud1', // Descuento Máx UD1
            'TABPRE.PP02UD as precio_ud2',    // Precio UD2
            'TABPRE.MG02UD as margen_ud2',    // Margen UD2
            'TABPRE.DTMA02UD as descuento_ud2', // Descuento Máx UD2
            DB::raw("
                CASE
                    WHEN TABPRE.KOLT = '13P' THEN PDIMEN.ZFLETE1
                    WHEN TABPRE.KOLT = '14P' THEN PDIMEN.ZFLETE2
                    WHEN TABPRE.KOLT = '15P' THEN PDIMEN.ZFLETE3
                    ELSE NULL
                END as flete
            ") // Lógica para determinar el campo flete
        )
        ->where('TABPRE.KOPR', $sku) // Filtrar por KOPR (producto actual)
        ->where('TABPRE.KOLT', '<>', '01C') // Excluir lista '01C'
        ->orderByRaw("
            CASE
                WHEN TABPRE.KOLT LIKE '%C' THEN 1
                WHEN TABPRE.KOLT LIKE '%P' THEN 2
                ELSE 3
            END,
            TABPRE.KOLT ASC
            ")
        ->get();


        $listas_costo = DB::connection('sqlsrv')->table('TABPRE')
        ->join('MAEPREM', 'TABPRE.KOPR', '=', 'MAEPREM.KOPR')
        ->select(
            'MAEPREM.PM as PM',
            'MAEPREM.PPUL01 as PPUL01',
            'MAEPREM.FEUL as FEUL',
            'TABPRE.KOLT as lista',           // Lista
            'TABPRE.PP01UD as precio_ud1'    // Precio UD1
        )
        ->where('TABPRE.KOPR', $sku) // Filtrar por KOPR (producto actual)
        ->where('TABPRE.KOLT', '01C') // Excluir lista '01C'
        ->get();

        // Obtener todas las listas
        $todasLasListas = DB::connection('sqlsrv')->table('TABPP')
            ->select('KOLT', 'NOKOLT')
            ->get();

        // Obtener las listas asociadas al producto
        $listasAsociadas = DB::connection('sqlsrv')->table('TABPRE')
            ->where('KOPR', $sku)
            ->pluck('KOLT')
            ->toArray();

        // Filtrar las listas no asociadas
        $listasDisponibles = $todasLasListas->filter(function ($lista) use ($listasAsociadas) {
            return !in_array($lista->KOLT, $listasAsociadas);
        });

        // Obtener bodegas disponibles (las que no tienen asociado el producto)
        $bodegasDisponibles = DB::connection('sqlsrv')->table('TABBO')
        ->leftJoin('MAEST', function ($join) use ($sku) {
            $join->on('TABBO.KOBO', '=', 'MAEST.KOBO')
                ->where('MAEST.KOPR', $sku);
        })
        ->select('TABBO.KOBO as bodega_id', 'TABBO.NOKOBO as nombre_bodega')
        ->whereNull('MAEST.KOPR') // Filtrar bodegas no asociadas
        ->get();
    
        // Retornar vista con el producto
        return view('productos.editar', compact('producto' , 'bodegas', 'listas', 'listas_costo', 'bodegasDisponibles', 'listasDisponibles'));
    }

    public function agregarBodega(Request $request)
{
    $validated = $request->validate([
        'bodega_id' => 'required',
        'stock_ud1' => 'required|numeric',
        'stock_ud2' => 'required|numeric',
    ]);

    $sku = $request->input('sku'); // Asegúrate de enviar este dato desde el modal (hidden input si es necesario)

    DB::connection('sqlsrv')->table('MAEST')->insert([
        'EMPRESA' => '01', // Valor fijo
        'KOSU' => '001', // Valor fijo
        'KOBO' => $validated['bodega_id'], // ID de la bodega seleccionada
        'KOPR' => $sku, // SKU del producto
        'STFI1' => $validated['stock_ud1'], // Stock UD1
        'STFI2' => $validated['stock_ud2'], // Stock UD2
        'STDV1' => 0,
        'STDV2' => 0,
        'STOCNV1' => 0,
        'STOCNV2' => 0,
        'STDV1C' => 0,
        'STOCNV1C' => 0,
        'STDV2C' => 0,
        'STOCNV2C' => 0,
        'DESPNOFAC1' => 0,
        'DESPNOFAC2' => 0,
        'RECENOFAC1' => 0,
        'RECENOFAC2' => 0,
        'STTR1' => 0,
        'STTR2' => 0,
        'PRESALCLI1' => 0,
        'PRESALCLI2' => 0,
        'PRESDEPRO1' => 0,
        'PRESDEPRO2' => 0,
        'CONSALCLI1' => 0,
        'CONSALCLI2' => 0,
        'CONSDEPRO1' => 0,
        'CONSDEPRO2' => 0,
        'DEVENGNCV1' => 0,
        'DEVENGNCV2' => 0,
        'DEVENGNCC1' => 0,
        'DEVENGNCC2' => 0,
        'DEVSINNCV1' => 0,
        'DEVSINNCV2' => 0,
        'DEVSINNCC1' => 0,
        'DEVSINNCC2' => 0,
        'STENFAB1' => 0,
        'STENFAB2' => 0,
        'STREQFAB1' => 0,
        'STREQFAB2' => 0,
    ]);

    return redirect()->back()->with('success', 'Bodega agregada correctamente.');
}


public function eliminarBodega(Request $request)
{
    $bodegaId = $request->input('bodega_id'); // ID de la bodega seleccionada
    $sku = $request->input('sku'); // SKU del producto asociado

    // Eliminar la bodega del producto en la tabla MAEST
    DB::connection('sqlsrv')->table('MAEST')
        ->where('KOBO', $bodegaId)
        ->where('KOPR', $sku)
        ->delete();

    return redirect()->back()->with('success', 'Bodega eliminada correctamente.');
}

public function obtenerBodegasDisponibles($sku)
{
    // Bodegas no asociadas al SKU actual
    $bodegasDisponibles = DB::connection('sqlsrv')->table('TABBO')
        ->leftJoin('MAEST', function ($join) use ($sku) {
            $join->on('TABBO.KOBO', '=', 'MAEST.KOBO')
                 ->where('MAEST.KOPR', $sku);
        })
        ->select('TABBO.KOBO as bodega_id', 'TABBO.NOKOBO as nombre_bodega')
        ->whereNull('MAEST.KOPR') // Filtrar bodegas sin el SKU actual
        ->get();

    return $bodegasDisponibles;
}

public function mostrarModal($sku)
{
    $producto = DB::connection('sqlsrv')->table('MAEPR')->where('KOPR', $sku)->first();

    $bodegasDisponibles = $this->obtenerBodegasDisponibles($sku);

    return view('productos.modal_bodegas', compact('producto', 'bodegasDisponibles'));
}


public function agregarLista(Request $request)
{
    $validated = $request->validate([
        'lista' => 'required',             // Lista seleccionada
        'sku' => 'required',               // SKU del producto
        'koprra' => 'required',            // KOPRRA del producto
        'mg01ud' => 'required|numeric',    // Margen UD1
        'mg02ud' => 'required|numeric',    // Margen UD2
        'dtma01ud' => 'required|numeric',  // Descuento Máx UD1
        'dtma02ud' => 'required|numeric',  // Descuento Máx UD2
    ]);

    // Costo base (PP01UD de 01C)
    $costoBase = DB::connection('sqlsrv')->table('TABPRE')
        ->where('KOLT', '01C')
        ->where('KOPR', $request->sku)
        ->value('PP01UD');

    if (!$costoBase) {
        return redirect()->back()->withErrors(['error' => 'El costo base (PP01UD de 01C) no se encuentra configurado para este producto.']);
    }

    // RLUD del producto
    $rlud = DB::connection('sqlsrv')->table('MAEPR')
        ->where('KOPR', $request->sku)
        ->value('RLUD') ?? 1;

    // Calcular PP01UD y PP02UD
    $pp01ud = $costoBase * (1 + $validated['mg01ud'] / 100);
    $pp02ud = ($costoBase * (1 + $validated['mg02ud'] / 100)) * $rlud;

    // Generar las ecuaciones dinámicas
    if (in_array($validated['lista'], ['13P', '14P', '15P'])) {
        $ecuacion = '(<01c>pp01ud*(1+mg01ud/100)+(zflete3*(1/rlud)))#3';
        $ecuacionU2 = '(((<01c>pp01ud*(1+mg02ud/100))*rlud)+zflete3)#3';
    } else {
        $ecuacion = '(<01c>pp01ud*(1+mg01ud/100))#3';
        $ecuacionU2 = '((<01c>pp01ud*(1+mg02ud/100))*rlud)#3';
    }

    // Insertar la nueva lista en TABPRE
    DB::connection('sqlsrv')->table('TABPRE')->insert([
        'KOLT' => $validated['lista'],       // Lista seleccionada
        'KOPR' => $request->sku,             // SKU del producto
        'KOPRRA' => $request->koprra,        // KOPRRA del producto
        'KOPRTE' => $request->sku,           // SKU técnico
        'FEVI' => null,                      // Fecha de vigencia (puede ser NULL)
        'ECUACION' => $ecuacion,             // Ecuación UD1
        'RLUD' => $rlud,                     // RLUD del producto
        'PP01UD' => $pp01ud,                 // Precio Venta UD1
        'MG01UD' => $validated['mg01ud'],    // Margen UD1
        'DTMA01UD' => $validated['dtma01ud'], // Descuento Máx UD1
        'PP02UD' => $pp02ud,                 // Precio Venta UD2
        'MG02UD' => $validated['mg02ud'],    // Margen UD2
        'DTMA02UD' => $validated['dtma02ud'], // Descuento Máx UD2
        'PPUL01' => 0.0,                     // Precio unitario libre UD1
        'PPUL02' => 0.0,                     // Precio unitario libre UD2
        'PM01' => 0.0,                       // Precio margen UD1
        'PM02' => 0.0,                       // Precio margen UD2
        'C_INSUMOS' => 0.0,                  // Costo de insumos
        'C_MAQUINAS' => 0.0,                 // Costo de máquinas
        'C_M_OBRA' => 0.0,                   // Costo de mano de obra
        'C_FABRIC' => 0.0,                   // Costo de fabricación
        'C_COMPRA' => 0.0,                   // Costo de compra
        'C_LIBRE' => 0.0,                    // Costo libre
        'ECUACIONU2' => $ecuacionU2,         // Ecuación UD2
        'EMG01UD' => null,                   // Margen estimado UD1
        'EMG02UD' => null,                   // Margen estimado UD2
        'EDTMA01UD' => null,                 // Descuento estimado UD1
        'EDTMA02UD' => null,                 // Descuento estimado UD2
        'DSCTONRO1' => 0.0,                  // Descuento no relacionado 1
        'FORM_029' => null,                  // Fórmula asociada
        'DSCTONRO2' => 0.0,                  // Descuento no relacionado 2
        'FORM_031' => null,                  // Fórmula asociada
        'DSCTONRO3' => 0.0,                  // Descuento no relacionado 3
        'FORM_033' => null,                  // Fórmula asociada
        'FLETE1' => 0.0,                     // Flete asociado
        'FORM_035' => null,                  // Fórmula asociada
    ]);

    return redirect()->back()->with('success', 'Lista de precios agregada correctamente.');
}



public function obtenerListasDisponibles($sku)
{
    // Obtener todas las listas de precios
    $todasLasListas = DB::connection('sqlsrv')->table('TABPP')
        ->select('KOLT', 'NOKOLT')
        ->get();

    // Obtener las listas ya asociadas al producto (SKU)
    $listasAsociadas = DB::connection('sqlsrv')->table('TABPRE')
        ->where('KOPR', $sku)
        ->pluck('KOLT')
        ->toArray();

    // Filtrar las listas que no están asociadas al producto
    $listasDisponibles = $todasLasListas->filter(function ($lista) use ($listasAsociadas) {
        return !in_array($lista->KOLT, $listasAsociadas);
    });

    return $listasDisponibles;
}

public function editar($sku)
{
    // Obtener las listas disponibles para el modal
    $listasDisponibles = $this->obtenerListasDisponibles($sku);

    // Pasar otros datos necesarios a la vista (por ejemplo, el producto)
    $producto = DB::connection('sqlsrv')->table('MAEPR')->where('KOPR', $sku)->first();

    return view('productos.editar', compact('producto', 'listasDisponibles'));
}

public function eliminarLista(Request $request)
{
    $listaId = $request->input('lista_id'); // ID de la lista seleccionada
    $sku = $request->input('sku'); // SKU del producto asociado

    if (!$listaId || !$sku) {
        return redirect()->back()->withErrors(['error' => 'Faltan datos para eliminar la lista.']);
    }

    try {
        // Eliminar la lista de precios del producto en la tabla TABPRE
        DB::connection('sqlsrv')->table('TABPRE')
            ->where('KOLT', $listaId)
            ->where('KOPR', $sku)
            ->delete();

        return redirect()->back()->with('success', 'Lista de precios eliminada correctamente.');
    } catch (\Exception $e) {
        return redirect()->back()->withErrors(['error' => 'Error al eliminar la lista de precios: ' . $e->getMessage()]);
    }
}


    

// Actualizar los datos del producto en la base de datos
public function actualizarProducto(Request $request, $sku)
{
    // Validar los datos generales del producto
    $request->validate([
        'costo_*' => 'nullable|numeric',
        'pmp' => 'nullable|numeric',
        'ultima_compra' => 'nullable|numeric',
        'fecha_ultima_compra' => 'nullable|date',
        'margen_ud1_*' => 'nullable|numeric',
        'descuento_ud1_*' => 'nullable|numeric',
        'margen_ud2_*' => 'nullable|numeric',
        'descuento_ud2_*' => 'nullable|numeric',
    ]);

    // Actualizar los costos en MAEPREM
    DB::connection('sqlsrv')->table('MAEPREM')
        ->where('KOPR', $sku)
        ->update([
            'PM' => $request->input('pmp', 0), // Precio medio ponderado
            'PPUL01' => $request->input('ultima_compra', 0), // Precio última compra
            'FEUL' => $request->input('fecha_ultima_compra', null), // Fecha última compra
        ]);

    // Procesar y actualizar las listas de precios en TABPRE
    $listas = DB::connection('sqlsrv')->table('TABPRE')
        ->where('KOPR', $sku)
        ->get();

    foreach ($listas as $lista) {
        $listaId = $lista->KOLT;

        // Actualizar los valores dinámicos
        DB::connection('sqlsrv')->table('TABPRE')
            ->where('KOPR', $sku)
            ->where('KOLT', $listaId)
            ->update([
                'MG01UD' => $request->input("margen_ud1_$listaId", $lista->MG01UD),
                'DTMA01UD' => $request->input("descuento_ud1_$listaId", $lista->DTMA01UD),
                'MG02UD' => $request->input("margen_ud2_$listaId", $lista->MG02UD),
                'DTMA02UD' => $request->input("descuento_ud2_$listaId", $lista->DTMA02UD),
                // Recalcular PP01UD y PP02UD
                'PP01UD' => $request->input("costo_01C", 0) * (1 + ($request->input("margen_ud1_$listaId", $lista->MG01UD) / 100)),
                'PP02UD' => $request->input("costo_01C", 0) * (1 + ($request->input("margen_ud2_$listaId", $lista->MG02UD) / 100)) * ($lista->RLUD ?? 1),
            ]);
    }

    return redirect()->route('productos.validar')->with('success', 'Producto y listas de precios actualizados correctamente.');
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
