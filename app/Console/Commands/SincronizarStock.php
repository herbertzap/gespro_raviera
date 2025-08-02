<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\StockService;

class SincronizarStock extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stock:sincronizar {--limit=100 : Número máximo de productos a sincronizar}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincronizar stock desde SQL Server a la base de datos local';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔄 Iniciando sincronización de stock desde SQL Server...');
        
        try {
            $stockService = new StockService();
            $productosSincronizados = $stockService->sincronizarStockDesdeSQLServer();
            
            if ($productosSincronizados > 0) {
                $this->info("✅ Sincronización completada: {$productosSincronizados} productos actualizados");
            } else {
                $this->warn('⚠️ No se pudieron sincronizar productos');
            }
            
        } catch (\Exception $e) {
            $this->error('❌ Error durante la sincronización: ' . $e->getMessage());
            return 1;
        }
        
        return 0;
    }
}
