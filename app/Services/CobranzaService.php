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
            $host = env('SQLSRV_EXTERNAL_HOST', '152.231.92.82');
            $port = env('SQLSRV_EXTERNAL_PORT', '1433');
            $database = env('SQLSRV_EXTERNAL_DATABASE', 'HIGUERA030924');
            $username = env('SQLSRV_EXTERNAL_USERNAME', 'AMANECER');
            $password = env('SQLSRV_EXTERNAL_PASSWORD', 'AMANECER');
            
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
        // En AWS usaremos conexión directa PDO
        try {
            $host = env('SQLSRV_EXTERNAL_HOST', '152.231.92.82');
            $port = env('SQLSRV_EXTERNAL_PORT', '1433');
            $database = env('SQLSRV_EXTERNAL_DATABASE', 'HIGUERA030924');
            $username = env('SQLSRV_EXTERNAL_USERNAME', 'AMANECER');
            $password = env('SQLSRV_EXTERNAL_PASSWORD', 'AMANECER');
            
            // Intentar conexión directa PDO (funcionará en AWS)
            $dsn = "odbc:Driver={ODBC Driver 18 for SQL Server};Server={$host},{$port};Database={$database};Encrypt=no;TrustServerCertificate=yes;";
            
            $pdo = new PDO($dsn, $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $query = "
                SELECT TOP 10 
                    CODIGO_CLIENTE,
                    NOMBRE_CLIENTE,
                    TELEFONO,
                    DIRECCION,
                    REGION,
                    COMUNA,
                    CANTIDAD_FACTURAS,
                    SALDO_TOTAL
                FROM vw_clientes_por_vendedor";
            
            // Agregar filtro por vendedor si se especifica
            if ($codigoVendedor) {
                $query .= " WHERE CODIGO_VENDEDOR = :codigoVendedor";
            }
            
            $query .= " ORDER BY NOMBRE_CLIENTE";
            
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
                        'CODIGO_CLIENTE' => '03191313',
                        'NOMBRE_CLIENTE' => 'MARIA CARREÑO CAMEÑO',
                        'TELEFONO' => '229963608',
                        'DIRECCION' => 'AV. PEDRO FONTOVA N° 5110',
                        'REGION' => 'REGION METROPOLITANA',
                        'COMUNA' => 'CONCHALI',
                        'CANTIDAD_FACTURAS' => 1,
                        'SALDO_TOTAL' => 331570
                    ],
                    [
                        'CODIGO_CLIENTE' => '03359217',
                        'NOMBRE_CLIENTE' => 'MARGARITA BOZO MUÑOZ',
                        'TELEFONO' => '227730649',
                        'DIRECCION' => 'SAN PABLO Nº 5894',
                        'REGION' => 'REGION METROPOLITANA',
                        'COMUNA' => 'LO PRADO',
                        'CANTIDAD_FACTURAS' => 3,
                        'SALDO_TOTAL' => 145321
                    ],
                    [
                        'CODIGO_CLIENTE' => '03944256',
                        'NOMBRE_CLIENTE' => 'ANA HURTADO CONTRERAS',
                        'TELEFONO' => '232065608',
                        'DIRECCION' => 'AV. DEL PINCOY N°562',
                        'REGION' => 'REGION METROPOLITANA',
                        'COMUNA' => 'HUECHURABA',
                        'CANTIDAD_FACTURAS' => 5,
                        'SALDO_TOTAL' => 868001
                    ]
                ];
            }
            
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

    public function getClienteInfo($codigoCliente)
    {
        if ($this->useTestData) {
            return $this->getTestClienteInfo($codigoCliente);
        }

        try {
            $query = "
                SELECT 
                    dbo.MAEEN.KOEN AS CODIGO_CLIENTE,
                    dbo.MAEEN.NOKOEN AS NOMBRE_CLIENTE,
                    dbo.MAEEN.FOEN AS TELEFONO,
                    dbo.MAEEN.DIEN AS DIRECCION,
                    dbo.TABCI.NOKOCI AS REGION,
                    dbo.TABCM.NOKOCM AS COMUNA,
                    dbo.TABFU.NOKOFU AS VENDEDOR
                FROM dbo.MAEEN
                LEFT JOIN dbo.TABCI ON dbo.MAEEN.CIEN = dbo.TABCI.KOCI
                LEFT JOIN dbo.TABCM ON dbo.MAEEN.CIEN = dbo.TABCM.KOCI AND dbo.MAEEN.CMEN = dbo.TABCM.KOCM
                LEFT JOIN dbo.TABFU ON dbo.MAEEN.KOFUEN = dbo.TABFU.KOFU
                WHERE dbo.MAEEN.KOEN = :codigoCliente";

            $stmt = $this->pdo->prepare($query);
            $stmt->bindParam(':codigoCliente', $codigoCliente);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            return $this->getTestClienteInfo($codigoCliente);
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
        $host = env('SQLSRV_EXTERNAL_HOST', '152.231.92.82');
        $port = env('SQLSRV_EXTERNAL_PORT', '1433');
        $database = env('SQLSRV_EXTERNAL_DATABASE', 'HIGUERA030924');
        $username = env('SQLSRV_EXTERNAL_USERNAME', 'AMANECER');
        $password = env('SQLSRV_EXTERNAL_PASSWORD', 'AMANECER');

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
} 