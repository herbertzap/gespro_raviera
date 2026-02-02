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
        $this->middleware(['auth', 'permission:ver_manejo_stock']);
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
            $producto = DB::connection('sqlsrv_external')
                ->table('MAEPR')
                ->select('KOPR', 'NOKOPR')
                ->where('KOPR', $sku)
                ->first();

            if (!$producto) {
                return back()->with('error', 'El SKU indicado no existe en MAEPR.')->withInput();
            }

            DB::connection('sqlsrv_external')->beginTransaction();

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
                    DB::connection('sqlsrv_external')->rollBack();
                    return redirect()->route('manejo-stock.contabilidad', [
                        'bodega_id' => $bodega?->id,
                        'ubicacion_id' => $ubicacion?->id,
                    ])->with('success', 'El código indicado ya coincide con el registrado. No fue necesario actualizar.');
                }

                $actualizado = DB::connection('sqlsrv_external')
                    ->table('TABCODAL')
                    ->where('KOPR', $skuSanitized)
                    ->where('KOPRAL', $existingSanitized)
                    ->update([
                        'KOPRAL' => $barcodeSanitized,
                        ...$valoresBase,
                    ]);

                if (!$actualizado) {
                    DB::connection('sqlsrv_external')->rollBack();
                    return redirect()->route('manejo-stock.asociar', [
                        'barcode' => $barcode,
                        'bodega_id' => $bodega?->id,
                        'ubicacion_id' => $ubicacion?->id,
                    ])->with('error', 'No se encontró el código actual para reemplazar.')->withInput();
                }
            } else {
                DB::connection('sqlsrv_external')
                    ->table('TABCODAL')
                    ->updateOrInsert(
                        ['KOPRAL' => $barcodeSanitized],
                        $valoresBase
                    );
            }

            CodigoBarraLog::create([
                'barcode' => $barcodeSanitized,
                'sku' => $skuSanitized,
                'user_id' => $request->user()->id,
                'bodega_id' => $bodega?->id,
            ]);

            DB::connection('sqlsrv_external')->commit();

            $mensaje = $accion === 'replace'
                ? "Código {$barcodeSanitized} reemplazó a {$existingSanitized}. Vuelve a escanear para verificar."
                : "Código {$barcodeSanitized} asociado correctamente a {$skuSanitized}. Vuelve a escanear para verificar.";

            return redirect()->route('manejo-stock.contabilidad', [
                'bodega_id' => $bodega?->id,
                'ubicacion_id' => $ubicacion?->id,
            ])->with('success', $mensaje);
        } catch (\Throwable $e) {
            if (DB::connection('sqlsrv_external')->transactionLevel() > 0) {
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
}
