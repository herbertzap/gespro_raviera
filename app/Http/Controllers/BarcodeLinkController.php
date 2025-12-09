<?php

namespace App\Http\Controllers;

use App\Models\Bodega;
use App\Models\CodigoBarraLog;
use App\Models\Ubicacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BarcodeLinkController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:Manejo Stock|Super Admin']);
    }

    public function create(Request $request)
    {
        $barcode = $request->query('barcode');
        $bodegaId = $request->query('bodega_id');
        $ubicacionId = $request->query('ubicacion_id');

        if (!$barcode || !$bodegaId) {
            return redirect()->route('manejo-stock.select')->with('error', 'Debe escanear un código de barras primero.');
        }

        $bodegaSeleccionada = Bodega::with('ubicaciones')->find($bodegaId);

        if (!$bodegaSeleccionada) {
            return redirect()->route('manejo-stock.select')->with('error', 'La bodega seleccionada no es válida.');
        }

        $ubicacionSeleccionada = null;
        if ($ubicacionId) {
            $ubicacionSeleccionada = $bodegaSeleccionada->ubicaciones->firstWhere('id', (int) $ubicacionId);
        }

        return view('manejo-stock.asociar', [
            'barcode' => trim($barcode),
            'bodega' => $bodegaSeleccionada,
            'ubicacion' => $ubicacionSeleccionada,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'barcode' => ['required', 'string', 'max:60'],
            'sku' => ['required', 'string', 'max:50'],
            'bodega_id' => ['nullable', 'exists:bodegas,id'],
            'ubicacion_id' => ['nullable', 'exists:ubicaciones,id'],
            'existing_barcode' => ['nullable', 'string', 'max:60'],
            'accion_codigo' => ['nullable', 'in:insert,replace'],
        ]);

        $barcode = trim($data['barcode']);
        $sku = trim($data['sku']);
        $bodega = null;
        $ubicacion = null;
        if (!empty($data['bodega_id'])) {
            $bodega = Bodega::find($data['bodega_id']);
        }
        if (!empty($data['ubicacion_id'])) {
            $ubicacion = Ubicacion::find($data['ubicacion_id']);
        }

        try {
            // Verificar si debemos usar tsql
            $encrypt = env('SQLSRV_EXTERNAL_ENCRYPT', 'yes');
            $usarTSQL = ($encrypt === 'no' || $encrypt === false || $encrypt === 'false');
            
            if ($usarTSQL) {
                // Usar tsql para consultar producto
                $producto = $this->obtenerProductoTSQL($sku);
            } else {
                // Usar conexión Laravel normal
                $producto = DB::connection('sqlsrv_external')
                    ->table('MAEPR')
                    ->select('KOPR', 'NOKOPR')
                    ->where('KOPR', $sku)
                    ->first();
            }

            if (!$producto) {
                return back()->with('error', 'El SKU indicado no existe en MAEPR.')->withInput();
            }

            // Las transacciones no funcionan con tsql, así que manejamos errores manualmente
            if (!$usarTSQL) {
                DB::connection('sqlsrv_external')->beginTransaction();
            }

            $accion = $data['accion_codigo'] ?? 'insert';
            $existingBarcode = isset($data['existing_barcode']) ? trim($data['existing_barcode']) : null;

            $barcodeSanitized = Str::upper(Str::limit($barcode, 21, ''));
            $existingSanitized = $existingBarcode ? Str::upper(Str::limit($existingBarcode, 21, '')) : null;
            $skuSanitized = Str::upper(Str::limit($sku, 13, ''));
            $nombreSanitizado = Str::limit(trim($producto->NOKOPR ?? ''), 50, '');

            $valoresBase = [
                'KOPR' => $skuSanitized,
                'NOKOPRAL' => $nombreSanitizado,
                'KOEN' => 'BLANCO',
                'NMARCA' => ' ',
                'CANTMINCOM' => 0,
                'MULTDECOM' => 0,
                'KOPRAL2' => 'BLANCO',
                'KOPRAL3' => 'BLANCO',
                'KOPRAL4' => 'BLANCO',
                'KOPRAL5' => 'BLANCO',
                'AUX01' => 'BLANCO',
                'AUX02' => 'BLANCO',
                'AUX03' => 'BLANCO',
                'AUX04' => 'BLANCO',
                'AUX05' => 'BLANCO',
                'AUX06' => 'BLANCO',
                'CONMULTI' => 0,
                'UNIMULTI' => 2,
                'MULTIPLO' => 0,
                'TXTMULTI' => 'BLANCO',
            ];

            if ($accion === 'replace' && $existingSanitized) {
                if (strcasecmp($existingSanitized, $barcodeSanitized) === 0) {
                    if (!$usarTSQL) {
                        DB::connection('sqlsrv_external')->rollBack();
                    }
                    return redirect()->route('manejo-stock.contabilidad', [
                        'bodega_id' => $bodega?->id,
                        'ubicacion_id' => $ubicacion?->id,
                    ])->with('success', 'El código indicado ya coincide con el registrado. No fue necesario actualizar.');
                }

                if ($usarTSQL) {
                    $actualizado = $this->actualizarCodigoBarraTSQL($skuSanitized, $existingSanitized, $barcodeSanitized, $valoresBase);
                } else {
                    $actualizado = DB::connection('sqlsrv_external')
                        ->table('TABCODAL')
                        ->where('KOPR', $skuSanitized)
                        ->where('KOPRAL', $existingSanitized)
                        ->update([
                            'KOPRAL' => $barcodeSanitized,
                            ...$valoresBase,
                        ]);
                }

                if (!$actualizado) {
                    if (!$usarTSQL) {
                        DB::connection('sqlsrv_external')->rollBack();
                    }
                    return redirect()->route('manejo-stock.asociar', [
                        'barcode' => $barcode,
                        'bodega_id' => $bodega?->id,
                        'ubicacion_id' => $ubicacion?->id,
                    ])->with('error', 'No se encontró el código actual para reemplazar.')->withInput();
                }
            } else {
                if ($usarTSQL) {
                    $this->insertarOActualizarCodigoBarraTSQL($barcodeSanitized, $valoresBase);
                } else {
                    DB::connection('sqlsrv_external')
                        ->table('TABCODAL')
                        ->updateOrInsert(
                            ['KOPRAL' => $barcodeSanitized],
                            $valoresBase
                        );
                }
            }

            CodigoBarraLog::create([
                'barcode' => $barcodeSanitized,
                'sku' => $skuSanitized,
                'user_id' => $request->user()->id,
                'bodega_id' => $bodega?->id,
            ]);

            if (!$usarTSQL) {
                DB::connection('sqlsrv_external')->commit();
            }

            $mensaje = $accion === 'replace'
                ? "Código {$barcodeSanitized} reemplazó a {$existingSanitized}. Vuelve a escanear para verificar."
                : "Código {$barcodeSanitized} asociado correctamente a {$skuSanitized}. Vuelve a escanear para verificar.";

            return redirect()->route('manejo-stock.contabilidad', [
                'bodega_id' => $bodega?->id,
                'ubicacion_id' => $ubicacion?->id,
            ])->with('success', $mensaje);
        } catch (\Throwable $e) {
            $encrypt = env('SQLSRV_EXTERNAL_ENCRYPT', 'yes');
            $usarTSQL = ($encrypt === 'no' || $encrypt === false || $encrypt === 'false');
            
            if (!$usarTSQL && DB::connection('sqlsrv_external')->transactionLevel() > 0) {
                DB::connection('sqlsrv_external')->rollBack();
            }

            $barcodeForLog = isset($barcodeSanitized) ? $barcodeSanitized : $barcode;

            Log::error('Error asociando código de barras desde el formulario', [
                'barcode' => $barcodeForLog,
                'sku' => $sku ?? 'N/A',
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('manejo-stock.asociar', [
                'barcode' => $barcode,
                'bodega_id' => $bodega?->id,
                'ubicacion_id' => $ubicacion?->id,
            ])->with('error', 'Error guardando la asociación: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Obtener producto usando tsql (para SQL Server 2012 sin TLS)
     */
    private function obtenerProductoTSQL(string $sku): ?object
    {
        $host = env('SQLSRV_EXTERNAL_HOST');
        $port = env('SQLSRV_EXTERNAL_PORT', '1433');
        $database = env('SQLSRV_EXTERNAL_DATABASE');
        $username = env('SQLSRV_EXTERNAL_USERNAME');
        $password = env('SQLSRV_EXTERNAL_PASSWORD');

        $skuEscapado = str_replace("'", "''", trim($sku));
        $query = "SELECT TOP 1 CAST(KOPR AS VARCHAR(30)) + '|' + CAST(NOKOPR AS VARCHAR(200)) AS DATOS FROM MAEPR WHERE KOPR = '{$skuEscapado}'";

        $tempFile = tempnam(sys_get_temp_dir(), 'sql_producto_');
        file_put_contents($tempFile, $query . "\ngo\nquit");
        
        $command = "tsql -H {$host} -p {$port} -U {$username} -P {$password} -D {$database} < {$tempFile} 2>&1";
        $output = shell_exec($command);
        unlink($tempFile);

        if (!$output || str_contains(strtolower($output), 'error')) {
            Log::error("Error tsql en obtenerProductoTSQL: " . $output);
            return null;
        }

        // Procesar output
        $lines = explode("\n", $output);
        $encontradoHeader = false;

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, 'locale') !== false || strpos($line, 'Setting') !== false || 
                preg_match('/^\d+>$/', $line) || strpos($line, 'rows affected') !== false) {
                continue;
            }
            if (strpos($line, 'DATOS') !== false && strpos($line, '|') === false) {
                $encontradoHeader = true;
                continue;
            }
            if ($encontradoHeader && strpos($line, '|') !== false) {
                if (preg_match('/^(\d+)\s*\|(.+)$/', $line, $matches)) {
                    return (object)[
                        'KOPR' => $matches[1],
                        'NOKOPR' => trim($matches[2]),
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Actualizar código de barra usando tsql
     */
    private function actualizarCodigoBarraTSQL(string $sku, string $existingBarcode, string $newBarcode, array $valoresBase): bool
    {
        $host = env('SQLSRV_EXTERNAL_HOST');
        $port = env('SQLSRV_EXTERNAL_PORT', '1433');
        $database = env('SQLSRV_EXTERNAL_DATABASE');
        $username = env('SQLSRV_EXTERNAL_USERNAME');
        $password = env('SQLSRV_EXTERNAL_PASSWORD');

        // Escapar valores
        $skuEscapado = str_replace("'", "''", $sku);
        $existingEscapado = str_replace("'", "''", $existingBarcode);
        $newEscapado = str_replace("'", "''", $newBarcode);
        $nombreEscapado = str_replace("'", "''", $valoresBase['NOKOPRAL'] ?? '');

        $query = "
            UPDATE TABCODAL 
            SET KOPRAL = '{$newEscapado}',
                KOPR = '{$skuEscapado}',
                NOKOPRAL = '{$nombreEscapado}',
                KOEN = 'BLANCO',
                NMARCA = ' ',
                CANTMINCOM = 0,
                MULTDECOM = 0,
                KOPRAL2 = 'BLANCO',
                KOPRAL3 = 'BLANCO',
                KOPRAL4 = 'BLANCO',
                KOPRAL5 = 'BLANCO',
                AUX01 = 'BLANCO',
                AUX02 = 'BLANCO',
                AUX03 = 'BLANCO',
                AUX04 = 'BLANCO',
                AUX05 = 'BLANCO',
                AUX06 = 'BLANCO',
                CONMULTI = 0,
                UNIMULTI = 2,
                MULTIPLO = 0,
                TXTMULTI = 'BLANCO'
            WHERE KOPR = '{$skuEscapado}' AND KOPRAL = '{$existingEscapado}'
        ";

        $tempFile = tempnam(sys_get_temp_dir(), 'sql_update_');
        file_put_contents($tempFile, $query . "\ngo\nquit");
        
        $command = "tsql -H {$host} -p {$port} -U {$username} -P {$password} -D {$database} < {$tempFile} 2>&1";
        $output = shell_exec($command);
        unlink($tempFile);

        if (str_contains(strtolower($output), 'error') || str_contains($output, 'Msg ')) {
            Log::error("Error tsql en actualizarCodigoBarraTSQL: " . $output);
            return false;
        }

        // Verificar si se actualizó al menos una fila
        return str_contains($output, 'rows affected') && !str_contains($output, '(0 rows affected)');
    }

    /**
     * Insertar o actualizar código de barra usando tsql
     */
    private function insertarOActualizarCodigoBarraTSQL(string $barcode, array $valoresBase): bool
    {
        $host = env('SQLSRV_EXTERNAL_HOST');
        $port = env('SQLSRV_EXTERNAL_PORT', '1433');
        $database = env('SQLSRV_EXTERNAL_DATABASE');
        $username = env('SQLSRV_EXTERNAL_USERNAME');
        $password = env('SQLSRV_EXTERNAL_PASSWORD');

        // Escapar valores
        $barcodeEscapado = str_replace("'", "''", $barcode);
        $skuEscapado = str_replace("'", "''", $valoresBase['KOPR'] ?? '');
        $nombreEscapado = str_replace("'", "''", $valoresBase['NOKOPRAL'] ?? '');

        // Primero verificar si existe
        $checkQuery = "SELECT COUNT(*) AS cnt FROM TABCODAL WHERE KOPRAL = '{$barcodeEscapado}'";
        $tempFile = tempnam(sys_get_temp_dir(), 'sql_check_');
        file_put_contents($tempFile, $checkQuery . "\ngo\nquit");
        
        $command = "tsql -H {$host} -p {$port} -U {$username} -P {$password} -D {$database} < {$tempFile} 2>&1";
        $output = shell_exec($command);
        unlink($tempFile);

        $existe = false;
        if (strpos($output, 'cnt') !== false) {
            $lines = explode("\n", $output);
            foreach ($lines as $line) {
                $line = trim($line);
                if (preg_match('/^\d+$/', $line)) {
                    $existe = ((int)$line) > 0;
                    break;
                }
            }
        }

        if ($existe) {
            // UPDATE
            $query = "
                UPDATE TABCODAL 
                SET KOPR = '{$skuEscapado}',
                    NOKOPRAL = '{$nombreEscapado}',
                    KOEN = 'BLANCO',
                    NMARCA = ' ',
                    CANTMINCOM = 0,
                    MULTDECOM = 0,
                    KOPRAL2 = 'BLANCO',
                    KOPRAL3 = 'BLANCO',
                    KOPRAL4 = 'BLANCO',
                    KOPRAL5 = 'BLANCO',
                    AUX01 = 'BLANCO',
                    AUX02 = 'BLANCO',
                    AUX03 = 'BLANCO',
                    AUX04 = 'BLANCO',
                    AUX05 = 'BLANCO',
                    AUX06 = 'BLANCO',
                    CONMULTI = 0,
                    UNIMULTI = 2,
                    MULTIPLO = 0,
                    TXTMULTI = 'BLANCO'
                WHERE KOPRAL = '{$barcodeEscapado}'
            ";
        } else {
            // INSERT
            $query = "
                INSERT INTO TABCODAL (
                    KOPRAL, KOPR, NOKOPRAL, KOEN, NMARCA, CANTMINCOM, MULTDECOM,
                    KOPRAL2, KOPRAL3, KOPRAL4, KOPRAL5,
                    AUX01, AUX02, AUX03, AUX04, AUX05, AUX06,
                    CONMULTI, UNIMULTI, MULTIPLO, TXTMULTI
                ) VALUES (
                    '{$barcodeEscapado}', '{$skuEscapado}', '{$nombreEscapado}', 'BLANCO', ' ', 0, 0,
                    'BLANCO', 'BLANCO', 'BLANCO', 'BLANCO',
                    'BLANCO', 'BLANCO', 'BLANCO', 'BLANCO', 'BLANCO', 'BLANCO',
                    0, 2, 0, 'BLANCO'
                )
            ";
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'sql_insert_update_');
        file_put_contents($tempFile, $query . "\ngo\nquit");
        
        $command = "tsql -H {$host} -p {$port} -U {$username} -P {$password} -D {$database} < {$tempFile} 2>&1";
        $output = shell_exec($command);
        unlink($tempFile);

        if (str_contains(strtolower($output), 'error') || str_contains($output, 'Msg ')) {
            Log::error("Error tsql en insertarOActualizarCodigoBarraTSQL: " . $output);
            return false;
        }

        return true;
    }
}
