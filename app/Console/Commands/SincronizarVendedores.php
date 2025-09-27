<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Vendedor;

class SincronizarVendedores extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vendedores:sincronizar';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincronizar vendedores desde SQL Server (TABFU)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Iniciando sincronización de vendedores...');

        try {
            // Obtener variables de entorno
            $server = env('SQLSRV_EXTERNAL_HOST');
            $user = env('SQLSRV_EXTERNAL_USERNAME');
            $password = env('SQLSRV_EXTERNAL_PASSWORD');
            $database = env('SQLSRV_EXTERNAL_DATABASE');

            if (!$server || !$user || !$password || !$database) {
                $this->error('Variables de entorno de SQL Server no configuradas');
                return 1;
            }

            // Comando tsql para obtener vendedores
            $query = "
                SELECT TOP 10
                    CAST(KOFU AS VARCHAR(10)) + '|' +
                    CAST(NOKOFU AS VARCHAR(100)) + '|' +
                    CAST(ISNULL(EMAIL, '') AS VARCHAR(100)) + '|' +
                    CAST(ISNULL(RTFU, '') AS VARCHAR(20)) AS LINEA
                FROM TABFU 
                WHERE KOFU IS NOT NULL AND NOKOFU IS NOT NULL
                ORDER BY NOKOFU
            ";

            $command = "echo \"{$query}\" | tsql -S {$server} -U {$user} -P {$password} -D {$database}";
            
            $this->info('Ejecutando consulta SQL Server...');
            $this->info('Comando: ' . $command);
            $output = shell_exec($command . ' 2>&1');

            if (!$output) {
                $this->error('No se pudo ejecutar la consulta a SQL Server');
                return 1;
            }

            $this->info('Output completo:');
            $this->info($output);

            // Procesar output
            $lines = explode("\n", $output);
            $vendedores = [];
            $vendedoresActualizados = 0;
            $vendedoresCreados = 0;

            foreach ($lines as $line) {
                $line = trim($line);
                
                // Saltar líneas vacías o de control
                if (empty($line) || strpos($line, '1>') === 0 || strpos($line, '2>') === 0) {
                    continue;
                }

                // Procesar línea de datos
                if (strpos($line, '|') !== false) {
                    $fields = explode('|', $line);
                    
                    if (count($fields) >= 4) {
                        $vendedores[] = [
                            'KOFU' => trim($fields[0]),
                            'NOKOFU' => trim($fields[1]),
                            'EMAIL' => trim($fields[2]) ?: null,
                            'RTFU' => trim($fields[3]) ?: null,
                        ];
                    }
                }
            }

            $this->info("Vendedores encontrados en SQL Server: " . count($vendedores));

            // Procesar cada vendedor
            foreach ($vendedores as $vendedorData) {
                $vendedor = Vendedor::where('KOFU', $vendedorData['KOFU'])->first();

                if ($vendedor) {
                    // Actualizar vendedor existente
                    $vendedor->update($vendedorData);
                    $vendedoresActualizados++;
                } else {
                    // Crear nuevo vendedor
                    Vendedor::create($vendedorData);
                    $vendedoresCreados++;
                }
            }

            $this->info("Sincronización completada:");
            $this->info("- Vendedores creados: {$vendedoresCreados}");
            $this->info("- Vendedores actualizados: {$vendedoresActualizados}");
            $this->info("- Total procesados: " . ($vendedoresCreados + $vendedoresActualizados));

            return 0;

        } catch (\Exception $e) {
            $this->error("Error durante la sincronización: " . $e->getMessage());
            return 1;
        }
    }
}