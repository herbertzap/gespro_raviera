<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PDO;
use PDOException;

class TestSqlServerConnection extends Command
{
    protected $signature = 'test:sqlsrv';
    protected $description = 'Test SQL Server connection using bridge';

    public function handle()
    {
        $this->info("Testing SQL Server connection...");

        try {
            // Probar conexión directa PDO
            $this->info("\nTesting direct PDO connection...");
            $host = env('SQLSRV_EXTERNAL_HOST');
            $port = env('SQLSRV_EXTERNAL_PORT', '1433');
            $database = env('SQLSRV_EXTERNAL_DATABASE');
            $username = env('SQLSRV_EXTERNAL_USERNAME');
            $password = env('SQLSRV_EXTERNAL_PASSWORD');
            
            // Verificar que las credenciales estén configuradas
            if (!$host || !$database || !$username || !$password) {
                $this->error("❌ Credenciales SQL Server no configuradas en .env");
                $this->info("Asegúrate de configurar:");
                $this->info("SQLSRV_EXTERNAL_HOST");
                $this->info("SQLSRV_EXTERNAL_DATABASE");
                $this->info("SQLSRV_EXTERNAL_USERNAME");
                $this->info("SQLSRV_EXTERNAL_PASSWORD");
                return 1;
            }
            
            $dsn = "odbc:Driver={ODBC Driver 18 for SQL Server};Server={$host},{$port};Database={$database};Encrypt=no;TrustServerCertificate=yes;";
            
            $this->info("DSN: {$dsn}");
            $this->info("Username: {$username}");
            
            $pdo = new PDO($dsn, $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $this->info("✅ Direct PDO connection successful!");
            
            // Probar consulta de cobranza para GOP
            $this->info("\nTesting cobranza query for GOP via PDO...");
            $query = "
                SELECT TOP 3 
                    dbo.MAEEDO.TIDO AS TIPO_DOCTO,
                    dbo.MAEEDO.NUDO AS NRO_DOCTO,
                    dbo.MAEEDO.ENDO AS CODIGO,
                    dbo.MAEEN.NOKOEN AS CLIENTE,
                    dbo.TABFU.NOKOFU AS VENDEDOR,
                    dbo.TABFU.KOFU AS COD_VEN,
                    dbo.MAEEDO.VABRDO AS VALOR,
                    dbo.MAEEDO.VAABDO AS ABONOS
                FROM dbo.TABFU 
                RIGHT OUTER JOIN dbo.MAEEN ON dbo.TABFU.KOFU = dbo.MAEEN.KOFUEN 
                RIGHT OUTER JOIN dbo.MAEEDO ON dbo.MAEEN.KOEN = dbo.MAEEDO.ENDO AND dbo.MAEEN.SUEN = dbo.MAEEDO.SUENDO
                WHERE (dbo.MAEEDO.EMPRESA = '01' OR dbo.MAEEDO.EMPRESA = '02') 
                    AND (dbo.MAEEDO.TIDO = 'NCV' OR dbo.MAEEDO.TIDO = 'FCV' OR dbo.MAEEDO.TIDO = 'FDV') 
                    AND (dbo.MAEEDO.FEEMDO > CONVERT(DATETIME, '2017-12-31 00:00:00', 102)) 
                    AND (dbo.MAEEDO.VABRDO > dbo.MAEEDO.VAABDO)
                    AND dbo.TABFU.KOFU = 'GOP'
                ORDER BY dbo.MAEEDO.FEEMDO DESC";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $this->info("✅ PDO query successful! Found " . count($results) . " records");
            
            if (count($results) > 0) {
                $this->info("Sample record:");
                $this->info("  Tipo: " . $results[0]['TIPO_DOCTO']);
                $this->info("  Número: " . $results[0]['NRO_DOCTO']);
                $this->info("  Cliente: " . $results[0]['CLIENTE']);
                $this->info("  Vendedor: " . $results[0]['VENDEDOR']);
                $this->info("  Valor: " . $results[0]['VALOR']);
            }

        } catch (PDOException $e) {
            $this->error("❌ Direct PDO connection failed: " . $e->getMessage());
            
            // Probar con Docker bridge como fallback
            $this->info("\nTrying Docker bridge connection...");
            
            try {
                $host = env('SQLSRV_EXTERNAL_HOST');
                $port = env('SQLSRV_EXTERNAL_PORT', '1433');
                $database = env('SQLSRV_EXTERNAL_DATABASE');
                $username = env('SQLSRV_EXTERNAL_USERNAME');
                $password = env('SQLSRV_EXTERNAL_PASSWORD');
                
                // Verificar que las credenciales estén configuradas
                if (!$host || !$database || !$username || !$password) {
                    $this->error("❌ Credenciales SQL Server no configuradas en .env");
                    return 1;
                }

                $this->info("Connecting to external database: {$host}:{$port}/{$database}");
                $this->info("Username: {$username}");

                // Probar consulta de vendedores
                $this->info("\nTesting vendedores query...");
                $sqlcmd1 = "docker exec sqlserver_bridge tsql -S HIGUERA030924 -D HIGUERA030924 -U {$username} -P {$password} -C -Q \"SELECT TOP 5 KOFU, NOKOFU, RTFU FROM TABFU WHERE KOFU = 'GOP'\"";
                $output1 = shell_exec($sqlcmd1 . " 2>&1");
                
                if ($output1 !== null) {
                    $this->info("✅ Vendedores query successful!");
                    $this->info("Output: " . substr($output1, 0, 500) . "...");
                }

                // Probar consulta de cobranza para el vendedor GOP
                $this->info("\nTesting cobranza query for GOP...");
                $sqlcmd2 = "docker exec sqlserver_bridge tsql -S HIGUERA030924 -D HIGUERA030924 -U {$username} -P {$password} -C -Q \"SELECT TOP 3 TIDO, NUDO, ENDO, NOKOEN, VABRDO, VAABDO FROM MAEEDO INNER JOIN MAEEN ON MAEEDO.ENDO = MAEEN.KOEN WHERE MAEEN.KOFUEN = 'GOP'\"";
                $output2 = shell_exec($sqlcmd2 . " 2>&1");
                
                if ($output2 !== null) {
                    $this->info("✅ Cobranza query successful!");
                    $this->info("Output: " . substr($output2, 0, 500) . "...");
                }

                // Probar consulta de clientes del vendedor GOP
                $this->info("\nTesting clientes query for GOP...");
                $sqlcmd3 = "docker exec sqlserver_bridge tsql -S HIGUERA030924 -D HIGUERA030924 -U {$username} -P {$password} -C -Q \"SELECT TOP 3 KOEN, NOKOEN, FOEN, DIEN FROM MAEEN WHERE KOFUEN = 'GOP'\"";
                $output3 = shell_exec($sqlcmd3 . " 2>&1");
                
                if ($output3 !== null) {
                    $this->info("✅ Clientes query successful!");
                    $this->info("Output: " . substr($output3, 0, 500) . "...");
                    
                    // Probar el servicio de cobranza real
                    $this->info("\nTesting CobranzaService with real data...");
                    $cobranzaService = new \App\Services\CobranzaService();
                    
                    // Probar consulta de cobranza
                    $cobranzaData = $cobranzaService->getCobranza('GOP');
                    $this->info("✅ CobranzaService cobranza query successful! Found " . count($cobranzaData) . " records");
                    
                    // Probar consulta de clientes
                    $clientesData = $cobranzaService->getClientesPorVendedor('GOP');
                    $this->info("✅ CobranzaService clientes query successful! Found " . count($clientesData) . " records");
                    
                    if (count($clientesData) > 0) {
                        $this->info("Sample cliente record:");
                        $this->info("  Código: " . ($clientesData[0]['CODIGO_CLIENTE'] ?? 'N/A'));
                        $this->info("  Nombre: " . ($clientesData[0]['NOMBRE_CLIENTE'] ?? 'N/A'));
                        $this->info("  Facturas: " . ($clientesData[0]['CANTIDAD_FACTURAS'] ?? 'N/A'));
                        $this->info("  Saldo: " . ($clientesData[0]['SALDO_TOTAL'] ?? 'N/A'));
                    }
                }

            } catch (\Exception $e) {
                $this->error("❌ Docker bridge connection also failed: " . $e->getMessage());
                $this->info("\nMake sure the SQL Server bridge container is running:");
                $this->info("docker-compose -f docker-compose-sqlsrv.yml up -d");
                return 1;
            }
        }

        return 0;
    }
}
