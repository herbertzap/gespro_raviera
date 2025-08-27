<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Cliente;
// use Illuminate\Support\Facades\DB; // No usable due to SSL on Win2012; stick to tsql

class SincronizarClientesSimple extends Command
{
    protected $signature = 'clientes:sincronizar-simple {vendedor}';
    protected $description = 'Sincronizar clientes de manera simple para un vendedor específico';

    public function handle()
    {
        $vendedor = $this->argument('vendedor');
        $this->info("🔄 Sincronizando clientes para vendedor: {$vendedor}");
        
        try {
            $host = env('SQLSRV_EXTERNAL_HOST');
            $port = env('SQLSRV_EXTERNAL_PORT', '1433');
            $database = env('SQLSRV_EXTERNAL_DATABASE');
            $username = env('SQLSRV_EXTERNAL_USERNAME');
            $password = env('SQLSRV_EXTERNAL_PASSWORD');
            
            // Query con formato de salida específico para extraer cada campo por separado
            $query = "
                SELECT 
                    CAST(MAEEN.KOEN AS VARCHAR(20)) + '|' +
                    CAST(MAEEN.NOKOEN AS VARCHAR(100)) + '|' +
                    CAST(MAEEN.DIEN AS VARCHAR(100)) + '|' +
                    CAST(MAEEN.FOEN AS VARCHAR(20)) + '|' +
                    CAST(MAEEN.KOFUEN AS VARCHAR(10)) + '|' +
                    CAST(MAEEN.EMAIL AS VARCHAR(100)) + '|' +
                    CAST(MAEEN.BLOQUEADO AS VARCHAR(1)) + '|' +
                    CAST(MAEEN.CPEN AS VARCHAR(50)) + '|' +
                    CAST(MAEEN.DIPRVE AS VARCHAR(10)) + '|' +
                    CAST(MAEEN.OBEN AS VARCHAR(500)) AS DATOS_CLIENTE
                FROM dbo.MAEEN 
                WHERE MAEEN.KOFUEN = '{$vendedor}'
                ORDER BY MAEEN.NOKOEN
            ";
            
            // Crear archivo temporal con la consulta
            $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
            file_put_contents($tempFile, $query . "\ngo\nquit");
            
            // Ejecutar consulta usando tsql
            $command = "tsql -H {$host} -p {$port} -U {$username} -P {$password} -D {$database} < {$tempFile} 2>&1";
            $output = shell_exec($command);
            
            // Limpiar archivo temporal
            unlink($tempFile);
            
            if (!$output || str_contains($output, 'error')) {
                throw new \Exception('Error ejecutando consulta tsql: ' . $output);
            }
            
            $this->info("📋 Procesando datos...");
            
            // Procesar la salida línea por línea (como funcionaba con GOP)
            $lines = explode("\n", $output);
            $clientesExternos = [];
            $lineaNumero = 0;
            
            // Asegurar que la salida esté en UTF-8
            $output = mb_convert_encoding($output, 'UTF-8', 'UTF-8');
            
            foreach ($lines as $line) {
                $line = trim($line);
                $lineaNumero++;
                
                // Saltar líneas vacías o de configuración
                if (empty($line) || 
                    strpos($line, 'locale') !== false || 
                    strpos($line, 'Setting') !== false || 
                    strpos($line, 'Msg ') !== false || 
                    strpos($line, 'Warning:') !== false ||
                    preg_match('/^\d+>$/', $line) ||
                    preg_match('/^\d+>\s+\d+>\s+\d+>/', $line) ||
                    strpos($line, 'rows affected') !== false ||
                    strpos($line, 'KOEN') !== false ||
                    strpos($line, 'CODIGO_CLIENTE') !== false) {
                    continue;
                }
                
                // Buscar líneas que empiecen con números (códigos de cliente)
                if (preg_match('/^(\d{7,})/', $line)) {
                    $cliente = self::extraerClienteDeLinea($line, $vendedor);
                    if ($cliente) {
                        $clientesExternos[] = $cliente;
                    }
                }
            }
            
            $this->info("📊 Total de clientes encontrados: " . count($clientesExternos));
            
            if (count($clientesExternos) == 0) {
                $this->warn("⚠️ No se encontraron clientes para el vendedor {$vendedor}");
                return 0;
            }
            
            // Sincronizar clientes
            $sincronizados = 0;
            $actualizados = 0;
            
            foreach ($clientesExternos as $clienteExterno) {
                // Buscar si ya existe en local
                $clienteLocal = Cliente::where('codigo_cliente', $clienteExterno['CODIGO_CLIENTE'])->first();
                
                $datosCliente = [
                    'codigo_cliente' => $clienteExterno['CODIGO_CLIENTE'],
                    'nombre_cliente' => $clienteExterno['NOMBRE_CLIENTE'],
                    'direccion' => $clienteExterno['DIRECCION'] ?? '',
                    'telefono' => $clienteExterno['TELEFONO'] ?? '',
                    'email' => $clienteExterno['EMAIL'] ?? '',
                    'codigo_vendedor' => $clienteExterno['CODIGO_VENDEDOR'],
                    'region' => $clienteExterno['REGION'] ?? '',
                    'comuna' => $clienteExterno['COMUNA'] ?? '',
                    'bloqueado' => !empty($clienteExterno['BLOQUEADO']) && $clienteExterno['BLOQUEADO'] != '0',
                    'condicion_pago' => $clienteExterno['CONDICION_PAGO'] ?? '',
                    'dias_credito' => intval($clienteExterno['DIAS_CREDITO'] ?? 0),
                    'comentario_administracion' => $clienteExterno['COMENTARIO_ADMIN'] ?? '',
                    'activo' => true,
                    'ultima_sincronizacion' => now()
                ];
                
                if ($clienteLocal) {
                    // Actualizar cliente existente
                    $clienteLocal->update($datosCliente);
                    $actualizados++;
                } else {
                    // Crear nuevo cliente
                    Cliente::create($datosCliente);
                    $sincronizados++;
                }
            }
            
            // Marcar como inactivos los clientes que ya no están en SQL Server
            $clientesCodigos = collect($clientesExternos)->pluck('CODIGO_CLIENTE')->toArray();
            Cliente::where('codigo_vendedor', $vendedor)
                ->whereNotIn('codigo_cliente', $clientesCodigos)
                ->update(['activo' => false]);
            
            $this->info("✅ Sincronización completada:");
            $this->info("   - Nuevos clientes: {$sincronizados}");
            $this->info("   - Clientes actualizados: {$actualizados}");
            $this->info("   - Total procesados: " . count($clientesExternos));
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            return 1;
        }
    }
    
    public static function extraerClienteDeLinea($line, $vendedor = null)
    {
        try {
            // Asegurar que la línea esté en UTF-8
            $line = mb_convert_encoding($line, 'UTF-8', 'UTF-8');
            
            // Buscar líneas que contengan datos (código de 8 dígitos seguido de |)
            if (!preg_match('/^(\d{8})\s+\|(.+)$/', $line, $matches)) {
                return null;
            }
            
            $codigoCliente = $matches[1];
            $datosCompletos = trim($matches[2]);
            
            // Separar los campos por el delimitador |
            $campos = explode('|', $datosCompletos);
            
            if (count($campos) < 9) {
                return null;
            }
            

            
            // Extraer cada campo según el orden de la consulta SQL (sin el código cliente que ya se extrajo)
            $nombreCliente = trim($campos[0] ?? '');
            $direccion = trim($campos[1] ?? '');
            $telefono = trim($campos[2] ?? '');
            $codigoVendedor = trim($campos[3] ?? $vendedor ?? 'LCB');
            $email = trim($campos[4] ?? '');
            $bloqueado = trim($campos[5] ?? '0');
            $condicionPago = trim($campos[6] ?? '');
            $diasCredito = trim($campos[7] ?? '0');
            $comentarioAdmin = trim($campos[8] ?? '');
            
            // Limpiar campos NULL y espacios
            if ($nombreCliente === 'NULL') $nombreCliente = '';
            if ($direccion === 'NULL') $direccion = '';
            if ($telefono === 'NULL') $telefono = '';
            if ($email === 'NULL') $email = '';
            if ($condicionPago === 'NULL') $condicionPago = '';
            if ($diasCredito === 'NULL') $diasCredito = '0';
            if ($comentarioAdmin === 'NULL') $comentarioAdmin = '';
            
            // Limpiar espacios extra
            $nombreCliente = trim($nombreCliente);
            $direccion = trim($direccion);
            $telefono = trim($telefono);
            $email = trim($email);
            $condicionPago = trim($condicionPago);
            $diasCredito = trim($diasCredito);
            $comentarioAdmin = trim($comentarioAdmin);
            
            return [
                'CODIGO_CLIENTE' => $codigoCliente,
                'NOMBRE_CLIENTE' => $nombreCliente,
                'DIRECCION' => $direccion,
                'TELEFONO' => $telefono,
                'CODIGO_VENDEDOR' => $codigoVendedor,
                'NOMBRE_VENDEDOR' => '',
                'REGION' => '',
                'COMUNA' => '',
                'EMAIL' => $email,
                'BLOQUEADO' => $bloqueado,
                'CONDICION_PAGO' => $condicionPago,
                'DIAS_CREDITO' => $diasCredito,
                'COMENTARIO_ADMIN' => $comentarioAdmin
            ];
            
        } catch (\Exception $e) {
            return null;
        }
    }

    public static function sincronizarVendedorDirecto($codigoVendedor)
    {
        return self::ejecutarSincronizacion($codigoVendedor);
    }
    
    private static function ejecutarSincronizacion($codigoVendedor)
    {
        \Log::info("Iniciando sincronización para vendedor: {$codigoVendedor}");
        try {
            // Definir variables de conexión
            $host = env('SQLSRV_EXTERNAL_HOST');
            $port = env('SQLSRV_EXTERNAL_PORT', '1433');
            $database = env('SQLSRV_EXTERNAL_DATABASE');
            $username = env('SQLSRV_EXTERNAL_USERNAME');
            $password = env('SQLSRV_EXTERNAL_PASSWORD');
            
            // Query simple con CAST para evitar problemas de formato (la que funcionaba con GOP)
            $query = "
                SELECT 
                    CAST(MAEEN.KOEN AS VARCHAR(20)) AS CODIGO_CLIENTE,
                    CAST(MAEEN.NOKOEN AS VARCHAR(100)) AS NOMBRE_CLIENTE,
                    CAST(MAEEN.DIEN AS VARCHAR(100)) AS DIRECCION,
                    CAST(MAEEN.FOEN AS VARCHAR(20)) AS TELEFONO,
                    CAST(MAEEN.KOFUEN AS VARCHAR(10)) AS CODIGO_VENDEDOR,
                    CAST(TABFU.NOKOFU AS VARCHAR(100)) AS NOMBRE_VENDEDOR,
                    CAST(TABCI.NOKOCI AS VARCHAR(50)) AS REGION,
                    CAST(TABCM.NOKOCM AS VARCHAR(50)) AS COMUNA,
                    0 AS BLOQUEADO
                FROM dbo.MAEEN 
                LEFT JOIN dbo.TABFU ON MAEEN.KOFUEN = TABFU.KOFU
                LEFT JOIN dbo.TABCI ON MAEEN.PAEN = TABCI.KOPA AND MAEEN.CIEN = TABCI.KOCI
                LEFT JOIN dbo.TABCM ON MAEEN.PAEN = TABCM.KOPA AND MAEEN.CIEN = TABCM.KOCI AND MAEEN.CMEN = TABCM.KOCM
                WHERE MAEEN.KOFUEN = '{$codigoVendedor}'
                ORDER BY MAEEN.NOKOEN
            ";
            
            $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
            file_put_contents($tempFile, $query . "\ngo\nquit");
            $command = "tsql -H {$host} -p {$port} -U {$username} -P {$password} -D {$database} < {$tempFile} 2>&1";
            $output = shell_exec($command);
            unlink($tempFile);
            
            if (!$output || str_contains($output, 'error')) {
                throw new \Exception('Error ejecutando consulta tsql: ' . $output);
            }
            
            // Procesar la salida línea por línea (como funcionaba con GOP)
            $lines = explode("\n", $output);
            $clientesExternos = [];
            
            // Asegurar que la salida esté en UTF-8
            $output = mb_convert_encoding($output, 'UTF-8', 'UTF-8');
            
            foreach ($lines as $line) {
                $line = trim($line);
                
                // Saltar líneas vacías o de configuración
                if (empty($line) || 
                    strpos($line, 'locale') !== false || 
                    strpos($line, 'Setting') !== false || 
                    strpos($line, 'Msg ') !== false || 
                    strpos($line, 'Warning:') !== false ||
                    preg_match('/^\d+>$/', $line) ||
                    preg_match('/^\d+>\s+\d+>\s+\d+>/', $line) ||
                    strpos($line, 'rows affected') !== false ||
                    strpos($line, 'KOEN') !== false ||
                    strpos($line, 'CODIGO_CLIENTE') !== false) {
                    continue;
                }
                
                // Buscar líneas que empiecen con números (códigos de cliente)
                if (preg_match('/^(\d{7,})/', $line)) {
                    $cliente = self::extraerClienteDeLinea($line, $codigoVendedor);
                    if ($cliente) {
                        $clientesExternos[] = $cliente;
                    }
                }
            }
            
            if (count($clientesExternos) == 0) {
                return [
                    'success' => false,
                    'message' => "No se encontraron clientes para el vendedor {$codigoVendedor}"
                ];
            }
            
            // Sincronizar clientes
            $sincronizados = 0;
            $actualizados = 0;
            
            foreach ($clientesExternos as $clienteExterno) {
                // Buscar si ya existe en local
                $clienteLocal = Cliente::where('codigo_cliente', $clienteExterno['CODIGO_CLIENTE'])->first();
                
                $datosCliente = [
                    'codigo_cliente' => $clienteExterno['CODIGO_CLIENTE'],
                    'nombre_cliente' => $clienteExterno['NOMBRE_CLIENTE'],
                    'direccion' => $clienteExterno['DIRECCION'] ?? '',
                    'telefono' => $clienteExterno['TELEFONO'] ?? '',
                    'email' => $clienteExterno['EMAIL'] ?? '',
                    'codigo_vendedor' => $clienteExterno['CODIGO_VENDEDOR'],
                    'region' => $clienteExterno['REGION'] ?? '',
                    'comuna' => $clienteExterno['COMUNA'] ?? '',
                    'bloqueado' => !empty($clienteExterno['BLOQUEADO']) && $clienteExterno['BLOQUEADO'] != '0',
                    'condicion_pago' => $clienteExterno['CONDICION_PAGO'] ?? '',
                    'dias_credito' => intval($clienteExterno['DIAS_CREDITO'] ?? 0),
                    'comentario_administracion' => $clienteExterno['COMENTARIO_ADMIN'] ?? '',
                    'activo' => true,
                    'ultima_sincronizacion' => now()
                ];
                
                if ($clienteLocal) {
                    // Actualizar cliente existente
                    $clienteLocal->update($datosCliente);
                    $actualizados++;
                } else {
                    // Crear nuevo cliente
                    Cliente::create($datosCliente);
                    $sincronizados++;
                }
            }
            
            // Marcar como inactivos los clientes que ya no están en SQL Server
            $clientesCodigos = collect($clientesExternos)->pluck('CODIGO_CLIENTE')->toArray();
            Cliente::where('codigo_vendedor', $codigoVendedor)
                ->whereNotIn('codigo_cliente', $clientesCodigos)
                ->update(['activo' => false]);
            
            \Log::info("Sincronización completada para {$codigoVendedor}: {$sincronizados} nuevos, {$actualizados} actualizados, " . count($clientesExternos) . " total");
            return [
                'success' => true,
                'nuevos' => $sincronizados,
                'actualizados' => $actualizados,
                'total' => count($clientesExternos)
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}
