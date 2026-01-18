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
    protected $signature = 'productos:sincronizar-programado';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincroniza todos los productos desde SQL Server (programado para ejecutarse por la noche) - Incluye productos nuevos, precios y stock';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Iniciando sincronización programada completa de productos...');
        Log::info('🚀 Iniciando sincronización programada completa de productos');
        
        try {
            // PASO 1: Sincronizar productos nuevos y actualizar existentes usando el comando SincronizarProductos
            $this->info('📦 Paso 1: Sincronizando productos nuevos y actualizando existentes...');
            Log::info('📦 Paso 1: Sincronizando productos nuevos y actualizando existentes');
            
            // Ejecutar el comando SincronizarProductos en lotes
            $limit = 1000;
            $offset = 0;
            $totalProcesados = 0;
            $totalCreados = 0;
            $totalActualizados = 0;
            $maxBatches = 50; // Máximo 50,000 productos por ejecución
            
            for ($batch = 0; $batch < $maxBatches; $batch++) {
                $this->info("   Procesando lote " . ($batch + 1) . " (offset: {$offset})...");
                
                try {
                    \Artisan::call('productos:sincronizar', [
                        '--limit' => $limit,
                        '--offset' => $offset
                    ], $this->getOutput());
                    
                    $output = \Artisan::output();
                    
                    // Extraer estadísticas del output
                    $procesados = 0;
                    $creados = 0;
                    $actualizados = 0;
                    
                    foreach (explode("\n", $output) as $line) {
                        $line = trim($line);
                        if (stripos($line, 'Productos procesados:') !== false) {
                            preg_match('/(\d+)/', $line, $matches);
                            if (isset($matches[1])) {
                                $procesados = (int)$matches[1];
                            }
                        } elseif (stripos($line, 'Productos creados:') !== false) {
                            preg_match('/(\d+)/', $line, $matches);
                            if (isset($matches[1])) {
                                $creados = (int)$matches[1];
                            }
                        } elseif (stripos($line, 'Productos actualizados:') !== false) {
                            preg_match('/(\d+)/', $line, $matches);
                            if (isset($matches[1])) {
                                $actualizados = (int)$matches[1];
                            }
                        }
                    }
                    
                    $totalProcesados += $procesados;
                    $totalCreados += $creados;
                    $totalActualizados += $actualizados;
                    
                    // Si no se procesaron productos, terminamos
                    if ($procesados == 0) {
                        break;
                    }
                    
                    $offset += $limit;
                    
                } catch (\Exception $e) {
                    $this->warn("   ⚠️ Error en lote " . ($batch + 1) . ": " . $e->getMessage());
                    Log::warning("Error en lote de sincronización " . ($batch + 1) . ": " . $e->getMessage());
                    break;
                }
            }
            
            $this->info("✅ Productos sincronizados: {$totalProcesados} procesados, {$totalCreados} creados, {$totalActualizados} actualizados");
            Log::info("✅ Productos sincronizados: {$totalProcesados} procesados, {$totalCreados} creados, {$totalActualizados} actualizados");
            
            // PASO 2: Sincronizar stock y precios desde bodega LIB (actualiza productos existentes y crea nuevos si no existen)
            $this->info('📦 Paso 2: Sincronizando stock y precios desde bodega LIB...');
            Log::info('📦 Paso 2: Sincronizando stock y precios desde bodega LIB');
            
            $stockService = new StockService();
            $cantidadStock = $stockService->sincronizarStockDesdeSQLServer();
            
            $this->info("✅ Stock sincronizado: {$cantidadStock} productos actualizados");
            Log::info("✅ Stock sincronizado: {$cantidadStock} productos actualizados");
            
            $this->info("✅ Sincronización completa finalizada");
            Log::info("✅ Sincronización programada completa finalizada: {$totalProcesados} productos procesados, {$totalCreados} nuevos, {$totalActualizados} actualizados, {$cantidadStock} con stock actualizado");
            
            return 0;
        } catch (\Exception $e) {
            $this->error('❌ Error en sincronización: ' . $e->getMessage());
            Log::error('❌ Error en sincronización programada: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return 1;
        }
    }
}
