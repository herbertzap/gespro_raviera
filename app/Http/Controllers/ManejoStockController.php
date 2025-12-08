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

            // Obtener código de funcionario del usuario autenticado si no viene en el request
            $funcionario = $data['funcionario'] ?? null;
            if (empty($funcionario) && auth()->user()) {
                $funcionario = auth()->user()->codigo_vendedor ?? null;
            }
            
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
                'funcionario' => $funcionario,
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
            // Usar tsql si encrypt=no, sino usar conexión Laravel
            $encrypt = env('SQLSRV_EXTERNAL_ENCRYPT', 'yes');
            
            if ($encrypt === 'no' || $encrypt === false || $encrypt === 'false') {
                // Usar tsql
                $barcodeEscapado = str_replace("'", "''", $barcode);
                $query = "SELECT TOP 1 CAST(KOPRAL AS VARCHAR(60)) + '|' + CAST(KOPR AS VARCHAR(30)) + '|' + CAST(ISNULL(NOKOPRAL, '') AS VARCHAR(200)) AS DATOS FROM TABCODAL WHERE KOPRAL = '{$barcodeEscapado}'";
                $output = $this->executeTSQL($query);
                
                // Procesar output
                $lines = explode("\n", $output);
                $registro = null;
                $encontradoHeader = false;
                
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line) || strpos($line, 'locale') !== false || strpos($line, 'Setting') !== false || 
                        preg_match('/^\d+>$/', $line) || strpos($line, 'rows affected') !== false) {
                        continue;
                    }
                    if (strpos($line, 'DATOS') !== false && !strpos($line, '|')) {
                        $encontradoHeader = true;
                        continue;
                    }
                    if ($encontradoHeader && strpos($line, '|') !== false) {
                        $campos = explode('|', $line);
                        if (count($campos) >= 3) {
                            $registro = (object)[
                                'KOPRAL' => trim($campos[0]),
                                'KOPR' => trim($campos[1]),
                                'NOKOPRAL' => trim($campos[2]),
                            ];
                            break;
                        }
                    }
                }
            } else {
                // Usar conexión Laravel normal
                $registro = DB::connection('sqlsrv_external')
                    ->table('TABCODAL')
                    ->select('KOPRAL', 'KOPR', 'NOKOPRAL')
                    ->where('KOPRAL', $barcode)
                    ->first();
            }

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
        try {
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

            try {
                $encrypt = env('SQLSRV_EXTERNAL_ENCRYPT', 'yes');
                
                if ($encrypt === 'no' || $encrypt === false || $encrypt === 'false') {
                    // Usar PDO directo para códigos de barra
                    $barcodes = $this->obtenerBarcodesPDO($data['sku']);
                } else {
                    $barcodes = DB::connection('sqlsrv_external')
                        ->table('TABCODAL')
                        ->where('KOPR', trim($data['sku']))
                        ->orderBy('KOPRAL')
                        ->pluck('KOPRAL')
                        ->map(fn ($codigo) => trim($codigo))
                        ->filter()
                        ->values();
                }
            } catch (\Exception $e) {
                Log::error("Error obteniendo códigos de barra: " . $e->getMessage());
                $barcodes = collect([]);
            }

            $detalle['barcode_actual'] = $barcodes->first();
            $detalle['barcodes'] = $barcodes;

            return response()->json([
                'success' => true,
                'producto' => $detalle,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación: ' . $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error("Error en ManejoStockController@producto: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener producto: ' . $e->getMessage(),
            ], 500);
        }
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
        try {
            // Verificar si debemos usar conexión sin TLS
            $encrypt = env('SQLSRV_EXTERNAL_ENCRYPT', 'yes');
            
            if ($encrypt === 'no' || $encrypt === false || $encrypt === 'false') {
                // Usar conexión PDO directa con Encrypt=no para servidores antiguos
                return $this->obtenerDetalleProductoPDO($sku, $bodega);
            }
            
            // Usar conexión Laravel normal para servidores modernos con TLS
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
        } catch (\Exception $e) {
            Log::error("Error en obtenerDetalleProducto para SKU {$sku}: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());
            throw $e; // Re-lanzar para que el método producto lo capture
        }
    }

    /**
     * Obtener detalle de producto usando tsql (para SQL Server 2012 sin TLS)
     */
    private function obtenerDetalleProductoPDO(string $sku, ?Bodega $bodega = null): ?array
    {
        $host = env('SQLSRV_EXTERNAL_HOST');
        $port = env('SQLSRV_EXTERNAL_PORT', '1433');
        $database = env('SQLSRV_EXTERNAL_DATABASE');
        $username = env('SQLSRV_EXTERNAL_USERNAME');
        $password = env('SQLSRV_EXTERNAL_PASSWORD');

        try {
            // Query con formato de salida usando pipes | para separar campos
            $skuEscapado = str_replace("'", "''", trim($sku));
            $stockCondition = '';
            if ($bodega && $bodega->kobo) {
                $koboEscapado = str_replace("'", "''", $bodega->kobo);
                $stockCondition = "AND MAEST.KOBO = '{$koboEscapado}'";
            }
            
            $query = "
                SELECT TOP 1
                    CAST(MAEPR.KOPR AS VARCHAR(30)) + '|' +
                    CAST(REPLACE(MAEPR.NOKOPR, '|', ' ') AS VARCHAR(200)) + '|' +
                    CAST(ISNULL(MAEPR.RLUD, 1) AS VARCHAR(20)) + '|' +
                    CAST(ISNULL(MAEPR.UD01PR, '') AS VARCHAR(10)) + '|' +
                    CAST(ISNULL(MAEPR.UD02PR, '') AS VARCHAR(10)) + '|' +
                    CAST(ISNULL(SUM(MAEST.STFI1), 0) AS VARCHAR(30)) + '|' +
                    CAST(ISNULL(SUM(MAEST.STOCNV1), 0) AS VARCHAR(30)) AS DATOS_PRODUCTO
                FROM MAEPR
                LEFT JOIN MAEST ON MAEPR.KOPR = MAEST.KOPR {$stockCondition}
                WHERE MAEPR.KOPR = '{$skuEscapado}'
                GROUP BY MAEPR.KOPR, MAEPR.NOKOPR, MAEPR.RLUD, MAEPR.UD01PR, MAEPR.UD02PR
            ";

            $tempFile = tempnam(sys_get_temp_dir(), 'sql_producto_');
            file_put_contents($tempFile, $query . "\ngo\nquit");
            
            $command = "tsql -H {$host} -p {$port} -U {$username} -P {$password} -D {$database} < {$tempFile} 2>&1";
            $output = shell_exec($command);
            unlink($tempFile);

            if (!$output || str_contains(strtolower($output), 'error')) {
                Log::error("Error tsql en obtenerDetalleProductoPDO: " . $output);
                return null;
            }

            // Procesar la salida línea por línea
            $lines = explode("\n", $output);
            $productoData = null;
            $encontradoHeader = false;
            
            Log::debug("Procesando output tsql para SKU {$sku}", [
                'total_lines' => count($lines),
                'output_preview' => substr($output, 0, 300)
            ]);

            foreach ($lines as $line) {
                $line = trim($line);
                
                // Saltar líneas vacías o de configuración
                if (empty($line) || 
                    strpos($line, 'locale') !== false || 
                    strpos($line, 'Setting') !== false || 
                    strpos($line, 'Msg ') !== false || 
                    strpos($line, 'Warning:') !== false ||
                    preg_match('/^\d+>$/', $line) ||
                    strpos($line, 'rows affected') !== false ||
                    strpos($line, 'using default') !== false) {
                    continue;
                }
                
                // Detectar header (puede tener números de línea antes: "1> 2> 3> DATOS_PRODUCTO")
                if (strpos($line, 'DATOS_PRODUCTO') !== false) {
                    $encontradoHeader = true;
                    Log::debug("Header encontrado: {$line}");
                    continue;
                }
                
                // También detectar si la línea contiene solo "DATOS" sin pipes (header alternativo)
                if (!$encontradoHeader && strpos($line, 'DATOS') !== false && strpos($line, '|') === false) {
                    $encontradoHeader = true;
                    Log::debug("Header alternativo encontrado: {$line}");
                    continue;
                }
                
                // Buscar línea con datos separados por | (después del header)
                // La línea debe tener pipes y el primer campo debe ser un número (código de producto)
                // Formato esperado: "0000008503050|ARPILLERA NATURAL 30 X 50|1|UN|UN|14|0"
                if ($encontradoHeader && strpos($line, '|') !== false) {
                    // Buscar patrón: código numérico seguido de pipe (puede tener espacios antes)
                    // Usar regex similar a SincronizarClientesSimple: /^(\d+)\s*\|(.+)$/
                    if (preg_match('/^(\d+)\s*\|(.+)$/', $line, $matches)) {
                        $codigo = $matches[1];
                        $resto = $matches[2];
                        $campos = explode('|', $resto);
                        
                        Log::debug("Línea de datos encontrada", [
                            'line' => $line,
                            'codigo' => $codigo,
                            'campos_count' => count($campos),
                            'campos' => $campos
                        ]);
                        
                        // Deberíamos tener al menos 6 campos más (nombre, rlud, ud1, ud2, stock_fisico, stock_comprometido)
                        if (count($campos) >= 6) {
                            $productoData = [
                                'codigo' => $codigo,
                                'nombre' => trim($campos[0]),
                                'rlud' => (float) (trim($campos[1]) ?: 1),
                                'unidad_1' => trim($campos[2]),
                                'unidad_2' => trim($campos[3]),
                                'stock_fisico' => (float) (trim($campos[4]) ?: 0),
                                'stock_comprometido' => (float) (trim($campos[5]) ?: 0),
                            ];
                            Log::debug("Producto parseado correctamente", $productoData);
                            break;
                        } else {
                            Log::warning("Campos insuficientes", [
                                'line' => $line,
                                'campos_count' => count($campos),
                                'campos' => $campos
                            ]);
                        }
                    } else {
                        Log::debug("Línea con pipe no coincide con regex", ['line' => $line]);
                    }
                }
            }

            if (!$productoData) {
                Log::warning("No se pudo parsear producto desde tsql output", [
                    'sku' => $sku,
                    'output_preview' => substr($output, 0, 500),
                    'lines_processed' => count($lines)
                ]);
                return null;
            }
            
            Log::debug("Producto parseado correctamente", [
                'sku' => $sku,
                'codigo' => $productoData['codigo'],
                'nombre' => $productoData['nombre']
            ]);

            $productoData['stock_disponible'] = $productoData['stock_fisico'] - $productoData['stock_comprometido'];
            
            $funcionario = null;
            if (auth()->user() && auth()->user()->codigo_vendedor) {
                $funcionario = auth()->user()->codigo_vendedor;
            }
            $productoData['funcionario'] = $funcionario;

            return $productoData;
        } catch (\Exception $e) {
            Log::error("Error en obtenerDetalleProductoPDO para SKU {$sku}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtener códigos de barra usando tsql (para SQL Server 2012 sin TLS)
     */
    private function obtenerBarcodesPDO(string $sku): \Illuminate\Support\Collection
    {
        $host = env('SQLSRV_EXTERNAL_HOST');
        $port = env('SQLSRV_EXTERNAL_PORT', '1433');
        $database = env('SQLSRV_EXTERNAL_DATABASE');
        $username = env('SQLSRV_EXTERNAL_USERNAME');
        $password = env('SQLSRV_EXTERNAL_PASSWORD');

        try {
            $skuEscapado = str_replace("'", "''", trim($sku));
            $query = "SELECT KOPRAL FROM TABCODAL WHERE KOPR = '{$skuEscapado}' ORDER BY KOPRAL";

            $tempFile = tempnam(sys_get_temp_dir(), 'sql_barcodes_');
            file_put_contents($tempFile, $query . "\ngo\nquit");
            
            $command = "tsql -H {$host} -p {$port} -U {$username} -P {$password} -D {$database} < {$tempFile} 2>&1";
            $output = shell_exec($command);
            unlink($tempFile);

            if (!$output || str_contains(strtolower($output), 'error')) {
                Log::error("Error tsql en obtenerBarcodesPDO: " . $output);
                return collect([]);
            }

            // Procesar la salida línea por línea
            $lines = explode("\n", $output);
            $barcodes = [];

            foreach ($lines as $line) {
                $line = trim($line);
                
                // Saltar líneas vacías o de configuración
                if (empty($line) || 
                    strpos($line, 'locale') !== false || 
                    strpos($line, 'Setting') !== false || 
                    strpos($line, 'Msg ') !== false || 
                    strpos($line, 'Warning:') !== false ||
                    preg_match('/^\d+>$/', $line) ||
                    preg_match('/^\d+>\s+\d+>\s+\d+>/', $line) ||
                    strpos($line, 'rows affected') !== false ||
                    strpos($line, 'KOPRAL') !== false) {
                    continue;
                }
                
                // Agregar código de barra si no está vacío
                if (!empty($line)) {
                    $barcodes[] = trim($line);
                }
            }

            return collect($barcodes)
                ->map(fn ($codigo) => trim($codigo))
                ->filter()
                ->values();
        } catch (\Exception $e) {
            Log::error("Error en obtenerBarcodesPDO para SKU {$sku}: " . $e->getMessage());
            return collect([]);
        }
    }

    /**
     * Previsualizar datos para insert en MAEEDO (sin ejecutar el insert)
     */
    private function previsualizarMAEEDO($temporal, $bodega, $tido)
    {
        try {
            // Verificar si debemos usar tsql
            $encrypt = env('SQLSRV_EXTERNAL_ENCRYPT', 'yes');
            $usarTSQL = ($encrypt === 'no' || $encrypt === false || $encrypt === 'false');
            
            if ($usarTSQL) {
                // Usar tsql para obtener siguiente ID
                $empresaEscapada = str_replace("'", "''", $bodega->empresa);
                $queryId = "SELECT TOP 1 ISNULL(MAX(IDMAEEDO), 0) + 1 AS siguiente_id FROM MAEEDO WHERE EMPRESA = '{$empresaEscapada}'";
                $outputId = $this->executeTSQL($queryId);
                $siguienteId = $this->parsearResultadoTSQL($outputId, 'siguiente_id');
                $siguienteId = (int) ($siguienteId ?? 1);
                if ($siguienteId <= 0) {
                    $siguienteId = 1;
                }

                // Usar tsql para obtener siguiente NUDO
                $tidoEscapado = str_replace("'", "''", $tido);
                $queryNudo = "SELECT TOP 1 CAST(NUDO AS INT) AS nudo_int FROM MAEEDO WHERE TIDO = '{$tidoEscapado}' AND ISNUMERIC(NUDO) = 1 ORDER BY CAST(NUDO AS INT) DESC";
                $outputNudo = $this->executeTSQL($queryNudo);
                $nudoInt = $this->parsearResultadoTSQL($outputNudo, 'nudo_int');
                $siguienteNudo = $nudoInt ? ((int) $nudoInt + 1) : 1;
            } else {
                // Usar conexión Laravel normal
                $connection = $this->sqlServerConnection();
                $resultId = $connection->selectOne("SELECT TOP 1 ISNULL(MAX(IDMAEEDO), 0) + 1 AS siguiente_id FROM MAEEDO WHERE EMPRESA = ?", [$bodega->empresa]);
                $siguienteId = (int) ($resultId->siguiente_id ?? 1);
                if ($siguienteId <= 0) {
                    $siguienteId = 1;
                }

                $resultNudo = $connection->selectOne("SELECT TOP 1 CAST(NUDO AS INT) AS nudo_int FROM MAEEDO WHERE TIDO = ? AND ISNUMERIC(NUDO) = 1 ORDER BY CAST(NUDO AS INT) DESC", [$tido]);
                $siguienteNudo = $resultNudo ? ((int) $resultNudo->nudo_int + 1) : 1;
            }
            
            $nudoFormateado = str_pad($siguienteNudo, 10, '0', STR_PAD_LEFT);
            
            // Valores según la tabla proporcionada
            // Usar valores del temporal si están disponibles, sino de la bodega
            $empresa = $temporal->empresa ?? $bodega->empresa ?? '02';
            $endo = '76427949-2';
            // Obtener código de funcionario del temporal, si no está, del usuario autenticado
            $funcionario = $temporal->funcionario ?? '';
            if (empty($funcionario) && auth()->user()) {
                $funcionario = auth()->user()->codigo_vendedor ?? '';
            }
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
            // Verificar si debemos usar tsql
            $encrypt = env('SQLSRV_EXTERNAL_ENCRYPT', 'yes');
            $usarTSQL = ($encrypt === 'no' || $encrypt === false || $encrypt === 'false');
            
            // Usar el IDMAEEDO y NUDO que ya fueron generados en MAEDDO
            $siguienteId = $idmaeedo;
            $nudoFormateado = $nudo;
            
            // Calcular totales desde MAEDDO (SUM de los campos correspondientes)
            if ($usarTSQL) {
                // Usar tsql para obtener totales
                $queryTotales = "
                    SELECT 
                        CAST(SUM(ISNULL(VAIVLI, 0)) AS VARCHAR(30)) + '|' +
                        CAST(SUM(ISNULL(VANELI, 0)) AS VARCHAR(30)) + '|' +
                        CAST(SUM(ISNULL(VABRLI, 0)) AS VARCHAR(30)) + '|' +
                        CAST(SUM(ISNULL(CAPRCO1, 0)) AS VARCHAR(30)) + '|' +
                        CAST(SUM(ISNULL(CAPRAD1, 0)) AS VARCHAR(30)) AS DATOS_TOTALES
                    FROM MAEDDO 
                    WHERE IDMAEEDO = {$idmaeedo}
                ";
                $outputTotales = $this->executeTSQL($queryTotales);
                
                // Parsear resultado
                $lines = explode("\n", $outputTotales);
                $totalesData = null;
                $encontradoHeader = false;
                
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line) || strpos($line, 'locale') !== false || strpos($line, 'Setting') !== false || 
                        preg_match('/^\d+>$/', $line) || strpos($line, 'rows affected') !== false) {
                        continue;
                    }
                    if (strpos($line, 'DATOS_TOTALES') !== false) {
                        $encontradoHeader = true;
                        continue;
                    }
                    if ($encontradoHeader && strpos($line, '|') !== false) {
                        $campos = explode('|', $line);
                        if (count($campos) >= 5) {
                            $totalesData = (object)[
                                'suma_vaivli' => trim($campos[0]),
                                'suma_vaneli' => trim($campos[1]),
                                'suma_vabrli' => trim($campos[2]),
                                'suma_caprco1' => trim($campos[3]),
                                'suma_caprad1' => trim($campos[4]),
                            ];
                            break;
                        }
                    }
                }
                $totales = $totalesData;
            } else {
                // Usar conexión Laravel normal
                $connection = $this->sqlServerConnection();
                $totales = $connection->selectOne("
                    SELECT 
                        SUM(ISNULL(VAIVLI, 0)) AS suma_vaivli,
                        SUM(ISNULL(VANELI, 0)) AS suma_vaneli,
                        SUM(ISNULL(VABRLI, 0)) AS suma_vabrli,
                        SUM(ISNULL(CAPRCO1, 0)) AS suma_caprco1,
                        SUM(ISNULL(CAPRAD1, 0)) AS suma_caprad1
                    FROM MAEDDO 
                    WHERE IDMAEEDO = ?
                ", [$idmaeedo]);
            }
            
            $vaivdo = (float) ($totales->suma_vaivli ?? 0);
            $vanedo = (float) ($totales->suma_vaneli ?? 0);
            $vabrdo = (float) ($totales->suma_vabrli ?? 0);
            // CAPRCO = SUM(MAEDDO->CAPRCO1)
            $caprco = (float) ($totales->suma_caprco1 ?? 0);
            // CAPRAD = SUM(MAEDDO->CAPRAD1)
            $caprad = (float) ($totales->suma_caprad1 ?? 0);
            
            // Valores según la tabla proporcionada
            // Usar valores del temporal si están disponibles, sino de la bodega
            // EMPRESA es char(2) y debe ser el código numérico (ej: '02'), no el nombre
            $empresaCodigo = $temporal->empresa ?? $bodega->empresa ?? '02';
            $empresa = $this->escapeSqlString($empresaCodigo); // EMPRESA usa el código numérico
            $endoRaw = '76427949-2'; // SETEADO EN LA APP según la tabla - CONFIRMAR SI ES CORRECTO
            // Obtener código de funcionario del temporal, si no está, del usuario autenticado
            $funcionarioRaw = $temporal->funcionario ?? '';
            if (empty($funcionarioRaw) && auth()->user()) {
                $funcionarioRaw = auth()->user()->codigo_vendedor ?? '';
            }
            $funcionario = $this->escapeSqlString($funcionarioRaw);
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
                if ($usarTSQL) {
                    // Usar tsql para obtener TAMODO
                    $queryTAMODO = "SELECT TOP 1 CAST(TAMODO AS VARCHAR(30)) AS TAMODO FROM MAEEDO WHERE TAMODO IS NOT NULL AND TAMODO > 0 ORDER BY IDMAEEDO DESC";
                    $outputTAMODO = $this->executeTSQL($queryTAMODO);
                    $tamodoValue = $this->parsearResultadoTSQL($outputTAMODO, 'TAMODO');
                    if ($tamodoValue) {
                        $tamodo = (float) $tamodoValue;
                    }
                } else {
                    // Usar conexión Laravel normal
                    $ultimoMAEEDO = $connection->selectOne("
                        SELECT TOP 1 TAMODO 
                        FROM MAEEDO 
                        WHERE TAMODO IS NOT NULL AND TAMODO > 0
                        ORDER BY IDMAEEDO DESC
                    ");
                    if ($ultimoMAEEDO && isset($ultimoMAEEDO->TAMODO)) {
                        $tamodo = (float) $ultimoMAEEDO->TAMODO;
                    }
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
                    GETDATE(), '{$funcionario}', 'C', 'S', {$caprco}, {$caprad}, 0, 0,
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
                'caprco' => $caprco,
                'caprad' => $caprad,
            ]);
            
            // Ejecutar SET IDENTITY_INSERT y el INSERT en una sola sentencia
            $sqlCompleto = "SET IDENTITY_INSERT MAEEDO ON;\n" . $insertMAEEDO . "\nSET IDENTITY_INSERT MAEEDO OFF;";
            
            if ($usarTSQL) {
                // Usar tsql para ejecutar el INSERT
                $this->executeTSQLStatement($sqlCompleto);
            } else {
                // Usar conexión Laravel normal
                $connection->statement($sqlCompleto);
            }
            
            Log::info("✓ MAEEDO insertado correctamente con totales desde MAEDDO", [
                'idmaeedo' => $siguienteId,
                'nudo' => $nudoFormateado,
                'vaivdo' => $vaivdo,
                'vanedo' => $vanedo,
                'vabrdo' => $vabrdo,
                'caprco' => $caprco,
                'caprad' => $caprad,
            ]);
            
            // Insertar en MAEEDOOB con observación "Documento GESTPRO"
            try {
                $obdo = 'Documento GESTPRO';
                $obdoEscapado = $this->escapeSqlString($obdo);
                
                $insertMAEEDOOB = "
                    INSERT INTO MAEEDOOB (
                        IDMAEEDO, OBDO
                    ) VALUES (
                        {$siguienteId}, '{$obdoEscapado}'
                    )
                ";
                
                if ($usarTSQL) {
                    // Usar tsql para ejecutar el INSERT
                    $this->executeTSQLStatement($insertMAEEDOOB);
                } else {
                    // Usar conexión Laravel normal
                    $connection->statement($insertMAEEDOOB);
                }
                
                Log::info("✓ MAEEDOOB insertado correctamente", [
                    'idmaeedo' => $siguienteId,
                    'obdo' => $obdo,
                ]);
            } catch (\Throwable $e) {
                // No lanzar excepción, solo loguear el error ya que MAEEDOOB es opcional
                Log::warning('Error insertando MAEEDOOB (no crítico): ' . $e->getMessage(), [
                    'idmaeedo' => $siguienteId,
                    'exception' => $e->getMessage(),
                ]);
            }
            
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
            // Verificar si debemos usar tsql
            $encrypt = env('SQLSRV_EXTERNAL_ENCRYPT', 'yes');
            $usarTSQL = ($encrypt === 'no' || $encrypt === false || $encrypt === 'false');
            
            if ($usarTSQL) {
                // Usar tsql para obtener siguiente ID
                $empresaEscapada = str_replace("'", "''", $bodega->empresa);
                $queryId = "SELECT TOP 1 ISNULL(MAX(IDMAEDDO), 0) + 1 AS siguiente_id FROM MAEDDO WHERE EMPRESA = '{$empresaEscapada}'";
                $outputId = $this->executeTSQL($queryId);
                $siguienteId = $this->parsearResultadoTSQL($outputId, 'siguiente_id');
                $siguienteId = (int) ($siguienteId ?? 1);
                if ($siguienteId <= 0) {
                    $siguienteId = 1;
                }
            } else {
                // Usar conexión Laravel normal
                $connection = $this->sqlServerConnection();
                $resultId = $connection->selectOne("SELECT TOP 1 ISNULL(MAX(IDMAEDDO), 0) + 1 AS siguiente_id FROM MAEDDO WHERE EMPRESA = ?", [$bodega->empresa]);
                $siguienteId = (int) ($resultId->siguiente_id ?? 1);
                if ($siguienteId <= 0) {
                    $siguienteId = 1;
                }
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
            // CAPRCO1 = TEMPORAL->STFI1 (siempre positivo, puede tener decimales)
            $caprco1 = abs((float) ($temporal->stfi1 ?? 0));
            // CAPRAD1 = TEMPORAL->STFI1 (siempre positivo, puede tener decimales)
            $caprad1 = $caprco1;
            // CAPRCO2 = TEMPORAL->STFI2 (siempre positivo, puede tener decimales)
            $caprco2 = abs((float) ($temporal->stfi2 ?? 0));
            // CAPRAD2 = TEMPORAL->STFI2 (siempre positivo, puede tener decimales)
            $caprad2 = $caprco2;
            $koprct = trim($temporal->sku);
            $nokopr = trim($temporal->nombre_producto);
            
            // Truncar nombre si es muy largo (NOKOPR en MAEDDO es varchar(50))
            if (strlen($nokopr) > 50) {
                $nokopr = substr($nokopr, 0, 50);
            }
            
            // CONSULTAR MAEPREM para obtener PPUL01 (precio de lista 1)
            // Usar empresa (código numérico) sin escapar para la consulta preparada
            $empresaParaConsulta = $empresaCodigo;
            $precioPPUL01 = 0;
            try {
                $maeprem = $connection->selectOne("
                    SELECT ISNULL(PPUL01, 0) AS PPUL01 
                    FROM MAEPREM 
                    WHERE KOPR = ? AND EMPRESA = ?
                ", [trim($temporal->sku), $empresaParaConsulta]);
                
                $precioPPUL01 = (float) ($maeprem->PPUL01 ?? 0);
            } catch (\Exception $e) {
                Log::warning("No se pudo obtener precio PPUL01 de MAEPREM para {$temporal->sku}: " . $e->getMessage());
                $precioPPUL01 = 0;
            }
            
            // Calcular campos según la tabla proporcionada
            // PPPRNELT = MAEPREM->PPUL01 (decimal)
            $ppprnelt = $precioPPUL01;
            // PPPRNE = MAEPREM->PPUL01 (decimal)
            $ppprne = $precioPPUL01;
            // PPPRPM = MAEPREM->PPUL01
            $ppprpm = $precioPPUL01;
            // PPPRNERE1 = MAEPREM->PPUL01
            $ppprnere1 = $precioPPUL01;
            // PPPRNERE2 = MAEPREM->PPUL01
            $ppprnere2 = $precioPPUL01;
            // PPPRPMSUC = MAEPREM->PPUL01
            $ppprpmsuc = $precioPPUL01;
            // PPPRPMIFRS = MAEPREM->PPUL01
            $ppprpmifrs = $precioPPUL01;
            
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
            // Verificar si debemos usar tsql
            $encrypt = env('SQLSRV_EXTERNAL_ENCRYPT', 'yes');
            $usarTSQL = ($encrypt === 'no' || $encrypt === false || $encrypt === 'false');
            
            // PRIMERO: Generar IDMAEEDO y NUDO que se usarán tanto en MAEDDO como en MAEEDO
            // Para las consultas SQL usar el código numérico de empresa
            $empresaCodigo = $temporal->empresa ?? $bodega->empresa ?? '02';
            
            if ($usarTSQL) {
                // Usar tsql para obtener siguiente IDMAEEDO
                $empresaEscapada = str_replace("'", "''", $empresaCodigo);
                $queryId = "SELECT TOP 1 ISNULL(MAX(IDMAEEDO), 0) + 1 AS siguiente_id FROM MAEEDO WHERE EMPRESA = '{$empresaEscapada}'";
                $outputId = $this->executeTSQL($queryId);
                $idmaeedo = (int) ($this->parsearResultadoTSQL($outputId, 'siguiente_id') ?? 1);
                if ($idmaeedo <= 0) {
                    $idmaeedo = 1;
                }

                // Usar tsql para obtener siguiente NUDO
                $tidoEscapado = str_replace("'", "''", $tido);
                $queryNudo = "SELECT TOP 1 CAST(NUDO AS INT) AS nudo_int FROM MAEEDO WHERE TIDO = '{$tidoEscapado}' AND ISNUMERIC(NUDO) = 1 ORDER BY CAST(NUDO AS INT) DESC";
                $outputNudo = $this->executeTSQL($queryNudo);
                $nudoInt = $this->parsearResultadoTSQL($outputNudo, 'nudo_int');
                $siguienteNudo = $nudoInt ? ((int) $nudoInt + 1) : 1;
                $nudo = str_pad($siguienteNudo, 10, '0', STR_PAD_LEFT);
                
                // Usar tsql para obtener siguiente IDMAEDDO
                $queryIdMAEDDO = "SELECT TOP 1 ISNULL(MAX(IDMAEDDO), 0) + 1 AS siguiente_id FROM MAEDDO WHERE EMPRESA = '{$empresaEscapada}'";
                $outputIdMAEDDO = $this->executeTSQL($queryIdMAEDDO);
                $siguienteId = (int) ($this->parsearResultadoTSQL($outputIdMAEDDO, 'siguiente_id') ?? 1);
                if ($siguienteId <= 0) {
                    $siguienteId = 1;
                }
            } else {
                // Usar conexión Laravel normal
                $connection = $this->sqlServerConnection();
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
            }

            // Valores para MAEDDO según la tabla proporcionada
            // EMPRESA es char(2) y debe ser el código numérico (ej: '02'), no el nombre
            $empresaCodigo = $temporal->empresa ?? $bodega->empresa ?? '02';
            $empresa = $this->escapeSqlString($empresaCodigo); // EMPRESA usa el código numérico
            $endo = '76427949-2'; // SETEADO EN LA APP
            // Obtener código de funcionario del temporal, si no está, del usuario autenticado
            $funcionarioRaw = $temporal->funcionario ?? '';
            if (empty($funcionarioRaw) && auth()->user()) {
                $funcionarioRaw = auth()->user()->codigo_vendedor ?? '';
            }
            $funcionario = $this->escapeSqlString($funcionarioRaw);
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
            // CAPRCO1 = TEMPORAL->STFI1 (siempre positivo, puede tener decimales)
            $caprco1 = abs((float) ($temporal->stfi1 ?? 0));
            // CAPRAD1 = TEMPORAL->STFI1 (siempre positivo, puede tener decimales)
            $caprad1 = $caprco1;
            // CAPRCO2 = TEMPORAL->STFI2 (siempre positivo, puede tener decimales)
            $caprco2 = abs((float) ($temporal->stfi2 ?? 0));
            // CAPRAD2 = TEMPORAL->STFI2 (siempre positivo, puede tener decimales)
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
            
            // CONSULTAR MAEPREM para obtener PPUL01 (precio de lista 1)
            // Usar empresa sin escapar para la consulta preparada
            $empresaParaConsulta = $temporal->empresa ?? $bodega->empresa ?? '02';
            $precioPPUL01 = 0;
            try {
                if ($usarTSQL) {
                    // Usar tsql para consultar MAEPREM
                    $skuEscapado = str_replace("'", "''", trim($temporal->sku));
                    $empresaEscapada = str_replace("'", "''", $empresaParaConsulta);
                    // Usar CAST para asegurar formato decimal en la salida
                    $queryPPUL01 = "SELECT TOP 1 CAST(ISNULL(PPUL01, 0) AS VARCHAR(30)) AS PPUL01 FROM MAEPREM WHERE KOPR = '{$skuEscapado}' AND EMPRESA = '{$empresaEscapada}'";
                    $outputPPUL01 = $this->executeTSQL($queryPPUL01);
                    $ppul01Value = $this->parsearResultadoTSQL($outputPPUL01, 'PPUL01');
                    $precioPPUL01 = (float) ($ppul01Value ?? 0);
                } else {
                    // Usar conexión Laravel normal
                    $maeprem = $connection->selectOne("
                        SELECT ISNULL(PPUL01, 0) AS PPUL01 
                        FROM MAEPREM 
                        WHERE KOPR = ? AND EMPRESA = ?
                    ", [trim($temporal->sku), $empresaParaConsulta]);
                    
                    $precioPPUL01 = (float) ($maeprem->PPUL01 ?? 0);
                }
                
                Log::info("Precio PPUL01 obtenido de MAEPREM", [
                    'sku' => $temporal->sku,
                    'empresa' => $empresaParaConsulta,
                    'ppul01' => $precioPPUL01,
                ]);
            } catch (\Exception $e) {
                Log::warning("No se pudo obtener precio PPUL01 de MAEPREM para {$temporal->sku}: " . $e->getMessage());
                $precioPPUL01 = 0;
            }
            
            // Calcular campos según la tabla proporcionada
            // PPPRNELT = MAEPREM->PPUL01 (decimal)
            $ppprnelt = $precioPPUL01;
            // PPPRNE = MAEPREM->PPUL01 (decimal)
            $ppprne = $precioPPUL01;
            // PPPRPM = MAEPREM->PPUL01
            $ppprpm = $precioPPUL01;
            // PPPRNERE1 = MAEPREM->PPUL01
            $ppprnere1 = $precioPPUL01;
            // PPPRNERE2 = MAEPREM->PPUL01
            $ppprnere2 = $precioPPUL01;
            // PPPRPMSUC = MAEPREM->PPUL01
            $ppprpmsuc = $precioPPUL01;
            // PPPRPMIFRS = MAEPREM->PPUL01
            $ppprpmifrs = $precioPPUL01;
            
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
                'precio_ppul01' => $precioPPUL01,
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
                'luvtlido' => $luvtlido,
                'bosulido' => $bosulido,
                'temporal_kosu' => $temporal->kosu,
                'temporal_centro_costo' => $temporal->centro_costo,
                'bodega_kosu' => $bodega->kosu ?? null,
                'bodega_centro_costo' => $bodega->centro_costo ?? null,
            ]);
            
            // Ejecutar SET IDENTITY_INSERT y el INSERT en una sola sentencia
            $sqlCompleto = "SET IDENTITY_INSERT MAEDDO ON;\n" . $insertMAEDDO . "\nSET IDENTITY_INSERT MAEDDO OFF;";
            
            if ($usarTSQL) {
                // Usar tsql para ejecutar el INSERT
                $this->executeTSQLStatement($sqlCompleto);
            } else {
                // Usar conexión Laravel normal
                $connection->statement($sqlCompleto);
            }
            
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
            // Verificar si debemos usar tsql
            $encrypt = env('SQLSRV_EXTERNAL_ENCRYPT', 'yes');
            $usarTSQL = ($encrypt === 'no' || $encrypt === false || $encrypt === 'false');
            
            $sku = trim($temporal->sku);
            $empresa = $temporal->empresa ?? $bodega->empresa ?? '02';
            $kobo = $temporal->kobo ?? $bodega->kobo ?? '';
            
            // Consultar stock actual desde MAEST antes de calcular DIF
            if ($usarTSQL) {
                // Usar tsql para obtener stock actual
                $skuEscapado = str_replace("'", "''", $sku);
                $empresaEscapada = str_replace("'", "''", $empresa);
                $koboEscapado = str_replace("'", "''", $kobo);
                $queryStock = "
                    SELECT TOP 1
                        CAST(ISNULL(STFI1, 0) AS VARCHAR(30)) + '|' +
                        CAST(ISNULL(STFI2, 0) AS VARCHAR(30)) AS DATOS_STOCK
                    FROM MAEST 
                    WHERE KOPR = '{$skuEscapado}' 
                      AND EMPRESA = '{$empresaEscapada}'
                      AND KOBO = '{$koboEscapado}'
                ";
                $outputStock = $this->executeTSQL($queryStock);
                
                // Parsear resultado
                $lines = explode("\n", $outputStock);
                $stockData = null;
                $encontradoHeader = false;
                
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line) || strpos($line, 'locale') !== false || strpos($line, 'Setting') !== false || 
                        preg_match('/^\d+>$/', $line) || strpos($line, 'rows affected') !== false) {
                        continue;
                    }
                    if (strpos($line, 'DATOS_STOCK') !== false) {
                        $encontradoHeader = true;
                        continue;
                    }
                    if ($encontradoHeader && strpos($line, '|') !== false) {
                        $campos = explode('|', $line);
                        if (count($campos) >= 2) {
                            $stockData = (object)[
                                'stfi1_actual' => trim($campos[0]),
                                'stfi2_actual' => trim($campos[1]),
                            ];
                            break;
                        }
                    }
                }
                $stockActual = $stockData;
            } else {
                // Usar conexión Laravel normal
                $connection = $this->sqlServerConnection();
                $stockActual = $connection->selectOne("
                    SELECT 
                        ISNULL(STFI1, 0) AS stfi1_actual,
                        ISNULL(STFI2, 0) AS stfi2_actual
                    FROM MAEST 
                    WHERE KOPR = ? 
                      AND EMPRESA = ?
                      AND KOBO = ?
                ", [$sku, $empresa, $kobo]);
            }
            
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
            $skuEscapado = str_replace("'", "''", $sku);
            $empresaEscapada = str_replace("'", "''", $empresa);
            $koboEscapado = str_replace("'", "''", $kobo);
            $captura1 = (float) $temporal->captura_1;
            $captura2 = $temporal->captura_2 !== null ? (float) $temporal->captura_2 : 'NULL';
            
            $updateMAEST = "
                UPDATE MAEST 
                SET STFI1 = {$captura1},
                    STFI2 = " . ($captura2 !== 'NULL' ? $captura2 : "ISNULL(STFI2, 0)") . "
                WHERE LTRIM(RTRIM(KOPR)) = '{$skuEscapado}' 
                  AND EMPRESA = '{$empresaEscapada}'
                  AND LTRIM(RTRIM(KOBO)) = '{$koboEscapado}'
            ";
            
            if ($usarTSQL) {
                // Usar tsql para ejecutar UPDATE
                $this->executeTSQLStatement($updateMAEST);
                $rowsAffectedMAEST = 1; // tsql no devuelve filas afectadas fácilmente
            } else {
                // Usar conexión Laravel normal
                $rowsAffectedMAEST = $connection->update($updateMAEST, [
                    $temporal->captura_1,
                    $temporal->captura_2,
                    $sku,
                    $empresa,
                    $kobo,
                ]);
            }
            
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
                SET STFI1 = ISNULL(STFI1, 0) + {$dif1},
                    STFI2 = ISNULL(STFI2, 0) + {$dif2}
                WHERE LTRIM(RTRIM(KOPR)) = '{$skuEscapado}'
            ";
            
            if ($usarTSQL) {
                // Usar tsql para ejecutar UPDATE
                $this->executeTSQLStatement($updateMAEPR);
                $rowsAffectedMAEPR = 1; // tsql no devuelve filas afectadas fácilmente
            } else {
                // Usar conexión Laravel normal
                $rowsAffectedMAEPR = $connection->update($updateMAEPR, [
                    $dif1,
                    $dif2,
                    $sku,
                ]);
            }
            
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
                SET STFI1 = ISNULL(STFI1, 0) + {$dif1},
                    STFI2 = ISNULL(STFI2, 0) + {$dif2}
                WHERE LTRIM(RTRIM(KOPR)) = '{$skuEscapado}' 
                  AND EMPRESA = '{$empresaEscapada}'
            ";
            
            if ($usarTSQL) {
                // Usar tsql para ejecutar UPDATE
                $this->executeTSQLStatement($updateMAEPREM);
                $rowsAffectedMAEPREM = 1; // tsql no devuelve filas afectadas fácilmente
            } else {
                // Usar conexión Laravel normal
                $rowsAffectedMAEPREM = $connection->update($updateMAEPREM, [
                    $dif1,
                    $dif2,
                    $sku,
                    $empresa,
                ]);
            }
            
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
        $encrypt = env('SQLSRV_EXTERNAL_ENCRYPT', 'yes');
        
        // Si encrypt=no, no podemos usar conexión directa (SQL Server 2012)
        // Los métodos que usan esto deben usar executeTSQL() en su lugar
        if ($encrypt === 'no' || $encrypt === false || $encrypt === 'false') {
            throw new \Exception('No se puede usar conexión directa cuando SQLSRV_EXTERNAL_ENCRYPT=no. Use executeTSQL() en su lugar.');
        }
        
        return DB::connection('sqlsrv_external');
    }

    /**
     * Ejecutar query SQL usando tsql (para SQL Server 2012 sin TLS)
     */
    private function executeTSQL(string $query): string
    {
        $host = env('SQLSRV_EXTERNAL_HOST');
        $port = env('SQLSRV_EXTERNAL_PORT', '1433');
        $database = env('SQLSRV_EXTERNAL_DATABASE');
        $username = env('SQLSRV_EXTERNAL_USERNAME');
        $password = env('SQLSRV_EXTERNAL_PASSWORD');

        $tempFile = tempnam(sys_get_temp_dir(), 'sql_tsql_');
        file_put_contents($tempFile, $query . "\ngo\nquit");
        
        $command = "tsql -H {$host} -p {$port} -U {$username} -P {$password} -D {$database} < {$tempFile} 2>&1";
        $output = shell_exec($command);
        unlink($tempFile);

        if (!$output) {
            throw new \Exception('No se obtuvo respuesta de tsql');
        }

        if (str_contains(strtolower($output), 'error') || str_contains($output, 'Msg ')) {
            Log::error("Error en executeTSQL: " . $output);
            throw new \Exception('Error ejecutando query tsql: ' . substr($output, 0, 500));
        }

        return $output;
    }

    /**
     * Ejecutar INSERT/UPDATE usando tsql (para SQL Server 2012 sin TLS)
     */
    private function executeTSQLStatement(string $query): bool
    {
        try {
            $output = $this->executeTSQL($query);
            // Verificar que no haya errores
            if (str_contains(strtolower($output), 'error') || 
                (str_contains($output, 'Msg ') && !str_contains(strtolower($output), 'rows affected'))) {
                Log::error("Error en executeTSQLStatement: " . $output);
                // Verificar si es error de tabla no encontrada
                if (str_contains($output, "Invalid object name")) {
                    throw new \Exception("La tabla no existe en SQL Server. Por favor, ejecute el script sql-scripts/create_tinventario_table.sql para crear la tabla TINVENTARIO.");
                }
                return false;
            }
            return true;
        } catch (\Exception $e) {
            Log::error("Exception en executeTSQLStatement: " . $e->getMessage());
            // Re-lanzar si es un error de tabla no encontrada
            if (str_contains($e->getMessage(), "Invalid object name")) {
                throw new \Exception("La tabla no existe en SQL Server. Por favor, ejecute el script sql-scripts/create_tinventario_table.sql para crear la tabla TINVENTARIO.");
            }
            return false;
        }
    }

    /**
     * Parsear resultado de tsql para obtener un valor simple (MAX, COUNT, etc.)
     */
    private function parsearResultadoTSQL(string $output, string $campoNombre): ?string
    {
        $lines = explode("\n", $output);
        $encontradoHeader = false;

        foreach ($lines as $line) {
            $line = trim($line);
            
            if (empty($line) || 
                strpos($line, 'locale') !== false || 
                strpos($line, 'Setting') !== false || 
                preg_match('/^\d+>$/', $line) ||
                strpos($line, 'rows affected') !== false) {
                continue;
            }
            
            // Detectar header
            if (strpos($line, $campoNombre) !== false && strpos($line, '|') === false) {
                $encontradoHeader = true;
                continue;
            }
            
            // Buscar línea con datos (número entero o decimal)
            // Acepta: 123, 123.45, 123.456, 1.5521e+003, etc.
            if ($encontradoHeader && (
                preg_match('/^-?\d+\.?\d*$/', $line) || 
                preg_match('/^-?\d+\.\d+[eE][+-]?\d+$/', $line) ||
                preg_match('/^-?\d+\.\d+$/', $line)
            )) {
                return $line;
            }
        }

        return null;
    }

    /**
     * Ejecutar SELECT y parsear múltiples filas desde tsql
     * Retorna array de objetos stdClass similar a DB::select()
     */
    private function ejecutarSelectTSQL(string $query, array $campos): array
    {
        $output = $this->executeTSQL($query);
        return $this->parsearResultadosMultiplesTSQL($output, $campos);
    }

    /**
     * Parsear múltiples filas desde output de tsql con pipes
     */
    private function parsearResultadosMultiplesTSQL(string $output, array $campos): array
    {
        $lines = explode("\n", $output);
        $resultados = [];
        $encontradoHeader = false;

        foreach ($lines as $line) {
            $line = trim($line);
            
            if (empty($line) || 
                strpos($line, 'locale') !== false || 
                strpos($line, 'Setting') !== false || 
                preg_match('/^\d+>$/', $line) ||
                strpos($line, 'rows affected') !== false) {
                continue;
            }
            
            // Detectar header (buscar alguno de los campos o "DATOS")
            if (!$encontradoHeader) {
                if (strpos($line, 'DATOS') !== false || strpos($line, $campos[0]) !== false) {
                    $encontradoHeader = true;
                    continue;
                }
            }
            
            // Procesar líneas de datos (que tengan pipes)
            if ($encontradoHeader && strpos($line, '|') !== false) {
                $valores = explode('|', $line);
                $fila = new \stdClass();
                foreach ($campos as $index => $campo) {
                    $fila->{$campo} = isset($valores[$index]) ? trim($valores[$index]) : null;
                }
                $resultados[] = $fila;
            } elseif ($encontradoHeader && !empty($line) && preg_match('/^\d+$/', $line)) {
                // Para consultas simples como SELECT DISTINCT campo (sin pipes)
                $fila = new \stdClass();
                $fila->{$campos[0]} = $line;
                $resultados[] = $fila;
            }
        }

        return $resultados;
    }

    /**
     * Mostrar historial de capturas y códigos de barras
     */
    public function historial(Request $request)
    {
        $user = auth()->user();
        
        // Obtener filtros del request
        $fechaDesde = $request->get('fecha_desde', '');
        $fechaHasta = $request->get('fecha_hasta', '');
        $usuario = $request->get('usuario', '');
        $tipo = $request->get('tipo', ''); // GDI o GRI
        $bodegaId = $request->get('bodega_id', '');
        $ubicacionId = $request->get('ubicacion_id', '');
        $codigoBuscar = $request->get('codigo_buscar', ''); // Código de barra o código de producto
        
        // Construir query para capturas
        $capturasQuery = Temporal::with(['bodega', 'ubicacion']);
        
        // Filtro por usuario (funcionario)
        if ($usuario) {
            $capturasQuery->where('funcionario', $usuario);
        } else {
            // Si el usuario es Super Admin o Manejo Stock, mostrar todos los registros
            // Si no, mostrar solo los del usuario actual o los que no tienen funcionario asignado
            if ($user->hasRole('Super Admin') || $user->hasRole('Manejo Stock')) {
                // Mostrar todos los registros
            } else {
                $codigoVendedor = $user->codigo_vendedor ?? 'PZZ';
                // Mostrar registros del usuario o registros sin funcionario (null o vacío)
                $capturasQuery->where(function($query) use ($codigoVendedor) {
                    $query->where('funcionario', $codigoVendedor)
                          ->orWhereNull('funcionario')
                          ->orWhere('funcionario', '');
                });
            }
        }
        
        // Filtro por rango de fechas
        if ($fechaDesde) {
            $capturasQuery->whereDate('created_at', '>=', $fechaDesde);
        }
        if ($fechaHasta) {
            $capturasQuery->whereDate('created_at', '<=', $fechaHasta);
        }
        
        // Filtro por tipo (TIDO: GDI o GRI)
        if ($tipo && in_array($tipo, ['GDI', 'GRI'])) {
            $capturasQuery->where('tido', $tipo);
        }
        
        // Filtro por bodega
        if ($bodegaId) {
            $capturasQuery->where('bodega_id', $bodegaId);
        }
        
        // Filtro por ubicación
        if ($ubicacionId) {
            $capturasQuery->where('ubicacion_id', $ubicacionId);
        }
        
        // Filtro por código (SKU)
        if ($codigoBuscar) {
            $codigoBuscar = trim($codigoBuscar);
            $capturasQuery->where('sku', 'LIKE', "%{$codigoBuscar}%");
        }
        
        $capturas = $capturasQuery->orderBy('created_at', 'desc')->paginate(20)->appends($request->query());
        
        // Obtener modificaciones de códigos de barras del usuario (sin filtros por ahora)
        $codigosBarras = CodigoBarraLog::with(['bodega', 'user'])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        
        // Obtener listas para los filtros
        $bodegas = Bodega::orderBy('nombre_bodega')->get();
        
        // Obtener lista de funcionarios únicos de las capturas
        $funcionarios = Temporal::select('funcionario')
            ->whereNotNull('funcionario')
            ->where('funcionario', '!=', '')
            ->distinct()
            ->orderBy('funcionario')
            ->pluck('funcionario');
        
        // Obtener ubicaciones si hay una bodega seleccionada
        $ubicaciones = collect();
        if ($bodegaId) {
            $ubicaciones = Ubicacion::where('bodega_id', $bodegaId)
                ->orderBy('codigo')
                ->get();
        }
        
        return view('manejo-stock.historial', [
            'capturas' => $capturas,
            'codigosBarras' => $codigosBarras,
            'bodegas' => $bodegas,
            'ubicaciones' => $ubicaciones,
            'funcionarios' => $funcionarios,
            'filtros' => [
                'fecha_desde' => $fechaDesde,
                'fecha_hasta' => $fechaHasta,
                'usuario' => $usuario,
                'tipo' => $tipo,
                'bodega_id' => $bodegaId,
                'ubicacion_id' => $ubicacionId,
                'codigo_buscar' => $codigoBuscar,
            ],
        ]);
    }

    /**
     * Mostrar reporte de documentos GDI/GRI con observación "Documento GESTPRO"
     */
    public function reporte(Request $request)
    {
        try {
            // Verificar si debemos usar tsql
            $encrypt = env('SQLSRV_EXTERNAL_ENCRYPT', 'yes');
            $usarTSQL = ($encrypt === 'no' || $encrypt === false || $encrypt === 'false');
            
            // Obtener filtros del request
            $fechaDesde = $request->get('fecha_desde', date('Y-m-d', strtotime('-30 days')));
            $fechaHasta = $request->get('fecha_hasta', date('Y-m-d'));
            $usuario = $request->get('usuario', '');
            $bodegaCodigo = $request->get('bodega_codigo', '');
            
            // Obtener lista de bodegas para el filtro
            $bodegas = \App\Models\Bodega::orderBy('nombre_bodega')->get();
            
            // Obtener lista de usuarios (funcionarios) que han creado documentos GDI/GRI
            if ($usarTSQL) {
                $queryUsuarios = "
                    SELECT CAST(KOFUDO AS VARCHAR(20)) AS KOFUDO
                    FROM (
                        SELECT DISTINCT MAEEDO.KOFUDO
                        FROM MAEEDO
                        WHERE MAEEDO.TIDO IN ('GDI', 'GRI')
                          AND MAEEDO.KOFUDO IS NOT NULL
                          AND MAEEDO.KOFUDO != ''
                    ) AS usuarios_distintos
                    ORDER BY KOFUDO
                ";
                $usuarios = $this->ejecutarSelectTSQL($queryUsuarios, ['KOFUDO']);
            } else {
                $connection = $this->sqlServerConnection();
                $usuarios = $connection->select("
                    SELECT DISTINCT MAEEDO.KOFUDO
                    FROM MAEEDO
                    WHERE MAEEDO.TIDO IN ('GDI', 'GRI')
                      AND MAEEDO.KOFUDO IS NOT NULL
                      AND MAEEDO.KOFUDO != ''
                    ORDER BY MAEEDO.KOFUDO
                ");
            }
            
            // Primero, verificar si hay documentos GDI/GRI sin filtro de observación para debugging
            if (!$usarTSQL) {
                $queryDebug = "
                    SELECT COUNT(*) AS total
                    FROM MAEEDO
                    WHERE MAEEDO.TIDO IN ('GDI', 'GRI')
                ";
                $debugResult = $connection->selectOne($queryDebug);
                Log::info("Debug reporte - Total documentos GDI/GRI: " . ($debugResult->total ?? 0));
                
                // Verificar documentos con observación (sin filtro de fecha primero)
                $queryDebugObs = "
                    SELECT COUNT(*) AS total
                    FROM MAEEDO
                    INNER JOIN MAEEDOOB ON MAEEDO.IDMAEEDO = MAEEDOOB.IDMAEEDO
                    WHERE MAEEDO.TIDO IN ('GDI', 'GRI')
                      AND LTRIM(RTRIM(MAEEDOOB.OBDO)) = 'Documento GESTPRO'
                ";
                $debugObsResult = $connection->selectOne($queryDebugObs);
                Log::info("Debug reporte - Total documentos con observación 'Documento GESTPRO': " . ($debugObsResult->total ?? 0));
                
                // Verificar algunos ejemplos de OBDO para ver qué valores tienen
                $queryDebugObsValues = "
                    SELECT TOP 10 
                        MAEEDO.IDMAEEDO,
                        MAEEDO.TIDO,
                        MAEEDOOB.OBDO,
                        LEN(MAEEDOOB.OBDO) AS LEN_OBDO
                    FROM MAEEDO
                    INNER JOIN MAEEDOOB ON MAEEDO.IDMAEEDO = MAEEDOOB.IDMAEEDO
                    WHERE MAEEDO.TIDO IN ('GDI', 'GRI')
                ";
                $debugObsValues = $connection->select($queryDebugObsValues);
                Log::info("Debug reporte - Ejemplos de OBDO en documentos GDI/GRI:", ['ejemplos' => $debugObsValues]);
            }
            
            // Construir consulta con filtros usando pipes para tsql
            $queryBase = "
                SELECT 
                    CAST(MAEEDO.IDMAEEDO AS VARCHAR(30)) + '|' +
                    CAST(MAEEDO.TIDO AS VARCHAR(10)) + '|' +
                    CAST(MAEEDO.NUDO AS VARCHAR(20)) + '|' +
                    CAST(MAEEDO.FEEMDO AS VARCHAR(30)) + '|' +
                    CAST(ISNULL(MAEEDO.KOFUDO, '') AS VARCHAR(20)) + '|' +
                    CAST(ISNULL(MAEEDO.ENDO, '') AS VARCHAR(20)) + '|' +
                    CAST(ISNULL(MAEEDO.SUENDO, '') AS VARCHAR(20)) + '|' +
                    CAST(ISNULL(MAEEDO.VANEDO, 0) AS VARCHAR(30)) + '|' +
                    CAST(ISNULL(MAEEDO.VAIVDO, 0) AS VARCHAR(30)) + '|' +
                    CAST(ISNULL(MAEEDO.VABRDO, 0) AS VARCHAR(30)) + '|' +
                    CAST(ISNULL(MAEEDO.CAPRCO, 0) AS VARCHAR(30)) + '|' +
                    CAST(ISNULL(MAEEDO.CAPRAD, 0) AS VARCHAR(30)) + '|' +
                    CAST(LTRIM(RTRIM(ISNULL(MAEEDOOB.OBDO, ''))) AS VARCHAR(200)) + '|' +
                    CAST(ISNULL(MAEDDO.KOPRCT, '') AS VARCHAR(30)) + '|' +
                    CAST(REPLACE(ISNULL(MAEDDO.NOKOPR, ''), '|', ' ') AS VARCHAR(200)) + '|' +
                    CAST(ISNULL(MAEDDO.CAPRCO1, 0) AS VARCHAR(30)) + '|' +
                    CAST(ISNULL(MAEDDO.CAPRAD1, 0) AS VARCHAR(30)) + '|' +
                    CAST(ISNULL(MAEDDO.VANELI, 0) AS VARCHAR(30)) + '|' +
                    CAST(ISNULL(MAEDDO.VAIVLI, 0) AS VARCHAR(30)) + '|' +
                    CAST(ISNULL(MAEDDO.VABRLI, 0) AS VARCHAR(30)) + '|' +
                    CAST(ISNULL(MAEDDO.BOSULIDO, '') AS VARCHAR(10)) + '|' +
                    CAST(ISNULL(TABBO.NOKOBO, '') AS VARCHAR(100)) AS DATOS_REPORTE
                FROM MAEDDO
                INNER JOIN MAEEDO ON MAEDDO.IDMAEEDO = MAEEDO.IDMAEEDO
                LEFT JOIN MAEEDOOB ON MAEEDO.IDMAEEDO = MAEEDOOB.IDMAEEDO
                LEFT JOIN TABBO ON MAEDDO.BOSULIDO = TABBO.KOBO
                WHERE MAEEDO.TIDO IN ('GDI', 'GRI')
                  AND (MAEEDOOB.OBDO IS NULL OR LTRIM(RTRIM(MAEEDOOB.OBDO)) = 'Documento GESTPRO')
            ";
            
            // Construir WHERE con filtros escapados
            $whereClause = "";
            
            // Filtro por rango de fechas
            if ($fechaDesde) {
                $fechaDesdeEscapada = str_replace("'", "''", $fechaDesde);
                $whereClause .= " AND CAST(MAEEDO.FEEMDO AS DATE) >= '{$fechaDesdeEscapada}'";
            }
            
            if ($fechaHasta) {
                $fechaHastaEscapada = str_replace("'", "''", $fechaHasta);
                $whereClause .= " AND CAST(MAEEDO.FEEMDO AS DATE) <= '{$fechaHastaEscapada}'";
            }
            
            // Filtro por usuario (funcionario)
            if ($usuario) {
                $usuarioEscapado = str_replace("'", "''", $usuario);
                $whereClause .= " AND MAEEDO.KOFUDO = '{$usuarioEscapado}'";
            }
            
            // Filtro por bodega
            if ($bodegaCodigo) {
                $bodegaEscapada = str_replace("'", "''", $bodegaCodigo);
                $whereClause .= " AND MAEDDO.BOSULIDO = '{$bodegaEscapada}'";
            }
            
            $query = $queryBase . $whereClause . " ORDER BY MAEEDO.FEEMDO DESC, MAEEDO.IDMAEEDO DESC, MAEDDO.IDMAEDDO";
            
            // Log de la consulta para debugging
            Log::info("Consulta reporte GESTPRO", [
                'usarTSQL' => $usarTSQL,
                'fecha_desde' => $fechaDesde,
                'fecha_hasta' => $fechaHasta,
                'usuario' => $usuario,
                'bodega_codigo' => $bodegaCodigo,
            ]);
            
            // Ejecutar consulta
            if ($usarTSQL) {
                $output = $this->executeTSQL($query);
                $resultados = $this->parsearResultadosMultiplesTSQL($output, [
                    'IDMAEEDO', 'TIDO', 'NUDO', 'FEEMDO', 'FUNCIONARIO', 'ENDO', 'SUENDO',
                    'VANEDO', 'VAIVDO', 'VABRDO', 'CAPRCO', 'CAPRAD', 'OBSERVACION',
                    'CODIGO_PRODUCTO', 'NOMBRE_PRODUCTO', 'CAPRCO1', 'CAPRAD1',
                    'VANELI', 'VAIVLI', 'VABRLI', 'BODEGA_CODIGO', 'BODEGA_NOMBRE'
                ]);
            } else {
                if (!isset($connection)) {
                    $connection = $this->sqlServerConnection();
                }
                $params = [];
                if ($fechaDesde) $params[] = $fechaDesde;
                if ($fechaHasta) $params[] = $fechaHasta;
                if ($usuario) $params[] = $usuario;
                if ($bodegaCodigo) $params[] = $bodegaCodigo;
                $queryNormal = "
                    SELECT 
                        MAEEDO.IDMAEEDO,
                        MAEEDO.TIDO,
                        MAEEDO.NUDO,
                        MAEEDO.FEEMDO,
                        MAEEDO.KOFUDO AS FUNCIONARIO,
                        MAEEDO.ENDO,
                        MAEEDO.SUENDO,
                        MAEEDO.VANEDO,
                        MAEEDO.VAIVDO,
                        MAEEDO.VABRDO,
                        MAEEDO.CAPRCO,
                        MAEEDO.CAPRAD,
                        LTRIM(RTRIM(ISNULL(MAEEDOOB.OBDO, ''))) AS OBSERVACION,
                        MAEDDO.KOPRCT AS CODIGO_PRODUCTO,
                        MAEDDO.NOKOPR AS NOMBRE_PRODUCTO,
                        MAEDDO.CAPRCO1,
                        MAEDDO.CAPRAD1,
                        MAEDDO.VANELI,
                        MAEDDO.VAIVLI,
                        MAEDDO.VABRLI,
                        MAEDDO.BOSULIDO AS BODEGA_CODIGO,
                        TABBO.NOKOBO AS BODEGA_NOMBRE
                    FROM MAEDDO
                    INNER JOIN MAEEDO ON MAEDDO.IDMAEEDO = MAEEDO.IDMAEEDO
                    LEFT JOIN MAEEDOOB ON MAEEDO.IDMAEEDO = MAEEDOOB.IDMAEEDO
                    LEFT JOIN TABBO ON MAEDDO.BOSULIDO = TABBO.KOBO
                    WHERE MAEEDO.TIDO IN ('GDI', 'GRI')
                      AND (MAEEDOOB.OBDO IS NULL OR LTRIM(RTRIM(MAEEDOOB.OBDO)) = 'Documento GESTPRO')
                ";
                if ($fechaDesde) $queryNormal .= " AND CAST(MAEEDO.FEEMDO AS DATE) >= ?";
                if ($fechaHasta) $queryNormal .= " AND CAST(MAEEDO.FEEMDO AS DATE) <= ?";
                if ($usuario) $queryNormal .= " AND MAEEDO.KOFUDO = ?";
                if ($bodegaCodigo) $queryNormal .= " AND MAEDDO.BOSULIDO = ?";
                $queryNormal .= " ORDER BY MAEEDO.FEEMDO DESC, MAEEDO.IDMAEEDO DESC, MAEDDO.IDMAEDDO";
                $resultados = $connection->select($queryNormal, $params);
            }
            
            Log::info("Resultados encontrados: " . count($resultados));
            
            // Agrupar resultados por documento (IDMAEEDO)
            $documentos = [];
            foreach ($resultados as $row) {
                $idmaeedo = $row->IDMAEEDO;
                if (!isset($documentos[$idmaeedo])) {
                    $documentos[$idmaeedo] = [
                        'idmaeedo' => $row->IDMAEEDO,
                        'tido' => $row->TIDO,
                        'nudo' => $row->NUDO,
                        'feemdo' => $row->FEEMDO,
                        'funcionario' => $row->FUNCIONARIO,
                        'endo' => $row->ENDO,
                        'suendo' => $row->SUENDO,
                        'vanedo' => $row->VANEDO,
                        'vaivdo' => $row->VAIVDO,
                        'vabrdo' => $row->VABRDO,
                        'caprco' => $row->CAPRCO,
                        'caprad' => $row->CAPRAD,
                        'observacion' => $row->OBSERVACION,
                        'detalles' => [],
                    ];
                }
                
                // Agregar detalle del producto
                $documentos[$idmaeedo]['detalles'][] = [
                    'codigo_producto' => $row->CODIGO_PRODUCTO,
                    'nombre_producto' => $row->NOMBRE_PRODUCTO,
                    'caprco1' => $row->CAPRCO1,
                    'caprad1' => $row->CAPRAD1,
                    'vaneli' => $row->VANELI,
                    'vaivli' => $row->VAIVLI,
                    'vabrli' => $row->VABRLI,
                    'bodega_codigo' => $row->BODEGA_CODIGO,
                    'bodega_nombre' => $row->BODEGA_NOMBRE,
                ];
            }
            
            return view('manejo-stock.reporte', [
                'documentos' => $documentos,
                'bodegas' => $bodegas,
                'usuarios' => $usuarios,
                'fechaDesde' => $fechaDesde,
                'fechaHasta' => $fechaHasta,
                'usuario' => $usuario,
                'bodegaCodigo' => $bodegaCodigo,
            ]);
            
        } catch (\Throwable $e) {
            Log::error('Error en reporte de documentos GESTPRO', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return redirect()->route('manejo-stock.select')
                ->with('error', 'Error al generar el reporte: ' . $e->getMessage());
        }
    }

    /**
     * Vista de Aplicación de Barrido
     * Igual que contabilidad pero solo inserta en TINVENTARIO (SQL Server)
     * Sin actualización de stock
     */
    public function barridoSelect()
    {
        $bodegas = Bodega::with(['ubicaciones' => function ($query) {
            $query->orderBy('codigo');
        }])->orderBy('nombre_bodega')->get();

        return view('manejo-stock.barrido-select', [
            'bodegas' => $bodegas,
        ]);
    }

    public function barrido(Request $request)
    {
        $data = $request->validate([
            'bodega_id' => ['required', 'exists:bodegas,id'],
            'ubicacion_id' => ['nullable', 'exists:ubicaciones,id'],
        ]);

        $bodega = Bodega::with('ubicaciones')->findOrFail($data['bodega_id']);

        $ubicacion = null;
        if (!empty($data['ubicacion_id'])) {
            $ubicacion = Ubicacion::where('id', (int) $data['ubicacion_id'])
                ->where('bodega_id', $bodega->id)
                ->first();
        }

        return view('manejo-stock.barrido', [
            'bodega' => $bodega,
            'ubicacion' => $ubicacion,
        ]);
    }

    /**
     * Guardar registro de barrido en TINVENTARIO (SQL Server)
     * Solo INSERT, sin actualización de stock ni inserts a MAEEDO/MAEDDO
     */
    public function guardarBarrido(Request $request)
    {
        $data = $request->validate([
            'bodega_id' => ['required', 'exists:bodegas,id'],
            'ubicacion_id' => ['nullable', 'exists:ubicaciones,id'],
            'sku' => ['required', 'string', 'max:50'],
            'nombre_producto' => ['required', 'string', 'max:200'],
            'codigo_barras' => ['nullable', 'string', 'max:60'],
            'rlud' => ['nullable', 'numeric', 'min:0'],
            'unidad_medida_1' => ['nullable', 'string', 'max:10'],
            'unidad_medida_2' => ['nullable', 'string', 'max:10'],
            'cantidad' => ['required', 'numeric', 'min:0.001'],
            'funcionario' => ['nullable', 'string', 'max:20'],
        ]);

        try {
            $bodega = Bodega::findOrFail($data['bodega_id']);
            $ubicacion = !empty($data['ubicacion_id']) ? Ubicacion::find($data['ubicacion_id']) : null;
            
            // Determinar si usar tsql o conexión Laravel
            $encrypt = env('SQLSRV_EXTERNAL_ENCRYPT', 'yes');
            $usarTSQL = ($encrypt === 'no' || $encrypt === false || $encrypt === 'false');
            
            if (!$usarTSQL) {
                $connection = $this->sqlServerConnection();
            }

            // Calcular cantidad en unidad 2 usando RLUD
            $rlud = (float) ($data['rlud'] ?? 1);
            $captura1 = (float) $data['cantidad'];
            $captura2 = $rlud > 0 ? ($captura1 / $rlud) : 0;
            
            // STFI1 y STFI2 = 0 porque en barrido no hay diferencia (solo conteo)
            $stfi1 = 0;
            $stfi2 = 0;
            
            // Obtener código de funcionario del usuario autenticado si no viene en el request
            $funcionarioRaw = $data['funcionario'] ?? null;
            $user = auth()->user();
            
            Log::info("Obteniendo funcionario para barrido", [
                'funcionario_request' => $funcionarioRaw,
                'user_id' => $user?->id,
                'user_name' => $user?->name,
                'codigo_vendedor' => $user?->codigo_vendedor,
                'user_email' => $user?->email,
            ]);
            
            if (empty($funcionarioRaw) && $user) {
                $funcionarioRaw = $user->codigo_vendedor ?? '';
                // Si aún está vacío, intentar usar el username o email como fallback
                if (empty($funcionarioRaw)) {
                    $funcionarioRaw = $user->name ?? $user->email ?? '';
                    Log::warning("codigo_vendedor vacío, usando name/email como fallback", [
                        'funcionario_fallback' => $funcionarioRaw,
                    ]);
                }
            }
            $funcionario = $this->escapeSqlString($funcionarioRaw ?? '');
            $sku = $this->escapeSqlString(trim($data['sku']));
            $nombreProducto = $this->escapeSqlString(trim($data['nombre_producto']));
            $codigoUbicacion = $this->escapeSqlString($ubicacion?->codigo ?? '');
            $empresa = $this->escapeSqlString($bodega->empresa ?? '02');
            $kobo = $this->escapeSqlString($bodega->kobo ?? '');
            $kosu = $this->escapeSqlString($bodega->kosu ?? '');
            $cc = $this->escapeSqlString($bodega->centro_costo ?? '');
            $ud1 = $this->escapeSqlString($data['unidad_medida_1'] ?? '');
            $ud2 = $this->escapeSqlString($data['unidad_medida_2'] ?? '');

            // INSERT a TINVENTARIO en SQL Server
            // Campos: EMPRESA, KOSU, KOBO, CC, SKU, NOKOPR, RLUD, UD01PR, UD02PR, 
            //         CAPTURA1, CAPTURA2, STFI1, STFI2, FUNCIONARIO, FECHA, UBICACION
            $insertSQL = "
                INSERT INTO TINVENTARIO (
                    EMPRESA, KOSU, KOBO, CC, SKU, NOKOPR, 
                    RLUD, UD01PR, UD02PR,
                    CAPTURA1, CAPTURA2, STFI1, STFI2,
                    FUNCIONARIO, FECHA, UBICACION
                ) VALUES (
                    '{$empresa}', '{$kosu}', '{$kobo}', '{$cc}', '{$sku}', '{$nombreProducto}',
                    {$rlud}, '{$ud1}', '{$ud2}',
                    {$captura1}, {$captura2}, {$stfi1}, {$stfi2},
                    '{$funcionario}', GETDATE(), '{$codigoUbicacion}'
                )
            ";
            
            Log::info("Insertando en TINVENTARIO (SQL Server)", [
                'sku' => $sku,
                'cantidad' => $data['cantidad'],
                'bodega' => $kobo,
                'funcionario_raw' => $funcionarioRaw ?? 'N/A',
                'funcionario_escaped' => $funcionario,
                'usarTSQL' => $usarTSQL,
                'insert_sql_preview' => substr($insertSQL, 0, 200),
            ]);
            
            if ($usarTSQL) {
                // Usar tsql para SQL Server 2012 sin TLS
                $resultado = $this->executeTSQLStatement($insertSQL);
                if (!$resultado) {
                    throw new \Exception("Error al insertar en TINVENTARIO. Verifique que la tabla existe en SQL Server.");
                }
            } else {
                // Usar conexión Laravel normal
                $connection->statement($insertSQL);
            }
            
            Log::info("✓ TINVENTARIO insertado correctamente", [
                'sku' => $sku,
                'cantidad' => $captura1,
            ]);

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Producto registrado correctamente.',
                    'data' => [
                        'producto' => trim($data['nombre_producto']),
                        'sku' => trim($data['sku']),
                        'cantidad' => $data['cantidad'],
                    ],
                ]);
            }

            return redirect()->route('manejo-stock.barrido', [
                'bodega_id' => $bodega->id,
                'ubicacion_id' => $ubicacion?->id,
            ])->with('success', 'Producto registrado correctamente.');

        } catch (\Throwable $e) {
            Log::error('Error guardando barrido en TINVENTARIO', [
                'data' => $data,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al registrar: ' . $e->getMessage(),
                ], 500);
            }

            return redirect()->back()
                ->with('error', 'Error al registrar: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Reporte de Inventario (TINVENTARIO desde SQL Server)
     * Opciones: Detallado (sin consolidar) y Consolidado (agrupado por SKU y Bodega)
     */
    public function reporteInventario(Request $request)
    {
        try {
            // Verificar si debemos usar tsql
            $encrypt = env('SQLSRV_EXTERNAL_ENCRYPT', 'yes');
            $usarTSQL = ($encrypt === 'no' || $encrypt === false || $encrypt === 'false');
            
            $filtros = [
                'fecha_desde' => $request->get('fecha_desde', date('Y-m-d', strtotime('-30 days'))),
                'fecha_hasta' => $request->get('fecha_hasta', date('Y-m-d', strtotime('+1 day'))), // +1 día para incluir registros de hoy
                'funcionario' => $request->get('funcionario', ''),
                'bodega_id' => $request->get('bodega_id', ''),
            ];
            $tipoReporte = $request->get('tipo', 'detallado');
            
            Log::info("Consulta reporte inventario", [
                'filtros' => $filtros,
                'tipo' => $tipoReporte,
                'usarTSQL' => $usarTSQL,
            ]);

            // Obtener lista de bodegas para el filtro
            $bodegas = Bodega::orderBy('nombre_bodega')->get();

            // Obtener lista de funcionarios desde TINVENTARIO
            if ($usarTSQL) {
                $queryFuncionarios = "
                    SELECT CAST(FUNCIONARIO AS VARCHAR(20)) AS FUNCIONARIO
                    FROM (
                        SELECT DISTINCT FUNCIONARIO
                        FROM TINVENTARIO 
                        WHERE FUNCIONARIO IS NOT NULL AND FUNCIONARIO != ''
                    ) AS funcionarios_distintos
                    ORDER BY FUNCIONARIO
                ";
                $funcionariosRaw = $this->ejecutarSelectTSQL($queryFuncionarios, ['FUNCIONARIO']);
                $funcionarios = collect($funcionariosRaw)->pluck('FUNCIONARIO');
            } else {
                $connection = $this->sqlServerConnection();
                $funcionarios = $connection->select("
                    SELECT DISTINCT FUNCIONARIO 
                    FROM TINVENTARIO 
                    WHERE FUNCIONARIO IS NOT NULL AND FUNCIONARIO != ''
                    ORDER BY FUNCIONARIO
                ");
                $funcionarios = collect($funcionarios)->pluck('FUNCIONARIO');
            }

            // Construir query base con filtros escapados
            $whereClause = "WHERE 1=1";
            
            if ($filtros['fecha_desde']) {
                $fechaDesdeEscapada = str_replace("'", "''", $filtros['fecha_desde']);
                $whereClause .= " AND CAST(FECHA AS DATE) >= '{$fechaDesdeEscapada}'";
            }
            if ($filtros['fecha_hasta']) {
                $fechaHastaEscapada = str_replace("'", "''", $filtros['fecha_hasta']);
                $whereClause .= " AND CAST(FECHA AS DATE) <= '{$fechaHastaEscapada}'";
            }
            if ($filtros['funcionario']) {
                $funcionarioEscapado = str_replace("'", "''", $filtros['funcionario']);
                $whereClause .= " AND FUNCIONARIO = '{$funcionarioEscapado}'";
            }
            if ($filtros['bodega_id']) {
                $bodegaFiltro = Bodega::find($filtros['bodega_id']);
                if ($bodegaFiltro) {
                    $koboEscapado = str_replace("'", "''", $bodegaFiltro->kobo);
                    $whereClause .= " AND KOBO = '{$koboEscapado}'";
                }
            }

            if ($tipoReporte === 'consolidado') {
                $query = "
                    SELECT 
                        CAST(SKU AS VARCHAR(30)) + '|' +
                        CAST(REPLACE(ISNULL(NOKOPR, ''), '|', ' ') AS VARCHAR(200)) + '|' +
                        CAST(ISNULL(KOBO, '') AS VARCHAR(10)) + '|' +
                        CAST(ISNULL(UD01PR, '') AS VARCHAR(10)) + '|' +
                        CAST(SUM(CAPTURA1) AS VARCHAR(30)) + '|' +
                        CAST(COUNT(*) AS VARCHAR(30)) + '|' +
                        CAST(MIN(FECHA) AS VARCHAR(30)) + '|' +
                        CAST(MAX(FECHA) AS VARCHAR(30)) AS DATOS_REPORTE
                    FROM TINVENTARIO
                    {$whereClause}
                    GROUP BY SKU, NOKOPR, KOBO, UD01PR
                    ORDER BY SKU
                ";
                if ($usarTSQL) {
                    $output = $this->executeTSQL($query);
                    $registrosRaw = $this->parsearResultadosMultiplesTSQL($output, [
                        'sku', 'nombre_producto', 'kobo', 'unidad_medida_1', 
                        'cantidad_total', 'total_registros', 'primera_fecha', 'ultima_fecha'
                    ]);
                    $registros = array_map(function($row) {
                        $obj = new \stdClass();
                        $obj->sku = $row->sku ?? '';
                        $obj->nombre_producto = $row->nombre_producto ?? '';
                        $obj->kobo = $row->kobo ?? '';
                        $obj->unidad_medida_1 = $row->unidad_medida_1 ?? '';
                        $obj->cantidad_total = (float)($row->cantidad_total ?? 0);
                        $obj->total_registros = (int)($row->total_registros ?? 0);
                        $obj->primera_fecha = $row->primera_fecha ?? null;
                        $obj->ultima_fecha = $row->ultima_fecha ?? null;
                        return $obj;
                    }, $registrosRaw);
                } else {
                    if (!isset($connection)) {
                        $connection = $this->sqlServerConnection();
                    }
                    $params = [];
                    if ($filtros['fecha_desde']) $params[] = $filtros['fecha_desde'];
                    if ($filtros['fecha_hasta']) $params[] = $filtros['fecha_hasta'];
                    if ($filtros['funcionario']) $params[] = $filtros['funcionario'];
                    if ($filtros['bodega_id'] && isset($bodegaFiltro)) $params[] = $bodegaFiltro->kobo;
                    $whereClauseNormal = str_replace(["'{$fechaDesdeEscapada}'", "'{$fechaHastaEscapada}'", "'{$funcionarioEscapado}'", "'{$koboEscapado}'"], ['?', '?', '?', '?'], $whereClause);
                    $queryNormal = "SELECT SKU AS sku, NOKOPR AS nombre_producto, KOBO AS kobo, UD01PR AS unidad_medida_1, SUM(CAPTURA1) AS cantidad_total, COUNT(*) AS total_registros, MIN(FECHA) AS primera_fecha, MAX(FECHA) AS ultima_fecha FROM TINVENTARIO {$whereClauseNormal} GROUP BY SKU, NOKOPR, KOBO, UD01PR ORDER BY SKU";
                    $registros = $connection->select($queryNormal, $params);
                }
            } else {
                $query = "
                    SELECT 
                        CAST(FECHA AS VARCHAR(30)) + '|' +
                        CAST(SKU AS VARCHAR(30)) + '|' +
                        CAST(REPLACE(ISNULL(NOKOPR, ''), '|', ' ') AS VARCHAR(200)) + '|' +
                        CAST(ISNULL(KOBO, '') AS VARCHAR(10)) + '|' +
                        CAST(ISNULL(UBICACION, '') AS VARCHAR(20)) + '|' +
                        CAST(ISNULL(CAPTURA1, 0) AS VARCHAR(30)) + '|' +
                        CAST(ISNULL(UD01PR, '') AS VARCHAR(10)) + '|' +
                        CAST(ISNULL(FUNCIONARIO, '') AS VARCHAR(20)) AS DATOS_REPORTE
                    FROM TINVENTARIO
                    {$whereClause}
                    ORDER BY FECHA DESC
                ";
                if ($usarTSQL) {
                    $output = $this->executeTSQL($query);
                    Log::info("Output tsql reporte detallado", ['output_preview' => substr($output, 0, 500)]);
                    $registrosRaw = $this->parsearResultadosMultiplesTSQL($output, [
                        'fecha_barrido', 'sku', 'nombre_producto', 'kobo', 
                        'codigo_ubicacion', 'cantidad', 'unidad_medida_1', 'funcionario'
                    ]);
                    Log::info("Registros parseados detallado", ['count' => count($registrosRaw), 'sample' => $registrosRaw[0] ?? null]);
                    $registros = array_map(function($row) {
                        $obj = new \stdClass();
                        $obj->fecha_barrido = $row->fecha_barrido ?? null;
                        $obj->sku = $row->sku ?? '';
                        $obj->nombre_producto = $row->nombre_producto ?? '';
                        $obj->kobo = $row->kobo ?? '';
                        $obj->codigo_ubicacion = $row->codigo_ubicacion ?? '';
                        $obj->cantidad = (float)($row->cantidad ?? 0);
                        $obj->unidad_medida_1 = $row->unidad_medida_1 ?? '';
                        $obj->funcionario = $row->funcionario ?? '';
                        return $obj;
                    }, $registrosRaw);
                } else {
                    if (!isset($connection)) {
                        $connection = $this->sqlServerConnection();
                    }
                    $params = [];
                    if ($filtros['fecha_desde']) $params[] = $filtros['fecha_desde'];
                    if ($filtros['fecha_hasta']) $params[] = $filtros['fecha_hasta'];
                    if ($filtros['funcionario']) $params[] = $filtros['funcionario'];
                    if ($filtros['bodega_id'] && isset($bodegaFiltro)) $params[] = $bodegaFiltro->kobo;
                    $whereClauseNormal = str_replace(["'{$fechaDesdeEscapada}'", "'{$fechaHastaEscapada}'", "'{$funcionarioEscapado}'", "'{$koboEscapado}'"], ['?', '?', '?', '?'], $whereClause);
                    $queryNormal = "SELECT FECHA AS fecha_barrido, SKU AS sku, NOKOPR AS nombre_producto, KOBO AS kobo, UBICACION AS codigo_ubicacion, CAPTURA1 AS cantidad, UD01PR AS unidad_medida_1, FUNCIONARIO AS funcionario FROM TINVENTARIO {$whereClauseNormal} ORDER BY FECHA DESC";
                    $registros = $connection->select($queryNormal, $params);
                }
            }

            return view('manejo-stock.reporte-inventario', [
                'registros' => collect($registros),
                'bodegas' => $bodegas,
                'funcionarios' => $funcionarios,
                'filtros' => $filtros,
                'tipoReporte' => $tipoReporte,
            ]);
            
        } catch (\Throwable $e) {
            Log::error('Error en reporte de inventario', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return redirect()->route('manejo-stock.select')
                ->with('error', 'Error al cargar el reporte: ' . $e->getMessage());
        }
    }

    /**
     * Exportar reporte de inventario a CSV
     */
    public function exportarInventario(Request $request)
    {
        try {
            $connection = $this->sqlServerConnection();
            
            $filtros = [
                'fecha_desde' => $request->get('fecha_desde'),
                'fecha_hasta' => $request->get('fecha_hasta'),
                'funcionario' => $request->get('funcionario', ''),
                'bodega_id' => $request->get('bodega_id', ''),
            ];
            $tipoReporte = $request->get('tipo', 'detallado');

            // Construir query con campos correctos de TINVENTARIO
            $whereClause = "WHERE 1=1";
            $params = [];
            
            if ($filtros['fecha_desde']) {
                $whereClause .= " AND CAST(FECHA AS DATE) >= ?";
                $params[] = $filtros['fecha_desde'];
            }
            if ($filtros['fecha_hasta']) {
                $whereClause .= " AND CAST(FECHA AS DATE) <= ?";
                $params[] = $filtros['fecha_hasta'];
            }
            if ($filtros['funcionario']) {
                $whereClause .= " AND FUNCIONARIO = ?";
                $params[] = $filtros['funcionario'];
            }
            if ($filtros['bodega_id']) {
                $bodegaFiltro = Bodega::find($filtros['bodega_id']);
                if ($bodegaFiltro) {
                    $whereClause .= " AND KOBO = ?";
                    $params[] = $bodegaFiltro->kobo;
                }
            }

            if ($tipoReporte === 'consolidado') {
                $query = "
                    SELECT SKU, NOKOPR, KOBO, UD01PR, 
                           SUM(CAPTURA1) AS cantidad_total, COUNT(*) AS total_registros,
                           MIN(FECHA) AS primera_fecha, MAX(FECHA) AS ultima_fecha
                    FROM TINVENTARIO {$whereClause}
                    GROUP BY SKU, NOKOPR, KOBO, UD01PR ORDER BY SKU
                ";
            } else {
                $query = "
                    SELECT FECHA, SKU, NOKOPR, KOBO, UBICACION, CAPTURA1, UD01PR, FUNCIONARIO
                    FROM TINVENTARIO {$whereClause} ORDER BY FECHA DESC
                ";
            }
            
            $registros = $connection->select($query, $params);

            $filename = 'inventario_' . $tipoReporte . '_' . date('Y-m-d_His') . '.csv';
            
            $headers = [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ];

            $callback = function () use ($registros, $tipoReporte) {
                $file = fopen('php://output', 'w');
                fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

                if ($tipoReporte === 'consolidado') {
                    fputcsv($file, ['SKU', 'Producto', 'Bodega', 'Unidad', 'Cantidad Total', 'Total Registros', 'Primera Fecha', 'Última Fecha'], ';');
                    foreach ($registros as $r) {
                        fputcsv($file, [$r->SKU, $r->NOKOPR, $r->KOBO, $r->UD01PR, $r->cantidad_total, $r->total_registros, $r->primera_fecha, $r->ultima_fecha], ';');
                    }
                } else {
                    fputcsv($file, ['Fecha', 'SKU', 'Producto', 'Bodega', 'Ubicación', 'Cantidad', 'Unidad', 'Funcionario'], ';');
                    foreach ($registros as $r) {
                        fputcsv($file, [$r->FECHA, $r->SKU, $r->NOKOPR, $r->KOBO, $r->UBICACION, $r->CAPTURA1, $r->UD01PR, $r->FUNCIONARIO], ';');
                    }
                }
                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
            
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Error al exportar: ' . $e->getMessage());
        }
    }
}
