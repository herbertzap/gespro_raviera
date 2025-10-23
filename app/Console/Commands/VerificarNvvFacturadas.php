<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Cotizacion;
use App\Services\HistorialCotizacionService;
use Illuminate\Support\Facades\Log;

class VerificarNvvFacturadas extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nvv:verificar-facturadas';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verifica cada hora si las NVV aprobadas ya fueron facturadas en SQL Server';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Verificando NVV facturadas...');
        Log::info('🔍 Iniciando verificación de NVV facturadas');

        try {
            // Obtener todas las NVV aprobadas que aún no están marcadas como facturadas
            $nvvAprobadas = Cotizacion::where('estado_aprobacion', 'aprobada_picking')
                ->where('tipo_documento', 'nota_venta')
                ->whereNotNull('numero_nvv')
                ->where(function($query) {
                    $query->whereNull('facturada')
                          ->orWhere('facturada', false);
                })
                ->get();

            $this->info("📊 NVV aprobadas sin facturar: {$nvvAprobadas->count()}");
            Log::info("📊 NVV aprobadas sin facturar: {$nvvAprobadas->count()}");

            $nvvFacturadas = 0;

            foreach ($nvvAprobadas as $nvv) {
                $numeroNvv = str_pad($nvv->numero_nvv, 10, '0', STR_PAD_LEFT);
                
                // Consultar en SQL Server si la NVV ya tiene FCV asociada
                $query = "
                    SELECT 
                        MAEDDO_NVV.NUDO AS NUMERO_NVV,
                        MAEDDO_FCV.TIDO AS TIPO_FACTURA,
                        MAEDDO_FCV.NUDO AS NUMERO_FACTURA,
                        MAEDDO_FCV.FEEMLI AS FECHA_FACTURA
                    FROM MAEDDO AS MAEDDO_NVV
                    LEFT JOIN MAEDDO AS MAEDDO_FCV ON MAEDDO_NVV.IDMAEDDO = MAEDDO_FCV.IDRST
                    WHERE MAEDDO_NVV.TIDO = 'NVV' 
                        AND MAEDDO_NVV.NUDO = '{$numeroNvv}'
                        AND MAEDDO_FCV.TIDO = 'FCV'
                ";

                $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
                file_put_contents($tempFile, $query . "\ngo\nquit");

                $command = "tsql -H " . env('SQLSRV_EXTERNAL_HOST') . " -p " . env('SQLSRV_EXTERNAL_PORT') . 
                          " -U " . env('SQLSRV_EXTERNAL_USERNAME') . " -P " . env('SQLSRV_EXTERNAL_PASSWORD') . 
                          " -D " . env('SQLSRV_EXTERNAL_DATABASE') . " < {$tempFile} 2>&1";
                
                $result = shell_exec($command);
                unlink($tempFile);

                // Verificar si se encontró una factura
                if ($result && !str_contains($result, 'error') && str_contains($result, 'FCV')) {
                    // Parsear el resultado para obtener el número de factura y fecha
                    $lines = explode("\n", $result);
                    $numeroFactura = '';
                    $fechaFactura = '';
                    
                    foreach ($lines as $line) {
                        $line = trim($line);
                        // Buscar línea que contenga NVV y FCV
                        if (preg_match('/(\d{10})\s+FCV\s+(\d{10})\s+(.+)/', $line, $matches)) {
                            $numeroFactura = $matches[2];
                            $fechaFactura = trim($matches[3]);
                            break;
                        }
                    }

                    if ($numeroFactura) {
                        // Marcar la NVV como facturada
                        $nvv->facturada = true;
                        $nvv->numero_factura = $numeroFactura;
                        $nvv->fecha_facturacion = $fechaFactura;
                        $nvv->save();

                        // Registrar en el historial
                        HistorialCotizacionService::registrar(
                            $nvv->id,
                            null, // Sin usuario específico (proceso automático)
                            'aprobada_picking',
                            'facturada',
                            'aprobacion',
                            "NVV N° {$nvv->numero_nvv} facturada como FCV N° {$numeroFactura}",
                            [
                                'numero_factura' => $numeroFactura,
                                'fecha_facturacion' => $fechaFactura,
                                'verificacion_automatica' => true
                            ]
                        );

                        $nvvFacturadas++;
                        $this->info("✅ NVV #{$nvv->id} ({$numeroNvv}) facturada como FCV {$numeroFactura}");
                        Log::info("✅ NVV #{$nvv->id} ({$numeroNvv}) facturada como FCV {$numeroFactura}");
                    }
                }
            }

            $this->info("✅ Verificación completada: {$nvvFacturadas} NVV facturadas encontradas");
            Log::info("✅ Verificación completada: {$nvvFacturadas} NVV facturadas encontradas");

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            Log::error("❌ Error en verificación de NVV facturadas: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}

