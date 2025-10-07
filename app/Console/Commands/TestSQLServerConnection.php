<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TestSQLServerConnection extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:sqlserver-nvv 
                            {--db= : Base de datos a usar (HIGUERA o HIGUERA030924)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Probar conexión e inserts a SQL Server';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $database = $this->option('db') ?? env('SQLSRV_EXTERNAL_DATABASE');
        
        $this->info("🔍 Probando conexión a SQL Server...");
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("📊 Host: " . env('SQLSRV_EXTERNAL_HOST'));
        $this->info("📊 Puerto: " . env('SQLSRV_EXTERNAL_PORT'));
        $this->info("📊 Database: {$database}");
        $this->info("📊 Usuario: " . env('SQLSRV_EXTERNAL_USERNAME'));
        $this->newLine();

        // Test 1: Conexión básica
        $this->info("Test 1: Verificando conexión básica...");
        if (!$this->testConnection($database)) {
            $this->error("❌ No se pudo conectar a SQL Server");
            return 1;
        }
        $this->info("✅ Conexión exitosa");
        $this->newLine();

        // Test 2: SELECT en MAEEDO
        $this->info("Test 2: Consultando tabla MAEEDO...");
        $countMAEEDO = $this->getTableCount('MAEEDO', $database);
        if ($countMAEEDO !== false) {
            $this->info("✅ MAEEDO tiene {$countMAEEDO} registros");
        } else {
            $this->error("❌ Error consultando MAEEDO");
        }
        $this->newLine();

        // Test 3: SELECT en MAEDDO
        $this->info("Test 3: Consultando tabla MAEDDO...");
        $countMAEDDO = $this->getTableCount('MAEDDO', $database);
        if ($countMAEDDO !== false) {
            $this->info("✅ MAEDDO tiene {$countMAEDDO} registros");
        } else {
            $this->error("❌ Error consultando MAEDDO");
        }
        $this->newLine();

        // Test 4: Obtener siguiente correlativo
        $this->info("Test 4: Obteniendo siguiente correlativo para NVV...");
        $siguienteId = $this->getSiguienteCorrelativo($database);
        if ($siguienteId !== false) {
            $this->info("✅ Siguiente ID disponible: {$siguienteId}");
        } else {
            $this->error("❌ Error obteniendo correlativo");
        }
        $this->newLine();

        // Test 5: SELECT últimas 5 NVV
        $this->info("Test 5: Consultando últimas 5 Notas de Venta...");
        $ultimasNVV = $this->getUltimasNVV($database);
        if ($ultimasNVV !== false) {
            $this->info("✅ Últimas NVV registradas:");
            foreach ($ultimasNVV as $nvv) {
                $this->line("   • ID: {$nvv['IDMAEEDO']} | NVV: {$nvv['NUDO']} | Cliente: {$nvv['ENDO']} | Total: \${$nvv['VABRDO']}");
            }
        } else {
            $this->error("❌ Error consultando NVV");
        }
        $this->newLine();

        // Test 6: Verificar permisos de INSERT
        $this->info("Test 6: Verificando permisos de escritura...");
        if ($this->confirm('¿Deseas hacer un test de INSERT (se insertará y eliminará una NVV de prueba)?', false)) {
            if ($this->testInsertPermissions($database, $siguienteId)) {
                $this->info("✅ Permisos de INSERT/DELETE verificados correctamente");
            } else {
                $this->error("❌ Error en permisos de escritura");
            }
        } else {
            $this->warn("⏭️  Test de INSERT omitido");
        }
        
        $this->newLine();
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("✅ Pruebas completadas");
        
        return 0;
    }

    private function testConnection($database)
    {
        $query = "SELECT 1 AS test";
        $result = $this->executeTSQL($query, $database);
        return $result !== false && !str_contains($result, 'error');
    }

    private function getTableCount($table, $database)
    {
        $query = "SELECT COUNT(*) AS total FROM {$table}";
        $result = $this->executeTSQL($query, $database);
        
        if ($result && !str_contains($result, 'error')) {
            $lines = explode("\n", $result);
            foreach ($lines as $line) {
                $line = trim($line);
                if (is_numeric($line)) {
                    return (int)$line;
                }
            }
        }
        
        return false;
    }

    private function getSiguienteCorrelativo($database)
    {
        $query = "SELECT ISNULL(MAX(IDMAEEDO), 0) + 1 AS siguiente_id FROM MAEEDO WHERE EMPRESA = '01'";
        $result = $this->executeTSQL($query, $database);
        
        if ($result && !str_contains($result, 'error')) {
            $lines = explode("\n", $result);
            foreach ($lines as $line) {
                $line = trim($line);
                if (is_numeric($line) && $line > 0) {
                    return (int)$line;
                }
            }
        }
        
        return false;
    }

    private function getUltimasNVV($database)
    {
        $query = "SELECT TOP 5 IDMAEEDO, NUDO, ENDO, VABRDO, FEEMDO FROM MAEEDO WHERE TIDO = 'NVV' ORDER BY IDMAEEDO DESC";
        $result = $this->executeTSQL($query, $database);
        
        if ($result && !str_contains($result, 'error')) {
            $lines = array_filter(explode("\n", $result), function($line) {
                return !empty(trim($line)) && !str_contains($line, '---') && !str_contains($line, 'IDMAEEDO');
            });
            
            $nvvs = [];
            foreach ($lines as $line) {
                $parts = preg_split('/\s+/', trim($line));
                if (count($parts) >= 4) {
                    $nvvs[] = [
                        'IDMAEEDO' => $parts[0] ?? '',
                        'NUDO' => $parts[1] ?? '',
                        'ENDO' => $parts[2] ?? '',
                        'VABRDO' => $parts[3] ?? '0'
                    ];
                }
            }
            
            return $nvvs;
        }
        
        return false;
    }

    private function testInsertPermissions($database, $siguienteId)
    {
        try {
            // Insertar NVV de prueba
            $testId = $siguienteId + 999999; // ID muy alto para prueba
            $insertQuery = "
                INSERT INTO MAEEDO (
                    IDMAEEDO, TIDO, NUDO, ENDO, SUENDO, FEEMDO, VABRDO, EMPRESA, SUDO, ESDO
                ) VALUES (
                    {$testId}, 'NVV', {$testId}, '77000000', '001', GETDATE(), 1000, '01', '001', 'N'
                )
            ";
            
            $this->line("   Insertando NVV de prueba (ID: {$testId})...");
            $result = $this->executeTSQL($insertQuery, $database);
            
            if ($result === false || str_contains($result, 'error')) {
                $this->error("   Error en INSERT: " . substr($result, 0, 100));
                return false;
            }
            
            $this->info("   ✓ INSERT exitoso");
            
            // Eliminar NVV de prueba
            $deleteQuery = "DELETE FROM MAEEDO WHERE IDMAEEDO = {$testId}";
            $this->line("   Eliminando NVV de prueba...");
            $result = $this->executeTSQL($deleteQuery, $database);
            
            if ($result === false || str_contains($result, 'error')) {
                $this->warn("   ⚠️  Error en DELETE (registro puede quedar en la BD)");
                return false;
            }
            
            $this->info("   ✓ DELETE exitoso");
            
            return true;
            
        } catch (\Exception $e) {
            $this->error("   Exception: " . $e->getMessage());
            return false;
        }
    }

    private function executeTSQL($query, $database)
    {
        try {
            $tempFile = tempnam(sys_get_temp_dir(), 'sql_test_');
            file_put_contents($tempFile, $query . "\ngo\nquit");
            
            $command = "tsql -H " . env('SQLSRV_EXTERNAL_HOST') . 
                       " -p " . env('SQLSRV_EXTERNAL_PORT') . 
                       " -U " . env('SQLSRV_EXTERNAL_USERNAME') . 
                       " -P " . env('SQLSRV_EXTERNAL_PASSWORD') . 
                       " -D {$database} < {$tempFile} 2>&1";
            
            $result = shell_exec($command);
            
            unlink($tempFile);
            
            return $result;
            
        } catch (\Exception $e) {
            return false;
        }
    }
}
