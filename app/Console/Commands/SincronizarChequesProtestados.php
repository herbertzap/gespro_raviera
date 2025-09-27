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
        
        try {
            $host = env('SQLSRV_EXTERNAL_HOST');
            $port = env('SQLSRV_EXTERNAL_PORT', '1433');
            $database = env('SQLSRV_EXTERNAL_DATABASE');
            $username = env('SQLSRV_EXTERNAL_USERNAME');
            $password = env('SQLSRV_EXTERNAL_PASSWORD');
            
            // Query basada en la proporcionada por el usuario
            $query = "
                SELECT 
                    CAST(MAEDPCE.TIDP AS VARCHAR(10)) + '|' +
                    CAST(MAEDPCE.NUDP AS VARCHAR(20)) + '|' +
                    CAST(MAEDPCE.ENDP AS VARCHAR(20)) + '|' +
                    CAST(CLIENTES.NOKOEN AS VARCHAR(100)) + '|' +
                    CAST(MAEDPCE.FEVEDP AS VARCHAR(10)) + '|' +
                    CAST(MAEDPCE.FEEMDP AS VARCHAR(10)) + '|' +
                    CAST(MAEDPCE.MODP AS VARCHAR(10)) + '|' +
                    CAST(CASE WHEN MAEDPCE.TIDP = 'CHC' THEN MAEDPCE.VADP * -1 ELSE MAEDPCE.VADP * 1 END AS VARCHAR(20)) + '|' +
                    CAST(MAEDPCE.SUREDP AS VARCHAR(10)) + '|' +
                    CAST(ISNULL(TABSU.NOKOSU, '') AS VARCHAR(100)) + '|' +
                    CAST(MAEDPCE.EMDP AS VARCHAR(10)) + '|' +
                    CAST(MAEDPCE.SUEMDP AS VARCHAR(10)) + '|' +
                    CAST(MAEDPCE.CUDP AS VARCHAR(20)) + '|' +
                    CAST(MAEDPCE.NUCUDP AS VARCHAR(20)) + '|' +
                    CAST(CASE WHEN TABCTAEM.NOCTACTEEM IS NULL THEN 'CARTERA' ELSE TABCTAEM.NOCTACTEEM END AS VARCHAR(50)) + '|' +
                    CAST(CLIENTES.KOFUEN AS VARCHAR(10)) + '|' +
                    CAST(ISNULL(TABFU.NOKOFU, '') AS VARCHAR(100)) AS DATOS_CHEQUE
                FROM dbo.TABFU 
                INNER JOIN dbo.CLIENTES ON TABFU.KOFU = CLIENTES.KOFUEN 
                RIGHT OUTER JOIN dbo.MAEDPCE ON CLIENTES.KOEN = MAEDPCE.ENDP 
                LEFT OUTER JOIN dbo.TABSU ON MAEDPCE.SUREDP = TABSU.KOSU 
                LEFT OUTER JOIN dbo.TABCTAEM ON MAEDPCE.CUDP = TABCTAEM.CTACTEEM
                WHERE MAEDPCE.TIDP = 'CHV' 
                AND MAEDPCE.ESPGDP = 'R' 
                AND MAEDPCE.EMPRESA = '01'
                " . ($vendedor ? "AND CLIENTES.KOFUEN = '{$vendedor}'" : "") . "
                ORDER BY CLIENTES.NOKOEN, MAEDPCE.FEVEDP
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
            
            $this->info("📊 Total de cheques protestados encontrados: " . count($chequesExternos));
            
            if (count($chequesExternos) == 0) {
                $this->warn("⚠️ No se encontraron cheques protestados" . ($vendedor ? " para el vendedor {$vendedor}" : ""));
                return 0;
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
            
            $this->info("✅ Sincronización completada:");
            $this->info("   - Cheques protestados insertados: {$insertados}");
            $this->info("   - Total procesados: " . count($chequesExternos));
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            return 1;
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
            
            if (count($campos) < 16) {
                return null;
            }
            
            // Extraer cada campo según el orden de la consulta SQL (sin el tipo de documento que ya se extrajo)
            $numeroDocumento = trim($campos[0] ?? '');
            $codigoCliente = trim($campos[1] ?? '');
            $nombreCliente = trim($campos[2] ?? '');
            $fechaVencimiento = trim($campos[3] ?? '');
            $fechaEmision = trim($campos[4] ?? '');
            $moneda = trim($campos[5] ?? '');
            $valor = trim($campos[6] ?? '0');
            $sucursal = trim($campos[7] ?? '');
            $nombreSucursal = trim($campos[8] ?? '');
            $empresa = trim($campos[9] ?? '');
            $sucursalEmpresa = trim($campos[10] ?? '');
            $cuenta = trim($campos[11] ?? '');
            $numeroCuenta = trim($campos[12] ?? '');
            $cuentaContable = trim($campos[13] ?? '');
            $codigoVendedor = trim($campos[14] ?? '');
            $nombreVendedor = trim($campos[15] ?? '');
            
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
            
            // Convertir fechas (formato de SQL Server: "Aug 30 202", "Jul 17 202")
            $fechaVencimientoFormateada = null;
            $fechaEmisionFormateada = null;
            
            if (!empty($fechaVencimiento) && $fechaVencimiento !== 'NULL') {
                try {
                    // Formato: "Aug 30 202" -> "2020-08-30"
                    $fechaVencimientoFormateada = \Carbon\Carbon::createFromFormat('M d Y', $fechaVencimiento)->format('Y-m-d');
                } catch (\Exception $e) {
                    try {
                        // Intentar con formato alternativo
                        $fechaVencimientoFormateada = \Carbon\Carbon::parse($fechaVencimiento)->format('Y-m-d');
                    } catch (\Exception $e2) {
                        $fechaVencimientoFormateada = null;
                    }
                }
            }
            
            if (!empty($fechaEmision) && $fechaEmision !== 'NULL') {
                try {
                    // Formato: "Jul 17 202" -> "2020-07-17"
                    $fechaEmisionFormateada = \Carbon\Carbon::createFromFormat('M d Y', $fechaEmision)->format('Y-m-d');
                } catch (\Exception $e) {
                    try {
                        // Intentar con formato alternativo
                        $fechaEmisionFormateada = \Carbon\Carbon::parse($fechaEmision)->format('Y-m-d');
                    } catch (\Exception $e2) {
                        $fechaEmisionFormateada = null;
                    }
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
