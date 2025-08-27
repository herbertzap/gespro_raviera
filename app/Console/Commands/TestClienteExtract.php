<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TestClienteExtract extends Command
{
    protected $signature = 'test:cliente-extract';
    protected $description = 'Test extraer datos de cliente de una línea';

    public function handle()
    {
        $line = "09020635       ABRAHAM MOISES LEYTON POBLETE                           MAIPU N° 409 LCB     LUIS CASANGA BERRIOS";
        
        $this->info("Línea de prueba: {$line}");
        
        $cliente = \App\Console\Commands\SincronizarClientesSimple::extraerClienteDeLinea($line);
        
        if ($cliente) {
            $this->info("✅ Cliente extraído:");
            $this->info("   Código: {$cliente['CODIGO_CLIENTE']}");
            $this->info("   Nombre: {$cliente['NOMBRE_CLIENTE']}");
            $this->info("   Dirección: {$cliente['DIRECCION']}");
            $this->info("   Teléfono: {$cliente['TELEFONO']}");
            $this->info("   Vendedor: {$cliente['CODIGO_VENDEDOR']}");
        } else {
            $this->error("❌ No se pudo extraer cliente");
        }
        
        return 0;
    }
}
