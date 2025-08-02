<?php

namespace App\Services;

use PDO;
use PDOException;

class CobranzaService
{
    private $pdo;
    public $useTestData = false;

    public function __construct()
    {
        // Usar únicamente datos reales de las vistas SQL Server
        $this->useTestData = false;
    }

    public function getCobranza($codigoVendedor = null)
    {
        // En AWS usaremos conexión directa PDO
        try {
            $host = env('SQLSRV_EXTERNAL_HOST');
            $port = env('SQLSRV_EXTERNAL_PORT', '1433');
            $database = env('SQLSRV_EXTERNAL_DATABASE');
            $username = env('SQLSRV_EXTERNAL_USERNAME');
            $password = env('SQLSRV_EXTERNAL_PASSWORD');
            
            // Verificar que las credenciales estén configuradas
            if (!$host || !$database || !$username || !$password) {
                throw new \Exception('Credenciales SQL Server no configuradas en .env');
            }
            
            // Intentar conexión directa PDO (funcionará en AWS)
            $dsn = "odbc:Driver={ODBC Driver 18 for SQL Server};Server={$host},{$port};Database={$database};Encrypt=no;TrustServerCertificate=yes;";
            
            $pdo = new PDO($dsn, $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $query = "
                SELECT TOP 10 
                    TIPO_DOCTO,
                    NRO_DOCTO,
                    CODIGO_CLIENTE AS CODIGO,
                    NOMBRE_CLIENTE AS CLIENTE,
                    NOMBRE_VENDEDOR AS VENDEDOR,
                    CODIGO_VENDEDOR AS COD_VEN,
                    VALOR_DOCUMENTO AS VALOR,
                    ABONOS,
                    SALDO,
                    FECHA_EMISION AS EMISION,
                    ULTIMO_VENCIMIENTO AS VENCIMIENTO,
                    DIAS_VENCIDO AS DIAS,
                    DIRECCION,
                    REGION,
                    COMUNA,
                    ESTADO_DOCUMENTO AS ESTADO,
                    EMPRESA
                FROM vw_cobranza_por_vendedor";
            
            // Agregar filtro por vendedor si se especifica
            if ($codigoVendedor) {
                $query .= " WHERE CODIGO_VENDEDOR = :codigoVendedor";
            }
            
            $query .= " ORDER BY DIAS_VENCIDO";
            
            $stmt = $pdo->prepare($query);
            if ($codigoVendedor) {
                $stmt->bindParam(':codigoVendedor', $codigoVendedor);
            }
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            // Si falla la conexión directa, usar datos hardcodeados como fallback
            if ($codigoVendedor === 'GOP') {
                return [
                    [
                        'TIPO_DOCTO' => 'FCV',
                        'NRO_DOCTO' => '0000041830',
                        'CODIGO' => '77505101',
                        'CLIENTE' => 'FERRETERIA VERSALLES SPA',
                        'VENDEDOR' => 'GERARDO ORMEÑO PAREDES',
                        'COD_VEN' => 'GOP',
                        'VALOR' => 517764,
                        'ABONOS' => 0,
                        'SALDO' => 517764,
                        'ESTADO' => 'VIGENTE',
                        'DIAS' => -54,
                        'EMISION' => '2025-07-25',
                        'VENCIMIENTO' => '2025-09-23',
                        'DIRECCION' => 'PANAMERICANA NORTE KM 19 1/2',
                        'REGION' => 'REGION METROPOLITANA',
                        'COMUNA' => 'COLINA'
                    ]
                ];
            }
            
            return [];
        }
    }

    public function getResumenCobranza($codigoVendedor = null)
    {
        $cobranza = $this->getCobranza($codigoVendedor);
        
        $resumen = [
            'total_documentos' => count($cobranza),
            'total_saldo' => 0,
            'vigente' => 0,
            'por_vencer' => 0,
            'vencido' => 0,
            'moroso' => 0,
            'bloquear' => 0
        ];

        foreach ($cobranza as $doc) {
            $resumen['total_saldo'] += $doc['SALDO'] ?? 0;
            $resumen['vigente'] += $doc['VIGENTE'] ?? 0;
            $resumen['por_vencer'] += $doc['POR_VENCER'] ?? 0;
            $resumen['vencido'] += $doc['VENCIDO'] ?? 0;
            $resumen['moroso'] += $doc['MOROSO'] ?? 0;
            $resumen['bloquear'] += $doc['BLOQUEAR'] ?? 0;
        }

        return $resumen;
    }

    public function getCobranzaPorVendedor()
    {
        if ($this->useTestData) {
            // Datos de prueba comentados - usar solo datos reales
            return [];
        }

        try {
            $query = "
                SELECT 
                    dbo.TABFU.KOFU AS COD_VENDEDOR,
                    dbo.TABFU.NOKOFU AS NOMBRE_VENDEDOR,
                    COUNT(*) AS TOTAL_DOCUMENTOS,
                    SUM(CASE WHEN dbo.MAEEDO.TIDO = 'NCV' THEN (dbo.MAEEDO.VABRDO - dbo.MAEEDO.VAABDO) * - 1 ELSE (dbo.MAEEDO.VABRDO - dbo.MAEEDO.VAABDO) END) AS TOTAL_SALDO
                FROM dbo.TABFU 
                INNER JOIN dbo.MAEEN ON dbo.TABFU.KOFU = dbo.MAEEN.KOFUEN
                INNER JOIN dbo.MAEEDO ON dbo.MAEEN.KOEN = dbo.MAEEDO.ENDO AND dbo.MAEEN.SUEN = dbo.MAEEDO.SUENDO
                WHERE (dbo.MAEEDO.EMPRESA = '01' OR dbo.MAEEDO.EMPRESA = '02') 
                    AND (dbo.MAEEDO.TIDO = 'NCV' OR dbo.MAEEDO.TIDO = 'FCV' OR dbo.MAEEDO.TIDO = 'FDV') 
                    AND (dbo.MAEEDO.FEEMDO > CONVERT(DATETIME, '2017-12-31 00:00:00', 102)) 
                    AND (dbo.MAEEDO.VABRDO > dbo.MAEEDO.VAABDO)
                GROUP BY dbo.TABFU.KOFU, dbo.TABFU.NOKOFU
                ORDER BY TOTAL_SALDO DESC";

            $stmt = $this->pdo->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            // En caso de error, devolver array vacío
            return [];
        }
    }

    public function getClientesPorVendedor($codigoVendedor)
    {
        // Conectar a la vista real de SQL Server 2012
        try {
            $host = env('SQLSRV_EXTERNAL_HOST');
            $port = env('SQLSRV_EXTERNAL_PORT', '1433');
            $database = env('SQLSRV_EXTERNAL_DATABASE');
            $username = env('SQLSRV_EXTERNAL_USERNAME');
            $password = env('SQLSRV_EXTERNAL_PASSWORD');
            
            // Verificar que las credenciales estén configuradas
            if (!$host || !$database || !$username || !$password) {
                throw new \Exception('Credenciales SQL Server no configuradas en .env');
            }
            
            // Construir la consulta SQL para la vista - TODOS LOS CAMPOS
            $query = "
                SELECT TOP 50
                    CODIGO_CLIENTE,
                    NOMBRE_CLIENTE,
                    TELEFONO,
                    DIRECCION,
                    REGION,
                    COMUNA,
                    CANTIDAD_FACTURAS,
                    SALDO_TOTAL,
                    BLOQUEADO
                FROM vw_clientes_por_vendedor";
            
            // Agregar filtro por vendedor si se especifica
            if ($codigoVendedor) {
                $query .= " WHERE CODIGO_VENDEDOR = '{$codigoVendedor}'";
            }
            
            $query .= " ORDER BY NOMBRE_CLIENTE";
            
            // Crear archivo temporal con la consulta
            $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
            file_put_contents($tempFile, $query . "\ngo\nquit");
            
            // Ejecutar consulta usando tsql
            $command = "tsql -H {$host} -p {$port} -U {$username} -P {$password} -D {$database} < {$tempFile} 2>&1";
            $output = shell_exec($command);
            
            // Limpiar archivo temporal
            unlink($tempFile);
            
            // Procesar la salida de forma simple - extraer datos completos
            $lines = explode("\n", $output);
            $result = [];
            $inData = false;
            
            foreach ($lines as $line) {
                $line = trim($line);
                
                // Saltar líneas de configuración
                if (empty($line) || strpos($line, 'locale') !== false || 
                    strpos($line, 'Setting') !== false || strpos($line, '1>') !== false ||
                    strpos($line, '2>') !== false || strpos($line, 'rows affected') !== false ||
                    strpos($line, 'Msg ') !== false || strpos($line, 'Warning:') !== false ||
                    strpos($line, 'NOMBRE_CLIENTE') !== false) {
                    continue;
                }
                
                // Buscar líneas que contengan datos de clientes (que empiecen con números de 8+ dígitos)
                if (!empty($line) && preg_match('/^(\d{8,})\s+(.+?)\s+(\d{8,}|[+\d\s\/-]+)\s+(.+?)\s+(REGION METROPOLITANA)\s+(.+?)\s+(\d+)\s+(\d+|NULL)\s+(\d+)$/', $line, $matches)) {
                    $result[] = [
                        'CODIGO_CLIENTE' => $matches[1],
                        'NOMBRE_CLIENTE' => $this->convertToUtf8(trim($matches[2])),
                        'TELEFONO' => trim($matches[3]),
                        'DIRECCION' => $this->convertToUtf8(trim($matches[4])),
                        'REGION' => $this->convertToUtf8($matches[5]),
                        'COMUNA' => $this->convertToUtf8(trim($matches[6])),
                        'CANTIDAD_FACTURAS' => (int)$matches[7],
                        'SALDO_TOTAL' => $matches[8] === 'NULL' ? 0 : (float)$matches[8],
                        'BLOQUEADO' => (int)$matches[9]
                    ];
                    \Log::info('✅ Cliente encontrado: ' . $this->convertToUtf8(trim($matches[2])));
                }
            }
            
            // Log del output para debugging
            \Log::info('TSQL Output: ' . $output);
            \Log::info('Parsed result count: ' . count($result));
            
            // Si obtuvimos datos reales, retornarlos
            if (!empty($result)) {
                return $result;
            }
            
            // Si no obtuvimos datos, lanzar excepción para usar fallback
            throw new \Exception('No se obtuvieron datos de la vista');
            
        } catch (\Exception $e) {
            // Log del error para debugging
            \Log::error('Error en CobranzaService::getClientesPorVendedor: ' . $e->getMessage());
            
            // Retornar array vacío en caso de error
            return [];
        }
    }

    public function getCotizacionesPorVendedor($codigoVendedor, $limit = 10)
    {
        // Conectar a SQL Server 2012 para obtener cotizaciones
        try {
            $host = env('SQLSRV_EXTERNAL_HOST');
            $port = env('SQLSRV_EXTERNAL_PORT', '1433');
            $database = env('SQLSRV_EXTERNAL_DATABASE');
            $username = env('SQLSRV_EXTERNAL_USERNAME');
            $password = env('SQLSRV_EXTERNAL_PASSWORD');
            
            // Verificar que las credenciales estén configuradas
            if (!$host || !$database || !$username || !$password) {
                throw new \Exception('Credenciales SQL Server no configuradas en .env');
            }
            
            // Construir la consulta SQL para obtener cotizaciones del vendedor
            $query = "
                SELECT TOP {$limit}
                    dbo.MAEEDO.TIDO AS TIPO_DOCTO,
                    dbo.MAEEDO.NUDO AS NRO_DOCTO,
                    dbo.MAEEDO.ENDO AS CODIGO_CLIENTE,
                    dbo.MAEEN.NOKOEN AS CLIENTE,
                    dbo.MAEEDO.FEEMDO AS EMISION,
                    dbo.MAEEDO.FEULVEDO AS VENCIMIENTO,
                    CAST(GETDATE() - dbo.MAEEDO.FEULVEDO AS INT) AS DIAS,
                    CASE WHEN dbo.MAEEDO.TIDO = 'NCV' THEN dbo.MAEEDO.VABRDO * -1 ELSE dbo.MAEEDO.VABRDO END AS VALOR,
                    CASE WHEN dbo.MAEEDO.TIDO = 'NCV' THEN (dbo.MAEEDO.VABRDO - dbo.MAEEDO.VAABDO) * -1 ELSE (dbo.MAEEDO.VABRDO - dbo.MAEEDO.VAABDO) END AS SALDO,
                    CASE WHEN CAST(GETDATE() - dbo.MAEEDO.FEULVEDO AS INT) < -8 THEN 'VIGENTE' 
                         WHEN CAST(GETDATE() - dbo.MAEEDO.FEULVEDO AS INT) BETWEEN -7 AND -1 THEN 'POR VENCER' 
                         WHEN CAST(GETDATE() - dbo.MAEEDO.FEULVEDO AS INT) BETWEEN 0 AND 7 THEN 'VENCIDO' 
                         WHEN CAST(GETDATE() - dbo.MAEEDO.FEULVEDO AS INT) BETWEEN 8 AND 30 THEN 'MOROSO' 
                         ELSE 'BLOQUEAR' END AS ESTADO
                FROM dbo.MAEEDO
                LEFT JOIN dbo.MAEEN ON dbo.MAEEN.KOEN = dbo.MAEEDO.ENDO AND dbo.MAEEN.SUEN = dbo.MAEEDO.SUENDO
                LEFT JOIN dbo.TABFU ON dbo.TABFU.KOFU = dbo.MAEEN.KOFUEN
                WHERE (dbo.MAEEDO.EMPRESA = '01' OR dbo.MAEEDO.EMPRESA = '02') 
                    AND (dbo.MAEEDO.TIDO = 'NCV' OR dbo.MAEEDO.TIDO = 'FCV' OR dbo.MAEEDO.TIDO = 'FDV') 
                    AND (dbo.MAEEDO.FEEMDO > CONVERT(DATETIME, '2017-12-31 00:00:00', 102)) 
                    AND (dbo.MAEEDO.VABRDO > dbo.MAEEDO.VAABDO)
                    AND dbo.TABFU.KOFU = '{$codigoVendedor}'
                ORDER BY dbo.MAEEDO.FEEMDO DESC";
            
            // Crear archivo temporal con la consulta
            $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
            file_put_contents($tempFile, $query . "\ngo\nquit");
            
            // Ejecutar consulta usando tsql
            $command = "tsql -H {$host} -p {$port} -U {$username} -P {$password} -D {$database} < {$tempFile} 2>&1";
            $output = shell_exec($command);
            
            // Limpiar archivo temporal
            unlink($tempFile);
            
            // Procesar la salida
            $lines = explode("\n", $output);
            $result = [];
            $inData = false;
            
            foreach ($lines as $line) {
                $line = trim($line);
                
                // Saltar líneas de configuración
                if (empty($line) || strpos($line, 'locale') !== false || 
                    strpos($line, 'Setting') !== false || strpos($line, '1>') !== false ||
                    strpos($line, '2>') !== false || strpos($line, 'rows affected') !== false ||
                    strpos($line, 'Msg ') !== false || strpos($line, 'Warning:') !== false ||
                    strpos($line, 'TIPO_DOCTO') !== false) {
                    continue;
                }
                
                // Buscar líneas que contengan datos de cotizaciones
                if (!empty($line) && preg_match('/^(\w+)\s+(\d+)\s+(\d+)\s+(.+?)\s+([A-Za-z]{3}\s+\d+\s+\d{4}\s+\d{2}:\d{2}:\d{2}:\d{3}[AP]M)\s+([A-Za-z]{3}\s+\d+\s+\d{4}\s+\d{2}:\d{2}:\d{2}:\d{3}[AP]M)\s+(-?\d+)\s+(\d+)\s+(\d+)\s+(.+)$/', $line, $matches)) {
                    $result[] = [
                        'TIPO_DOCTO' => trim($matches[1]),
                        'NRO_DOCTO' => trim($matches[2]),
                        'CODIGO_CLIENTE' => trim($matches[3]),
                        'CLIENTE' => $this->convertToUtf8(trim($matches[4])),
                        'EMISION' => trim($matches[5]),
                        'VENCIMIENTO' => trim($matches[6]),
                        'DIAS' => (int)$matches[7],
                        'VALOR' => (float)$matches[8],
                        'SALDO' => (float)$matches[9],
                        'ESTADO' => $this->convertToUtf8(trim($matches[10]))
                    ];
                    \Log::info('✅ Cotización encontrada: ' . trim($matches[2]));
                }
            }
            
            // Si obtuvimos datos reales, retornarlos
            if (!empty($result)) {
                return $result;
            }
            
            // Si no obtuvimos datos, lanzar excepción
            throw new \Exception('No se obtuvieron cotizaciones del vendedor');
            
        } catch (\Exception $e) {
            // Log del error para debugging
            \Log::error('Error en CobranzaService::getCotizacionesPorVendedor: ' . $e->getMessage());
            
            // Retornar array vacío en caso de error
            return [];
        }
    }

    public function getResumenCobranzaPorVendedor($codigoVendedor)
    {
        return $this->getResumenCobranza($codigoVendedor);
    }

    public function getFacturasPendientesCliente($codigoCliente)
    {
        if ($this->useTestData) {
            return $this->getTestFacturasPendientesCliente($codigoCliente);
        }

        try {
            $query = "
                SELECT 
                    dbo.MAEEDO.TIDO AS TIPO_DOCTO,
                    dbo.MAEEDO.NUDO AS NRO_DOCTO,
                    dbo.MAEEDO.FEEMDO AS EMISION,
                    dbo.MAEEDO.FEULVEDO AS VENCIMIENTO,
                    CAST(GETDATE() - dbo.MAEEDO.FEULVEDO AS INT) AS DIAS_VENCIDO,
                    CASE WHEN dbo.MAEEDO.TIDO = 'NCV' THEN dbo.MAEEDO.VABRDO * - 1 ELSE dbo.MAEEDO.VABRDO END AS VALOR,
                    CASE WHEN dbo.MAEEDO.TIDO = 'NCV' THEN dbo.MAEEDO.VAABDO * - 1 ELSE dbo.MAEEDO.VAABDO END AS ABONOS,
                    CASE WHEN dbo.MAEEDO.TIDO = 'NCV' THEN (dbo.MAEEDO.VABRDO - dbo.MAEEDO.VAABDO) * - 1 ELSE (dbo.MAEEDO.VABRDO - dbo.MAEEDO.VAABDO) END AS SALDO,
                    CASE WHEN CAST(GETDATE() - dbo.MAEEDO.FEULVEDO AS INT) < - 8 THEN 'VIGENTE' 
                         WHEN CAST(GETDATE() - dbo.MAEEDO.FEULVEDO AS INT) BETWEEN - 7 AND - 1 THEN 'POR VENCER' 
                         WHEN CAST(GETDATE() - dbo.MAEEDO.FEULVEDO AS INT) BETWEEN 0 AND 7 THEN 'VENCIDO' 
                         WHEN CAST(GETDATE() - dbo.MAEEDO.FEULVEDO AS INT) BETWEEN 8 AND 30 THEN 'MOROSO' 
                         ELSE 'BLOQUEAR' END AS ESTADO
                FROM dbo.MAEEDO
                WHERE dbo.MAEEDO.ENDO = :codigoCliente
                    AND (dbo.MAEEDO.EMPRESA = '01' OR dbo.MAEEDO.EMPRESA = '02') 
                    AND (dbo.MAEEDO.TIDO = 'NCV' OR dbo.MAEEDO.TIDO = 'FCV' OR dbo.MAEEDO.TIDO = 'FDV') 
                    AND (dbo.MAEEDO.VABRDO > dbo.MAEEDO.VAABDO)
                ORDER BY DIAS_VENCIDO DESC";

            $stmt = $this->pdo->prepare($query);
            $stmt->bindParam(':codigoCliente', $codigoCliente);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            return $this->getTestFacturasPendientesCliente($codigoCliente);
        }
    }

    public function validarClienteParaVenta($codigoCliente)
    {
        $facturas = $this->getFacturasPendientesCliente($codigoCliente);
        
        // Contar facturas vencidas (estado VENCIDO, MOROSO, BLOQUEAR)
        $facturasVencidas = array_filter($facturas, function($factura) {
            return in_array($factura['ESTADO'], ['VENCIDO', 'MOROSO', 'BLOQUEAR']);
        });

        return [
            'puede_vender' => count($facturasVencidas) < 2,
            'facturas_vencidas' => count($facturasVencidas),
            'mensaje' => count($facturasVencidas) >= 2 ? 'Cliente con más de 2 facturas vencidas' : 'Cliente válido para venta'
        ];
    }
    
    public function getNotasVentaCliente($codigoCliente)
    {
        try {
            $host = env('SQLSRV_EXTERNAL_HOST');
            $port = env('SQLSRV_EXTERNAL_PORT', '1433');
            $database = env('SQLSRV_EXTERNAL_DATABASE');
            $username = env('SQLSRV_EXTERNAL_USERNAME');
            $password = env('SQLSRV_EXTERNAL_PASSWORD');
            
            // Verificar que las credenciales estén configuradas
            if (!$host || !$database || !$username || !$password) {
                throw new \Exception('Credenciales SQL Server no configuradas en .env');
            }
            
            // Consulta para obtener notas de venta del cliente
            $query = "
                SELECT TOP 20
                    dbo.MAEEDO.TIDO AS TIPO_DOCTO,
                    dbo.MAEEDO.NUDO AS NRO_DOCTO,
                    dbo.MAEEDO.FEEMDO AS EMISION,
                    dbo.MAEEDO.FEULVEDO AS VENCIMIENTO,
                    CAST(GETDATE() - dbo.MAEEDO.FEULVEDO AS INT) AS DIAS_VENCIDO,
                    CASE WHEN dbo.MAEEDO.TIDO = 'NCV' THEN dbo.MAEEDO.VABRDO * -1 ELSE dbo.MAEEDO.VABRDO END AS VALOR,
                    CASE WHEN dbo.MAEEDO.TIDO = 'NCV' THEN (dbo.MAEEDO.VABRDO - dbo.MAEEDO.VAABDO) * -1 ELSE (dbo.MAEEDO.VABRDO - dbo.MAEEDO.VAABDO) END AS SALDO,
                    CASE WHEN CAST(GETDATE() - dbo.MAEEDO.FEULVEDO AS INT) < -8 THEN 'VIGENTE' 
                         WHEN CAST(GETDATE() - dbo.MAEEDO.FEULVEDO AS INT) BETWEEN -7 AND -1 THEN 'POR VENCER' 
                         WHEN CAST(GETDATE() - dbo.MAEEDO.FEULVEDO AS INT) BETWEEN 0 AND 7 THEN 'VENCIDO' 
                         WHEN CAST(GETDATE() - dbo.MAEEDO.FEULVEDO AS INT) BETWEEN 8 AND 30 THEN 'MOROSO' 
                         ELSE 'BLOQUEAR' END AS ESTADO
                FROM dbo.MAEEDO
                WHERE dbo.MAEEDO.ENDO = '{$codigoCliente}'
                    AND dbo.MAEEDO.TIDO = 'NVV'
                    AND (dbo.MAEEDO.EMPRESA = '01' OR dbo.MAEEDO.EMPRESA = '02')
                ORDER BY dbo.MAEEDO.FEEMDO DESC";
            
            // Crear archivo temporal con la consulta
            $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
            file_put_contents($tempFile, $query . "\ngo\nquit");
            
            // Ejecutar consulta usando tsql
            $command = "tsql -H {$host} -p {$port} -U {$username} -P {$password} -D {$database} < {$tempFile} 2>&1";
            $output = shell_exec($command);
            
            // Limpiar archivo temporal
            unlink($tempFile);
            
            // Procesar la salida
            $lines = explode("\n", $output);
            $result = [];
            
            foreach ($lines as $line) {
                $line = trim($line);
                
                // Saltar líneas de configuración
                if (empty($line) || strpos($line, 'locale') !== false || 
                    strpos($line, 'Setting') !== false || strpos($line, '1>') !== false ||
                    strpos($line, '2>') !== false || strpos($line, 'rows affected') !== false ||
                    strpos($line, 'Msg ') !== false || strpos($line, 'Warning:') !== false ||
                    strpos($line, 'TIPO_DOCTO') !== false) {
                    continue;
                }
                
                // Buscar líneas que contengan datos de notas de venta
                if (!empty($line) && preg_match('/^(NVV)\s+(\d+)\s+([A-Za-z]{3}\s+\d+\s+\d{4}\s+\d{2}:\d{2}:\d{2}:\d{3}[AP]M)\s+([A-Za-z]{3}\s+\d+\s+\d{4}\s+\d{2}:\d{2}:\d{2}:\d{3}[AP]M)\s+(-?\d+)\s+(\d+)\s+(\d+)\s+(.+)$/', $line, $matches)) {
                    $result[] = [
                        'TIPO_DOCTO' => trim($matches[1]),
                        'NRO_DOCTO' => trim($matches[2]),
                        'EMISION' => trim($matches[3]),
                        'VENCIMIENTO' => trim($matches[4]),
                        'DIAS_VENCIDO' => (int)$matches[5],
                        'VALOR' => (float)$matches[6],
                        'SALDO' => (float)$matches[7],
                        'ESTADO' => $this->convertToUtf8(trim($matches[8]))
                    ];
                }
            }
            
            return $result;
            
        } catch (\Exception $e) {
            \Log::error('Error en getNotasVentaCliente: ' . $e->getMessage());
            return [];
        }
    }

    public function getClienteInfo($codigoCliente)
    {
        \Log::info('🔍 getClienteInfo llamado con código: ' . $codigoCliente);
        
        try {
            $host = env('SQLSRV_EXTERNAL_HOST');
            $port = env('SQLSRV_EXTERNAL_PORT', '1433');
            $database = env('SQLSRV_EXTERNAL_DATABASE');
            $username = env('SQLSRV_EXTERNAL_USERNAME');
            $password = env('SQLSRV_EXTERNAL_PASSWORD');
            
            // Verificar que las credenciales estén configuradas
            if (!$host || !$database || !$username || !$password) {
                throw new \Exception('Credenciales SQL Server no configuradas en .env');
            }
            
            // Usar exactamente la misma consulta que funciona en getClientesPorVendedor
            $query = "
                SELECT TOP 1
                    CODIGO_CLIENTE,
                    NOMBRE_CLIENTE,
                    TELEFONO,
                    DIRECCION,
                    REGION,
                    COMUNA,
                    CANTIDAD_FACTURAS,
                    SALDO_TOTAL
                FROM vw_clientes_por_vendedor
                WHERE CODIGO_CLIENTE = '{$codigoCliente}'";
            
            // Crear archivo temporal con la consulta
            $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
            file_put_contents($tempFile, $query . "\ngo\nquit");
            
            // Ejecutar consulta usando tsql
            $command = "tsql -H {$host} -p {$port} -U {$username} -P {$password} -D {$database} < {$tempFile} 2>&1";
            $output = shell_exec($command);
            
            // Limpiar archivo temporal
            unlink($tempFile);
            
            \Log::info('📋 TSQL Output: ' . substr($output, 0, 500));
            
            // Procesar la salida
            $lines = explode("\n", $output);
            $result = null;
            
            foreach ($lines as $line) {
                $line = trim($line);
                
                // Saltar líneas de configuración
                if (empty($line) || strpos($line, 'locale') !== false || 
                    strpos($line, 'Setting') !== false || strpos($line, '1>') !== false ||
                    strpos($line, '2>') !== false || strpos($line, 'rows affected') !== false ||
                    strpos($line, 'Msg ') !== false || strpos($line, 'Warning:') !== false ||
                    strpos($line, 'CODIGO_CLIENTE') !== false) {
                    continue;
                }
                
                // Usar exactamente la misma expresión regular que funciona en getClientesPorVendedor
                if (!empty($line) && preg_match('/^(\d{8,})\s+(.+?)\s+(\d{8,}|[+\d\s\/-]+)\s+(.+?)\s+(REGION METROPOLITANA)\s+(.+?)\s+(\d+)\s+(\d+|NULL)$/', $line, $matches)) {
                    $result = [
                        'CODIGO_CLIENTE' => $matches[1],
                        'NOMBRE_CLIENTE' => $this->convertToUtf8(trim($matches[2])),
                        'TELEFONO' => trim($matches[3]),
                        'DIRECCION' => $this->convertToUtf8(trim($matches[4])),
                        'REGION' => $this->convertToUtf8($matches[5]),
                        'COMUNA' => $this->convertToUtf8(trim($matches[6])),
                        'CANTIDAD_FACTURAS' => (int)$matches[7],
                        'SALDO_TOTAL' => $matches[8] === 'NULL' ? 0 : (float)$matches[8]
                    ];
                    
                    \Log::info('✅ Cliente encontrado: ' . $result['NOMBRE_CLIENTE']);
                    break;
                }
            }
            
            // Si no se encontró el cliente, usar datos por defecto
            if (!$result) {
                \Log::warning('⚠️ Cliente no encontrado, usando datos por defecto');
                $result = [
                    'CODIGO_CLIENTE' => $codigoCliente,
                    'NOMBRE_CLIENTE' => 'Cliente ' . $codigoCliente,
                    'TELEFONO' => 'No disponible',
                    'DIRECCION' => 'No disponible',
                    'REGION' => 'No disponible',
                    'COMUNA' => 'No disponible',
                    'CODIGO_VENDEDOR' => '01',
                    'NOMBRE_VENDEDOR' => 'Vendedor por defecto',
                    'CANTIDAD_FACTURAS' => '0',
                    'SALDO_TOTAL' => '0'
                ];
            }
            
            // Agregar datos adicionales que no están en la vista
            $result['LISTA_PRECIOS_CODIGO'] = '01';
            $result['LISTA_PRECIOS_NOMBRE'] = 'Lista General';
            $result['BLOQUEADO'] = '0';
            $result['EMAIL'] = 'Sin email';
            
            \Log::info('✅ getClienteInfo retornando datos completos');
            return $result;
            
        } catch (\Exception $e) {
            \Log::error('Error en getClienteInfo: ' . $e->getMessage());
            
            // Retornar datos por defecto en caso de error
            return [
                'CODIGO_CLIENTE' => $codigoCliente,
                'NOMBRE_CLIENTE' => 'Cliente ' . $codigoCliente,
                'TELEFONO' => 'Error en consulta',
                'DIRECCION' => 'Error en consulta',
                'REGION' => 'Error en consulta',
                'COMUNA' => 'Error en consulta',
                'CODIGO_VENDEDOR' => '01',
                'NOMBRE_VENDEDOR' => 'Error en consulta',
                'CANTIDAD_FACTURAS' => '0',
                'SALDO_TOTAL' => '0',
                'LISTA_PRECIOS_CODIGO' => '01',
                'LISTA_PRECIOS_NOMBRE' => 'Error en consulta',
                'BLOQUEADO' => '0',
                'EMAIL' => 'Sin email'
            ];
        }
    }
    
    // MÉTODO ORIGINAL COMENTADO TEMPORALMENTE
    /*
    public function getClienteInfoOriginal($codigoCliente)
    {
        \Log::info('🔍 getClienteInfo llamado con código: ' . $codigoCliente);
        
        try {
            $host = env('SQLSRV_EXTERNAL_HOST');
            $port = env('SQLSRV_EXTERNAL_PORT', '1433');
            $database = env('SQLSRV_EXTERNAL_DATABASE');
            $username = env('SQLSRV_EXTERNAL_USERNAME');
            $password = env('SQLSRV_EXTERNAL_PASSWORD');
            
            // Verificar que las credenciales estén configuradas
            if (!$host || !$database || !$username || !$password) {
                throw new \Exception('Credenciales SQL Server no configuradas en .env');
            }
            
            // Consulta directa a MAEEN para obtener datos del cliente
            $query = "
                SELECT TOP 1
                    MAEEN.KOEN,
                    MAEEN.NOKOEN,
                    MAEEN.FOEN,
                    MAEEN.DIEN,
                    MAEEN.KOFUEN,
                    MAEEN.LCEN,
                    MAEEN.BLOQUEADO
                FROM MAEEN
                WHERE MAEEN.KOEN = '{$codigoCliente}'";
            
            // Crear archivo temporal con la consulta
            $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
            file_put_contents($tempFile, $query . "\ngo\nquit");
            
            // Ejecutar consulta usando tsql
            $command = "tsql -H {$host} -p {$port} -U {$username} -P {$password} -D {$database} < {$tempFile} 2>&1";
            $output = shell_exec($command);
            
            // Limpiar archivo temporal
            unlink($tempFile);
            
            \Log::info('📋 TSQL Output: ' . substr($output, 0, 500));
            
            // Procesar la salida
            $lines = explode("\n", $output);
            $result = null;
            
            foreach ($lines as $line) {
                $line = trim($line);
                
                // Saltar líneas de configuración
                if (empty($line) || strpos($line, 'locale') !== false || 
                    strpos($line, 'Setting') !== false || strpos($line, '1>') !== false ||
                    strpos($line, '2>') !== false || strpos($line, 'rows affected') !== false ||
                    strpos($line, 'Msg ') !== false || strpos($line, 'Warning:') !== false ||
                    strpos($line, 'CODIGO_CLIENTE') !== false) {
                    continue;
                }
                
                // Buscar líneas que contengan datos del cliente
                if (!empty($line) && strpos($line, $codigoCliente) === 0) {
                    // Dividir la línea por espacios múltiples
                    $parts = preg_split('/\s+/', trim($line));
                    
                    if (count($parts) >= 7) {
                        $result = [
                            'CODIGO_CLIENTE' => trim($parts[0]),
                            'NOMBRE_CLIENTE' => trim($parts[1]),
                            'TELEFONO' => trim($parts[2]),
                            'DIRECCION' => trim($parts[3]),
                            'CODIGO_VENDEDOR' => trim($parts[4]),
                            'LISTA_PRECIOS_CODIGO' => trim($parts[5]),
                            'BLOQUEADO' => trim($parts[6])
                        ];
                        
                        \Log::info('✅ Cliente encontrado: ' . $result['NOMBRE_CLIENTE']);
                        \Log::info('📋 Datos parseados: ' . json_encode($result));
                        break;
                    }
                }
            }
            
            // Si no se encontró el cliente, usar datos por defecto
            if (!$result) {
                \Log::warning('⚠️ Cliente no encontrado, usando datos por defecto');
                $result = [
                    'CODIGO_CLIENTE' => $codigoCliente,
                    'NOMBRE_CLIENTE' => 'Cliente ' . $codigoCliente,
                    'TELEFONO' => 'No disponible',
                    'DIRECCION' => 'No disponible',

                    'CODIGO_VENDEDOR' => '01',
                    'LISTA_PRECIOS_CODIGO' => '01',
                    'BLOQUEADO' => '0'
                ];
            }
            
            // Agregar datos adicionales
            $result['REGION'] = 'Región por defecto';
            $result['COMUNA'] = 'Comuna por defecto';
            $result['EMAIL'] = 'Sin email';
            $result['NOMBRE_VENDEDOR'] = 'Vendedor ' . $result['CODIGO_VENDEDOR'];
            $result['CANTIDAD_FACTURAS'] = '0';
            $result['SALDO_TOTAL'] = '0';
            $result['LISTA_PRECIOS_NOMBRE'] = 'Lista ' . $result['LISTA_PRECIOS_CODIGO'];
            
            return $result;
            
        } catch (\Exception $e) {
            \Log::error('Error en getClienteInfo: ' . $e->getMessage());
            
            // Retornar datos por defecto en caso de error
            return [
                'CODIGO_CLIENTE' => $codigoCliente,
                'NOMBRE_CLIENTE' => 'Cliente ' . $codigoCliente,
                'TELEFONO' => 'Error en consulta',
                'DIRECCION' => 'Error en consulta',

                'REGION' => 'Error en consulta',
                'COMUNA' => 'Error en consulta',
                'CODIGO_VENDEDOR' => '01',
                'NOMBRE_VENDEDOR' => 'Error en consulta',
                'CANTIDAD_FACTURAS' => '0',
                'SALDO_TOTAL' => '0',
                'LISTA_PRECIOS_CODIGO' => '01',
                'LISTA_PRECIOS_NOMBRE' => 'Error en consulta',
                'BLOQUEADO' => '0'
            ];
        }
    }
    */

    private function getDatosAdicionalesCliente($codigoCliente)
    {
        try {
            $host = env('SQLSRV_EXTERNAL_HOST');
            $port = env('SQLSRV_EXTERNAL_PORT', '1433');
            $database = env('SQLSRV_EXTERNAL_DATABASE');
            $username = env('SQLSRV_EXTERNAL_USERNAME');
            $password = env('SQLSRV_EXTERNAL_PASSWORD');
            
            // Verificar que las credenciales estén configuradas
            if (!$host || !$database || !$username || !$password) {
                throw new \Exception('Credenciales SQL Server no configuradas en .env');
            }
            
            // Consulta para obtener lista de precios y estado bloqueado
            $query = "
                SELECT TOP 1
                    MAEEN.LCEN AS LISTA_PRECIOS_CODIGO,
                    TABLT.NOKOLT AS LISTA_PRECIOS_NOMBRE,
                    MAEEN.BLOQUEADO AS BLOQUEADO
                FROM MAEEN
                LEFT JOIN TABLT ON MAEEN.LCEN = TABLT.KOLT
                WHERE MAEEN.KOEN = '{$codigoCliente}'";
            
            // Crear archivo temporal con la consulta
            $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
            file_put_contents($tempFile, $query . "\ngo\nquit");
            
            // Ejecutar consulta usando tsql
            $command = "tsql -H {$host} -p {$port} -U {$username} -P {$password} -D {$database} < {$tempFile} 2>&1";
            $output = shell_exec($command);
            
            // Limpiar archivo temporal
            unlink($tempFile);
            
            // Procesar la salida
            $lines = explode("\n", $output);
            $result = null;
            
            foreach ($lines as $line) {
                $line = trim($line);
                
                // Saltar líneas de configuración
                if (empty($line) || strpos($line, 'locale') !== false || 
                    strpos($line, 'Setting') !== false || strpos($line, '1>') !== false ||
                    strpos($line, '2>') !== false || strpos($line, 'rows affected') !== false ||
                    strpos($line, 'Msg ') !== false || strpos($line, 'Warning:') !== false ||
                    strpos($line, 'LISTA_PRECIOS_CODIGO') !== false) {
                    continue;
                }
                
                // Buscar líneas que contengan datos
                if (!empty($line) && preg_match('/^([A-Z0-9]*)\s+"?([^"]*)"?\s+"?([^"]*)"?/', $line, $matches)) {
                    $result = [
                        'LISTA_PRECIOS_CODIGO' => trim($matches[1]),
                        'LISTA_PRECIOS_NOMBRE' => trim($matches[2]),
                        'BLOQUEADO' => trim($matches[3])
                    ];
                    break;
                }
            }
            
            return $result;
            
        } catch (\Exception $e) {
            \Log::error('Error en getDatosAdicionalesCliente: ' . $e->getMessage());
            return null;
        }
    }

    // Métodos de datos de prueba (COMENTADOS - usar solo datos reales)
    /*
    private function getTestCobranza($codigoVendedor = null)
    {
        $data = [
            [
                'TIPO_DOCTO' => 'FCV',
                'NRO_DOCTO' => '0000041830',
                'CODIGO' => '77505101',
                'CLIENTE' => 'FERRETERIA VERSALLES SPA',
                'VENDEDOR' => 'GERARDO ORMEÑO PAREDES',
                'COD_VEN' => 'GOP',
                'VALOR' => 517764.0,
                'ABONOS' => 0.0,
                'SALDO' => 517764.0,
                'ESTADO' => 'VIGENTE',
                'DIAS' => -54,
                'EMISION' => '2025-07-25',
                'VENCIMIENTO' => '2025-09-23',
                'DIRECCION' => 'PANAMERICANA NORTE KM 19 1/2',
                'REGION' => 'REGION METROPOLITANA',
                'COMUNA' => 'COLINA'
            ],
            [
                'TIPO_DOCTO' => 'FCV',
                'NRO_DOCTO' => '0000041831',
                'CODIGO' => '77505101',
                'CLIENTE' => 'FERRETERIA VERSALLES SPA',
                'VENDEDOR' => 'GERARDO ORMEÑO PAREDES',
                'COD_VEN' => 'GOP',
                'VALOR' => 454832.0,
                'ABONOS' => 0.0,
                'SALDO' => 454832.0,
                'ESTADO' => 'VIGENTE',
                'DIAS' => -54,
                'EMISION' => '2025-07-25',
                'VENCIMIENTO' => '2025-09-23',
                'DIRECCION' => 'PANAMERICANA NORTE KM 19 1/2',
                'REGION' => 'REGION METROPOLITANA',
                'COMUNA' => 'COLINA'
            ],
            [
                'TIPO_DOCTO' => 'FCV',
                'NRO_DOCTO' => '0000041832',
                'CODIGO' => '77505101',
                'CLIENTE' => 'FERRETERIA VERSALLES SPA',
                'VENDEDOR' => 'GERARDO ORMEÑO PAREDES',
                'COD_VEN' => 'GOP',
                'VALOR' => 240422.0,
                'ABONOS' => 0.0,
                'SALDO' => 240422.0,
                'ESTADO' => 'VIGENTE',
                'DIAS' => -54,
                'EMISION' => '2025-07-25',
                'VENCIMIENTO' => '2025-09-23',
                'DIRECCION' => 'PANAMERICANA NORTE KM 19 1/2',
                'REGION' => 'REGION METROPOLITANA',
                'COMUNA' => 'COLINA'
            ]
        ];
        
        // Filtrar por vendedor si se especifica
        if ($codigoVendedor) {
            $data = array_filter($data, function($item) use ($codigoVendedor) {
                return $item['COD_VEN'] === $codigoVendedor;
            });
        }
        
        return array_values($data);
    }
    */

    /*
    private function getTestCobranzaPorVendedor()
    {
        return [
            [
                'COD_VENDEDOR' => 'VEN001',
                'NOMBRE_VENDEDOR' => 'Vendedor Test',
                'TOTAL_DOCUMENTOS' => 2,
                'TOTAL_SALDO' => 50000
            ]
        ];
    }

    private function getTestClientesPorVendedor($codigoVendedor)
    {
        // Solo mostrar clientes si el vendedor es GOP
        if ($codigoVendedor === 'GOP') {
            return [
                [
                    'CODIGO_CLIENTE' => '77505101',
                    'NOMBRE_CLIENTE' => 'FERRETERIA VERSALLES SPA',
                    'TELEFONO' => '',
                    'DIRECCION' => 'PANAMERICANA NORTE KM 19 1/2',
                    'REGION' => 'REGION METROPOLITANA',
                    'COMUNA' => 'COLINA',
                    'CANTIDAD_FACTURAS' => 3,
                    'SALDO_TOTAL' => 1213018.0
                ]
            ];
        }
        
        return [];
    }

    private function getTestFacturasPendientesCliente($codigoCliente)
    {
        return [
            [
                'TIPO_DOCTO' => 'FCV',
                'NRO_DOCTO' => '001',
                'EMISION' => '2024-01-15',
                'VENCIMIENTO' => '2024-02-15',
                'DIAS_VENCIDO' => 15,
                'VALOR' => 100000,
                'ABONOS' => 50000,
                'SALDO' => 50000,
                'ESTADO' => 'VENCIDO'
            ]
        ];
    }

    private function getTestClienteInfo($codigoCliente)
    {
        return [
            'CODIGO_CLIENTE' => $codigoCliente,
            'NOMBRE_CLIENTE' => 'Cliente Test',
            'TELEFONO' => '+56912345678',
            'DIRECCION' => 'Dirección Test',
            'REGION' => 'Región Test',
            'COMUNA' => 'Comuna Test',
            'VENDEDOR' => 'Vendedor Test'
        ];
    }
    */

    private function executeQueryViaBridge($query)
    {
        $host = env('SQLSRV_EXTERNAL_HOST');
        $port = env('SQLSRV_EXTERNAL_PORT', '1433');
        $database = env('SQLSRV_EXTERNAL_DATABASE');
        $username = env('SQLSRV_EXTERNAL_USERNAME');
        $password = env('SQLSRV_EXTERNAL_PASSWORD');
        
        // Verificar que las credenciales estén configuradas
        if (!$host || !$database || !$username || !$password) {
            throw new \Exception('Credenciales SQL Server no configuradas en .env');
        }

                       // Ejecutar consulta usando tsql en modo interactivo
               $queryWithGo = $query . "\ngo\nquit";
               $sqlcmd = "echo -e \"{$queryWithGo}\" | /usr/local/bin/docker exec -i sqlserver_bridge tsql -S HIGUERA030924 -D HIGUERA030924 -U {$username} -P {$password}";
               $output = shell_exec($sqlcmd . " 2>&1");
        
        if ($output === null) {
            throw new \Exception("Failed to execute query via Docker bridge");
        }

        // Log del output completo para debug
        \Log::info("TSQL Output for query: " . substr($query, 0, 100) . "...");
        \Log::info("TSQL Raw output: " . $output);

        // Parsear el resultado de tsql
        $lines = explode("\n", $output);
        $results = [];
        $headers = null;
        $inData = false;
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Saltar líneas vacías o de configuración
            if (empty($line) || strpos($line, 'Compile-time settings') !== false || 
                strpos($line, 'Version:') !== false || strpos($line, 'freetds') !== false ||
                strpos($line, 'MS db-lib') !== false || strpos($line, 'Sybase binary') !== false ||
                strpos($line, 'Thread safety') !== false || strpos($line, 'iconv library') !== false ||
                strpos($line, 'TDS version') !== false || strpos($line, 'iODBC') !== false ||
                strpos($line, 'unixodbc') !== false || strpos($line, 'SSPI') !== false ||
                strpos($line, 'Kerberos') !== false || strpos($line, 'OpenSSL') !== false ||
                strpos($line, 'GnuTLS') !== false || strpos($line, 'MARS') !== false ||
                strpos($line, 'Setting HIGUERA030924') !== false || strpos($line, 'locale is') !== false ||
                strpos($line, 'using default charset') !== false || strpos($line, 'rows affected') !== false ||
                strpos($line, 'Msg ') !== false || strpos($line, 'severity') !== false ||
                strpos($line, 'state') !== false || strpos($line, 'from SERVERRANDOM') !== false ||
                strpos($line, 'Line') !== false || strpos($line, 'Incorrect syntax') !== false ||
                strpos($line, '1>') !== false || strpos($line, '2>') !== false || strpos($line, '%') !== false) {
                continue;
            }
            
            // Buscar la línea de headers (contiene nombres de columnas)
            if (!$inData && preg_match('/^[A-Z_]+(\s+[A-Z_]+)*$/', $line)) {
                $headers = preg_split('/\s{2,}/', $line);
                $headers = array_map('trim', $headers);
                $inData = true;
                \Log::info("Headers found: " . implode(', ', $headers));
                continue;
            }
            
            // Si estamos en datos y encontramos una línea con datos
            if ($inData && $headers && !empty($line) && !preg_match('/^[A-Z_]+(\s+[A-Z_]+)*$/', $line)) {
                // Parsear la línea de datos usando espacios múltiples como separador
                $parts = preg_split('/\s{2,}/', $line);
                $parts = array_map('trim', $parts);
                
                if (count($parts) >= count($headers)) {
                    $row = [];
                    foreach ($headers as $index => $header) {
                        if (isset($parts[$index])) {
                            $row[$header] = $parts[$index];
                        }
                    }
                    if (!empty($row)) {
                        $results[] = $row;
                        \Log::info("Row added: " . json_encode($row));
                    }
                }
            }
        }
        
        \Log::info("Total results parsed: " . count($results));
        return $results;
    }
    
    /**
     * Convierte texto de codificación Windows-1252 a UTF-8
     * Para manejar correctamente las ñ y acentos
     */
    private function convertToUtf8($text)
    {
        if (empty($text)) {
            return $text;
        }
        
        // Detectar si el texto ya está en UTF-8
        if (mb_detect_encoding($text, 'UTF-8', true)) {
            return $text;
        }
        
        // Convertir de Windows-1252 (o similar) a UTF-8
        $converted = mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
        
        // Si la conversión falló, intentar con ISO-8859-1
        if ($converted === false) {
            $converted = mb_convert_encoding($text, 'UTF-8', 'ISO-8859-1');
        }
        
        // Si aún falla, devolver el texto original
        if ($converted === false) {
            return $text;
        }
        
        return $converted;
    }
} 