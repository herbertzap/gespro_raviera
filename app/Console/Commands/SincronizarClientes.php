<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Cliente;

class SincronizarClientes extends Command
{
    protected $signature = 'clientes:sincronizar {--vendedor= : Código del vendedor específico} {--todos : Sincronizar todos los vendedores}';
    protected $description = 'Sincronizar clientes desde SQL Server a la base de datos local';

    public function handle()
    {
        $this->info('🔄 Iniciando sincronización de clientes...');
        
        $vendedor = $this->option('vendedor');
        $todos = $this->option('todos');
        
        if ($vendedor) {
            $this->info("📋 Sincronizando clientes del vendedor: {$vendedor}");
            $resultado = Cliente::sincronizarDesdeSQLServer($vendedor);
        } elseif ($todos) {
            $this->info('📋 Sincronizando clientes de todos los vendedores...');
            $resultado = Cliente::sincronizarDesdeSQLServer();
        } else {
            // Por defecto, sincronizar solo GOP para testing
            $this->info('📋 Sincronizando clientes del vendedor GOP (por defecto)...');
            $resultado = Cliente::sincronizarDesdeSQLServer('GOP');
        }
        
        if ($resultado['success']) {
            $this->info('✅ Sincronización completada exitosamente');
            $this->info("📊 Resumen:");
            $this->info("   - Nuevos clientes: {$resultado['nuevos']}");
            $this->info("   - Clientes actualizados: {$resultado['actualizados']}");
            $this->info("   - Total procesados: {$resultado['total']}");
        } else {
            $this->error('❌ Error en la sincronización: ' . $resultado['message']);
            return 1;
        }
        
        return 0;
    }
} 