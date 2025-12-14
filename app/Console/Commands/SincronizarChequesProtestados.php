<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SincronizarChequesProtestados extends Command
{
    protected $signature = 'cheques:sincronizar {--vendedor= : Código del vendedor específico}';
    protected $description = 'Sincronizar cheques protestados desde SQL Server';

    public function handle()
    {
        $vendedor = $this->option('vendedor');
        
        if ($vendedor) {
            $this->info("🔄 Sincronizando cheques protestados para vendedor: {$vendedor}");
        } else {
            $this->info("🔄 Sincronizando todos los cheques protestados");
        }
        
        $resultado = self::sincronizarDirecto($vendedor);
        
        if ($resultado['success']) {
            $this->info("✅ Sincronización completada: {$resultado['insertados']} cheques insertados");
            return 0;
        } else {
            $this->error("❌ Error: " . $resultado['message']);
            return 1;
        }
    }
    
    /**
     * Método estático para sincronizar directamente desde otros componentes
     */
    public static function sincronizarDirecto($vendedor = null)
    {
        try {
            $host = env('SQLSRV_EXTERNAL_HOST');
            $port = env('SQLSRV_EXTERNAL_PORT', '1433');
            $database = env('SQLSRV_EXTERNAL_DATABASE');
            $username = env('SQLSRV_EXTERNAL_USERNAME');
            $password = env('SQLSRV_EXTERNAL_PASSWORD');
            
            // Escapar código de vendedor para prevenir SQL injection
            $vendedorEscapado = $vendedor ? str_replace("'", "''", trim($vendedor)) : null;
            
            // Query basada exactamente en la proporcionada por el usuario
            // Concatena campos con '|' para parsear después
            $query = "
                SELECT 
                    CAST(dbo.MAEDPCE.TIDP AS VARCHAR(10)) + '|' +
                    CAST(dbo.MAEDPCE.NUDP AS VARCHAR(20)) + '|' +
                    CAST(dbo.MAEDPCE.ENDP AS VARCHAR(20)) + '|' +
                    CAST(dbo.CLIENTES.NOKOEN AS VARCHAR(100)) + '|' +
                    CAST(dbo.MAEDPCE.FEVEDP AS VARCHAR(20)) + '|' +
                    CAST(dbo.MAEDPCE.FEVEDP AS VARCHAR(20)) + '|' +
                    CAST(dbo.MAEDPCE.MODP AS VARCHAR(10)) + '|' +
                    CAST(CASE WHEN dbo.MAEDPCE.TIDP = 'CHC' THEN dbo.MAEDPCE.VADP * -1 ELSE dbo.MAEDPCE.VADP * 1 END AS VARCHAR(20)) + '|' +
                    CAST(dbo.MAEDPCE.SUREDP AS VARCHAR(10)) + '|' +
                    CAST(ISNULL(dbo.TABSU.NOKOSU, '') AS VARCHAR(100)) + '|' +
                    CAST(dbo.MAEDPCE.EMDP AS VARCHAR(10)) + '|' +
                    CAST(dbo.MAEDPCE.SUEMDP AS VARCHAR(10)) + '|' +
                    CAST(dbo.MAEDPCE.CUDP AS VARCHAR(20)) + '|' +
                    CAST(dbo.MAEDPCE.FEEMDP AS VARCHAR(20)) + '|' +
                    CAST(dbo.MAEDPCE.NUCUDP AS VARCHAR(20)) + '|' +
                    CAST(CASE WHEN dbo.TABCTAEM.NOCTACTEEM IS NULL THEN 'CARTERA' ELSE dbo.TABCTAEM.NOCTACTEEM END AS VARCHAR(50)) + '|' +
                    CAST(dbo.CLIENTES.KOFUEN AS VARCHAR(10)) + '|' +
                    CAST(ISNULL(dbo.TABFU.NOKOFU, '') AS VARCHAR(100)) AS DATOS_CHEQUE
                FROM dbo.TABFU 
                INNER JOIN dbo.CLIENTES ON dbo.TABFU.KOFU = dbo.CLIENTES.KOFUEN 
                RIGHT OUTER JOIN dbo.MAEDPCE ON dbo.CLIENTES.KOEN = dbo.MAEDPCE.ENDP 
                LEFT OUTER JOIN dbo.TABSU ON dbo.MAEDPCE.SUREDP = dbo.TABSU.KOSU 
                LEFT OUTER JOIN dbo.TABCTAEM ON dbo.MAEDPCE.CUDP = dbo.TABCTAEM.CTACTEEM
                WHERE (dbo.MAEDPCE.TIDP = 'CHV') 
                AND (dbo.MAEDPCE.ESPGDP = 'R') 
                AND (dbo.MAEDPCE.EMPRESA = '01')
                " . ($vendedorEscapado ? "AND dbo.CLIENTES.KOFUEN = '{$vendedorEscapado}'" : "") . "
                ORDER BY dbo.CLIENTES.NOKOEN, dbo.MAEDPCE.FEVEDP
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
            
            Log::info("📋 Procesando datos de cheques protestados...");
            
            // Procesar la salida línea por línea
            $lines = explode("\n", $output);
            $chequesExternos = [];
            
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
                    strpos($line, 'TIDP') !== false ||
                    strpos($line, 'DATOS_CHEQUE') !== false) {
                    continue;
                }
                
                // Buscar líneas que contengan datos (tipo de documento seguido de |)
                if (strpos($line, 'CHV|') === 0 || strpos($line, 'CHC|') === 0) {
                    $cheque = self::extraerChequeDeLinea($line);
                    if ($cheque) {
                        $chequesExternos[] = $cheque;
                    }
                }
            }
            
            // Log en lugar de info cuando se llama directamente
            Log::info("📊 Total de cheques protestados encontrados: " . count($chequesExternos));
            
            if (count($chequesExternos) == 0) {
                Log::warning("⚠️ No se encontraron cheques protestados" . ($vendedor ? " para el vendedor {$vendedor}" : ""));
                return [
                    'success' => true,
                    'insertados' => 0,
                    'total' => 0,
                    'message' => 'No se encontraron cheques protestados'
                ];
            }
            
            // Limpiar cheques existentes para el vendedor (si se especifica) o todos
            if ($vendedor) {
                DB::table('cheques_protestados')->where('codigo_vendedor', $vendedor)->delete();
            } else {
                DB::table('cheques_protestados')->truncate();
            }
            
            // Insertar cheques protestados
            $insertados = 0;
            
            foreach ($chequesExternos as $chequeExterno) {
                try {
                    DB::table('cheques_protestados')->insert([
                        'tipo_documento' => $chequeExterno['TIPO_DOCUMENTO'],
                        'numero_documento' => $chequeExterno['NUMERO_DOCUMENTO'],
                        'codigo_cliente' => $chequeExterno['CODIGO_CLIENTE'],
                        'nombre_cliente' => $chequeExterno['NOMBRE_CLIENTE'],
                        'fecha_vencimiento' => $chequeExterno['FECHA_VENCIMIENTO'],
                        'fecha_emision' => $chequeExterno['FECHA_EMISION'],
                        'moneda' => $chequeExterno['MONEDA'],
                        'valor' => $chequeExterno['VALOR'],
                        'sucursal' => $chequeExterno['SUCURSAL'],
                        'nombre_sucursal' => $chequeExterno['NOMBRE_SUCURSAL'],
                        'empresa' => $chequeExterno['EMPRESA'],
                        'sucursal_empresa' => $chequeExterno['SUCURSAL_EMPRESA'],
                        'cuenta' => $chequeExterno['CUENTA'],
                        'numero_cuenta' => $chequeExterno['NUMERO_CUENTA'],
                        'cuenta_contable' => $chequeExterno['CUENTA_CONTABLE'],
                        'codigo_vendedor' => $chequeExterno['CODIGO_VENDEDOR'],
                        'nombre_vendedor' => $chequeExterno['NOMBRE_VENDEDOR'],
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                    $insertados++;
                } catch (\Exception $e) {
                    Log::error("Error insertando cheque protestado: " . $e->getMessage());
                }
            }
            
            Log::info("✅ Sincronización de cheques protestados completada: {$insertados} insertados de " . count($chequesExternos) . " procesados");
            
            return [
                'success' => true,
                'insertados' => $insertados,
                'total' => count($chequesExternos),
                'message' => "Sincronización completada: {$insertados} cheques insertados"
            ];
            
        } catch (\Exception $e) {
            Log::error("❌ Error sincronizando cheques protestados: " . $e->getMessage());
            return [
                'success' => false,
                'insertados' => 0,
                'total' => 0,
                'message' => $e->getMessage()
            ];
        }
    }
    
    public static function extraerChequeDeLinea($line)
    {
        try {
            // Asegurar que la línea esté en UTF-8
            $line = mb_convert_encoding($line, 'UTF-8', 'UTF-8');
            
            // Buscar líneas que contengan datos (tipo de documento seguido de |)
            if (strpos($line, 'CHV|') !== 0 && strpos($line, 'CHC|') !== 0) {
                return null;
            }
            
            $tipoDocumento = substr($line, 0, 3); // CHV o CHC
            $datosCompletos = substr($line, 4); // Todo después de "CHV|" o "CHC|"
            
            // Separar los campos por el delimitador |
            $campos = explode('|', $datosCompletos);
            
            // Ahora tenemos 18 campos (incluyendo el VCMTO duplicado)
            if (count($campos) < 17) {
                return null;
            }
            
            // Extraer cada campo según el orden de la consulta SQL (sin el tipo de documento que ya se extrajo)
            // Orden: NUDP, ENDP, NOKOEN, FEVEDP, FEVEDP(VCMTO), MODP, VALOR, SUREDP, NOKOSU, EMDP, SUEMDP, CUDP, FEEMDP, NUCUDP, CTA, KOFUEN, NOKOFU
            $numeroDocumento = trim($campos[0] ?? '');           // NUDP
            $codigoCliente = trim($campos[1] ?? '');             // ENDP
            $nombreCliente = trim($campos[2] ?? '');             // NOKOEN
            $fechaVencimiento = trim($campos[3] ?? '');          // FEVEDP
            $fechaVencimientoVCMTO = trim($campos[4] ?? '');     // FEVEDP AS VCMTO (duplicado, ignoramos)
            $moneda = trim($campos[5] ?? '');                    // MODP
            $valor = trim($campos[6] ?? '0');                    // VALOR
            $sucursal = trim($campos[7] ?? '');                  // SUREDP
            $nombreSucursal = trim($campos[8] ?? '');            // NOKOSU
            $empresa = trim($campos[9] ?? '');                   // EMDP
            $sucursalEmpresa = trim($campos[10] ?? '');          // SUEMDP
            $cuenta = trim($campos[11] ?? '');                   // CUDP
            $fechaEmision = trim($campos[12] ?? '');             // FEEMDP
            $numeroCuenta = trim($campos[13] ?? '');             // NUCUDP
            $cuentaContable = trim($campos[14] ?? '');           // CTA
            $codigoVendedor = trim($campos[15] ?? '');           // KOFUEN
            $nombreVendedor = trim($campos[16] ?? '');           // NOKOFU
            
            // Limpiar campos NULL y espacios
            if ($numeroDocumento === 'NULL') $numeroDocumento = '';
            if ($codigoCliente === 'NULL') $codigoCliente = '';
            if ($nombreCliente === 'NULL') $nombreCliente = '';
            if ($fechaVencimiento === 'NULL') $fechaVencimiento = '';
            if ($fechaEmision === 'NULL') $fechaEmision = '';
            if ($moneda === 'NULL') $moneda = '';
            if ($valor === 'NULL') $valor = '0';
            if ($sucursal === 'NULL') $sucursal = '';
            if ($nombreSucursal === 'NULL') $nombreSucursal = '';
            if ($empresa === 'NULL') $empresa = '';
            if ($sucursalEmpresa === 'NULL') $sucursalEmpresa = '';
            if ($cuenta === 'NULL') $cuenta = '';
            if ($numeroCuenta === 'NULL') $numeroCuenta = '';
            if ($cuentaContable === 'NULL') $cuentaContable = '';
            if ($codigoVendedor === 'NULL') $codigoVendedor = '';
            if ($nombreVendedor === 'NULL') $nombreVendedor = '';
            
            // Limpiar espacios extra
            $tipoDocumento = trim($tipoDocumento);
            $numeroDocumento = trim($numeroDocumento);
            $codigoCliente = trim($codigoCliente);
            $nombreCliente = trim($nombreCliente);
            $fechaVencimiento = trim($fechaVencimiento);
            $fechaEmision = trim($fechaEmision);
            $moneda = trim($moneda);
            $valor = trim($valor);
            $sucursal = trim($sucursal);
            $nombreSucursal = trim($nombreSucursal);
            $empresa = trim($empresa);
            $sucursalEmpresa = trim($sucursalEmpresa);
            $cuenta = trim($cuenta);
            $numeroCuenta = trim($numeroCuenta);
            $cuentaContable = trim($cuentaContable);
            $codigoVendedor = trim($codigoVendedor);
            $nombreVendedor = trim($nombreVendedor);
            
            // Validar que tenemos datos mínimos
            if (empty($codigoCliente) || empty($tipoDocumento) || empty($numeroDocumento)) {
                return null;
            }
            
            // Convertir fechas (formato de SQL Server: "Dec 13 2025 12:00AM" o "Dec 13 2025")
            $fechaVencimientoFormateada = null;
            $fechaEmisionFormateada = null;
            
            if (!empty($fechaVencimiento) && $fechaVencimiento !== 'NULL') {
                try {
                    // Limpiar la fecha: puede venir como "Dec 13 2025 12:00AM" o "Dec 13 2025"
                    $fechaVencimiento = trim($fechaVencimiento);
                    
                    // Intentar varios formatos comunes de SQL Server
                    // Formato 1: "Dec 13 2025 12:00AM" -> "2025-12-13"
                    if (preg_match('/^([A-Za-z]{3})\s+(\d{1,2})\s+(\d{4})(\s+\d{1,2}:\d{2}:\d{2}(AM|PM))?/i', $fechaVencimiento, $matches)) {
                        $mes = $matches[1];
                        $dia = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
                        $ano = $matches[3];
                        // Construir fecha limpia para strtotime: "Dec 13 2025"
                        $fechaLimpia = "{$mes} {$dia} {$ano}";
                        $timestamp = strtotime($fechaLimpia);
                        if ($timestamp !== false && $timestamp > 0) {
                            $fechaVencimientoFormateada = date('Y-m-d', $timestamp);
                        }
                    }
                    
                    // Si no funcionó, intentar parseo genérico
                    if (!$fechaVencimientoFormateada) {
                        $timestamp = strtotime($fechaVencimiento);
                        if ($timestamp !== false && $timestamp > 0) {
                            $fechaVencimientoFormateada = date('Y-m-d', $timestamp);
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning("Error parseando fecha de vencimiento: {$fechaVencimiento} - " . $e->getMessage());
                    $fechaVencimientoFormateada = null;
                }
            }
            
            if (!empty($fechaEmision) && $fechaEmision !== 'NULL') {
                try {
                    // Limpiar la fecha: puede venir como "Dec 13 2025 12:00AM" o "Dec 13 2025"
                    $fechaEmision = trim($fechaEmision);
                    
                    // Intentar varios formatos comunes de SQL Server
                    // Formato 1: "Dec 13 2025 12:00AM" -> "2025-12-13"
                    if (preg_match('/^([A-Za-z]{3})\s+(\d{1,2})\s+(\d{4})(\s+\d{1,2}:\d{2}:\d{2}(AM|PM))?/i', $fechaEmision, $matches)) {
                        $mes = $matches[1];
                        $dia = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
                        $ano = $matches[3];
                        // Construir fecha limpia para strtotime: "Dec 13 2025"
                        $fechaLimpia = "{$mes} {$dia} {$ano}";
                        $timestamp = strtotime($fechaLimpia);
                        if ($timestamp !== false && $timestamp > 0) {
                            $fechaEmisionFormateada = date('Y-m-d', $timestamp);
                        }
                    }
                    
                    // Si no funcionó, intentar parseo genérico
                    if (!$fechaEmisionFormateada) {
                        $timestamp = strtotime($fechaEmision);
                        if ($timestamp !== false && $timestamp > 0) {
                            $fechaEmisionFormateada = date('Y-m-d', $timestamp);
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning("Error parseando fecha de emisión: {$fechaEmision} - " . $e->getMessage());
                    $fechaEmisionFormateada = null;
                }
            }
            
            return [
                'TIPO_DOCUMENTO' => $tipoDocumento,
                'NUMERO_DOCUMENTO' => $numeroDocumento,
                'CODIGO_CLIENTE' => $codigoCliente,
                'NOMBRE_CLIENTE' => $nombreCliente,
                'FECHA_VENCIMIENTO' => $fechaVencimientoFormateada,
                'FECHA_EMISION' => $fechaEmisionFormateada,
                'MONEDA' => $moneda,
                'VALOR' => (float)$valor,
                'SUCURSAL' => $sucursal,
                'NOMBRE_SUCURSAL' => $nombreSucursal,
                'EMPRESA' => $empresa,
                'SUCURSAL_EMPRESA' => $sucursalEmpresa,
                'CUENTA' => $cuenta,
                'NUMERO_CUENTA' => $numeroCuenta,
                'CUENTA_CONTABLE' => $cuentaContable,
                'CODIGO_VENDEDOR' => $codigoVendedor,
                'NOMBRE_VENDEDOR' => $nombreVendedor
            ];
            
        } catch (\Exception $e) {
            Log::error("Error extrayendo cheque de línea: " . $e->getMessage());
            return null;
        }
    }
}
