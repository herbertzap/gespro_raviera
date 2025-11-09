<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\StockService;
use Illuminate\Support\Facades\Log;

class SincronizarProductosProgramado extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'productos:sincronizar';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincroniza todos los productos desde SQL Server (programado para ejecutarse por la noche)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Iniciando sincronización programada de productos...');
        Log::info('🚀 Iniciando sincronización programada de productos');
        
        try {
            $stockService = new StockService();
            $cantidad = $stockService->sincronizarStockDesdeSQLServer();
            
            $this->info("✅ Sincronización completada: {$cantidad} productos actualizados");
            Log::info("✅ Sincronización programada completada: {$cantidad} productos actualizados");
            
            return 0;
        } catch (\Exception $e) {
            $this->error('❌ Error en sincronización: ' . $e->getMessage());
            Log::error('❌ Error en sincronización programada: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return 1;
        }
    }
}
