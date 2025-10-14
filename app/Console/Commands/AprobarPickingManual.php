<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Cotizacion;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class AprobarPickingManual extends Command
{
    protected $signature = 'aprobar:picking {cotizacion_id}';
    protected $description = 'Aprobar cotización por picking manualmente con logs detallados';

    public function handle()
    {
        $cotizacionId = $this->argument('cotizacion_id');
        
        $cotizacion = Cotizacion::with('productos', 'user')->find($cotizacionId);
        
        if (!$cotizacion) {
            $this->error("Cotización no encontrada");
            return 1;
        }
        
        $this->info("=== APROBANDO COTIZACIÓN #{$cotizacionId} ===");
        $this->info("Cliente: {$cotizacion->cliente_nombre} ({$cotizacion->cliente_codigo})");
        $this->info("Total: \${$cotizacion->total}");
        $this->info("Estado actual: {$cotizacion->estado_aprobacion}");
        $this->info("");
        
        if (!$this->confirm('¿Continuar con la aprobación?', true)) {
            return 0;
        }
        
        try {
            // Aprobar en MySQL
            $picking = User::find(7); // Usuario picking
            $cotizacion->aprobarPorPicking($picking->id, 'Aprobado manualmente con logs detallados');
            
            $this->info("✓ Aprobado en MySQL");
            $this->info("");
            
            // Ejecutar insert en SQL Server
            $this->info("=== EJECUTANDO INSERT EN SQL SERVER ===");
            $this->info("Los logs detallados se guardarán en storage/logs/laravel.log");
            $this->info("");
            
            $resultado = $this->insertarEnSQLServer($cotizacion);
            
            if ($resultado['success']) {
                $this->info("");
                $this->info("🎉 ¡ÉXITO!");
                $this->info("NVV N° {$resultado['nota_venta_id']} creada en SQL Server");
                $this->info("Número correlativo: {$resultado['numero_correlativo']}");
                
                // Guardar número correlativo (NUDO) no el ID interno
                $cotizacion->numero_nvv = $resultado['numero_correlativo'];
                $cotizacion->save();
                
                $this->info("✓ Número NVV {$resultado['numero_correlativo']} guardado en MySQL");
            } else {
                $this->error("Error al insertar en SQL Server");
                return 1;
            }
            
        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            Log::error("Error en aprobar:picking: " . $e->getMessage());
            return 1;
        }
        
        return 0;
    }
    
    private function insertarEnSQLServer($cotizacion)
    {
        try {
            // Obtener siguiente ID
            $queryId = "SELECT TOP 1 ISNULL(MAX(IDMAEEDO), 0) + 1 AS siguiente_id FROM MAEEDO WHERE EMPRESA = '01'";
            $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
            file_put_contents($tempFile, $queryId . "\ngo\nquit");
            
            $command = "tsql -H " . env('SQLSRV_EXTERNAL_HOST') . " -p " . env('SQLSRV_EXTERNAL_PORT') . " -U " . env('SQLSRV_EXTERNAL_USERNAME') . " -P " . env('SQLSRV_EXTERNAL_PASSWORD') . " -D " . env('SQLSRV_EXTERNAL_DATABASE') . " < {$tempFile} 2>&1";
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
            
            Log::info("Siguiente IDMAEEDO: {$siguienteId}");
            
            // Obtener siguiente NUDO con verificación anti-colisión
            $maxIntentos = 5;
            $intento = 0;
            $nudoFormateado = null;
            $siguienteNudo = null;
            
            while ($intento < $maxIntentos && !$nudoFormateado) {
                $intento++;
                
                // Obtener último NUDO de NVV específicamente (cada tipo tiene su propia secuencia)
                // IMPORTANTE: Filtrar por TIDO = 'NVV' porque cada tipo de documento tiene numeración independiente
                $queryNudo = "SELECT TOP 1 CAST(NUDO AS INT) as ULTIMO_NUDO FROM MAEEDO WHERE TIDO = 'NVV' AND ISNUMERIC(NUDO) = 1 ORDER BY IDMAEEDO DESC";
                $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
                file_put_contents($tempFile, $queryNudo . "\ngo\nquit");
                
                $command = "tsql -H " . env('SQLSRV_EXTERNAL_HOST') . " -p " . env('SQLSRV_EXTERNAL_PORT') . " -U " . env('SQLSRV_EXTERNAL_USERNAME') . " -P " . env('SQLSRV_EXTERNAL_PASSWORD') . " -D " . env('SQLSRV_EXTERNAL_DATABASE') . " < {$tempFile} 2>&1";
                $result = shell_exec($command);
                unlink($tempFile);
                
                $ultimoNudo = 37559;
                if ($result && !str_contains($result, 'error')) {
                    $lines = explode("\n", $result);
                    foreach ($lines as $line) {
                        $line = trim($line);
                        if (is_numeric($line) && $line > 0) {
                            $ultimoNudo = (int)$line;
                            break;
                        }
                    }
                }
                
                $siguienteNudo = $ultimoNudo + 1;
                $nudoFormateado = str_pad($siguienteNudo, 10, '0', STR_PAD_LEFT);
                
                Log::info("Intento {$intento}: Último NUDO: {$ultimoNudo}, Siguiente: {$nudoFormateado}");
                
                // Verificar que no exista (anti-colisión)
                $queryVerificar = "SELECT COUNT(*) as EXISTE FROM MAEEDO WHERE LTRIM(RTRIM(NUDO)) = '{$nudoFormateado}'";
                $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
                file_put_contents($tempFile, $queryVerificar . "\ngo\nquit");
                
                $command = "tsql -H " . env('SQLSRV_EXTERNAL_HOST') . " -p " . env('SQLSRV_EXTERNAL_PORT') . " -U " . env('SQLSRV_EXTERNAL_USERNAME') . " -P " . env('SQLSRV_EXTERNAL_PASSWORD') . " -D " . env('SQLSRV_EXTERNAL_DATABASE') . " < {$tempFile} 2>&1";
                $result = shell_exec($command);
                unlink($tempFile);
                
                $existe = false;
                if ($result && !str_contains($result, 'error')) {
                    $lines = explode("\n", $result);
                    foreach ($lines as $line) {
                        $line = trim($line);
                        if (is_numeric($line)) {
                            $existe = ((int)$line > 0);
                            break;
                        }
                    }
                }
                
                if ($existe) {
                    Log::warning("⚠️ NUDO {$nudoFormateado} ya existe (colisión detectada), reintentando...");
                    $nudoFormateado = null;
                    sleep(1);
                } else {
                    Log::info("✓ NUDO {$nudoFormateado} disponible y único");
                }
            }
            
            if (!$nudoFormateado) {
                throw new \Exception("No se pudo obtener un número correlativo único después de {$maxIntentos} intentos");
            }
            
            // Obtener sucursal del cliente
            $querySucursal = "SELECT LTRIM(RTRIM(SUEN)) as SUCURSAL FROM MAEEN WHERE KOEN = '{$cotizacion->cliente_codigo}'";
            $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
            file_put_contents($tempFile, $querySucursal . "\ngo\nquit");
            
            $command = "tsql -H " . env('SQLSRV_EXTERNAL_HOST') . " -p " . env('SQLSRV_EXTERNAL_PORT') . " -U " . env('SQLSRV_EXTERNAL_USERNAME') . " -P " . env('SQLSRV_EXTERNAL_PASSWORD') . " -D " . env('SQLSRV_EXTERNAL_DATABASE') . " < {$tempFile} 2>&1";
            $result = shell_exec($command);
            unlink($tempFile);
            
            $sucursalCliente = '';
            if ($result && !str_contains($result, 'error')) {
                $lines = explode("\n", $result);
                $foundHeader = false;
                foreach ($lines as $line) {
                    $line = trim($line);
                    // Primero encontrar el header "SUCURSAL"
                    if ($line === 'SUCURSAL') {
                        $foundHeader = true;
                        continue;
                    }
                    // Después del header, la siguiente línea con contenido es el valor
                    if ($foundHeader && !empty($line) && !str_contains($line, 'row') && !str_contains($line, '---') && !str_contains($line, '>')) {
                        $sucursalCliente = $line;
                        break;
                    }
                }
            }
            
            // Si la sucursal está vacía o no se encontró, dejar vacío (no usar '001')
            Log::info("Sucursal del cliente '{$cotizacion->cliente_codigo}': '{$sucursalCliente}' " . (empty($sucursalCliente) ? "(vacía)" : ""));
            
            $codigoVendedor = $cotizacion->user->codigo_vendedor ?? '001';
            $fechaVencimiento = date('Y-m-d', strtotime('+30 days'));
            
            Log::info("=== DATOS PARA INSERT ===");
            Log::info("IDMAEEDO: {$siguienteId}");
            Log::info("NUDO: {$nudoFormateado}");
            Log::info("ENDO (Cliente): {$cotizacion->cliente_codigo}");
            Log::info("SUENDO (Sucursal): '{$sucursalCliente}'");
            Log::info("KOFUDO (Vendedor): {$codigoVendedor}");
            Log::info("VABRDO (Total): {$cotizacion->total}");
            
            // Insert MAEEDO
            $insertMAEEDO = "
                SET IDENTITY_INSERT MAEEDO ON
                
                INSERT INTO MAEEDO (
                    IDMAEEDO, EMPRESA, TIDO, NUDO, ENDO, SUENDO, SUDO,
                    TIGEDO, LUVTDO, MEARDO,
                    FEEMDO, FE01VEDO, FEULVEDO, 
                    VABRDO, VANEDO, VAABDO, ESDO, KOFUDO
                ) VALUES (
                    {$siguienteId}, '01', 'NVV', '{$nudoFormateado}', '{$cotizacion->cliente_codigo}', 
                    '{$sucursalCliente}', '001',
                    'I', 'LIB', 'S',
                    GETDATE(), '{$fechaVencimiento}', '{$fechaVencimiento}', 
                    {$cotizacion->total}, {$cotizacion->total}, 0, 'N', '{$codigoVendedor}'
                )
                
                SET IDENTITY_INSERT MAEEDO OFF
            ";
            
            Log::info("=== SQL INSERT MAEEDO ===");
            Log::info($insertMAEEDO);
            
            $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
            file_put_contents($tempFile, $insertMAEEDO . "\ngo\nquit");
            
            $command = "tsql -H " . env('SQLSRV_EXTERNAL_HOST') . " -p " . env('SQLSRV_EXTERNAL_PORT') . " -U " . env('SQLSRV_EXTERNAL_USERNAME') . " -P " . env('SQLSRV_EXTERNAL_PASSWORD') . " -D " . env('SQLSRV_EXTERNAL_DATABASE') . " < {$tempFile} 2>&1";
            $result = shell_exec($command);
            unlink($tempFile);
            
            if (str_contains($result, 'Msg') || str_contains($result, 'Error')) {
                Log::error("Error insertando MAEEDO: " . $result);
                throw new \Exception('Error insertando encabezado: ' . $result);
            }
            
            Log::info("✓ MAEEDO insertado correctamente");
            
            // Insert MAEDDO
            foreach ($cotizacion->productos as $index => $producto) {
                $lineaId = $index + 1;
                $subtotal = $producto->cantidad * $producto->precio_unitario;
                
                // Obtener datos del producto desde MySQL
                $productoDB = \App\Models\Producto::where('KOPR', $producto->codigo_producto)->first();
                
                $udtrpr = 1;
                $rludpr = 1;
                $ud01pr = 'UN';
                $ud02pr = 'CJ';
                
                if ($productoDB) {
                    $rludpr = $productoDB->RLUD ?? 1;
                    $ud01pr = trim($productoDB->UD01PR ?? 'UN');
                    $ud02pr = trim($productoDB->UD02PR ?? 'CJ');
                    $udtrpr = ($rludpr > 1) ? 2 : 1;
                }
                
                Log::info("=== PRODUCTO #{$lineaId} ===");
                Log::info("KOPRCT: {$producto->codigo_producto}");
                Log::info("NOKOPR: {$producto->nombre_producto}");
                Log::info("CAPRCO1: {$producto->cantidad}");
                Log::info("PPPRNE: {$producto->precio_unitario}");
                Log::info("VANELI: {$subtotal}");
                Log::info("UDTRPR: {$udtrpr}, RLUDPR: {$rludpr}, UD01PR: {$ud01pr}, UD02PR: {$ud02pr}");
                
                $insertMAEDDO = "
                    INSERT INTO MAEDDO (
                        IDMAEEDO, EMPRESA, TIDO, NUDO, ENDO, SUENDO,
                        LILG, NULIDO, KOPRCT, NOKOPR, 
                        CAPRCO1, PPPRNE, VANELI, VABRLI,
                        KOFULIDO, UDTRPR, RLUDPR, UD01PR, UD02PR,
                        FEEMLI, FEERLI
                    ) VALUES (
                        {$siguienteId}, '01', 'NVV', '{$nudoFormateado}',
                        '{$cotizacion->cliente_codigo}', '{$sucursalCliente}',
                        'SI', '{$lineaId}', '{$producto->codigo_producto}', '{$producto->nombre_producto}',
                        {$producto->cantidad}, {$producto->precio_unitario}, {$subtotal}, {$subtotal},
                        '{$codigoVendedor}', {$udtrpr}, {$rludpr}, '{$ud01pr}', '{$ud02pr}',
                        GETDATE(), '{$cotizacion->fecha_despacho->format('Y-m-d H:i:s')}'
                    )
                ";
                
                Log::info("SQL INSERT MAEDDO:");
                Log::info($insertMAEDDO);
                
                $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
                file_put_contents($tempFile, $insertMAEDDO . "\ngo\nquit");
                
                $command = "tsql -H " . env('SQLSRV_EXTERNAL_HOST') . " -p " . env('SQLSRV_EXTERNAL_PORT') . " -U " . env('SQLSRV_EXTERNAL_USERNAME') . " -P " . env('SQLSRV_EXTERNAL_PASSWORD') . " -D " . env('SQLSRV_EXTERNAL_DATABASE') . " < {$tempFile} 2>&1";
                $result = shell_exec($command);
                unlink($tempFile);
                
                if (str_contains($result, 'Msg') || str_contains($result, 'Error')) {
                    Log::error("Error insertando MAEDDO línea {$lineaId}: " . $result);
                    throw new \Exception('Error insertando detalle');
                }
                
                Log::info("✓ MAEDDO línea {$lineaId} insertado");
            }
            
            Log::info("=== INSERT COMPLETADO ===");
            
            return [
                'success' => true,
                'nota_venta_id' => $siguienteId,
                'numero_correlativo' => $nudoFormateado,
                'message' => 'NVV insertada correctamente'
            ];
            
        } catch (\Exception $e) {
            Log::error('Error en insertarEnSQLServer: ' . $e->getMessage());
            throw $e;
        }
    }
}

