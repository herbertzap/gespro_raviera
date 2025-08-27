<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\CobranzaService;

class VerificarVendedoresYClientes extends Command
{
    protected $signature = 'vendedores:verificar {--vendedor= : Código del vendedor específico} {--todos : Verificar todos los vendedores}';
    protected $description = 'Verificar qué vendedores existen y cuáles tienen clientes asociados';

    public function handle()
    {
        $this->info('🔍 Verificando vendedores y clientes...');
        
        $vendedor = $this->option('vendedor');
        $todos = $this->option('todos');
        
        $cobranzaService = new CobranzaService();
        
        if ($vendedor) {
            $this->verificarVendedorEspecifico($vendedor, $cobranzaService);
        } elseif ($todos) {
            $this->verificarTodosLosVendedores($cobranzaService);
        } else {
            // Verificar vendedores específicos que creamos
            $vendedores = ['LCB', 'GMB', 'GOP'];
            foreach ($vendedores as $vend) {
                $this->verificarVendedorEspecifico($vend, $cobranzaService);
            }
        }
        
        return 0;
    }
    
    private function verificarVendedorEspecifico($codigoVendedor, $cobranzaService)
    {
        $this->info("\n📋 Verificando vendedor: {$codigoVendedor}");
        
        try {
            // Verificar si el vendedor existe en TABFU
            $this->info("   🔍 Verificando existencia en TABFU...");
            
            $host = env('SQLSRV_EXTERNAL_HOST');
            $port = env('SQLSRV_EXTERNAL_PORT', '1433');
            $database = env('SQLSRV_EXTERNAL_DATABASE');
            $username = env('SQLSRV_EXTERNAL_USERNAME');
            $password = env('SQLSRV_EXTERNAL_PASSWORD');
            
            // Query para verificar si existe el vendedor
            $queryVendedor = "
                SELECT KOFU, NOKOFU
                FROM dbo.TABFU 
                WHERE KOFU = '{$codigoVendedor}'
            ";
            
            // Crear archivo temporal con la consulta
            $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
            file_put_contents($tempFile, $queryVendedor . "\ngo\nquit");
            
            // Ejecutar consulta usando tsql
            $command = "tsql -H {$host} -p {$port} -U {$username} -P {$password} -D {$database} < {$tempFile} 2>&1";
            $output = shell_exec($command);
            
            // Limpiar archivo temporal
            unlink($tempFile);
            
            if (str_contains($output, $codigoVendedor)) {
                $this->info("   ✅ Vendedor {$codigoVendedor} existe en TABFU");
                
                // Extraer nombre del vendedor
                $lines = explode("\n", $output);
                foreach ($lines as $line) {
                    if (str_contains($line, $codigoVendedor)) {
                        $parts = preg_split('/\s+/', trim($line));
                        if (count($parts) >= 2) {
                            $nombre = implode(' ', array_slice($parts, 1));
                            $this->info("   📝 Nombre: {$nombre}");
                        }
                        break;
                    }
                }
            } else {
                $this->warn("   ❌ Vendedor {$codigoVendedor} NO existe en TABFU");
                return;
            }
            
            // Verificar clientes asociados
            $this->info("   🔍 Verificando clientes asociados...");
            
            $queryClientes = "
                SELECT COUNT(*) AS TOTAL_CLIENTES
                FROM dbo.MAEEN 
                WHERE KOFUEN = '{$codigoVendedor}'
            ";
            
            // Crear archivo temporal con la consulta
            $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
            file_put_contents($tempFile, $queryClientes . "\ngo\nquit");
            
            // Ejecutar consulta usando tsql
            $command = "tsql -H {$host} -p {$port} -U {$username} -P {$password} -D {$database} < {$tempFile} 2>&1";
            $output = shell_exec($command);
            
            // Limpiar archivo temporal
            unlink($tempFile);
            
            // Extraer número de clientes
            $lines = explode("\n", $output);
            $totalClientes = 0;
            foreach ($lines as $line) {
                if (preg_match('/^\s*(\d+)\s*$/', trim($line), $matches)) {
                    $totalClientes = (int)$matches[1];
                    break;
                }
            }
            
            if ($totalClientes > 0) {
                $this->info("   ✅ Total de clientes asociados: {$totalClientes}");
                
                // Mostrar algunos clientes de ejemplo
                $queryEjemplos = "
                    SELECT TOP 5 KOEN, NOKOEN, DIEN, FOEN
                    FROM dbo.MAEEN 
                    WHERE KOFUEN = '{$codigoVendedor}'
                    ORDER BY NOKOEN
                ";
                
                // Crear archivo temporal con la consulta
                $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
                file_put_contents($tempFile, $queryEjemplos . "\ngo\nquit");
                
                // Ejecutar consulta usando tsql
                $command = "tsql -H {$host} -p {$port} -U {$username} -P {$password} -D {$database} < {$tempFile} 2>&1";
                $output = shell_exec($command);
                
                // Limpiar archivo temporal
                unlink($tempFile);
                
                $this->info("   📋 Ejemplos de clientes:");
                $lines = explode("\n", $output);
                $encontrados = 0;
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (preg_match('/^(\d{7,})\s+(.+?)\s+([^\s]+)\s+([^\s]+)$/', $line, $matches)) {
                        $codigo = $matches[1];
                        $nombre = trim($matches[2]);
                        $direccion = $matches[3];
                        $telefono = $matches[4];
                        $this->info("      - {$codigo}: {$nombre}");
                        $encontrados++;
                        if ($encontrados >= 3) break;
                    }
                }
                
            } else {
                $this->warn("   ❌ No hay clientes asociados al vendedor {$codigoVendedor}");
            }
            
            // Verificar si hay datos en la vista vw_clientes_por_vendedor
            $this->info("   🔍 Verificando vista vw_clientes_por_vendedor...");
            
            $queryVista = "
                SELECT COUNT(*) AS TOTAL_EN_VISTA
                FROM vw_clientes_por_vendedor
                WHERE CODIGO_VENDEDOR = '{$codigoVendedor}'
            ";
            
            // Crear archivo temporal con la consulta
            $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
            file_put_contents($tempFile, $queryVista . "\ngo\nquit");
            
            // Ejecutar consulta usando tsql
            $command = "tsql -H {$host} -p {$port} -U {$username} -P {$password} -D {$database} < {$tempFile} 2>&1";
            $output = shell_exec($command);
            
            // Limpiar archivo temporal
            unlink($tempFile);
            
            // Extraer número de registros en la vista
            $lines = explode("\n", $output);
            $totalEnVista = 0;
            foreach ($lines as $line) {
                if (preg_match('/^\s*(\d+)\s*$/', trim($line), $matches)) {
                    $totalEnVista = (int)$matches[1];
                    break;
                }
            }
            
            if ($totalEnVista > 0) {
                $this->info("   ✅ Total en vista vw_clientes_por_vendedor: {$totalEnVista}");
            } else {
                $this->warn("   ❌ No hay datos en la vista vw_clientes_por_vendedor para {$codigoVendedor}");
            }
            
        } catch (\Exception $e) {
            $this->error("   ❌ Error verificando vendedor {$codigoVendedor}: " . $e->getMessage());
        }
    }
    
    private function verificarTodosLosVendedores($cobranzaService)
    {
        $this->info("\n📋 Verificando todos los vendedores...");
        
        try {
            $host = env('SQLSRV_EXTERNAL_HOST');
            $port = env('SQLSRV_EXTERNAL_PORT', '1433');
            $database = env('SQLSRV_EXTERNAL_DATABASE');
            $username = env('SQLSRV_EXTERNAL_USERNAME');
            $password = env('SQLSRV_EXTERNAL_PASSWORD');
            
            // Query para obtener todos los vendedores con clientes
            $query = "
                SELECT 
                    TABFU.KOFU,
                    TABFU.NOKOFU,
                    COUNT(MAEEN.KOEN) AS TOTAL_CLIENTES
                FROM dbo.TABFU 
                LEFT JOIN dbo.MAEEN ON TABFU.KOFU = MAEEN.KOFUEN
                GROUP BY TABFU.KOFU, TABFU.NOKOFU
                HAVING COUNT(MAEEN.KOEN) > 0
                ORDER BY TOTAL_CLIENTES DESC
            ";
            
            // Crear archivo temporal con la consulta
            $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
            file_put_contents($tempFile, $query . "\ngo\nquit");
            
            // Ejecutar consulta usando tsql
            $command = "tsql -H {$host} -p {$port} -U {$username} -P {$password} -D {$database} < {$tempFile} 2>&1";
            $output = shell_exec($command);
            
            // Limpiar archivo temporal
            unlink($tempFile);
            
            $this->info("   📊 Vendedores con clientes:");
            $lines = explode("\n", $output);
            $encontrados = 0;
            foreach ($lines as $line) {
                $line = trim($line);
                if (preg_match('/^([A-Z]{3})\s+(.+?)\s+(\d+)$/', $line, $matches)) {
                    $codigo = $matches[1];
                    $nombre = trim($matches[2]);
                    $total = $matches[3];
                    $this->info("      - {$codigo}: {$nombre} ({$total} clientes)");
                    $encontrados++;
                    if ($encontrados >= 10) break;
                }
            }
            
        } catch (\Exception $e) {
            $this->error("   ❌ Error verificando todos los vendedores: " . $e->getMessage());
        }
    }
}
