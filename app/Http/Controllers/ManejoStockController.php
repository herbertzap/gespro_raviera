<?php

namespace App\Http\Controllers;

use App\Models\Bodega;
use App\Models\CodigoBarraLog;
use App\Models\Temporal;
use App\Models\Ubicacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ManejoStockController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:Manejo Stock|Super Admin']);
    }

    public function seleccionar()
    {
        $bodegas = Bodega::with(['ubicaciones' => function ($query) {
            $query->orderBy('codigo');
        }])->orderBy('nombre_bodega')->get();

        return view('manejo-stock.select', [
            'bodegas' => $bodegas,
        ]);
    }

    public function contabilidad(Request $request)
    {
        $data = $request->validate([
            'bodega_id' => ['required', 'exists:bodegas,id'],
            'ubicacion_id' => ['nullable', 'exists:ubicaciones,id'],
        ]);

        $bodega = Bodega::with('ubicaciones')->findOrFail($data['bodega_id']);

        $ubicacion = null;
        if (!empty($data['ubicacion_id'])) {
            // Buscar la ubicación directamente para asegurar que existe
            $ubicacion = Ubicacion::where('id', (int) $data['ubicacion_id'])
                ->where('bodega_id', $bodega->id)
                ->first();
        }

        return view('manejo-stock.contabilidad', [
            'bodega' => $bodega,
            'ubicacion' => $ubicacion,
        ]);
    }

    public function buscarUbicaciones(Request $request)
    {
        $data = $request->validate([
            'bodega_id' => ['required', 'exists:bodegas,id'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $query = Ubicacion::where('bodega_id', $data['bodega_id'])
            ->orderBy('codigo');

        if (!empty($data['q'])) {
            $search = trim($data['q']);
            $query->where(function ($q) use ($search) {
                $q->where('codigo', 'like', "%{$search}%")
                    ->orWhere('descripcion', 'like', "%{$search}%");
            });
        }

        $ubicaciones = $query->limit(50)->get();

        return response()->json(
            $ubicaciones->map(function ($ubicacion) {
                return [
                    'id' => $ubicacion->id,
                    'codigo' => $ubicacion->codigo,
                    'descripcion' => $ubicacion->descripcion,
                ];
            })
        );
    }

    public function guardarCaptura(Request $request)
    {
        $data = $request->validate([
            'bodega_id' => ['required', 'exists:bodegas,id'],
            'ubicacion_id' => ['required', 'exists:ubicaciones,id'],
            'sku' => ['required', 'string', 'max:50'],
            'nombre_producto' => ['required', 'string', 'max:200'],
            'rlud' => ['required', 'numeric', 'min:0'],
            'unidad_medida_1' => ['nullable', 'string', 'max:10'],
            'unidad_medida_2' => ['nullable', 'string', 'max:10'],
            'captura_1' => ['required', 'numeric', 'min:0'],
            'captura_2' => ['nullable', 'numeric'],
            'stfi1' => ['nullable', 'numeric'],
            'stfi2' => ['nullable', 'numeric'],
            'funcionario' => ['nullable', 'string', 'max:20'],
        ]);

        try {
            $bodega = Bodega::findOrFail($data['bodega_id']);
            $ubicacion = Ubicacion::findOrFail($data['ubicacion_id']);

            // Determinar TIDO basado en STFI1 (antes de convertir a absoluto)
            $tido = null;
            $stfi1Value = $data['stfi1'] ?? null;
            if ($stfi1Value !== null) {
                $tido = $stfi1Value < 0 ? 'GDI' : 'GRI';
            }

            // Guardar valores absolutos (sin signo negativo) ya que TIDO indica el tipo
            $stfi1Absoluto = $stfi1Value !== null ? abs($stfi1Value) : null;
            $stfi2Absoluto = isset($data['stfi2']) && $data['stfi2'] !== null ? abs($data['stfi2']) : null;

            $temporal = Temporal::create([
                'bodega_id' => $bodega->id,
                'ubicacion_id' => $ubicacion->id,
                'codigo_ubicacion' => $ubicacion->codigo,
                'empresa' => $bodega->empresa,
                'kosu' => $bodega->kosu,
                'kobo' => $bodega->kobo,
                'centro_costo' => $bodega->centro_costo,
                'sku' => trim($data['sku']),
                'nombre_producto' => trim($data['nombre_producto']),
                'rlud' => $data['rlud'],
                'unidad_medida_1' => $data['unidad_medida_1'] ?? null,
                'unidad_medida_2' => $data['unidad_medida_2'] ?? null,
                'captura_1' => $data['captura_1'],
                'captura_2' => $data['captura_2'] ?? null,
                'stfi1' => $stfi1Absoluto,
                'stfi2' => $stfi2Absoluto,
                'funcionario' => $data['funcionario'] ?? null,
                'tido' => $tido,
            ]);

            // Generar logs de previsualización para MAEEDO y MAEDDO
            /*
            if ($tido) {
                try {
                    $previewMAEEDO = $this->previsualizarMAEEDO($temporal, $bodega, $tido);
                    if ($previewMAEEDO && $previewMAEEDO['datos']) {
                        $previewMAEDDO = $this->previsualizarMAEDDO($temporal, $bodega, $tido, $previewMAEEDO['datos']['IDMAEEDO'], $previewMAEEDO['datos']['NUDO']);

                        Log::info("═══════════════════════════════════════════════════════════════════════════");
                        Log::info("📝 PREVISUALIZACIÓN INSERT MAEEDO - Temporal ID: {$temporal->id}");
                        Log::info("═══════════════════════════════════════════════════════════════════════════");
                        Log::info("Datos a insertar en MAEEDO:");
                        foreach ($previewMAEEDO['datos'] as $campo => $valor) {
                            Log::info("  {$campo}: {$valor}");
                        }
                        Log::info("SQL que se ejecutará:");
                        Log::info($previewMAEEDO['sql_preview']);
                        Log::info("═══════════════════════════════════════════════════════════════════════════");

                        if ($previewMAEDDO && $previewMAEDDO['datos']) {
                            Log::info("═══════════════════════════════════════════════════════════════════════════");
                            Log::info("📝 PREVISUALIZACIÓN INSERT MAEDDO - Temporal ID: {$temporal->id}");
                            Log::info("═══════════════════════════════════════════════════════════════════════════");
                            Log::info("Datos a insertar en MAEDDO:");
                            foreach ($previewMAEDDO['datos'] as $campo => $valor) {
                                Log::info("  {$campo}: {$valor}");
                            }
                            Log::info("SQL que se ejecutará:");
                            Log::info($previewMAEDDO['sql_preview']);
                            Log::info("═══════════════════════════════════════════════════════════════════════════");
                        }
                    }
                } catch (\Throwable $e) {
                    Log::error('Error generando logs de previsualización de MAEEDO/MAEDDO', [
                        'temporal_id' => $temporal->id,
                        'exception' => $e,
                    ]);
                }
            }
            */

            if ($tido) {
                // PRIMERO insertar MAEDDO, luego calcular totales y insertar MAEEDO
                $insertResultado = $this->insertarMAEDDO($temporal, $bodega, $tido);
                
                if (!$insertResultado || empty($insertResultado['idmaeedo']) || empty($insertResultado['nudo'])) {
                    throw new \Exception('Error al insertar en MAEDDO: No se pudo obtener IDMAEEDO o NUDO.');
                }
                
                // Ahora insertar MAEEDO con los totales calculados desde MAEDDO
                $this->insertarMAEEDO($temporal, $bodega, $tido, $insertResultado['idmaeedo'], $insertResultado['nudo']);
                
                // DESPUÉS de los inserts, actualizar MAEST, MAEPR y MAEPREM
                $this->actualizarStock($temporal, $bodega, $tido);
            }

            // Si la petición es AJAX, devolver JSON
            if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Captura guardada correctamente.',
                'data' => [
                    'producto' => $temporal->nombre_producto,
                    'sku' => $temporal->sku,
                    'cantidad' => $temporal->captura_1,
                    'tido' => $temporal->tido,
                ],
            ]);
            }

            return redirect()->route('manejo-stock.contabilidad', [
                'bodega_id' => $bodega->id,
                'ubicacion_id' => $ubicacion->id,
            ])->with('success', 'Captura guardada correctamente.');
        } catch (\Throwable $e) {
            Log::error('Error guardando captura de stock', [
                'data' => $data,
                'exception' => $e,
            ]);

            // Si la petición es AJAX, devolver JSON con error
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al guardar la captura: ' . $e->getMessage(),
                    'error_details' => config('app.debug') ? $e->getTraceAsString() : null,
                ], 500);
            }

            return redirect()->route('manejo-stock.contabilidad', [
                'bodega_id' => $data['bodega_id'],
                'ubicacion_id' => $data['ubicacion_id'],
            ])->with('error', 'Error al guardar la captura. Intente nuevamente.')->withInput();
        }
    }

    /**
     * Confirmar e insertar en MAEEDO después de la previsualización
     */
    public function confirmarInsertMAEEDO(Request $request)
    {
        $data = $request->validate([
            'temporal_id' => ['required', 'exists:temporales,id'],
        ]);

        try {
            $temporal = Temporal::with('bodega')->findOrFail($data['temporal_id']);
            $bodega = $temporal->bodega;

            if (!$temporal->tido) {
                return response()->json([
                    'success' => false,
                    'message' => 'El registro temporal no tiene TIDO definido.',
                ], 400);
            }

            // Ejecutar el insert: PRIMERO MAEDDO, luego MAEEDO con totales calculados
            $insertResultado = $this->insertarMAEDDO($temporal, $bodega, $temporal->tido);
            
            if (!$insertResultado || empty($insertResultado['idmaeedo']) || empty($insertResultado['nudo'])) {
                throw new \Exception('Error al insertar en MAEDDO: No se pudo obtener IDMAEEDO o NUDO.');
            }
            
            // Ahora insertar MAEEDO con los totales calculados desde MAEDDO
            $this->insertarMAEEDO($temporal, $bodega, $temporal->tido, $insertResultado['idmaeedo'], $insertResultado['nudo']);
            
            // DESPUÉS de los inserts, actualizar MAEST, MAEPR y MAEPREM
            $this->actualizarStock($temporal, $bodega, $temporal->tido);

            return response()->json([
                'success' => true,
                'message' => 'Insert en MAEEDO y MAEDDO realizado correctamente.',
                'data' => [
                    'producto' => $temporal->nombre_producto,
                    'sku' => $temporal->sku,
                    'cantidad' => $temporal->captura_1,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Error confirmando insert en MAEEDO', [
                'temporal_id' => $data['temporal_id'],
                'exception' => $e,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al insertar en MAEEDO: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function buscarCodigoBarras(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:60'],
            'bodega_id' => ['nullable', 'exists:bodegas,id'],
        ]);

        $barcode = trim($validated['code']);
        $bodega = $this->resolverBodega($validated['bodega_id'] ?? null);

        try {
            $registro = DB::connection('sqlsrv_external')
                ->table('TABCODAL')
                ->select('KOPRAL', 'KOPR', 'NOKOPRAL')
                ->where('KOPRAL', $barcode)
                ->first();

            if (!$registro) {
                return response()->json([
                    'found' => false,
                    'barcode' => $barcode,
                    'message' => 'Código de barras no asociado a un producto.',
                ]);
            }

            $detalle = $this->obtenerDetalleProducto($registro->KOPR, $bodega);

            if (!$detalle) {
                return response()->json([
                    'found' => false,
                    'barcode' => $barcode,
                    'message' => 'Producto no encontrado en MAEPR.',
                ], 404);
            }

            // Incluir el código de barras en el objeto producto
            $detalle['barcode'] = $barcode;
            $detalle['barcode_actual'] = $barcode;

            return response()->json([
                'found' => true,
                'barcode' => $barcode,
                'producto' => $detalle,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error buscando código de barras en SQL Server', [
                'barcode' => $barcode,
                'exception' => $e,
            ]);

            return response()->json([
                'found' => false,
                'barcode' => $barcode,
                'message' => 'Error consultando SQL Server.',
            ], 500);
        }
    }

    public function producto(Request $request)
    {
        $data = $request->validate([
            'sku' => ['required', 'string', 'max:50'],
            'bodega_id' => ['nullable', 'exists:bodegas,id'],
        ]);

        $bodega = $this->resolverBodega($data['bodega_id'] ?? null);

        $detalle = $this->obtenerDetalleProducto($data['sku'], $bodega);

        if (!$detalle) {
            return response()->json([
                'success' => false,
                'message' => 'Producto no encontrado en SQL Server.',
            ], 404);
        }

        $barcodes = DB::connection('sqlsrv_external')
            ->table('TABCODAL')
            ->where('KOPR', trim($data['sku']))
            ->orderBy('KOPRAL')
            ->pluck('KOPRAL')
            ->map(fn ($codigo) => trim($codigo))
            ->filter()
            ->values();

        $detalle['barcode_actual'] = $barcodes->first();
        $detalle['barcodes'] = $barcodes;

        return response()->json([
            'success' => true,
            'producto' => $detalle,
        ]);
    }

    private function resolverBodega($bodegaId): ?Bodega
    {
        if (!$bodegaId) {
            return null;
        }

        return Bodega::find($bodegaId);
    }

    private function obtenerDetalleProducto(string $sku, ?Bodega $bodega = null): ?array
    {
        $producto = DB::connection('sqlsrv_external')
            ->table('MAEPR')
            ->select('KOPR', 'NOKOPR', 'RLUD', 'UD01PR', 'UD02PR')
            ->where('KOPR', trim($sku))
            ->first();

        if (!$producto) {
            return null;
        }

        $stockQuery = DB::connection('sqlsrv_external')
            ->table('MAEST')
            ->selectRaw('SUM(ISNULL(STFI1,0)) as stock_fisico, SUM(ISNULL(STOCNV1,0)) as stock_comprometido')
            ->where('KOPR', trim($sku));

        if ($bodega && $bodega->kobo) {
            $stockQuery->where('KOBO', $bodega->kobo);
        }

        $stockData = $stockQuery->first();
        $stockFisico = (float) ($stockData->stock_fisico ?? 0);
        $stockComprometido = (float) ($stockData->stock_comprometido ?? 0);
        $stockDisponible = $stockFisico - $stockComprometido;

        $funcionario = null;
        if (auth()->user() && auth()->user()->codigo_vendedor) {
            $funcionario = auth()->user()->codigo_vendedor;
        }

        return [
            'codigo' => trim($producto->KOPR),
            'nombre' => trim($producto->NOKOPR),
            'rlud' => (float) ($producto->RLUD ?? 1),
            'unidad_1' => trim($producto->UD01PR ?? ''),
            'unidad_2' => trim($producto->UD02PR ?? ''),
            'stock_fisico' => $stockFisico,
            'stock_comprometido' => $stockComprometido,
            'stock_disponible' => $stockDisponible,
            'funcionario' => $funcionario,
        ];
    }

    /**
     * Previsualizar datos para insert en MAEEDO (sin ejecutar el insert)
     */
    private function previsualizarMAEEDO($temporal, $bodega, $tido)
    {
        try {
            $connection = $this->sqlServerConnection();
            $resultId = $connection->selectOne("SELECT TOP 1 ISNULL(MAX(IDMAEEDO), 0) + 1 AS siguiente_id FROM MAEEDO WHERE EMPRESA = ?", [$bodega->empresa]);
            $siguienteId = (int) ($resultId->siguiente_id ?? 1);
            if ($siguienteId <= 0) {
                $siguienteId = 1;
            }

            $resultNudo = $connection->selectOne("SELECT TOP 1 CAST(NUDO AS INT) AS nudo_int FROM MAEEDO WHERE TIDO = ? AND ISNUMERIC(NUDO) = 1 ORDER BY CAST(NUDO AS INT) DESC", [$tido]);
            $siguienteNudo = $resultNudo ? ((int) $resultNudo->nudo_int + 1) : 1;
            
            $nudoFormateado = str_pad($siguienteNudo, 10, '0', STR_PAD_LEFT);
            
            // Valores según la tabla proporcionada
            // Usar valores del temporal si están disponibles, sino de la bodega
            $empresa = $temporal->empresa ?? $bodega->empresa ?? '02';
            $endo = '76427949-2';
            $funcionario = $temporal->funcionario ?? '';
            $kosu = $temporal->kosu ?? $bodega->kosu ?? 'CMM';
            $suendo = $temporal->kosu ?? $bodega->kosu ?? 'CMM';
            // SUDO viene del temporal (centro_costo), si no está, usar el de la bodega
            $sudo = $temporal->centro_costo ?? $bodega->centro_costo ?? '001';
            $lisactiva = 'TABPP01C';
            $horagrab = time();
            
            // Preparar datos para mostrar
            $datos = [
                'IDMAEEDO' => $siguienteId,
                'EMPRESA' => $empresa,
                'TIDO' => $tido,
                'NUDO' => $nudoFormateado,
                'ENDO' => $endo,
                'SUENDO' => $suendo,
                'ENDOFI' => '',
                'TIGEDO' => 'I',
                'SUDO' => $sudo,
                'LUVTDO' => '',
                'FEEMDO' => 'GETDATE()',
                'KOFUDO' => $funcionario,
                'ESDO' => 'C',
                'ESPGDO' => 'S',
                'CAPRCO' => 0,
                'CAPRAD' => 0,
                'CAPREX' => 0,
                'CAPRNC' => 0,
                'MEARDO' => 'N',
                    'MODO' => '$',
                    'TIMODO' => 'N',
                    'TAMODO' => $tamodo,
                'NUCTAP' => 0,
                'VACTDTNEDO' => 0,
                'VACTDTBRDO' => 0,
                'NUIVDO' => 0,
                'POIVDO' => 0,
                'VAIVDO' => 0,
                'NUIMDO' => 0,
                'VAIMDO' => 0,
                'VANEDO' => 0,
                'VABRDO' => 0,
                'POPIDO' => 0,
                'VAPIDO' => 0,
                'FE01VEDO' => 'GETDATE()',
                'FEULVEDO' => 'GETDATE()',
                'NUVEDO' => 1,
                'VAABDO' => 0,
                'MARCA' => 'I',
                'FEER' => 'GETDATE()',
                'NUTRANSMI' => '',
                'NUCOCO' => '',
                'KOTU' => '1',
                'LIBRO' => '',
                'LCLV' => 'NULL',
                'ESFADO' => '',
                'KOTRPCVH' => '',
                'NULICO' => '',
                'PERIODO' => '',
                'NUDONODEFI' => 0,
                'TRANSMASI' => '',
                'POIVARET' => 0,
                'VAIVARET' => 0,
                'RESUMEN' => '',
                'LAHORA' => 'GETDATE()',
                'KOFUAUDO' => '',
                'KOOPDO' => '',
                'ESPRODDO' => '',
                'DESPACHO' => 1,
                'HORAGRAB' => $horagrab,
                'RUTCONTACT' => '',
                'SUBTIDO' => 'AJU',
                'TIDOELEC' => 0,
                'ESDOIMP' => 'I',
                'CUOGASDIF' => 0,
                'BODESTI' => '',
                'PROYECTO' => '',
                'FECHATRIB' => 'NULL',
                'NUMOPERVEN' => 0,
                'BLOQUEAPAG' => '',
                'VALORRET' => 0,
                'FLIQUIFCV' => 'GETDATE()',
                'VADEIVDO' => 0,
                'KOCANAL' => '',
                'KOCRYPT' => '',
                'LEYZONA' => '',
                'KOSIFIC' => '',
                'LISACTIVA' => $lisactiva,
                'KOFUAUTO' => '',
                'SUENDOFI' => '',
                'VAIVDOZF' => 0,
                'ENDOMANDA' => '',
                'FLUVTCALZA' => '',
                'ARCHIXML' => '',
                'IDXML' => 0,
                'SERIENUDO' => '',
                'VALORAJU' => 0,
                'PODETRAC' => 0,
                'DETRACCION' => 0,
                'TIPOOPCOM' => '',
                'CREFIYAF' => '',
                'NRODETRAC' => '',
                'IDPDAENCA' => 0,
                'TIDEVE' => '',
                'TIDEVEFE' => 'NULL',
                'TIDEVEHO' => '',
            ];
            
            return [
                'temporal_id' => $temporal->id,
                'datos' => $datos,
                'sql_preview' => $this->generarSQLPreview($datos),
            ];
            
        } catch (\Throwable $e) {
            Log::error('Error en previsualizarMAEEDO', [
                'exception' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Generar vista previa del SQL que se ejecutará
     */
    private function generarSQLPreview($datos)
    {
        $sql = "SET IDENTITY_INSERT MAEEDO ON\n\n";
        $sql .= "INSERT INTO MAEEDO (\n";
        $sql .= "    IDMAEEDO, EMPRESA, TIDO, NUDO, ENDO, SUENDO, ENDOFI, TIGEDO, SUDO, LUVTDO,\n";
        $sql .= "    FEEMDO, KOFUDO, ESDO, ESPGDO, CAPRCO, CAPRAD, CAPREX, CAPRNC,\n";
        $sql .= "    MEARDO, MODO, TIMODO, TAMODO, NUCTAP, VACTDTNEDO, VACTDTBRDO,\n";
        $sql .= "    NUIVDO, POIVDO, VAIVDO, NUIMDO, VAIMDO, VANEDO, VABRDO, POPIDO, VAPIDO,\n";
        $sql .= "    FE01VEDO, FEULVEDO, NUVEDO, VAABDO, MARCA, FEER, NUTRANSMI, NUCOCO,\n";
        $sql .= "    KOTU, LIBRO, LCLV, ESFADO, KOTRPCVH, NULICO, PERIODO, NUDONODEFI,\n";
        $sql .= "    TRANSMASI, POIVARET, VAIVARET, RESUMEN, LAHORA, KOFUAUDO, KOOPDO,\n";
        $sql .= "    ESPRODDO, DESPACHO, HORAGRAB, RUTCONTACT, SUBTIDO, TIDOELEC, ESDOIMP,\n";
        $sql .= "    CUOGASDIF, BODESTI, PROYECTO, FECHATRIB, NUMOPERVEN, BLOQUEAPAG,\n";
        $sql .= "    VALORRET, FLIQUIFCV, VADEIVDO, KOCANAL, KOCRYPT, LEYZONA, KOSIFIC,\n";
        $sql .= "    LISACTIVA, KOFUAUTO, SUENDOFI, VAIVDOZF, ENDOMANDA, FLUVTCALZA,\n";
        $sql .= "    ARCHIXML, IDXML, SERIENUDO, VALORAJU, PODETRAC, DETRACCION,\n";
        $sql .= "    TIPOOPCOM, CREFIYAF, NRODETRAC, IDPDAENCA, TIDEVE, TIDEVEFE, TIDEVEHO\n";
        $sql .= ") VALUES (\n";
        $sql .= "    {$datos['IDMAEEDO']}, '{$datos['EMPRESA']}', '{$datos['TIDO']}', '{$datos['NUDO']}', '{$datos['ENDO']}', '{$datos['SUENDO']}', '{$datos['ENDOFI']}', '{$datos['TIGEDO']}', '{$datos['SUDO']}', '{$datos['LUVTDO']},\n";
        $sql .= "    {$datos['FEEMDO']}, '{$datos['KOFUDO']}', '{$datos['ESDO']}', '{$datos['ESPGDO']}', {$datos['CAPRCO']}, {$datos['CAPRAD']}, {$datos['CAPREX']}, {$datos['CAPRNC']},\n";
        $sql .= "    '{$datos['MEARDO']}', '{$datos['MODO']}', '{$datos['TIMODO']}', {$datos['TAMODO']}, {$datos['NUCTAP']}, {$datos['VACTDTNEDO']}, {$datos['VACTDTBRDO']},\n";
        $sql .= "    {$datos['NUIVDO']}, {$datos['POIVDO']}, {$datos['VAIVDO']}, {$datos['NUIMDO']}, {$datos['VAIMDO']}, {$datos['VANEDO']}, {$datos['VABRDO']}, {$datos['POPIDO']}, {$datos['VAPIDO']},\n";
        $sql .= "    {$datos['FE01VEDO']}, {$datos['FEULVEDO']}, {$datos['NUVEDO']}, {$datos['VAABDO']}, '{$datos['MARCA']}', {$datos['FEER']}, '{$datos['NUTRANSMI']}', '{$datos['NUCOCO']},\n";
        $sql .= "    '{$datos['KOTU']}', '{$datos['LIBRO']}', {$datos['LCLV']}, '{$datos['ESFADO']}', '{$datos['KOTRPCVH']}', '{$datos['NULICO']}', '{$datos['PERIODO']}', {$datos['NUDONODEFI']},\n";
        $sql .= "    '{$datos['TRANSMASI']}', {$datos['POIVARET']}, {$datos['VAIVARET']}, '{$datos['RESUMEN']}', {$datos['LAHORA']}, '{$datos['KOFUAUDO']}', '{$datos['KOOPDO']},\n";
        $sql .= "    '{$datos['ESPRODDO']}', {$datos['DESPACHO']}, {$datos['HORAGRAB']}, '{$datos['RUTCONTACT']}', '{$datos['SUBTIDO']}', {$datos['TIDOELEC']}, '{$datos['ESDOIMP']},\n";
        $sql .= "    {$datos['CUOGASDIF']}, '{$datos['BODESTI']}', '{$datos['PROYECTO']}', {$datos['FECHATRIB']}, {$datos['NUMOPERVEN']}, '{$datos['BLOQUEAPAG']},\n";
        $sql .= "    {$datos['VALORRET']}, {$datos['FLIQUIFCV']}, {$datos['VADEIVDO']}, '{$datos['KOCANAL']}', '{$datos['KOCRYPT']}', '{$datos['LEYZONA']}', '{$datos['KOSIFIC']},\n";
        $sql .= "    '{$datos['LISACTIVA']}', '{$datos['KOFUAUTO']}', '{$datos['SUENDOFI']}', {$datos['VAIVDOZF']}, '{$datos['ENDOMANDA']}', '{$datos['FLUVTCALZA']},\n";
        $sql .= "    '{$datos['ARCHIXML']}', {$datos['IDXML']}, '{$datos['SERIENUDO']}', {$datos['VALORAJU']}, {$datos['PODETRAC']}, {$datos['DETRACCION']},\n";
        $sql .= "    '{$datos['TIPOOPCOM']}', '{$datos['CREFIYAF']}', '{$datos['NRODETRAC']}', {$datos['IDPDAENCA']}, '{$datos['TIDEVE']}', {$datos['TIDEVEFE']}, '{$datos['TIDEVEHO']}\n";
        $sql .= ")\n\n";
        $sql .= "SET IDENTITY_INSERT MAEEDO OFF";
        
        return $sql;
    }

    /**
     * Escapar string para SQL Server (reemplazar comillas simples)
     */
    private function escapeSqlString($value)
    {
        if ($value === null) {
            return '';
        }
        // Escapar comillas simples duplicándolas (estándar SQL Server)
        return str_replace("'", "''", (string) $value);
    }

    /**
     * Insertar registro en MAEEDO (encabezado de documento de ajuste de stock)
     * Ahora recibe idmaeedo y nudo que ya fueron generados en MAEDDO
     */
    private function insertarMAEEDO($temporal, $bodega, $tido, $idmaeedo, $nudo)
    {
        try {
            $connection = $this->sqlServerConnection();
            
            // Usar el IDMAEEDO y NUDO que ya fueron generados en MAEDDO
            $siguienteId = $idmaeedo;
            $nudoFormateado = $nudo;
            
            // Calcular totales desde MAEDDO (SUM de los campos correspondientes)
            $totales = $connection->selectOne("
                SELECT 
                    SUM(ISNULL(VAIVLI, 0)) AS suma_vaivli,
                    SUM(ISNULL(VANELI, 0)) AS suma_vaneli,
                    SUM(ISNULL(VABRLI, 0)) AS suma_vabrli
                FROM MAEDDO 
                WHERE IDMAEEDO = ?
            ", [$idmaeedo]);
            
            $vaivdo = (float) ($totales->suma_vaivli ?? 0);
            $vanedo = (float) ($totales->suma_vaneli ?? 0);
            $vabrdo = (float) ($totales->suma_vabrli ?? 0);
            
            // Valores según la tabla proporcionada
            // Usar valores del temporal si están disponibles, sino de la bodega
            // EMPRESA es char(2) y debe ser el código numérico (ej: '02'), no el nombre
            $empresaCodigo = $temporal->empresa ?? $bodega->empresa ?? '02';
            $empresa = $this->escapeSqlString($empresaCodigo); // EMPRESA usa el código numérico
            $endoRaw = '76427949-2'; // SETEADO EN LA APP según la tabla - CONFIRMAR SI ES CORRECTO
            $funcionario = $this->escapeSqlString($temporal->funcionario ?? '');
            $kosu = $this->escapeSqlString($temporal->kosu ?? $bodega->kosu ?? 'CMM');
            // SUENDO = TEMPORAL->KOSU (nombre de empresa), no centro_costo
            $suendoRaw = $temporal->kosu ?? $bodega->kosu ?? '';
            // SUDO = TEMPORAL->KOSU (nombre de empresa), no centro_costo
            $sudoRaw = $temporal->kosu ?? $bodega->kosu ?? '';
            $lisactiva = 'TABPP01C'; // SE DEBE SETEAR EN LA APP según la tabla - CONFIRMAR SI ES CORRECTO
            
            // Validar que TIDO sea válido
            if (!in_array($tido, ['GRI', 'GDI'])) {
                throw new \Exception("TIDO inválido: {$tido}. Debe ser 'GRI' o 'GDI'");
            }
            
            // Consultar TAMODO (valor del dólar) desde el último registro de MAEEDO
            $tamodo = 1; // Valor por defecto
            try {
                $ultimoMAEEDO = $connection->selectOne("
                    SELECT TOP 1 TAMODO 
                    FROM MAEEDO 
                    WHERE TAMODO IS NOT NULL AND TAMODO > 0
                    ORDER BY IDMAEEDO DESC
                ");
                if ($ultimoMAEEDO && isset($ultimoMAEEDO->TAMODO)) {
                    $tamodo = (float) $ultimoMAEEDO->TAMODO;
                }
            } catch (\Exception $e) {
                Log::warning("No se pudo obtener TAMODO del último registro de MAEEDO: " . $e->getMessage());
            }
            
            // Calcular HORAGRAB (función de Excel: convertir fecha/hora a número serial)
            $fechaActual = now();
            $diasDesde1900 = $fechaActual->diffInDays('1900-01-01') + 2; // +2 por bug de Excel (año 1900 bisiesto)
            $horaDecimal = ($fechaActual->hour * 3600 + $fechaActual->minute * 60 + $fechaActual->second) / 86400;
            $horagrab = $diasDesde1900 + $horaDecimal;
            
            // Asegurar que campos char() tengan el padding correcto antes de escapar
            $suendo = $this->escapeSqlString(str_pad(substr($suendoRaw, 0, 10), 10, ' ', STR_PAD_RIGHT)); // SUENDO es char(10)
            $sudo = $this->escapeSqlString(str_pad(substr($sudoRaw, 0, 3), 3, ' ', STR_PAD_RIGHT)); // SUDO es char(3)
            $endo = $this->escapeSqlString(str_pad(substr($endoRaw, 0, 13), 13, ' ', STR_PAD_RIGHT)); // ENDO es char(13)
            
            // Construir el INSERT según la tabla proporcionada
            // Nota: CMM no existe en MAEEDO, se omite. SUDO viene de centro_costo
            $insertMAEEDO = "
                INSERT INTO MAEEDO (
                    IDMAEEDO, EMPRESA, TIDO, NUDO, ENDO, SUENDO, ENDOFI, TIGEDO, SUDO, LUVTDO,
                    FEEMDO, KOFUDO, ESDO, ESPGDO, CAPRCO, CAPRAD, CAPREX, CAPRNC,
                    MEARDO, MODO, TIMODO, TAMODO, NUCTAP, VACTDTNEDO, VACTDTBRDO,
                    NUIVDO, POIVDO, VAIVDO, NUIMDO, VAIMDO, VANEDO, VABRDO, POPIDO, VAPIDO,
                    FE01VEDO, FEULVEDO, NUVEDO, VAABDO, MARCA, FEER, NUTRANSMI, NUCOCO,
                    KOTU, LIBRO, LCLV, ESFADO, KOTRPCVH, NULICO, PERIODO, NUDONODEFI,
                    TRANSMASI, POIVARET, VAIVARET, RESUMEN, LAHORA, KOFUAUDO, KOOPDO,
                    ESPRODDO, DESPACHO, HORAGRAB, RUTCONTACT, SUBTIDO, TIDOELEC, ESDOIMP,
                    CUOGASDIF, BODESTI, PROYECTO, FECHATRIB, NUMOPERVEN, BLOQUEAPAG,
                    VALORRET, FLIQUIFCV, VADEIVDO, KOCANAL, KOCRYPT, LEYZONA, KOSIFIC,
                    LISACTIVA, KOFUAUTO, SUENDOFI, VAIVDOZF, ENDOMANDA, FLUVTCALZA,
                    ARCHIXML, IDXML, SERIENUDO, VALORAJU, PODETRAC, DETRACCION,
                    TIPOOPCOM, CREFIYAF, NRODETRAC, IDPDAENCA, TIDEVE, TIDEVEFE, TIDEVEHO
                ) VALUES (
                    {$siguienteId}, '{$empresa}', '{$tido}', '{$nudoFormateado}', '{$endo}', '{$suendo}', '', 'I', '{$sudo}', '',
                    GETDATE(), '{$funcionario}', 'C', 'S', 0, 0, 0, 0,
                    'N', '$', 'N', {$tamodo}, 0, 0, 0,
                    0, 0, {$vaivdo}, 0, 0, {$vanedo}, {$vabrdo}, 0, 0,
                    GETDATE(), GETDATE(), 1, 0, 'I', GETDATE(), '', '',
                    '1', '', NULL, '', '', '', '', 0,
                    '', 0, 0, '', GETDATE(), '', '',
                    '', 1, {$horagrab}, '', 'AJU', 0, 'I',
                    0, '', '', NULL, 0, '',
                    0, GETDATE(), 0, '', '', '', '',
                    '{$lisactiva}', '', '', 0, '', '',
                    '', 0, '', 0, 0, 0,
                    '', '', '', 0, '', NULL, ''
                )
            ";
            
            Log::info("Insertando en MAEEDO (DESPUÉS de MAEDDO)", [
                'temporal_id' => $temporal->id,
                'tido' => $tido,
                'idmaeedo' => $siguienteId,
                'nudo' => $nudoFormateado,
                'vaivdo' => $vaivdo,
                'vanedo' => $vanedo,
                'vabrdo' => $vabrdo,
            ]);
            
            // Ejecutar SET IDENTITY_INSERT y el INSERT en una sola sentencia
            $sqlCompleto = "SET IDENTITY_INSERT MAEEDO ON;\n" . $insertMAEEDO . "\nSET IDENTITY_INSERT MAEEDO OFF;";
            $connection->statement($sqlCompleto);
            
            Log::info("✓ MAEEDO insertado correctamente con totales desde MAEDDO", [
                'idmaeedo' => $siguienteId,
                'nudo' => $nudoFormateado,
                'vaivdo' => $vaivdo,
                'vanedo' => $vanedo,
                'vabrdo' => $vabrdo,
            ]);
            
            return [
                'id' => $siguienteId,
                'nudo' => $nudoFormateado,
            ];
            
        } catch (\Throwable $e) {
            Log::error('Error en insertarMAEEDO', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Previsualizar datos para insert en MAEDDO (sin ejecutar el insert)
     */
    private function previsualizarMAEDDO($temporal, $bodega, $tido, $idmaeedo, $nudo)
    {
        try {
            $connection = $this->sqlServerConnection();
            $resultId = $connection->selectOne("SELECT TOP 1 ISNULL(MAX(IDMAEDDO), 0) + 1 AS siguiente_id FROM MAEDDO WHERE EMPRESA = ?", [$bodega->empresa]);
            $siguienteId = (int) ($resultId->siguiente_id ?? 1);
            if ($siguienteId <= 0) {
                $siguienteId = 1;
            }

            // Valores para MAEDDO según la tabla proporcionada
            // EMPRESA es char(2) y debe ser el código numérico (ej: '02'), no el nombre
            $empresaCodigo = $temporal->empresa ?? $bodega->empresa ?? '02';
            $empresa = $empresaCodigo; // EMPRESA usa el código numérico
            $endo = '76427949-2'; // SETEADO EN LA APP
            $funcionario = $temporal->funcionario ?? '';
            // SUENDO = TEMPORAL->KOSU (nombre de empresa), no centro_costo
            $suendoDetalle = $temporal->kosu ?? $bodega->kosu ?? '';
            // SULIDO = TEMPORAL->KOSU (nombre de empresa), no centro_costo
            $sulido = $temporal->kosu ?? $bodega->kosu ?? '';
            // LUVTLIDO = TEMPORAL->CC (centro_costo)
            $luvtlido = $temporal->centro_costo ?? $bodega->centro_costo ?? '';
            // BOSULIDO = TEMPORAL->KOBO
            $bosulido = $temporal->kobo ?? $bodega->kobo ?? '';
            
            $udtrpr = 1; // Según tabla: UDTRPR = 1
            $rludpr = (float) ($temporal->rlud ?? 1);
            $ud01pr = trim($temporal->unidad_medida_1 ?? 'UN');
            $ud02pr = trim($temporal->unidad_medida_2 ?? 'UN');
            $nulido = '00001'; // NULIDO es char(5)
            $caprco1 = (float) ($temporal->captura_1 ?? 0);
            $caprco2 = (float) ($temporal->captura_2 ?? 0);
            // CAPRAD1 = TEMPORAL->CAPTURA1 (no 0)
            $caprad1 = $caprco1;
            // CAPRAD2 = TEMPORAL->CAPTURA2 (no 0)
            $caprad2 = $caprco2;
            $koprct = trim($temporal->sku);
            $nokopr = trim($temporal->nombre_producto);
            
            // Truncar nombre si es muy largo (NOKOPR en MAEDDO es varchar(50))
            if (strlen($nokopr) > 50) {
                $nokopr = substr($nokopr, 0, 50);
            }
            
            // CONSULTAR MAEPREM para obtener PM (precio mínimo)
            // Usar empresa (código numérico) sin escapar para la consulta preparada
            $empresaParaConsulta = $empresaCodigo;
            $precioPM = 0;
            try {
                $maeprem = $connection->selectOne("
                    SELECT ISNULL(PM, 0) AS PM 
                    FROM MAEPREM 
                    WHERE KOPR = ? AND EMPRESA = ?
                ", [trim($temporal->sku), $empresaParaConsulta]);
                
                $precioPM = (float) ($maeprem->PM ?? 0);
            } catch (\Exception $e) {
                Log::warning("No se pudo obtener precio PM de MAEPREM para {$temporal->sku}: " . $e->getMessage());
                $precioPM = 0;
            }
            
            // Calcular campos según la tabla proporcionada
            // PPPRNELT = MAEPREM->PM (decimal)
            $ppprnelt = $precioPM;
            // PPPRNE = MAEPREM->PM (decimal)
            $ppprne = $precioPM;
            // PPPRPM = MAEPREM->PM
            $ppprpm = $precioPM;
            // PPPRNERE1 = MAEPREM->PM
            $ppprnere1 = $precioPM;
            // PPPRNERE2 = MAEPREM->PM
            $ppprnere2 = $precioPM;
            // PPPRPMSUC = MAEPREM->PM
            $ppprpmsuc = $precioPM;
            // PPPRPMIFRS = MAEPREM->PM
            $ppprpmifrs = $precioPM;
            
            // PPPRBRLT = PPPRNELT * 1.19 (decimal)
            $ppprbrlt = $ppprnelt * 1.19;
            // PPPRBR = PPPRNE * 1.19 (decimal)
            $ppprbr = $ppprne * 1.19;
            
            // Según la tabla: VANELI = PPPRNE * CAPRCO1 (redondeo)
            $vaneli = round($ppprne * $caprco1, 0);
            // Según la tabla: VAIVLI = PPPRNE * 19% (decimal 2)
            $vaivli = round($ppprne * 0.19, 2);
            // Según la tabla: VABRLI = VANELI * 1.19 (redondeo)
            $vabrli = round($vaneli * 1.19, 0);
            
            // KOLTPR según tabla: TABPP01C (no TABPP01P)
            $koltpr = 'TABPP01C';
            
            // Preparar datos para mostrar
            $datos = [
                'IDMAEDDO' => $siguienteId,
                'IDMAEEDO' => $idmaeedo,
                'EMPRESA' => $empresa,
                'TIDO' => $tido,
                'NUDO' => $nudo,
                'ENDO' => $endo,
                'SUENDO' => $suendoDetalle,
                'LILG' => 'SI',
                'NULIDO' => $nulido,
                'SULIDO' => $sulido,
                'BOSULIDO' => $bosulido,
                'LUVTLIDO' => $luvtlido,
                'KOFULIDO' => $funcionario,
                'TIPR' => 'FPN',
                'UDTRPR' => $udtrpr,
                'RLUDPR' => $rludpr,
                'UD01PR' => $ud01pr,
                'UD02PR' => $ud02pr,
                'KOPRCT' => $koprct,
                'NOKOPR' => $nokopr,
                'CAPRCO1' => $caprco1,
                'CAPRAD1' => $caprad1,
                'CAPREX1' => 0,
                'CAPRNC1' => 0,
                'CAPRCO2' => $caprco2,
                'CAPRAD2' => $caprad2,
                'CAPREX2' => 0,
                'CAPRNC2' => 0,
                'KOLTPR' => $koltpr,
                'MOPPPR' => '$',
                'TIMOPPPR' => 'N',
                'TAMOPPPR' => 1,
                'PPPRNE' => $ppprne,
                'PPPRNELT' => $ppprnelt,
                'PPPRBR' => $ppprbr,
                'PPPRBRLT' => $ppprbrlt,
                'PODTGLLI' => 0,
                'VADTNELI' => 0,
                'VANELI' => $vaneli,
                'POIVLI' => 19,
                'VAIVLI' => $vaivli,
                'VABRLI' => $vabrli,
                'TIGELI' => 'I',
                'FEEMLI' => 'GETDATE()',
                'FEERLI' => 'GETDATE()',
                'NUDTLI' => 0,
                'ARCHIRST' => '',
                'IDRST' => 0,
                'PPPRPM' => $ppprpm,
                'PPPRNERE1' => $ppprnere1,
                'PPPRNERE2' => $ppprnere2,
                'TASADORIG' => 1,
                'CUOGASDIF' => 0,
                'PROYECTO' => '',
                'POTENCIA' => 0,
                'HUMEDAD' => 0,
                'IDTABITPRE' => 0,
                'FEERLIMODI' => 'GETDATE()',
                'PPPRPMSUC' => $ppprpmsuc,
                'PPPRPMIFRS' => $ppprpmifrs,
            ];
            
            return [
                'temporal_id' => $temporal->id,
                'datos' => $datos,
                'sql_preview' => $this->generarSQLPreviewMAEDDO($datos),
            ];
            
        } catch (\Throwable $e) {
            Log::error('Error en previsualizarMAEDDO', [
                'exception' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Generar vista previa del SQL para MAEDDO
     */
    private function generarSQLPreviewMAEDDO($datos)
    {
        $sql = "INSERT INTO MAEDDO (\n";
        $sql .= "    IDMAEDDO, IDMAEEDO, EMPRESA, TIDO, NUDO, ENDO, SUENDO,\n";
        $sql .= "    LILG, NULIDO, SULIDO, BOSULIDO, LUVTLIDO, KOFULIDO, TIPR,\n";
        $sql .= "    UDTRPR, RLUDPR, UD01PR, UD02PR,\n";
        $sql .= "    KOPRCT, NOKOPR, CAPRCO1, CAPRCO2,\n";
        $sql .= "    KOLTPR, MOPPPR, TIMOPPPR, TAMOPPPR,\n";
        $sql .= "    PPPRNE, PPPRNELT, PPPRBR, PPPRBRLT,\n";
        $sql .= "    PODTGLLI, VADTNELI, VANELI, POIVLI, VAIVLI, VABRLI,\n";
        $sql .= "    TIGELI, FEEMLI, FEERLI, NUDTLI, ARCHIRST, IDRST,\n";
        $sql .= "    PPPRPM, PPPRNERE1, PPPRNERE2, TASADORIG, CUOGASDIF, PROYECTO,\n";
        $sql .= "    POTENCIA, HUMEDAD, IDTABITPRE, FEERLIMODI\n";
        $sql .= ") VALUES (\n";
        $sql .= "    {$datos['IDMAEDDO']}, {$datos['IDMAEEDO']}, '{$datos['EMPRESA']}', '{$datos['TIDO']}', '{$datos['NUDO']}', '{$datos['ENDO']}', '{$datos['SUENDO']},\n";
        $sql .= "    '{$datos['LILG']}', '{$datos['NULIDO']}', '{$datos['SULIDO']}', '{$datos['BOSULIDO']}', '{$datos['LUVTLIDO']}', '{$datos['KOFULIDO']}', '{$datos['TIPR']},\n";
        $sql .= "    {$datos['UDTRPR']}, {$datos['RLUDPR']}, '{$datos['UD01PR']}', '{$datos['UD02PR']},\n";
        $sql .= "    '{$datos['KOPRCT']}', '{$datos['NOKOPR']}', {$datos['CAPRCO1']}, {$datos['CAPRCO2']},\n";
        $sql .= "    '{$datos['KOLTPR']}', '{$datos['MOPPPR']}', '{$datos['TIMOPPPR']}', {$datos['TAMOPPPR']},\n";
        $sql .= "    {$datos['PPPRNE']}, {$datos['PPPRNELT']}, {$datos['PPPRBR']}, {$datos['PPPRBRLT']},\n";
        $sql .= "    {$datos['PODTGLLI']}, {$datos['VADTNELI']}, {$datos['VANELI']}, {$datos['POIVLI']}, {$datos['VAIVLI']}, {$datos['VABRLI']},\n";
        $sql .= "    '{$datos['TIGELI']}', {$datos['FEEMLI']}, {$datos['FEERLI']}, {$datos['NUDTLI']}, '{$datos['ARCHIRST']}', {$datos['IDRST']},\n";
        $sql .= "    {$datos['PPPRPM']}, {$datos['PPPRNERE1']}, {$datos['PPPRNERE2']}, {$datos['TASADORIG']}, {$datos['CUOGASDIF']}, {$datos['PROYECTO']},\n";
        $sql .= "    {$datos['POTENCIA']}, {$datos['HUMEDAD']}, {$datos['IDTABITPRE']}, {$datos['FEERLIMODI']}\n";
        $sql .= ")";
        
        return $sql;
    }

    /**
     * Insertar registro en MAEDDO (detalle de documento de ajuste de stock)
     * PRIMERO se inserta MAEDDO, generando IDMAEEDO y NUDO aquí
     */
    private function insertarMAEDDO($temporal, $bodega, $tido)
    {
        try {
            $connection = $this->sqlServerConnection();
            
            // PRIMERO: Generar IDMAEEDO y NUDO que se usarán tanto en MAEDDO como en MAEEDO
            // Para las consultas SQL usar el código numérico de empresa
            $empresaCodigo = $temporal->empresa ?? $bodega->empresa ?? '02';
            $resultId = $connection->selectOne("SELECT TOP 1 ISNULL(MAX(IDMAEEDO), 0) + 1 AS siguiente_id FROM MAEEDO WHERE EMPRESA = ?", [$empresaCodigo]);
            $idmaeedo = (int) ($resultId->siguiente_id ?? 1);
            if ($idmaeedo <= 0) {
                $idmaeedo = 1;
            }

            $resultNudo = $connection->selectOne("SELECT TOP 1 CAST(NUDO AS INT) AS nudo_int FROM MAEEDO WHERE TIDO = ? AND ISNUMERIC(NUDO) = 1 ORDER BY CAST(NUDO AS INT) DESC", [$tido]);
            $siguienteNudo = $resultNudo ? ((int) $resultNudo->nudo_int + 1) : 1;
            $nudo = str_pad($siguienteNudo, 10, '0', STR_PAD_LEFT);
            
            // Generar IDMAEDDO
            $resultIdMAEDDO = $connection->selectOne("SELECT TOP 1 ISNULL(MAX(IDMAEDDO), 0) + 1 AS siguiente_id FROM MAEDDO WHERE EMPRESA = ?", [$empresaCodigo]);
            $siguienteId = (int) ($resultIdMAEDDO->siguiente_id ?? 1);
            if ($siguienteId <= 0) {
                $siguienteId = 1;
            }

            // Valores para MAEDDO según la tabla proporcionada
            // EMPRESA es char(2) y debe ser el código numérico (ej: '02'), no el nombre
            $empresaCodigo = $temporal->empresa ?? $bodega->empresa ?? '02';
            $empresa = $this->escapeSqlString($empresaCodigo); // EMPRESA usa el código numérico
            $endo = '76427949-2'; // SETEADO EN LA APP
            $funcionario = $this->escapeSqlString($temporal->funcionario ?? '');
            // SUENDO = TEMPORAL->KOSU (nombre de empresa), no centro_costo
            $suendoDetalle = $this->escapeSqlString($temporal->kosu ?? $bodega->kosu ?? '');
            // SULIDO = TEMPORAL->KOSU (nombre de empresa), no centro_costo
            $sulido = $this->escapeSqlString($temporal->kosu ?? $bodega->kosu ?? '');
            // LUVTLIDO = TEMPORAL->CC (centro_costo)
            $luvtlido = $this->escapeSqlString($temporal->centro_costo ?? $bodega->centro_costo ?? '');
            // BOSULIDO = TEMPORAL->KOBO
            $bosulido = $this->escapeSqlString($temporal->kobo ?? $bodega->kobo ?? '');
            
            $udtrpr = 1; // Según tabla: UDTRPR = 1
            $rludpr = (float) ($temporal->rlud ?? 1);
            $ud01pr = $this->escapeSqlString(trim($temporal->unidad_medida_1 ?? 'UN'));
            $ud02pr = $this->escapeSqlString(trim($temporal->unidad_medida_2 ?? 'UN'));
            $nulido = '00001'; // NULIDO es char(5)
            $caprco1 = (float) ($temporal->captura_1 ?? 0);
            $caprco2 = (float) ($temporal->captura_2 ?? 0);
            // CAPRAD1 = TEMPORAL->CAPTURA1 (no 0)
            $caprad1 = $caprco1;
            // CAPRAD2 = TEMPORAL->CAPTURA2 (no 0)
            $caprad2 = $caprco2;
            $koprct = $this->escapeSqlString(trim($temporal->sku));
            $nokopr = trim($temporal->nombre_producto);
            
            // Validar que SKU no esté vacío
            if (empty($koprct)) {
                throw new \Exception("SKU no puede estar vacío");
            }
            
            // Validar que nombre no esté vacío
            if (empty($nokopr)) {
                throw new \Exception("Nombre de producto no puede estar vacío");
            }
            
            // Truncar nombre si es muy largo (NOKOPR en MAEDDO es varchar(50))
            if (strlen($nokopr) > 50) {
                $nokopr = substr($nokopr, 0, 50);
            }
            $nokopr = $this->escapeSqlString($nokopr);
            
            // CONSULTAR MAEPREM para obtener PM (precio mínimo)
            // Usar empresa sin escapar para la consulta preparada
            $empresaParaConsulta = $temporal->empresa ?? $bodega->empresa ?? '02';
            $precioPM = 0;
            try {
                $maeprem = $connection->selectOne("
                    SELECT ISNULL(PM, 0) AS PM 
                    FROM MAEPREM 
                    WHERE KOPR = ? AND EMPRESA = ?
                ", [trim($temporal->sku), $empresaParaConsulta]);
                
                $precioPM = (float) ($maeprem->PM ?? 0);
                
                Log::info("Precio PM obtenido de MAEPREM", [
                    'sku' => $temporal->sku,
                    'empresa' => $empresaParaConsulta,
                    'pm' => $precioPM,
                ]);
            } catch (\Exception $e) {
                Log::warning("No se pudo obtener precio PM de MAEPREM para {$temporal->sku}: " . $e->getMessage());
                $precioPM = 0;
            }
            
            // Calcular campos según la tabla proporcionada
            // PPPRNELT = MAEPREM->PM (decimal)
            $ppprnelt = $precioPM;
            // PPPRNE = MAEPREM->PM (decimal)
            $ppprne = $precioPM;
            // PPPRPM = MAEPREM->PM
            $ppprpm = $precioPM;
            // PPPRNERE1 = MAEPREM->PM
            $ppprnere1 = $precioPM;
            // PPPRNERE2 = MAEPREM->PM
            $ppprnere2 = $precioPM;
            // PPPRPMSUC = MAEPREM->PM
            $ppprpmsuc = $precioPM;
            // PPPRPMIFRS = MAEPREM->PM
            $ppprpmifrs = $precioPM;
            
            // PPPRBRLT = PPPRNELT * 1.19 (decimal)
            $ppprbrlt = $ppprnelt * 1.19;
            // PPPRBR = PPPRNE * 1.19 (decimal)
            $ppprbr = $ppprne * 1.19;
            
            // Según la tabla: VANELI = PPPRNE * CAPRCO1 (redondeo)
            $vaneli = round($ppprne * $caprco1, 0);
            // Según la tabla: VAIVLI = PPPRNE * 19% (decimal 2)
            $vaivli = round($ppprne * 0.19, 2);
            // Según la tabla: VABRLI = VANELI * 1.19 (redondeo)
            $vabrli = round($vaneli * 1.19, 0);
            
            // Asegurar que otros campos no excedan sus límites según estructura real
            // Los campos char() necesitan exactamente el número de caracteres especificado
            $koprct = str_pad(substr($koprct, 0, 13), 13, ' ', STR_PAD_RIGHT); // KOPRCT es char(13)
            $ud01pr = str_pad(substr($ud01pr, 0, 2), 2, ' ', STR_PAD_RIGHT); // UD01PR es char(2)
            $ud02pr = str_pad(substr($ud02pr, 0, 2), 2, ' ', STR_PAD_RIGHT); // UD02PR es char(2)
            $suendoDetalle = str_pad(substr($suendoDetalle, 0, 10), 10, ' ', STR_PAD_RIGHT); // SUENDO es char(10)
            $sulido = str_pad(substr($sulido, 0, 3), 3, ' ', STR_PAD_RIGHT); // SULIDO es char(3)
            $luvtlido = str_pad(substr($luvtlido, 0, 8), 8, ' ', STR_PAD_RIGHT); // LUVTLIDO es char(8)
            $bosulido = str_pad(substr($bosulido, 0, 3), 3, ' ', STR_PAD_RIGHT); // BOSULIDO es char(3)
            $endo = str_pad(substr($endo, 0, 13), 13, ' ', STR_PAD_RIGHT); // ENDO es char(13)
            
            // Validar valores numéricos
            if ($caprco1 <= 0) {
                throw new \Exception("CAPRCO1 debe ser mayor a 0");
            }
            
            // KOLTPR según tabla: TABPP01C (no TABPP01P)
            $koltpr = 'TABPP01C';
            
            $insertMAEDDO = "
                INSERT INTO MAEDDO (
                    IDMAEDDO, IDMAEEDO, EMPRESA, TIDO, NUDO, ENDO, SUENDO,
                    LILG, NULIDO, SULIDO, BOSULIDO, LUVTLIDO, KOFULIDO, TIPR,
                    UDTRPR, RLUDPR, UD01PR, UD02PR,
                    KOPRCT, NOKOPR, CAPRCO1, CAPRAD1, CAPREX1, CAPRNC1, CAPRCO2, CAPRAD2, CAPREX2, CAPRNC2,
                    KOLTPR, MOPPPR, TIMOPPPR, TAMOPPPR,
                    PPPRNE, PPPRNELT, PPPRBR, PPPRBRLT,
                    PODTGLLI, VADTNELI, VANELI, POIVLI, VAIVLI, VABRLI,
                    TIGELI, FEEMLI, FEERLI, NUDTLI, ARCHIRST, IDRST,
                    PPPRPM, PPPRNERE1, PPPRNERE2, TASADORIG, CUOGASDIF, PROYECTO,
                    POTENCIA, HUMEDAD, IDTABITPRE, FEERLIMODI, PPPRPMSUC, PPPRPMIFRS
                ) VALUES (
                    {$siguienteId}, {$idmaeedo}, '{$empresa}', '{$tido}', '{$nudo}', '{$endo}', '{$suendoDetalle}',
                    'SI', '{$nulido}', '{$sulido}', '{$bosulido}', '{$luvtlido}', '{$funcionario}', 'FPN',
                    {$udtrpr}, {$rludpr}, '{$ud01pr}', '{$ud02pr}',
                    '{$koprct}', '{$nokopr}', {$caprco1}, {$caprad1}, 0, 0, {$caprco2}, {$caprad2}, 0, 0,
                    '{$koltpr}', '$', 'N', 1,
                    {$ppprne}, {$ppprnelt}, {$ppprbr}, {$ppprbrlt},
                    0, 0, {$vaneli}, 19, {$vaivli}, {$vabrli},
                    'I', GETDATE(), GETDATE(), 0, '', 0,
                    {$ppprpm}, {$ppprnere1}, {$ppprnere2}, 1, 0, '',
                    0, 0, 0, GETDATE(), {$ppprpmsuc}, {$ppprpmifrs}
                )
            ";
            
            Log::info("Insertando en MAEDDO (PRIMERO)", [
                'temporal_id' => $temporal->id,
                'idmaeddo' => $siguienteId,
                'idmaeedo' => $idmaeedo,
                'tido' => $tido,
                'nudo' => $nudo,
                'precio_pm' => $precioPM,
                'ppprne' => $ppprne,
                'ppprnelt' => $ppprnelt,
                'ppprbr' => $ppprbr,
                'ppprbrlt' => $ppprbrlt,
                'vaneli' => $vaneli,
                'vaivli' => $vaivli,
                'vabrli' => $vabrli,
                'caprco1' => $caprco1,
                'caprad1' => $caprad1,
                'suendo' => $suendoDetalle,
                'sulido' => $sulido,
                'bosulido' => $bosulido,
            ]);
            
            // Ejecutar SET IDENTITY_INSERT y el INSERT en una sola sentencia
            $sqlCompleto = "SET IDENTITY_INSERT MAEDDO ON;\n" . $insertMAEDDO . "\nSET IDENTITY_INSERT MAEDDO OFF;";
            $connection->statement($sqlCompleto);
            
            Log::info("✓ MAEDDO insertado correctamente", [
                'idmaeddo' => $siguienteId,
                'idmaeedo' => $idmaeedo,
            ]);
            
            // Retornar IDMAEEDO y NUDO para usarlos en MAEEDO
            return [
                'idmaeedo' => $idmaeedo,
                'nudo' => $nudo,
                'idmaeddo' => $siguienteId,
            ];
            
        } catch (\Throwable $e) {
            Log::error('Error en insertarMAEDDO', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Actualizar stock en MAEST, MAEPR y MAEPREM después de los inserts
     * 
     * UPDATE MAEST:
     *   MAEST->STFI1 = temporal CAPTURA1
     *   MAEST->STFI2 = temporal CAPTURA2
     * 
     * UPDATE MAEPR:
     *   MAEPR->STFI1 = (MAEPR->STFI1) + DIF
     *   MAEPR->STFI2 = (MAEPR->STFI2) + DIF
     *   donde DIF = CAPTURA - DICE (stfi1 del temporal)
     * 
     * UPDATE MAEPREM:
     *   MAEPREM->STFI1 = (MAEPREM->STFI1) + DIF
     *   MAEPREM->STFI2 = (MAEPREM->STFI2) + DIF
     */
    private function actualizarStock($temporal, $bodega, $tido)
    {
        try {
            $connection = $this->sqlServerConnection();
            $sku = trim($temporal->sku);
            $empresa = $temporal->empresa ?? $bodega->empresa ?? '02';
            $kobo = $temporal->kobo ?? $bodega->kobo ?? '';
            
            // Consultar stock actual desde MAEST antes de calcular DIF
            $stockActual = $connection->selectOne("
                SELECT 
                    ISNULL(STFI1, 0) AS stfi1_actual,
                    ISNULL(STFI2, 0) AS stfi2_actual
                FROM MAEST 
                WHERE KOPR = ? 
                  AND EMPRESA = ?
                  AND KOBO = ?
            ", [$sku, $empresa, $kobo]);
            
            $stfi1_actual = (float) ($stockActual->stfi1_actual ?? 0);
            $stfi2_actual = (float) ($stockActual->stfi2_actual ?? 0);
            
            // Calcular DIF: DIF = CAPTURA - STOCK_ACTUAL (desde MAEST)
            $dif1 = (float) $temporal->captura_1 - $stfi1_actual;
            $dif2 = $temporal->captura_2 !== null 
                ? (float) $temporal->captura_2 - $stfi2_actual
                : 0;
            
            Log::info("Actualizando stock", [
                'sku' => $sku,
                'empresa' => $empresa,
                'kobo' => $kobo,
                'captura_1' => $temporal->captura_1,
                'stfi1_actual' => $stfi1_actual,
                'dif1' => $dif1,
                'captura_2' => $temporal->captura_2,
                'stfi2_actual' => $stfi2_actual,
                'dif2' => $dif2,
                'tido' => $tido,
            ]);
            
            // 1. UPDATE MAEST: STFI1 = CAPTURA1, STFI2 = CAPTURA2
            $updateMAEST = "
                UPDATE MAEST 
                SET STFI1 = ?,
                    STFI2 = ISNULL(?, STFI2)
                WHERE LTRIM(RTRIM(KOPR)) = ? 
                  AND EMPRESA = ?
                  AND LTRIM(RTRIM(KOBO)) = ?
            ";
            
            $rowsAffectedMAEST = $connection->update($updateMAEST, [
                $temporal->captura_1,
                $temporal->captura_2,
                $sku,
                $empresa,
                $kobo,
            ]);
            
            Log::info("✓ MAEST actualizado", [
                'sku' => $sku,
                'stfi1' => $temporal->captura_1,
                'stfi2' => $temporal->captura_2,
                'rows_affected' => $rowsAffectedMAEST,
            ]);
            
            if ($rowsAffectedMAEST === 0) {
                Log::warning("⚠ MAEST: No se actualizó ninguna fila. Verificar que exista el registro con KOPR='{$sku}', EMPRESA='{$empresa}', KOBO='{$kobo}'");
            }
            
            // 2. UPDATE MAEPR: STFI1 = STFI1 + DIF1, STFI2 = STFI2 + DIF2
            // NOTA: MAEPR NO tiene columna EMPRESA, solo se filtra por KOPR
            $updateMAEPR = "
                UPDATE MAEPR 
                SET STFI1 = ISNULL(STFI1, 0) + ?,
                    STFI2 = ISNULL(STFI2, 0) + ?
                WHERE LTRIM(RTRIM(KOPR)) = ?
            ";
            
            $rowsAffectedMAEPR = $connection->update($updateMAEPR, [
                $dif1,
                $dif2,
                $sku,
            ]);
            
            Log::info("✓ MAEPR actualizado", [
                'sku' => $sku,
                'dif1' => $dif1,
                'dif2' => $dif2,
                'rows_affected' => $rowsAffectedMAEPR,
            ]);
            
            if ($rowsAffectedMAEPR === 0) {
                Log::warning("⚠ MAEPR: No se actualizó ninguna fila. Verificar que exista el registro con KOPR='{$sku}'");
            }
            
            // 3. UPDATE MAEPREM: STFI1 = STFI1 + DIF1, STFI2 = STFI2 + DIF2
            // NOTA: MAEPREM SÍ tiene columna EMPRESA
            $updateMAEPREM = "
                UPDATE MAEPREM 
                SET STFI1 = ISNULL(STFI1, 0) + ?,
                    STFI2 = ISNULL(STFI2, 0) + ?
                WHERE LTRIM(RTRIM(KOPR)) = ? 
                  AND EMPRESA = ?
            ";
            
            $rowsAffectedMAEPREM = $connection->update($updateMAEPREM, [
                $dif1,
                $dif2,
                $sku,
                $empresa,
            ]);
            
            Log::info("✓ MAEPREM actualizado", [
                'sku' => $sku,
                'dif1' => $dif1,
                'dif2' => $dif2,
                'rows_affected' => $rowsAffectedMAEPREM,
            ]);
            
            if ($rowsAffectedMAEPREM === 0) {
                Log::warning("⚠ MAEPREM: No se actualizó ninguna fila. Verificar que exista el registro con KOPR='{$sku}', EMPRESA='{$empresa}'");
            }
            
        } catch (\Throwable $e) {
            Log::error('Error actualizando stock en MAEST/MAEPR/MAEPREM', [
                'temporal_id' => $temporal->id,
                'sku' => $temporal->sku ?? 'N/A',
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    private function sqlServerConnection()
    {
        return DB::connection('sqlsrv_external');
    }

    /**
     * Mostrar historial de capturas y códigos de barras
     */
    public function historial()
    {
        $user = auth()->user();
        
        // Obtener capturas de stock (temporales) del usuario
        $capturas = Temporal::with(['bodega', 'ubicacion'])
            ->where('funcionario', $user->codigo_vendedor ?? 'PZZ')
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        
        // Obtener modificaciones de códigos de barras del usuario
        $codigosBarras = CodigoBarraLog::with(['bodega', 'user'])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        
        return view('manejo-stock.historial', [
            'capturas' => $capturas,
            'codigosBarras' => $codigosBarras,
        ]);
    }
}
