<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Cotizacion;
use Illuminate\Support\Facades\Log;

class TestInsertNVV extends Command
{
    protected $signature = 'test:insert-nvv {cotizacion_id}';
    protected $description = 'Probar insert de NVV en base de datos de respaldo';

    public function handle()
    {
        $cotizacionId = $this->argument('cotizacion_id');
        
        $this->info("=== PRUEBA DE INSERT NVV ===");
        $this->info("Cotización ID: {$cotizacionId}");
        $this->info("");
        
        // Cargar cotización
        $cotizacion = Cotizacion::with('productos', 'user')->find($cotizacionId);
        
        if (!$cotizacion) {
            $this->error("Cotización no encontrada");
            return 1;
        }
        
        $this->info("Cliente: {$cotizacion->cliente_nombre} ({$cotizacion->cliente_codigo})");
        $this->info("Total: \${$cotizacion->total}");
        $this->info("Productos: " . $cotizacion->productos->count());
        $this->info("Estado: {$cotizacion->estado_aprobacion}");
        $this->info("");
        
        // Mostrar productos
        $this->info("=== PRODUCTOS ===");
        foreach ($cotizacion->productos as $producto) {
            $this->info("  - {$producto->codigo_producto}: {$producto->nombre_producto}");
            $this->info("    Cantidad: {$producto->cantidad} | Precio: \${$producto->precio_unitario}");
            $this->info("    Stock disponible: {$producto->stock_disponible}");
        }
        $this->info("");
        
        // Verificar conexión SQL Server
        $this->info("=== VERIFICANDO CONEXIÓN SQL SERVER ===");
        $host = env('SQLSRV_EXTERNAL_HOST');
        $port = env('SQLSRV_EXTERNAL_PORT');
        $database = env('SQLSRV_EXTERNAL_DATABASE');
        
        $this->info("Host: {$host}");
        $this->info("Port: {$port}");
        $this->info("Database: {$database}");
        $this->info("");
        
        // Test de conexión
        $testQuery = "SELECT TOP 1 IDMAEEDO FROM MAEEDO WHERE EMPRESA = '01' ORDER BY IDMAEEDO DESC";
        $tempFile = tempnam(sys_get_temp_dir(), 'sql_test_');
        file_put_contents($tempFile, $testQuery . "\ngo\nquit");
        
        $command = "tsql -H {$host} -p {$port} -U " . env('SQLSRV_EXTERNAL_USERNAME') . " -P " . env('SQLSRV_EXTERNAL_PASSWORD') . " -D {$database} < {$tempFile} 2>&1";
        $result = shell_exec($command);
        
        unlink($tempFile);
        
        $this->info("Resultado de conexión:");
        $this->line($result);
        $this->info("");
        
        if (str_contains($result, 'error') || str_contains($result, 'Error')) {
            $this->error("Error de conexión a SQL Server");
            return 1;
        }
        
        // Obtener siguiente ID
        $this->info("=== OBTENIENDO SIGUIENTE ID ===");
        $queryCorrelativo = "SELECT TOP 1 ISNULL(MAX(IDMAEEDO), 0) + 1 AS siguiente_id FROM MAEEDO WHERE EMPRESA = '01'";
        
        $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
        file_put_contents($tempFile, $queryCorrelativo . "\ngo\nquit");
        
        $command = "tsql -H {$host} -p {$port} -U " . env('SQLSRV_EXTERNAL_USERNAME') . " -P " . env('SQLSRV_EXTERNAL_PASSWORD') . " -D {$database} < {$tempFile} 2>&1";
        $result = shell_exec($command);
        
        unlink($tempFile);
        
        $siguienteId = 1;
        if ($result && !str_contains($result, 'error')) {
            $lines = explode("\n", $result);
            foreach ($lines as $line) {
                $line = trim($line);
                if (is_numeric($line) && $line > 0) {
                    $siguienteId = (int)$line;
                    break;
                }
            }
        }
        
        $this->info("Siguiente ID para NVV: {$siguienteId}");
        $this->info("");
        
        // Preparar datos
        $fechaVencimiento = date('Y-m-d', strtotime('+30 days'));
        $codigoVendedor = $cotizacion->user->codigo_vendedor ?? '001';
        $nombreVendedor = $cotizacion->user->name ?? 'Vendedor Sistema';
        
        $this->info("=== DATOS DE INSERT ===");
        $this->info("Vendedor: {$nombreVendedor} ({$codigoVendedor})");
        $this->info("Fecha vencimiento: {$fechaVencimiento}");
        $this->info("");
        
        // Generar SQL de INSERT para MAEEDO
        $this->info("=== SQL INSERT MAEEDO ===");
        $insertMAEEDO = "
            INSERT INTO MAEEDO (
                IDMAEEDO, TIDO, NUDO, ENDO, SUENDO, FEEMDO, FE01VEDO, FEULVEDO, 
                VABRDO, VAABDO, EMPRESA, KOFU, SUDO, ESDO, TIDOEXTE, NUDOEXTE,
                FEULVEDO, KOFUEN, KOFUAUX, KOFUPA, KOFUVE, KOFUCO, KOFUCA,
                KOFUCH, KOFUPE, KOFUIN, KOFUAD, KOFUGE, KOFUGE2, KOFUGE3,
                KOFUGE4, KOFUGE5, KOFUGE6, KOFUGE7, KOFUGE8, KOFUGE9, KOFUGE10
            ) VALUES (
                {$siguienteId}, 'NVV', {$siguienteId}, '{$cotizacion->cliente_codigo}', 
                '001', GETDATE(), '{$fechaVencimiento}', '{$fechaVencimiento}', 
                {$cotizacion->total}, 0, '01', '{$codigoVendedor}', '001', 'N',
                'NVV', {$siguienteId}, '{$fechaVencimiento}', '{$codigoVendedor}',
                '{$codigoVendedor}', '{$codigoVendedor}', '{$codigoVendedor}',
                '{$codigoVendedor}', '{$codigoVendedor}', '{$codigoVendedor}',
                '{$codigoVendedor}', '{$codigoVendedor}', '{$codigoVendedor}',
                '{$codigoVendedor}', '{$codigoVendedor}', '{$codigoVendedor}',
                '{$codigoVendedor}', '{$codigoVendedor}', '{$codigoVendedor}',
                '{$codigoVendedor}', '{$codigoVendedor}', '{$codigoVendedor}',
                '{$codigoVendedor}', '{$codigoVendedor}', '{$codigoVendedor}',
                '{$codigoVendedor}', '{$codigoVendedor}'
            )
        ";
        
        $this->line($insertMAEEDO);
        $this->info("");
        
        // Generar SQL de INSERT para MAEDDO
        $this->info("=== SQL INSERT MAEDDO ===");
        foreach ($cotizacion->productos as $index => $producto) {
            $lineaId = $index + 1;
            $subtotal = $producto->cantidad * $producto->precio_unitario;
            
            $insertMAEDDO = "
                INSERT INTO MAEDDO (
                    IDMAEEDO, IDMAEDDO, KOPRCT, NOKOPR, CAPRCO1, PPPRNE, 
                    CAPRCO2, PPPRNE2, EMPRESA, TIDO, NUDO, ENDO, SUENDO,
                    FEEMLI, FEULVE, VANELI, VABRLI, VAABLI, ESDO, LILG,
                    CAPRAD1, CAPREX1, CAPRAD2, CAPREX2, KOFULIDO, KOFUAUX,
                    KOFUPA, KOFUVE, KOFUCO, KOFUCA, KOFUCH, KOFUPE, KOFUIN,
                    KOFUAD, KOFUGE, KOFUGE2, KOFUGE3, KOFUGE4, KOFUGE5,
                    KOFUGE6, KOFUGE7, KOFUGE8, KOFUGE9, KOFUGE10
                ) VALUES (
                    {$siguienteId}, {$lineaId}, '{$producto->codigo_producto}', 
                    '{$producto->nombre_producto}', {$producto->cantidad}, 
                    {$producto->precio_unitario}, 0, 0, '01', 'NVV', {$siguienteId},
                    '{$cotizacion->cliente_codigo}', '001', GETDATE(),
                    '{$fechaVencimiento}', {$subtotal}, {$subtotal}, 0, 'N', 'SI',
                    0, 0, 0, 0, '{$codigoVendedor}', '{$codigoVendedor}',
                    '{$codigoVendedor}', '{$codigoVendedor}', '{$codigoVendedor}',
                    '{$codigoVendedor}', '{$codigoVendedor}', '{$codigoVendedor}',
                    '{$codigoVendedor}', '{$codigoVendedor}', '{$codigoVendedor}',
                    '{$codigoVendedor}', '{$codigoVendedor}', '{$codigoVendedor}',
                    '{$codigoVendedor}', '{$codigoVendedor}', '{$codigoVendedor}',
                    '{$codigoVendedor}', '{$codigoVendedor}', '{$codigoVendedor}',
                    '{$codigoVendedor}', '{$codigoVendedor}'
                )
            ";
            
            $this->line("Línea {$lineaId}: {$producto->codigo_producto}");
            $this->line($insertMAEDDO);
            $this->info("");
        }
        
        // Generar SQL de UPDATE para MAEST
        $this->info("=== SQL UPDATE MAEST (Stock) ===");
        foreach ($cotizacion->productos as $producto) {
            $updateMAEST = "
                UPDATE MAEST 
                SET STOCKSALIDA = ISNULL(STOCKSALIDA, 0) + {$producto->cantidad}
                WHERE KOPR = '{$producto->codigo_producto}' AND EMPRESA = '01'
            ";
            
            $this->line("Producto: {$producto->codigo_producto} - Cantidad: {$producto->cantidad}");
            $this->line($updateMAEST);
            $this->info("");
        }
        
        // Preguntar si ejecutar
        if ($this->confirm('¿Deseas ejecutar el INSERT en la base de datos de respaldo?', false)) {
            $this->info("Ejecutando INSERT...");
            
            try {
                // INSERT MAEEDO
                $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
                file_put_contents($tempFile, $insertMAEEDO . "\ngo\nquit");
                
                $command = "tsql -H {$host} -p {$port} -U " . env('SQLSRV_EXTERNAL_USERNAME') . " -P " . env('SQLSRV_EXTERNAL_PASSWORD') . " -D {$database} < {$tempFile} 2>&1";
                $result = shell_exec($command);
                
                unlink($tempFile);
                
                if (str_contains($result, 'error') || str_contains($result, 'Error')) {
                    $this->error("Error insertando MAEEDO:");
                    $this->line($result);
                    return 1;
                }
                
                $this->info("✓ MAEEDO insertado correctamente");
                
                // INSERT MAEDDO
                foreach ($cotizacion->productos as $index => $producto) {
                    $lineaId = $index + 1;
                    $subtotal = $producto->cantidad * $producto->precio_unitario;
                    
                    $insertMAEDDO = "
                        INSERT INTO MAEDDO (
                            IDMAEEDO, IDMAEDDO, KOPRCT, NOKOPR, CAPRCO1, PPPRNE, 
                            CAPRCO2, PPPRNE2, EMPRESA, TIDO, NUDO, ENDO, SUENDO,
                            FEEMLI, FEULVE, VANELI, VABRLI, VAABLI, ESDO, LILG,
                            CAPRAD1, CAPREX1, CAPRAD2, CAPREX2, KOFULIDO, KOFUAUX,
                            KOFUPA, KOFUVE, KOFUCO, KOFUCA, KOFUCH, KOFUPE, KOFUIN,
                            KOFUAD, KOFUGE, KOFUGE2, KOFUGE3, KOFUGE4, KOFUGE5,
                            KOFUGE6, KOFUGE7, KOFUGE8, KOFUGE9, KOFUGE10
                        ) VALUES (
                            {$siguienteId}, {$lineaId}, '{$producto->codigo_producto}', 
                            '{$producto->nombre_producto}', {$producto->cantidad}, 
                            {$producto->precio_unitario}, 0, 0, '01', 'NVV', {$siguienteId},
                            '{$cotizacion->cliente_codigo}', '001', GETDATE(),
                            '{$fechaVencimiento}', {$subtotal}, {$subtotal}, 0, 'N', 'SI',
                            0, 0, 0, 0, '{$codigoVendedor}', '{$codigoVendedor}',
                            '{$codigoVendedor}', '{$codigoVendedor}', '{$codigoVendedor}',
                            '{$codigoVendedor}', '{$codigoVendedor}', '{$codigoVendedor}',
                            '{$codigoVendedor}', '{$codigoVendedor}', '{$codigoVendedor}',
                            '{$codigoVendedor}', '{$codigoVendedor}', '{$codigoVendedor}',
                            '{$codigoVendedor}', '{$codigoVendedor}', '{$codigoVendedor}',
                            '{$codigoVendedor}', '{$codigoVendedor}', '{$codigoVendedor}',
                            '{$codigoVendedor}', '{$codigoVendedor}'
                        )
                    ";
                    
                    $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
                    file_put_contents($tempFile, $insertMAEDDO . "\ngo\nquit");
                    
                    $command = "tsql -H {$host} -p {$port} -U " . env('SQLSRV_EXTERNAL_USERNAME') . " -P " . env('SQLSRV_EXTERNAL_PASSWORD') . " -D {$database} < {$tempFile} 2>&1";
                    $result = shell_exec($command);
                    
                    unlink($tempFile);
                    
                    if (str_contains($result, 'error') || str_contains($result, 'Error')) {
                        $this->error("Error insertando MAEDDO línea {$lineaId}:");
                        $this->line($result);
                        return 1;
                    }
                    
                    $this->info("✓ MAEDDO línea {$lineaId} insertada correctamente");
                }
                
                // UPDATE MAEST
                foreach ($cotizacion->productos as $producto) {
                    $updateMAEST = "
                        UPDATE MAEST 
                        SET STOCKSALIDA = ISNULL(STOCKSALIDA, 0) + {$producto->cantidad}
                        WHERE KOPR = '{$producto->codigo_producto}' AND EMPRESA = '01'
                    ";
                    
                    $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
                    file_put_contents($tempFile, $updateMAEST . "\ngo\nquit");
                    
                    $command = "tsql -H {$host} -p {$port} -U " . env('SQLSRV_EXTERNAL_USERNAME') . " -P " . env('SQLSRV_EXTERNAL_PASSWORD') . " -D {$database} < {$tempFile} 2>&1";
                    $result = shell_exec($command);
                    
                    unlink($tempFile);
                    
                    if (str_contains($result, 'error') || str_contains($result, 'Error')) {
                        $this->warn("Advertencia actualizando MAEST para {$producto->codigo_producto}:");
                        $this->line($result);
                    } else {
                        $this->info("✓ MAEST actualizado para {$producto->codigo_producto}");
                    }
                }
                
                $this->info("");
                $this->info("=== INSERT COMPLETADO ===");
                $this->info("NVV N° {$siguienteId} creada exitosamente");
                
            } catch (\Exception $e) {
                $this->error("Error: " . $e->getMessage());
                return 1;
            }
        } else {
            $this->info("INSERT cancelado");
        }
        
        return 0;
    }
}
