<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\CobranzaService;

class TestNvvAgrupada extends Command
{
    protected $signature = 'test:nvv-agrupada {vendedor?}';
    protected $description = 'Probar la vista agrupada de NVV';

    public function handle()
    {
        $vendedor = $this->argument('vendedor') ?? 'GOP';
        $this->info("Probando vista agrupada de NVV para vendedor: {$vendedor}");

        $cobranzaService = new CobranzaService();
        
        try {
            // Agregar logging temporal
            \Log::info("Iniciando prueba de NVV agrupada para vendedor: {$vendedor}");
            
            $nvvPendientes = $cobranzaService->getNvvPendientesDetalle($vendedor, 5);
            
            \Log::info("Resultado obtenido: " . count($nvvPendientes) . " NVV");
            
            $this->info("Total de NVV encontradas: " . count($nvvPendientes));
            
            if (!empty($nvvPendientes)) {
                $this->info("\nPrimeras 3 NVV:");
                foreach (array_slice($nvvPendientes, 0, 3) as $index => $nvv) {
                    $this->info("NVV " . ($index + 1) . ":");
                    $this->info("  - Número: {$nvv['TD']}-{$nvv['NUM']}");
                    $this->info("  - Cliente: {$nvv['CLIE']}");
                    $this->info("  - Productos: {$nvv['CANTIDAD_PRODUCTOS']}");
                    $this->info("  - Total Pendiente: {$nvv['TOTAL_PENDIENTE']}");
                    $this->info("  - Valor Pendiente: $" . number_format($nvv['TOTAL_VALOR_PENDIENTE'], 0));
                    $this->info("  - Días: {$nvv['DIAS']}");
                    $this->info("  - Estado: {$nvv['ESTADO_FACTURACION']}");
                    $this->info("  - Vendedor: {$nvv['VENDEDOR_NOMBRE']}");
                    $this->info("");
                }
            } else {
                $this->warn("No se encontraron NVV para el vendedor {$vendedor}");
            }
            
        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
        }
    }
}
