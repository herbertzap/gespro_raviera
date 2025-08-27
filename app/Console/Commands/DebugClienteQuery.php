<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class DebugClienteQuery extends Command
{
    protected $signature = 'debug:cliente-query {vendedor}';
    protected $description = 'Debug de la consulta de clientes para ver el output exacto';

    public function handle()
    {
        $vendedor = $this->argument('vendedor');
        $this->info("🔍 Debug consulta para vendedor: {$vendedor}");
        
        try {
            $host = env('SQLSRV_EXTERNAL_HOST');
            $port = env('SQLSRV_EXTERNAL_PORT', '1433');
            $database = env('SQLSRV_EXTERNAL_DATABASE');
            $username = env('SQLSRV_EXTERNAL_USERNAME');
            $password = env('SQLSRV_EXTERNAL_PASSWORD');
            
            // Query simple
            $query = "
                SELECT TOP 5
                    CAST(MAEEN.KOEN AS VARCHAR(20)) AS CODIGO_CLIENTE,
                    CAST(MAEEN.NOKOEN AS VARCHAR(100)) AS NOMBRE_CLIENTE,
                    CAST(MAEEN.DIEN AS VARCHAR(100)) AS DIRECCION,
                    CAST(MAEEN.FOEN AS VARCHAR(20)) AS TELEFONO,
                    CAST(MAEEN.KOFUEN AS VARCHAR(10)) AS CODIGO_VENDEDOR
                FROM dbo.MAEEN 
                WHERE MAEEN.KOFUEN = '{$vendedor}'
                ORDER BY MAEEN.NOKOEN
            ";
            
            $this->info("📋 Query:");
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
            $this->info("📋 Análisis línea por línea:");
            
            foreach ($lines as $lineNumber => $line) {
                $line = trim($line);
                $this->line("Línea {$lineNumber}: " . substr($line, 0, 100));
                
                // Verificar si es una línea de datos
                if (!empty($line) && preg_match('/^(\d{7,})/', $line)) {
                    $this->info("   ✅ Línea de datos encontrada");
                    
                    // Intentar dividir por espacios
                    $partes = preg_split('/\s+/', trim($line));
                    $this->info("   📊 Número de partes: " . count($partes));
                    
                    foreach ($partes as $i => $parte) {
                        $this->line("      Parte {$i}: '{$parte}'");
                    }
                    
                    // Intentar extraer usando el método del comando anterior
                    $cliente = $this->extraerClienteDeLinea($line);
                    if ($cliente) {
                        $this->info("   ✅ Cliente extraído exitosamente:");
                        $this->line("      Código: {$cliente['CODIGO_CLIENTE']}");
                        $this->line("      Nombre: {$cliente['NOMBRE_CLIENTE']}");
                        $this->line("      Dirección: {$cliente['DIRECCION']}");
                        $this->line("      Teléfono: {$cliente['TELEFONO']}");
                        $this->line("      Vendedor: {$cliente['CODIGO_VENDEDOR']}");
                    } else {
                        $this->warn("   ❌ No se pudo extraer cliente de esta línea");
                    }
                }
            }
            
        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
        }
        
        return 0;
    }
    
    private function extraerClienteDeLinea($line)
    {
        try {
            // Dividir la línea por espacios y tomar los campos necesarios
            $partes = preg_split('/\s+/', trim($line));
            
            if (count($partes) < 5) {
                return null;
            }
            
            // Los primeros campos son: código, nombre (puede tener múltiples palabras), dirección, teléfono, vendedor
            $codigoCliente = $partes[0];
            
            // Buscar el vendedor al final de la línea
            $codigoVendedor = end($partes);
            
            // El teléfono está antes del vendedor
            $telefono = prev($partes);
            
            // El resto es nombre y dirección
            $nombreYDireccion = array_slice($partes, 1, -2);
            
            // Separar nombre y dirección (asumiendo que la dirección está al final)
            $nombreCliente = implode(' ', array_slice($nombreYDireccion, 0, -1));
            $direccion = end($nombreYDireccion);
            
            return [
                'CODIGO_CLIENTE' => $codigoCliente,
                'NOMBRE_CLIENTE' => $nombreCliente,
                'DIRECCION' => $direccion,
                'TELEFONO' => $telefono,
                'CODIGO_VENDEDOR' => $codigoVendedor,
                'NOMBRE_VENDEDOR' => '',
                'REGION' => '',
                'COMUNA' => '',
                'BLOQUEADO' => '0'
            ];
            
        } catch (\Exception $e) {
            return null;
        }
    }
}
