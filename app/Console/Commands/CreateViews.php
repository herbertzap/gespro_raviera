<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CreateViews extends Command
{
    protected $signature = 'sqlsrv:create-views';
    protected $description = 'Create SQL Server views for cobranza and clientes';

    public function handle()
    {
        $this->info("Creating SQL Server views...");

        // Ejecutar ambos scripts SQL
        $host = env('SQLSRV_EXTERNAL_HOST', '152.231.92.82');
        $port = env('SQLSRV_EXTERNAL_PORT', '1433');
        $database = env('SQLSRV_EXTERNAL_DATABASE', 'HIGUERA030924');
        $username = env('SQLSRV_EXTERNAL_USERNAME', 'AMANECER');
        $password = env('SQLSRV_EXTERNAL_PASSWORD', 'AMANECER');

        // Crear vista de cobranza
        $this->info("Creating cobranza view...");
        $sqlScript1 = file_get_contents(base_path('docs/vista_cobranza.sql'));
        if ($sqlScript1) {
            $tempFile1 = tempnam(sys_get_temp_dir(), 'sql_script_1_');
            file_put_contents($tempFile1, $sqlScript1);
            $sqlcmd1 = "docker exec -i sqlserver_bridge tsql -S HIGUERA030924 -D HIGUERA030924 -U {$username} -P {$password} < {$tempFile1}";
            $output1 = shell_exec($sqlcmd1 . " 2>&1");
            unlink($tempFile1);
            $this->info("Cobranza view output: " . $output1);
        }

        // Crear vista de clientes
        $this->info("Creating clientes view...");
        $sqlScript2 = file_get_contents(base_path('docs/vista_clientes.sql'));
        if ($sqlScript2) {
            $tempFile2 = tempnam(sys_get_temp_dir(), 'sql_script_2_');
            file_put_contents($tempFile2, $sqlScript2);
            $sqlcmd2 = "docker exec -i sqlserver_bridge tsql -S HIGUERA030924 -D HIGUERA030924 -U {$username} -P {$password} < {$tempFile2}";
            $output2 = shell_exec($sqlcmd2 . " 2>&1");
            unlink($tempFile2);
            $this->info("Clientes view output: " . $output2);
        }

        $output = $output1 . "\n" . $output2;

        if ($output === null) {
            $this->error("Failed to execute SQL script");
            return 1;
        }

        $this->info("SQL script executed successfully!");
        $this->info("Output: " . $output);

        // Probar las vistas
        $this->info("\nTesting views...");
        
        // Probar vista de cobranza
        $testQuery1 = "SELECT TOP 3 * FROM vw_cobranza_por_vendedor WHERE CODIGO_VENDEDOR = 'GOP'";
        $sqlcmd1 = "echo -e \"{$testQuery1}\ngo\nquit\" | docker exec -i sqlserver_bridge tsql -S HIGUERA030924 -D HIGUERA030924 -U {$username} -P {$password}";
        $output1 = shell_exec($sqlcmd1 . " 2>&1");
        
        $this->info("Cobranza view test:");
        $this->info($output1);

        // Probar vista de clientes
        $testQuery2 = "SELECT TOP 3 * FROM vw_clientes_por_vendedor WHERE CODIGO_VENDEDOR = 'GOP'";
        $sqlcmd2 = "echo -e \"{$testQuery2}\ngo\nquit\" | docker exec -i sqlserver_bridge tsql -S HIGUERA030924 -D HIGUERA030924 -U {$username} -P {$password}";
        $output2 = shell_exec($sqlcmd2 . " 2>&1");
        
        $this->info("Clientes view test:");
        $this->info($output2);

        return 0;
    }
} 