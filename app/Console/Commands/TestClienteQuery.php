<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TestClienteQuery extends Command
{
    protected $signature = 'test:cliente-query {vendedor}';
    protected $description = 'Probar la consulta de clientes para un vendedor específico';

    public function handle()
    {
        $vendedor = $this->argument('vendedor');
        $this->info("🔍 Probando consulta para vendedor: {$vendedor}");
        
        try {
            $host = env('SQLSRV_EXTERNAL_HOST');
            $port = env('SQLSRV_EXTERNAL_PORT', '1433');
            $database = env('SQLSRV_EXTERNAL_DATABASE');
            $username = env('SQLSRV_EXTERNAL_USERNAME');
            $password = env('SQLSRV_EXTERNAL_PASSWORD');
            
            // Query simple para obtener clientes del vendedor
            $query = "
                SELECT TOP 10
                    MAEEN.KOEN,
                    MAEEN.NOKOEN,
                    MAEEN.DIEN,
                    MAEEN.FOEN,
                    MAEEN.KOFUEN
                FROM dbo.MAEEN 
                WHERE MAEEN.KOFUEN = '{$vendedor}'
                ORDER BY MAEEN.NOKOEN
            ";
            
            $this->info("📋 Query ejecutada:");
            $this->line($query);
            
            // Crear archivo temporal con la consulta
            $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
            file_put_contents($tempFile, $query . "\ngo\nquit");
            
            // Ejecutar consulta usando tsql
            $command = "tsql -H {$host} -p {$port} -U {$username} -P {$password} -D {$database} < {$tempFile} 2>&1";
            $output = shell_exec($command);
            
            // Limpiar archivo temporal
            unlink($tempFile);
            
            $this->info("📋 Output completo:");
            $this->line("=" . str_repeat("=", 80));
            $this->line($output);
            $this->line("=" . str_repeat("=", 80));
            
            // Procesar línea por línea
            $lines = explode("\n", $output);
            $this->info("📋 Procesando líneas:");
            
            foreach ($lines as $lineNumber => $line) {
                $line = trim($line);
                $this->line("Línea {$lineNumber}: " . substr($line, 0, 100));
                
                // Verificar si es una línea de datos
                if (!empty($line) && preg_match('/^(\d{7,})/', $line)) {
                    $this->info("   ✅ Línea de datos encontrada: " . substr($line, 0, 100));
                    
                    // Intentar procesar
                    $parts = preg_split('/\s+/', trim($line), 5);
                    $this->info("   📊 Partes encontradas: " . count($parts));
                    foreach ($parts as $i => $part) {
                        $this->line("      Parte {$i}: '{$part}'");
                    }
                }
            }
            
        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
        }
        
        return 0;
    }
}
