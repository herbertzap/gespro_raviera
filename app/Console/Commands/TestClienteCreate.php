<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Cliente;

class TestClienteCreate extends Command
{
    protected $signature = 'test:cliente-create';
    protected $description = 'Test crear cliente en la base de datos';

    public function handle()
    {
        try {
            $this->info("Probando crear cliente...");
            
            $cliente = Cliente::create([
                'codigo_cliente' => '99999999',
                'nombre_cliente' => 'Cliente de Prueba',
                'direccion' => 'Dirección de Prueba',
                'telefono' => '123456789',
                'codigo_vendedor' => 'LCB',
                'region' => 'Test',
                'comuna' => 'Test',
                'bloqueado' => false,
                'activo' => true,
                'ultima_sincronizacion' => now()
            ]);
            
            $this->info("✅ Cliente creado exitosamente: {$cliente->codigo_cliente} - {$cliente->nombre_cliente}");
            
            // Verificar que se guardó
            $clienteGuardado = Cliente::where('codigo_cliente', '99999999')->first();
            if ($clienteGuardado) {
                $this->info("✅ Cliente encontrado en BD: {$clienteGuardado->codigo_cliente} - Activo: " . ($clienteGuardado->activo ? 'Sí' : 'No'));
            } else {
                $this->error("❌ Cliente no encontrado en BD");
            }
            
            return 0;
        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            return 1;
        }
    }
}
