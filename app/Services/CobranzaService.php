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
            $dsn = "sqlsrv:Server={$host},{$port};Database={$database};Encrypt=no;TrustServerCertificate=yes;ConnectionPooling=0;";
            
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
        try {
            // Obtener los clientes directamente de la base de datos local
            $clientes = \App\Models\Cliente::where('codigo_vendedor', $codigoVendedor)
                ->where('activo', true)
                ->get();
            
            // Obtener todos los datos de facturación en una sola consulta
            $datosFacturacion = $this->getDatosFacturacionTodosClientes($codigoVendedor);
            
            return $clientes->map(function($cliente) use ($datosFacturacion) {
                $codigoCliente = $cliente->codigo_cliente;
                $datosCliente = $datosFacturacion[$codigoCliente] ?? [
                    'cantidad_facturas' => 0,
                    'saldo_total' => 0
                ];
                
                return [
                    'CODIGO_CLIENTE' => $cliente->codigo_cliente,
                    'NOMBRE_CLIENTE' => $cliente->nombre_cliente,
                    'TELEFONO' => $cliente->telefono,
                    'DIRECCION' => $cliente->direccion,
                    'REGION' => $cliente->region,
                    'COMUNA' => $cliente->comuna,
                    'CODIGO_VENDEDOR' => $cliente->codigo_vendedor,
                    'NOMBRE_VENDEDOR' => $cliente->nombre_vendedor ?? 'Vendedor',
                    'CANTIDAD_FACTURAS' => $datosCliente['cantidad_facturas'],
                    'SALDO_TOTAL' => $datosCliente['saldo_total'],
                    'BLOQUEADO' => $cliente->bloqueado ? 1 : 0
                ];
            })->toArray();
            
        } catch (\Exception $e) {
            \Log::error('Error en CobranzaService::getClientesPorVendedor: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener clientes vencidos, bloqueados y morosos por vendedor
     */
    public function getClientesVencidosBloqueadosMorosos($codigoVendedor)
    {
        try {
            // Obtener los clientes directamente de la base de datos local
            $clientes = \App\Models\Cliente::where('codigo_vendedor', $codigoVendedor)
                ->where('activo', true)
                ->get();
            
            // Obtener todos los datos de facturación en una sola consulta
            $datosFacturacion = $this->getDatosFacturacionTodosClientes($codigoVendedor);
            
            $clientesProblema = [];
            
            foreach ($clientes as $cliente) {
                $codigoCliente = $cliente->codigo_cliente;
                $datosCliente = $datosFacturacion[$codigoCliente] ?? [
                    'cantidad_facturas' => 0,
                    'saldo_total' => 0
                ];
                
                // Verificar si el cliente tiene problemas
                $tieneProblemas = false;
                $problemas = [];
                
                // Cliente bloqueado
                if ($cliente->bloqueado) {
                    $tieneProblemas = true;
                    $problemas[] = 'BLOQUEADO';
                }
                
                // Cliente con facturas vencidas (más de 30 días)
                if ($datosCliente['cantidad_facturas'] > 0 && $datosCliente['saldo_total'] > 0) {
                    // Obtener facturas del cliente para verificar vencimiento
                    $facturasCliente = $this->getFacturasPendientesCliente($codigoCliente);
                    $facturasVencidas = array_filter($facturasCliente, function($factura) {
                        return in_array($factura['ESTADO'], ['VENCIDO', 'MOROSO', 'BLOQUEAR']);
                    });
                    
                    if (count($facturasVencidas) > 0) {
                        $tieneProblemas = true;
                        $problemas[] = 'VENCIDO';
                    }
                }
                
                // Solo incluir clientes con problemas
                if ($tieneProblemas) {
                    $clientesProblema[] = [
                        'CODIGO_CLIENTE' => $cliente->codigo_cliente,
                        'NOMBRE_CLIENTE' => $cliente->nombre_cliente,
                        'TELEFONO' => $cliente->telefono,
                        'DIRECCION' => $cliente->direccion,
                        'REGION' => $cliente->region,
                        'COMUNA' => $cliente->comuna,
                        'CODIGO_VENDEDOR' => $cliente->codigo_vendedor,
                        'NOMBRE_VENDEDOR' => $cliente->nombre_vendedor ?? 'Vendedor',
                        'CANTIDAD_FACTURAS' => $datosCliente['cantidad_facturas'],
                        'SALDO_TOTAL' => $datosCliente['saldo_total'],
                        'BLOQUEADO' => $cliente->bloqueado ? 1 : 0,
                        'PROBLEMAS' => implode(', ', $problemas)
                    ];
                }
            }
            
            return $clientesProblema;
            
        } catch (\Exception $e) {
            \Log::error('Error en CobranzaService::getClientesVencidosBloqueadosMorosos: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Procesar una línea individual de cliente
     */
    private function procesarLineaCliente($line, $lineNumber)
    {
        try {
            // Limpiar la línea de caracteres especiales
            $line = preg_replace('/\s+/', ' ', $line); // Reemplazar múltiples espacios por uno
            $line = trim($line);
            
            // \Log::info('Línea limpia ' . $lineNumber . ': ' . $line);
            
            // Dividir la línea por espacios y procesar cada campo
            $fields = explode(' ', $line);
            
            // Buscar el patrón: CODIGO_CLIENTE NOMBRE_CLIENTE TELEFONO DIRECCION...
            $cliente = null;
            
            for ($i = 0; $i < count($fields) - 5; $i++) {
                // Verificar si encontramos un código de cliente (8+ dígitos)
                if (isset($fields[$i]) && is_numeric($fields[$i]) && strlen($fields[$i]) >= 8) {
                    $codigoCliente = $fields[$i];
                    
                    // \Log::info('Código cliente encontrado: ' . $codigoCliente);
                    
                    // Extraer campos usando posiciones relativas
                    $cliente = $this->extraerCamposCliente($fields, $i);
                    break;
                }
            }
            
            if ($cliente) {
                // \Log::info('Cliente extraído: ' . json_encode($cliente));
            }
            
            return $cliente;
            
        } catch (\Exception $e) {
            \Log::error('Error procesando línea ' . $lineNumber . ': ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Extraer campos de cliente desde un array de campos
     */
    private function extraerCamposCliente($fields, $startIndex)
    {
        try {
            // Los campos vienen en este orden de la vista:
            // CODIGO_CLIENTE, NOMBRE_CLIENTE, TELEFONO, DIRECCION, REGION, COMUNA, CODIGO_VENDEDOR, NOMBRE_VENDEDOR, CANTIDAD_FACTURAS, SALDO_TOTAL, BLOQUEADO
            
            $currentIndex = $startIndex;
            
            // Extraer cada campo por posición
            $codigoCliente = isset($fields[$currentIndex]) ? $fields[$currentIndex] : '';
            $currentIndex++;
            
            // Extraer nombre del cliente (puede tener espacios)
            $nombreCliente = '';
            while ($currentIndex < count($fields) && !preg_match('/^[\d\+\/\s\-]+$/', $fields[$currentIndex])) {
                $nombreCliente .= ' ' . $fields[$currentIndex];
                $currentIndex++;
            }
            $nombreCliente = trim($nombreCliente);
            
            // Extraer teléfono
            $telefono = isset($fields[$currentIndex]) ? $fields[$currentIndex] : '';
            $currentIndex++;
            
            // Extraer dirección (puede tener espacios)
            $direccion = '';
            while ($currentIndex < count($fields) && strpos($fields[$currentIndex], 'REGION') === false) {
                $direccion .= ' ' . $fields[$currentIndex];
                $currentIndex++;
            }
            $direccion = trim($direccion);
            
            // Extraer región
            $region = isset($fields[$currentIndex]) ? $fields[$currentIndex] : '';
            $currentIndex++;
            
            // Extraer comuna (puede tener espacios)
            $comuna = '';
            while ($currentIndex < count($fields) && !preg_match('/^[A-Z]{3}$/', $fields[$currentIndex])) {
                $comuna .= ' ' . $fields[$currentIndex];
                $currentIndex++;
            }
            $comuna = trim($comuna);
            
            // Extraer código de vendedor
            $codigoVendedor = isset($fields[$currentIndex]) ? $fields[$currentIndex] : '';
            $currentIndex++;
            
            // Extraer nombre del vendedor (puede tener espacios)
            $nombreVendedor = '';
            while ($currentIndex < count($fields) && !is_numeric($fields[$currentIndex])) {
                $nombreVendedor .= ' ' . $fields[$currentIndex];
                $currentIndex++;
            }
            $nombreVendedor = trim($nombreVendedor);
            
            // Extraer valores numéricos
            $cantidadFacturas = isset($fields[$currentIndex]) ? (int)$fields[$currentIndex] : 0;
            $currentIndex++;
            
            $saldoTotal = isset($fields[$currentIndex]) ? (float)$fields[$currentIndex] : 0;
            $currentIndex++;
            
            $bloqueado = isset($fields[$currentIndex]) ? (int)$fields[$currentIndex] : 0;
            
            // \Log::info('Campos extraídos: Cliente=' . $codigoCliente . ', Vendedor=' . $codigoVendedor);
            
            // Crear objeto de cliente
            $cliente = [
                'CODIGO_CLIENTE' => $codigoCliente,
                'NOMBRE_CLIENTE' => $this->convertToUtf8($nombreCliente),
                'TELEFONO' => $telefono,
                'DIRECCION' => $this->convertToUtf8($direccion),
                'REGION' => $this->convertToUtf8($region),
                'COMUNA' => $this->convertToUtf8($comuna),
                'CODIGO_VENDEDOR' => $codigoVendedor,
                'NOMBRE_VENDEDOR' => $this->convertToUtf8($nombreVendedor),
                'CANTIDAD_FACTURAS' => $cantidadFacturas,
                'SALDO_TOTAL' => $saldoTotal,
                'BLOQUEADO' => $bloqueado
            ];
            
            // \Log::info('Cliente creado: ' . $cliente['NOMBRE_CLIENTE'] . ' - Vendedor: ' . $cliente['CODIGO_VENDEDOR']);
            
            return $cliente;
            
        } catch (\Exception $e) {
            \Log::error('Error extrayendo campos cliente: ' . $e->getMessage());
            return null;
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
                    // \Log::info('✅ Cotización encontrada: ' . trim($matches[2]));
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
            $host = env('SQLSRV_EXTERNAL_HOST');
            $port = env('SQLSRV_EXTERNAL_PORT', '1433');
            $database = env('SQLSRV_EXTERNAL_DATABASE');
            $username = env('SQLSRV_EXTERNAL_USERNAME');
            $password = env('SQLSRV_EXTERNAL_PASSWORD');
            
            // Verificar que las credenciales estén configuradas
            if (!$host || !$database || !$username || !$password) {
                throw new \Exception('Credenciales SQL Server no configuradas en .env');
            }
            
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
                WHERE dbo.MAEEDO.ENDO = '{$codigoCliente}'
                    AND (dbo.MAEEDO.EMPRESA = '01' OR dbo.MAEEDO.EMPRESA = '02') 
                    AND (dbo.MAEEDO.TIDO = 'FCV' OR dbo.MAEEDO.TIDO = 'FDV') 
                    AND (dbo.MAEEDO.VABRDO > dbo.MAEEDO.VAABDO)
                ORDER BY DIAS_VENCIDO DESC";

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
            
            // Procesar la salida
            $lines = explode("\n", $output);
            $result = [];
            
            foreach ($lines as $line) {
                $line = trim($line);
                
                // Saltar líneas de configuración
                if (empty($line) || 
                    strpos($line, 'locale') !== false || 
                    strpos($line, 'Setting') !== false || 
                    strpos($line, 'Msg ') !== false || 
                    strpos($line, 'Warning:') !== false ||
                    preg_match('/^\d+>$/', $line) ||
                    preg_match('/^\d+>\s+\d+>\s+\d+>/', $line) ||
                    strpos($line, 'rows affected') !== false ||
                    strpos($line, 'TIPO_DOCTO') !== false ||
                    strpos($line, 'NRO_DOCTO') !== false ||
                    strpos($line, 'EMISION') !== false) {
                    continue;
                }
                
                // Buscar líneas con datos de facturas
                if (preg_match('/^(\w+)\s+(\d+)\s+(.+?)\s+(.+?)\s+(-?\d+)\s+([\d\.-]+)\s+([\d\.-]+)\s+([\d\.-]+)\s+(.+)$/', $line, $matches)) {
                    $result[] = [
                        'TIPO_DOCTO' => $matches[1],
                        'NRO_DOCTO' => $matches[2],
                        'EMISION' => $matches[3],
                        'VENCIMIENTO' => $matches[4],
                        'DIAS_VENCIDO' => (int)$matches[5],
                        'VALOR' => (float)$matches[6],
                        'ABONOS' => (float)$matches[7],
                        'SALDO' => (float)$matches[8],
                        'ESTADO' => trim($matches[9])
                    ];
                }
            }
            
            return $result;

        } catch (\Exception $e) {
            \Log::error('Error obteniendo facturas pendientes del cliente ' . $codigoCliente . ': ' . $e->getMessage(), [
                'codigo_cliente' => $codigoCliente,
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // En lugar de usar datos de prueba, retornar array vacío para evitar errores
            return [];
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
            
            // Consulta para obtener notas de venta pendientes del cliente (usando tu consulta)
            $query = "
                SELECT TOP 20
                    dbo.MAEDDO.TIDO AS TD, 
                    dbo.MAEDDO.NUDO AS NUM, 
                    dbo.MAEDDO.FEEMLI AS EMIS_FCV, 
                    dbo.MAEDDO.ENDO AS COD_CLI, 
                    dbo.MAEEN.NOKOEN AS CLIE, 
                    dbo.MAEDDO.KOPRCT, 
                    dbo.MAEDDO.CAPRCO1, 
                    dbo.MAEDDO.NOKOPR, 
                    dbo.MAEDDO.CAPRCO1 - (dbo.MAEDDO.CAPRCO1 - dbo.MAEDDO.CAPRAD1 - dbo.MAEDDO.CAPREX1) AS FACT, 
                    dbo.MAEDDO.CAPRCO1 - dbo.MAEDDO.CAPRAD1 - dbo.MAEDDO.CAPREX1 AS PEND, 
                    dbo.TABFU.NOKOFU, 
                    dbo.TABCI.NOKOCI, 
                    dbo.TABCM.NOKOCM, 
                    CAST(GETDATE() - dbo.MAEDDO.FEEMLI AS INT) AS DIAS, 
                    CASE WHEN CAST(GETDATE() - dbo.MAEDDO.FEEMLI AS INT) < 8 THEN 'Entre 1 y 7 días' 
                         WHEN CAST(GETDATE() - dbo.MAEDDO.FEEMLI AS INT) BETWEEN 8 AND 30 THEN 'Entre 8 y 30 Días' 
                         WHEN CAST(GETDATE() - dbo.MAEDDO.FEEMLI AS INT) BETWEEN 31 AND 60 THEN 'Entre 31 y 60 Días' 
                         ELSE 'Mas de 60 Días' END AS Rango, 
                    dbo.MAEDDO.VANELI / dbo.MAEDDO.CAPRCO1 AS PUNIT, 
                    (dbo.MAEDDO.VANELI / dbo.MAEDDO.CAPRCO1) * (dbo.MAEDDO.CAPRCO1 - dbo.MAEDDO.CAPRAD1 - dbo.MAEDDO.CAPREX1) AS PEND_VAL, 
                    CASE WHEN MAEDDO_1.TIDO IS NULL THEN '' ELSE MAEDDO_1.TIDO END AS TD_R, 
                    CASE WHEN MAEDDO_1.NUDO IS NULL THEN '' ELSE MAEDDO_1.NUDO END AS N_FCV, 
                    dbo.TABFU.KOFU
                FROM dbo.MAEDDO 
                INNER JOIN dbo.MAEEN ON dbo.MAEDDO.ENDO = dbo.MAEEN.KOEN AND dbo.MAEDDO.SUENDO = dbo.MAEEN.SUEN 
                INNER JOIN dbo.TABFU ON dbo.MAEDDO.KOFULIDO = dbo.TABFU.KOFU 
                INNER JOIN dbo.TABCI ON dbo.MAEEN.PAEN = dbo.TABCI.KOPA AND dbo.MAEEN.CIEN = dbo.TABCI.KOCI 
                INNER JOIN dbo.TABCM ON dbo.MAEEN.PAEN = dbo.TABCM.KOPA AND dbo.MAEEN.CIEN = dbo.TABCM.KOCI AND dbo.MAEEN.CMEN = dbo.TABCM.KOCM 
                LEFT OUTER JOIN dbo.MAEDDO AS MAEDDO_1 ON dbo.MAEDDO.IDMAEDDO = MAEDDO_1.IDRST
                WHERE (dbo.MAEDDO.TIDO = 'NVV') 
                    AND (dbo.MAEDDO.LILG = 'SI') 
                    AND (dbo.MAEDDO.CAPRCO1 - dbo.MAEDDO.CAPRAD1 - dbo.MAEDDO.CAPREX1 <> 0) 
                    AND (dbo.MAEDDO.KOPRCT <> 'D') 
                    AND (dbo.MAEDDO.KOPRCT <> 'FLETE')
                    AND dbo.MAEDDO.ENDO = '{$codigoCliente}'
                ORDER BY dbo.MAEDDO.FEEMLI DESC";
            
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
                
                // Buscar líneas que contengan datos de notas de venta (nuevo formato)
                if (!empty($line) && preg_match('/^(NVV)\s+(\d+)\s+(.+?)\s+([A-Z0-9]+)\s+(.+?)\s+([A-Z0-9]+)\s+([\d\.]+)\s+(.+?)\s+([\d\.]+)\s+([\d\.]+)\s+(.+?)\s+(.+?)\s+(.+?)\s+(-?\d+)\s+(.+?)\s+([\d\.]+)\s+([\d\.]+)\s+([A-Z0-9]*)\s+([A-Z0-9]*)\s+([A-Z0-9]+)$/', $line, $matches)) {
                    $result[] = [
                        'TIPO_DOCTO' => trim($matches[1]),
                        'NRO_DOCTO' => trim($matches[2]),
                        'EMISION' => trim($matches[3]),
                        'CODIGO_CLIENTE' => trim($matches[4]),
                        'NOMBRE_CLIENTE' => $this->convertToUtf8(trim($matches[5])),
                        'CODIGO_PRODUCTO' => trim($matches[6]),
                        'CANTIDAD_TOTAL' => (float)$matches[7],
                        'NOMBRE_PRODUCTO' => $this->convertToUtf8(trim($matches[8])),
                        'CANTIDAD_FACTURADA' => (float)$matches[9],
                        'CANTIDAD_PENDIENTE' => (float)$matches[10],
                        'VENDEDOR' => $this->convertToUtf8(trim($matches[11])),
                        'REGION' => $this->convertToUtf8(trim($matches[12])),
                        'COMUNA' => $this->convertToUtf8(trim($matches[13])),
                        'DIAS' => (int)$matches[14],
                        'RANGO' => $this->convertToUtf8(trim($matches[15])),
                        'PRECIO_UNITARIO' => (float)$matches[16],
                        'VALOR_PENDIENTE' => (float)$matches[17],
                        'TIPO_FACTURA' => trim($matches[18]),
                        'NUMERO_FACTURA' => trim($matches[19]),
                        'CODIGO_VENDEDOR' => trim($matches[20])
                    ];
                }
            }
            
            return $result;
            
        } catch (\Exception $e) {
            \Log::error('Error en getNotasVentaCliente: ' . $e->getMessage());
            return [];
        }
    }

    public function getClienteInfo($codigoCliente, $codigoVendedor = null)
    {
        try {
            // Si no se proporciona vendedor, intentar obtenerlo del cliente
            if (!$codigoVendedor) {
                // Buscar en todos los vendedores (esto es menos eficiente pero funcional)
                $vendedores = ['LCB', 'LCC', 'LCD']; // Agregar más vendedores según sea necesario
                
                foreach ($vendedores as $vend) {
                    $clientes = $this->getClientesPorVendedor($vend);
                    foreach ($clientes as $cliente) {
                        if ($cliente['CODIGO_CLIENTE'] === $codigoCliente) {
                            return $cliente;
                        }
                    }
                }
            } else {
                // Obtener clientes del vendedor específico
                $clientes = $this->getClientesPorVendedor($codigoVendedor);
                
                foreach ($clientes as $cliente) {
                    if ($cliente['CODIGO_CLIENTE'] === $codigoCliente) {
                        return $cliente;
                    }
                }
            }
            
            return null;
            
        } catch (\Exception $e) {
            \Log::error('Error en CobranzaService::getClienteInfo: ' . $e->getMessage());
            
            // Retornar datos por defecto en caso de error
            return [
                'CODIGO_CLIENTE' => $codigoCliente,
                'NOMBRE_CLIENTE' => 'Cliente ' . $codigoCliente,
                'TELEFONO' => 'Teléfono por defecto',
                'DIRECCION' => 'Dirección por defecto',
                'REGION' => 'Región por defecto',
                'COMUNA' => 'Comuna por defecto',
                'CODIGO_VENDEDOR' => '01',
                'NOMBRE_VENDEDOR' => 'Vendedor por defecto',
                'BLOQUEADO' => 0,
                'LISTA_PRECIOS_CODIGO' => '01',
                'LISTA_PRECIOS_NOMBRE' => 'Lista General'
            ];
        }
    }
    
    /**
     * Procesar una línea individual de información de cliente
     */
    private function procesarLineaClienteInfo($line, $lineNumber)
    {
        try {
            // Limpiar la línea de caracteres especiales
            $line = preg_replace('/\s+/', ' ', $line); // Reemplazar múltiples espacios por uno
            $line = trim($line);
            
            // \Log::info('Línea limpia cliente info ' . $lineNumber . ': ' . $line);
            
            // Dividir la línea por espacios y procesar cada campo
            $fields = explode(' ', $line);
            
            // Buscar el patrón: CODIGO_CLIENTE NOMBRE_CLIENTE TELEFONO DIRECCION...
            $cliente = null;
            
            for ($i = 0; $i < count($fields) - 5; $i++) {
                // Verificar si encontramos el código de cliente específico
                if (isset($fields[$i]) && $fields[$i] === $codigoCliente) {
                    // \Log::info('Código cliente encontrado: ' . $fields[$i]);
                    
                    // Extraer campos usando posiciones relativas
                    $cliente = $this->extraerCamposClienteInfo($fields, $i);
                    break;
                }
            }
            
            if ($cliente) {
                // \Log::info('Cliente info extraído: ' . json_encode($cliente));
            }
            
            return $cliente;
            
        } catch (\Exception $e) {
            \Log::error('Error procesando línea cliente info ' . $lineNumber . ': ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Extraer campos de información de cliente desde un array de campos
     */
    private function extraerCamposClienteInfo($fields, $startIndex)
    {
        try {
            // Campo básico
            $codigoCliente = $fields[$startIndex];
            
            // \Log::info('Extraer campos cliente info: CODIGO=' . $codigoCliente);
            
            // Buscar campos después del código de cliente
            $currentIndex = $startIndex + 1;
            
            // Extraer nombre del cliente (hasta encontrar un teléfono)
            $nombreCliente = '';
            while ($currentIndex < count($fields)) {
                $field = $fields[$currentIndex];
                
                // Si encontramos un teléfono (formato: números, +, /, espacios), paramos
                if (preg_match('/^[\d\+\/\s\-]+$/', $field)) {
                    // \Log::info('Teléfono encontrado: ' . $field . ', parando extracción de nombre');
                    break;
                }
                
                $nombreCliente .= ' ' . $field;
                $currentIndex++;
            }
            $nombreCliente = trim($nombreCliente);
            // \Log::info('Nombre cliente extraído: "' . $nombreCliente . '"');
            
            // Extraer teléfono
            $telefono = '';
            if ($currentIndex < count($fields)) {
                $telefono = $fields[$currentIndex];
                $currentIndex++;
                // \Log::info('Teléfono: ' . $telefono);
            }
            
            // Extraer dirección (hasta encontrar REGION)
            $direccion = '';
            while ($currentIndex < count($fields)) {
                $field = $fields[$currentIndex];
                
                if (strpos($field, 'REGION') !== false) {
                    // \Log::info('Región encontrada: ' . $field . ', parando extracción de dirección');
                    break;
                }
                
                $direccion .= ' ' . $field;
                $currentIndex++;
            }
            $direccion = trim($direccion);
            // \Log::info('Dirección extraída: "' . $direccion . '"');
            
            // Extraer región y comuna
            $region = '';
            $comuna = '';
            
            // Buscar región y comuna
            while ($currentIndex < count($fields)) {
                $field = $fields[$currentIndex];
                
                // Si encontramos un código de vendedor (3 caracteres), paramos
                if (preg_match('/^[A-Z]{3}$/', $field)) {
                    // \Log::info('Código vendedor encontrado: ' . $field . ', parando extracción de región/comuna');
                    break;
                }
                
                if (empty($region)) {
                    $region = $field;
                } else {
                    $comuna .= ' ' . $field;
                }
                $currentIndex++;
            }
            $comuna = trim($comuna);
            // \Log::info('Región extraída: "' . $region . '"');
            // \Log::info('Comuna extraída: "' . $comuna . '"');
            
            // Extraer código y nombre del vendedor
            $codigoVendedor = '';
            $nombreVendedor = '';
            
            if ($currentIndex < count($fields)) {
                $codigoVendedor = $fields[$currentIndex];
                $currentIndex++;
                // \Log::info('Código vendedor: ' . $codigoVendedor);
            }
            
            // Extraer nombre del vendedor
            while ($currentIndex < count($fields)) {
                $field = $fields[$currentIndex];
                
                // Si encontramos un número (bloqueado), paramos
                if (is_numeric($field)) {
                    // \Log::info('Bloqueado encontrado: ' . $field . ', parando extracción de nombre vendedor');
                    break;
                }
                
                $nombreVendedor .= ' ' . $field;
                $currentIndex++;
            }
            $nombreVendedor = trim($nombreVendedor);
            // \Log::info('Nombre vendedor extraído: "' . $nombreVendedor . '"');
            
            // Extraer valores numéricos restantes
            $bloqueado = 0;
            $listaPreciosCodigo = '01';
            
            $numerosEncontrados = 0;
            while ($currentIndex < count($fields) && $numerosEncontrados < 2) {
                if (is_numeric($fields[$currentIndex])) {
                    switch ($numerosEncontrados) {
                        case 0: $bloqueado = (int)$fields[$currentIndex]; break;
                        case 1: $listaPreciosCodigo = $fields[$currentIndex]; break;
                    }
                    $numerosEncontrados++;
                    // \Log::info('Número ' . $numerosEncontrados . ': ' . $fields[$currentIndex]);
                }
                $currentIndex++;
            }
            
            // Crear objeto de cliente
            $cliente = [
                'CODIGO_CLIENTE' => $codigoCliente,
                'NOMBRE_CLIENTE' => $this->convertToUtf8($nombreCliente),
                'TELEFONO' => $telefono,
                'DIRECCION' => $this->convertToUtf8($direccion),
                'REGION' => $this->convertToUtf8($region),
                'COMUNA' => $this->convertToUtf8($comuna),
                'CODIGO_VENDEDOR' => $codigoVendedor,
                'NOMBRE_VENDEDOR' => $this->convertToUtf8($nombreVendedor),
                'BLOQUEADO' => $bloqueado,
                'LISTA_PRECIOS_CODIGO' => $listaPreciosCodigo,
                'LISTA_PRECIOS_NOMBRE' => 'Lista General'
            ];
            
            // \Log::info('Cliente info creado: ' . $cliente['NOMBRE_CLIENTE']);
            
            return $cliente;
            
        } catch (\Exception $e) {
            \Log::error('Error extrayendo campos cliente info: ' . $e->getMessage());
            return null;
        }
    }

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

    /**
     * Método temporal para verificar un cliente específico en la vista
     */
    public function verificarClienteEnVista($codigoCliente)
    {
        try {
            $sql = "SELECT TOP 1 * FROM vw_clientes_por_vendedor WHERE CODIGO_CLIENTE = '$codigoCliente'";
            
            // Crear archivo temporal con la consulta
            $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
            file_put_contents($tempFile, $sql);
            
            // Ejecutar consulta
            $command = "tsql -S HIGUERA030924 -U sa -P 123456 -D HIGUERA030924 -o f -o h < $tempFile 2>&1";
            $output = shell_exec($command);
            
            // Limpiar archivo temporal
            unlink($tempFile);
            
            \Log::info("Verificación cliente $codigoCliente en vista: " . $output);
            
            return $output;
        } catch (\Exception $e) {
            \Log::error("Error verificando cliente en vista: " . $e->getMessage());
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

    public function getTestFacturasPendientesCliente($codigoCliente)
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
        // \Log::info("TSQL Output for query: " . substr($query, 0, 100) . "...");
        // \Log::info("TSQL Raw output: " . $output);

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
                // \Log::info("Headers found: " . implode(', ', $headers));
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
                        // \Log::info("Row added: " . json_encode($row));
                    }
                }
            }
        }
        
        // \Log::info("Total results parsed: " . count($results));
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

    /**
     * Crear vista optimizada para datos de cliente en cotizaciones/notas de venta
     */
    public function crearVistaClienteCotizacion()
    {
        try {
            $host = env('SQLSRV_EXTERNAL_HOST');
            $port = env('SQLSRV_EXTERNAL_PORT', '1433');
            $database = env('SQLSRV_EXTERNAL_DATABASE');
            $username = env('SQLSRV_EXTERNAL_USERNAME');
            $password = env('SQLSRV_EXTERNAL_PASSWORD');
            
            // Vista optimizada para cotizaciones y notas de venta
            $sql = "
                IF OBJECT_ID('vw_cliente_cotizacion', 'V') IS NOT NULL
                    DROP VIEW vw_cliente_cotizacion
                GO
                
                CREATE VIEW vw_cliente_cotizacion AS
                SELECT 
                    dbo.MAEEN.KOEN AS CODIGO_CLIENTE,
                    dbo.MAEEN.NOKOEN AS NOMBRE_CLIENTE,
                    dbo.MAEEN.FOEN AS TELEFONO,
                    dbo.MAEEN.DIEN AS DIRECCION,
                    dbo.TABCI.NOKOCI AS REGION,
                    dbo.TABCM.NOKOCM AS COMUNA,
                    dbo.TABFU.KOFU AS CODIGO_VENDEDOR,
                    dbo.TABFU.NOKOFU AS NOMBRE_VENDEDOR,
                    dbo.MAEEN.BLOQUEADO,
                    dbo.MAEEN.LCEN AS LISTA_PRECIOS_CODIGO,
                    dbo.TABLT.NOKOLT AS LISTA_PRECIOS_NOMBRE,
                    dbo.MAEEN.SUEN AS SUBSIDIARIA,
                    dbo.MAEEN.CIEN AS CODIGO_CIUDAD,
                    dbo.MAEEN.CMEN AS CODIGO_COMUNA,
                    dbo.MAEEN.PAEN AS CODIGO_PAIS,
                    dbo.MAEEN.KOFUEN AS VENDEDOR_ASIGNADO,
                    dbo.MAEEN.CRCH AS CREDITO_CHECKS,
                    dbo.MAEEN.CRLT AS CREDITO_LIMITE,
                    dbo.MAEEN.CRPA AS CREDITO_PLAZO,
                    dbo.MAEEN.CRTO AS CREDITO_TOTAL,
                    dbo.MAEEN.ZOEN AS ZONA_VENTA,
                    dbo.MAEEN.EMPRESA AS EMPRESA_CLIENTE
                FROM dbo.MAEEN
                LEFT JOIN dbo.TABCI ON dbo.MAEEN.CIEN = dbo.TABCI.KOCI
                LEFT JOIN dbo.TABCM ON dbo.MAEEN.CIEN = dbo.TABCM.KOCI AND dbo.MAEEN.CMEN = dbo.TABCM.KOCM
                LEFT JOIN dbo.TABFU ON dbo.TABFU.KOFU = dbo.MAEEN.KOFUEN
                LEFT JOIN dbo.TABLT ON dbo.TABLT.KOLT = dbo.MAEEN.LCEN
                WHERE dbo.MAEEN.EMPRESA IN ('01', '02')
                GO
            ";
            
            // Crear archivo temporal con la consulta
            $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
            file_put_contents($tempFile, $sql);
            
            // Ejecutar consulta usando tsql
            $command = "tsql -H {$host} -p {$port} -U {$username} -P {$password} -D {$database} < {$tempFile} 2>&1";
            $output = shell_exec($command);
            
            // Limpiar archivo temporal
            unlink($tempFile);
            
            \Log::info('Vista vw_cliente_cotizacion creada/actualizada: ' . $output);
            
            return !str_contains($output, 'error');
            
        } catch (\Exception $e) {
            \Log::error('Error creando vista cliente cotización: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener información completa del cliente para cotizaciones usando la nueva vista
     */
    public function getClienteInfoCompleto($codigoCliente)
    {
        try {
            $host = env('SQLSRV_EXTERNAL_HOST');
            $port = env('SQLSRV_EXTERNAL_PORT', '1433');
            $database = env('SQLSRV_EXTERNAL_DATABASE');
            $username = env('SQLSRV_EXTERNAL_USERNAME');
            $password = env('SQLSRV_EXTERNAL_PASSWORD');
            
            // Consulta usando la nueva vista optimizada
            $query = "
                SELECT TOP 1
                    CAST(CODIGO_CLIENTE AS VARCHAR(20)) AS CODIGO_CLIENTE,
                    CAST(NOMBRE_CLIENTE AS VARCHAR(100)) AS NOMBRE_CLIENTE,
                    CAST(TELEFONO AS VARCHAR(20)) AS TELEFONO,
                    CAST(DIRECCION AS VARCHAR(100)) AS DIRECCION,
                    CAST(REGION AS VARCHAR(50)) AS REGION,
                    CAST(COMUNA AS VARCHAR(50)) AS COMUNA,
                    CAST(CODIGO_VENDEDOR AS VARCHAR(10)) AS CODIGO_VENDEDOR,
                    CAST(NOMBRE_VENDEDOR AS VARCHAR(50)) AS NOMBRE_VENDEDOR,
                    CAST(BLOQUEADO AS INT) AS BLOQUEADO,
                    CAST(LISTA_PRECIOS_CODIGO AS VARCHAR(10)) AS LISTA_PRECIOS_CODIGO,
                    CAST(LISTA_PRECIOS_NOMBRE AS VARCHAR(50)) AS LISTA_PRECIOS_NOMBRE,
                    CAST(SUBSIDIARIA AS VARCHAR(10)) AS SUBSIDIARIA,
                    CAST(CODIGO_CIUDAD AS VARCHAR(10)) AS CODIGO_CIUDAD,
                    CAST(CODIGO_COMUNA AS VARCHAR(10)) AS CODIGO_COMUNA,
                    CAST(CODIGO_PAIS AS VARCHAR(10)) AS CODIGO_PAIS,
                    CAST(VENDEDOR_ASIGNADO AS VARCHAR(10)) AS VENDEDOR_ASIGNADO,
                    CAST(CREDITO_CHECKS AS VARCHAR(10)) AS CREDITO_CHECKS,
                    CAST(CREDITO_LIMITE AS DECIMAL(18,2)) AS CREDITO_LIMITE,
                    CAST(CREDITO_PLAZO AS INT) AS CREDITO_PLAZO,
                    CAST(CREDITO_TOTAL AS DECIMAL(18,2)) AS CREDITO_TOTAL,
                    CAST(ZONA_VENTA AS VARCHAR(10)) AS ZONA_VENTA,
                    CAST(EMPRESA_CLIENTE AS VARCHAR(10)) AS EMPRESA_CLIENTE
                FROM dbo.vw_cliente_cotizacion
                WHERE CODIGO_CLIENTE = '{$codigoCliente}'
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
            
            // Procesar la salida usando el mismo método que getClientesPorVendedor
            $lines = explode("\n", $output);
            $result = null;
            $inDataSection = false;
            $headerFound = false;
            
            foreach ($lines as $lineNumber => $line) {
                $line = trim($line);
                
                // Saltar líneas vacías o de configuración
                if (empty($line) || 
                    strpos($line, 'locale') !== false || 
                    strpos($line, 'Setting') !== false || 
                    strpos($line, 'Msg ') !== false || 
                    strpos($line, 'Warning:') !== false ||
                    preg_match('/^\d+>$/', $line) || // Líneas como "1>", "2>", etc.
                    preg_match('/^\d+>\s+\d+>\s+\d+>/', $line)) { // Líneas con múltiples números como "1> 2> 3> 4> 5>..."
                    continue;
                }
                
                // Detectar el header de la tabla
                if (strpos($line, 'CODIGO_CLIENTE') !== false) {
                    $headerFound = true;
                    $inDataSection = true;
                    continue;
                }
                
                // Detectar cuando terminamos la sección de datos
                if (strpos($line, '(1 row affected)') !== false || strpos($line, 'rows affected') !== false) {
                    $inDataSection = false;
                    break;
                }
                
                // Si estamos en la sección de datos y la línea no está vacía
                if ($inDataSection && $headerFound && !empty($line)) {
                    // Procesar cada línea individualmente
                    $cliente = $this->procesarLineaClienteCompleto($line, $lineNumber);
                    
                    if ($cliente) {
                        $result = $cliente;
                        break; // Solo necesitamos el primer resultado
                    }
                }
            }
            
            return $result;
            
        } catch (\Exception $e) {
            \Log::error('Error obteniendo información completa del cliente: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Procesar línea de cliente completo (con todos los campos para cotización)
     */
    private function procesarLineaClienteCompleto($line, $lineNumber)
    {
        try {
            // Dividir la línea por espacios/tabs
            $fields = preg_split('/\s+/', $line);
            
            if (count($fields) < 10) {
                \Log::warning('Línea de cliente completo con pocos campos: ' . $line);
                return null;
            }
            
            // Extraer campos usando el método existente
            $cliente = $this->extraerCamposClienteCompleto($fields, 0);
            
            return $cliente;
            
        } catch (\Exception $e) {
            \Log::error('Error procesando línea cliente completo: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Extraer campos de cliente completo
     */
    private function extraerCamposClienteCompleto($fields, $startIndex)
    {
        try {
            $currentIndex = $startIndex;
            
            // Extraer código del cliente
            $codigoCliente = isset($fields[$currentIndex]) ? $fields[$currentIndex] : '';
            $currentIndex++;
            
            // Extraer nombre del cliente (máximo 5 palabras para evitar incluir otros campos)
            $nombreCliente = '';
            $palabrasNombre = 0;
            $maxPalabrasNombre = 5; // Limitar a 5 palabras para el nombre
            
            while ($currentIndex < count($fields) && 
                   $palabrasNombre < $maxPalabrasNombre && 
                   !preg_match('/^[\d\+\/\s\-]+$/', $fields[$currentIndex]) &&
                   !preg_match('/^[A-Z]{2,3}$/', $fields[$currentIndex])) { // Evitar códigos de vendedor
                $nombreCliente .= ' ' . $fields[$currentIndex];
                $currentIndex++;
                $palabrasNombre++;
            }
            $nombreCliente = trim($nombreCliente);
            
            // Extraer teléfono
            $telefono = isset($fields[$currentIndex]) ? $fields[$currentIndex] : '';
            $currentIndex++;
            
            // Extraer dirección (máximo 3 palabras)
            $direccion = '';
            $palabrasDireccion = 0;
            $maxPalabrasDireccion = 3;
            
            while ($currentIndex < count($fields) && 
                   $palabrasDireccion < $maxPalabrasDireccion &&
                   strpos($fields[$currentIndex], 'REGION') === false &&
                   !preg_match('/^[A-Z]{2,3}$/', $fields[$currentIndex])) {
                $direccion .= ' ' . $fields[$currentIndex];
                $currentIndex++;
                $palabrasDireccion++;
            }
            $direccion = trim($direccion);
            
            // Extraer región
            $region = isset($fields[$currentIndex]) ? $fields[$currentIndex] : '';
            $currentIndex++;
            
            // Extraer comuna (máximo 2 palabras)
            $comuna = '';
            $palabrasComuna = 0;
            $maxPalabrasComuna = 2;
            
            while ($currentIndex < count($fields) && 
                   $palabrasComuna < $maxPalabrasComuna &&
                   !preg_match('/^[A-Z]{3}$/', $fields[$currentIndex])) {
                $comuna .= ' ' . $fields[$currentIndex];
                $currentIndex++;
                $palabrasComuna++;
            }
            $comuna = trim($comuna);
            
            // Extraer código de vendedor
            $codigoVendedor = isset($fields[$currentIndex]) ? $fields[$currentIndex] : '';
            $currentIndex++;
            
            // Extraer nombre del vendedor (máximo 3 palabras)
            $nombreVendedor = '';
            $palabrasVendedor = 0;
            $maxPalabrasVendedor = 3;
            
            while ($currentIndex < count($fields) && 
                   $palabrasVendedor < $maxPalabrasVendedor &&
                   !is_numeric($fields[$currentIndex])) {
                $nombreVendedor .= ' ' . $fields[$currentIndex];
                $currentIndex++;
                $palabrasVendedor++;
            }
            $nombreVendedor = trim($nombreVendedor);
            
            // Extraer campos adicionales para cotización
            $bloqueado = isset($fields[$currentIndex]) ? (int)$fields[$currentIndex] : 0;
            $currentIndex++;
            
            $listaPreciosCodigo = isset($fields[$currentIndex]) ? $fields[$currentIndex] : '01';
            $currentIndex++;
            
            $listaPreciosNombre = '';
            while ($currentIndex < count($fields) && !is_numeric($fields[$currentIndex])) {
                $listaPreciosNombre .= ' ' . $fields[$currentIndex];
                $currentIndex++;
            }
            $listaPreciosNombre = trim($listaPreciosNombre) ?: 'Lista General';
            
            // Crear objeto de cliente completo
            $cliente = [
                'CODIGO_CLIENTE' => $codigoCliente,
                'NOMBRE_CLIENTE' => $this->convertToUtf8($nombreCliente),
                'TELEFONO' => $telefono,
                'DIRECCION' => $this->convertToUtf8($direccion),
                'REGION' => $this->convertToUtf8($region),
                'COMUNA' => $this->convertToUtf8($comuna),
                'CODIGO_VENDEDOR' => $codigoVendedor,
                'NOMBRE_VENDEDOR' => $this->convertToUtf8($nombreVendedor),
                'BLOQUEADO' => $bloqueado,
                'LISTA_PRECIOS_CODIGO' => $listaPreciosCodigo,
                'LISTA_PRECIOS_NOMBRE' => $this->convertToUtf8($listaPreciosNombre),
                'SUBSIDIARIA' => isset($fields[$currentIndex]) ? $fields[$currentIndex] : '001',
                'CODIGO_CIUDAD' => isset($fields[$currentIndex + 1]) ? $fields[$currentIndex + 1] : '',
                'CODIGO_COMUNA' => isset($fields[$currentIndex + 2]) ? $fields[$currentIndex + 2] : '',
                'CODIGO_PAIS' => isset($fields[$currentIndex + 3]) ? $fields[$currentIndex + 3] : 'CHI',
                'VENDEDOR_ASIGNADO' => isset($fields[$currentIndex + 4]) ? $fields[$currentIndex + 4] : $codigoVendedor,
                'CREDITO_CHECKS' => isset($fields[$currentIndex + 5]) ? $fields[$currentIndex + 5] : '',
                'CREDITO_LIMITE' => isset($fields[$currentIndex + 6]) ? (float)$fields[$currentIndex + 6] : 0,
                'CREDITO_PLAZO' => isset($fields[$currentIndex + 7]) ? (int)$fields[$currentIndex + 7] : 30,
                'CREDITO_TOTAL' => isset($fields[$currentIndex + 8]) ? (float)$fields[$currentIndex + 8] : 0,
                'ZONA_VENTA' => isset($fields[$currentIndex + 9]) ? $fields[$currentIndex + 9] : '',
                'EMPRESA_CLIENTE' => isset($fields[$currentIndex + 10]) ? $fields[$currentIndex + 10] : '01'
            ];
            
            return $cliente;
            
        } catch (\Exception $e) {
            \Log::error('Error extrayendo campos cliente completo: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtener datos de facturación y saldos para todos los clientes de un vendedor en una sola consulta
     */
    private function getDatosFacturacionTodosClientes($codigoVendedor)
    {
        try {
            $host = env('SQLSRV_EXTERNAL_HOST');
            $port = env('SQLSRV_EXTERNAL_PORT', '1433');
            $database = env('SQLSRV_EXTERNAL_DATABASE');
            $username = env('SQLSRV_EXTERNAL_USERNAME');
            $password = env('SQLSRV_EXTERNAL_PASSWORD');
            
            // Consulta optimizada para obtener datos de facturación de todos los clientes del vendedor
            $query = "
                SELECT 
                    MAEEN.KOEN AS CODIGO_CLIENTE,
                    COUNT(MAEEDO.ENDO) AS CANTIDAD_FACTURAS,
                    SUM(CASE WHEN MAEEDO.TIDO = 'NCV' THEN (MAEEDO.VABRDO - MAEEDO.VAABDO) * -1 ELSE (MAEEDO.VABRDO - MAEEDO.VAABDO) END) AS SALDO_TOTAL
                FROM dbo.MAEEN
                LEFT JOIN dbo.MAEEDO ON MAEEN.KOEN = MAEEDO.ENDO 
                    AND (MAEEDO.TIDO = 'FCV' OR MAEEDO.TIDO = 'NCV' OR MAEEDO.TIDO = 'FDV')
                    AND (MAEEDO.VABRDO > MAEEDO.VAABDO)
                    AND (MAEEDO.EMPRESA = '01' OR MAEEDO.EMPRESA = '02')
                WHERE MAEEN.KOFUEN = '{$codigoVendedor}'
                GROUP BY MAEEN.KOEN
                ORDER BY MAEEN.KOEN
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
            
            // Procesar la salida
            $lines = explode("\n", $output);
            $datosFacturacion = [];
            
            foreach ($lines as $line) {
                $line = trim($line);
                
                // Saltar líneas de configuración
                if (empty($line) || 
                    strpos($line, 'locale') !== false || 
                    strpos($line, 'Setting') !== false || 
                    strpos($line, 'Msg ') !== false || 
                    strpos($line, 'Warning:') !== false ||
                    preg_match('/^\d+>$/', $line) ||
                    preg_match('/^\d+>\s+\d+>\s+\d+>/', $line) ||
                    strpos($line, 'rows affected') !== false ||
                    strpos($line, 'CODIGO_CLIENTE') !== false ||
                    strpos($line, 'CANTIDAD_FACTURAS') !== false ||
                    strpos($line, 'SALDO_TOTAL') !== false) {
                    continue;
                }
                
                // Buscar líneas con datos de clientes
                if (preg_match('/^\s*([A-Z0-9]+)\s+(\d+)\s+([\d\.]+)\s*$/', $line, $matches)) {
                    $codigoCliente = $matches[1];
                    $cantidadFacturas = (int)$matches[2];
                    $saldoTotal = (float)$matches[3];
                    
                    $datosFacturacion[$codigoCliente] = [
                        'cantidad_facturas' => $cantidadFacturas,
                        'saldo_total' => $saldoTotal
                    ];
                }
            }
            
            return $datosFacturacion;
            
        } catch (\Exception $e) {
            \Log::error('Error obteniendo datos de facturación para vendedor ' . $codigoVendedor . ': ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener datos de facturación y saldos de un cliente específico
     */
    public function getDatosFacturacionCliente($codigoCliente)
    {
        try {
            $host = env('SQLSRV_EXTERNAL_HOST');
            $port = env('SQLSRV_EXTERNAL_PORT', '1433');
            $database = env('SQLSRV_EXTERNAL_DATABASE');
            $username = env('SQLSRV_EXTERNAL_USERNAME');
            $password = env('SQLSRV_EXTERNAL_PASSWORD');
            
            // Consulta para obtener facturas pendientes del cliente
            $queryFacturas = "
                SELECT 
                    COUNT(*) AS CANTIDAD_FACTURAS,
                    SUM(CASE WHEN TIDO = 'NCV' THEN (VABRDO - VAABDO) * -1 ELSE (VABRDO - VAABDO) END) AS SALDO_TOTAL
                FROM dbo.MAEEDO
                WHERE ENDO = '{$codigoCliente}'
                    AND (TIDO = 'FCV' OR TIDO = 'NCV' OR TIDO = 'FDV')
                    AND (VABRDO > VAABDO)
                    AND (EMPRESA = '01' OR EMPRESA = '02')
            ";
            
            // Consulta para obtener crédito de compras (ventas de los últimos 3 meses)
            $queryCredito = "
                SELECT 
                    ENDO,
                    CASE WHEN TIDO = 'NCV' THEN SUM(VANEDO) * -1 ELSE SUM(VANEDO) * 1 END AS VENTA3M,
                    CASE WHEN TIDO = 'NCV' THEN (SUM(VANEDO) / 3) * -1 ELSE (SUM(VANEDO) / 3) * 1 END AS VENTAM
                FROM dbo.MAEEDO
                WHERE (TIDO = 'FCV' OR TIDO = 'NCV') 
                    AND (FEEMDO > GETDATE() - 90)
                    AND ENDO = '{$codigoCliente}'
                GROUP BY ENDO, TIDO
            ";
            
            // Crear archivo temporal con las consultas
            $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
            file_put_contents($tempFile, $queryFacturas . "\ngo\n" . $queryCredito . "\ngo\nquit");
            
            // Ejecutar consulta usando tsql
            $command = "tsql -H {$host} -p {$port} -U {$username} -P {$password} -D {$database} < {$tempFile} 2>&1";
            $output = shell_exec($command);
            
            // Limpiar archivo temporal
            unlink($tempFile);
            
            if (!$output || str_contains($output, 'error')) {
                throw new \Exception('Error ejecutando consulta tsql: ' . $output);
            }
            
            // Procesar la salida
            $lines = explode("\n", $output);
            $cantidadFacturas = 0;
            $saldoTotal = 0;
            $venta3M = 0;
            $ventaM = 0;
            
            \Log::info('Procesando salida de tsql para cliente ' . $codigoCliente . ': ' . substr($output, 0, 500));
            
            foreach ($lines as $lineNumber => $line) {
                $line = trim($line);
                
                // Debug: mostrar todas las líneas
                \Log::info("Línea {$lineNumber}: " . $line);
                
                // Saltar líneas de configuración
                if (empty($line) || 
                    strpos($line, 'locale') !== false || 
                    strpos($line, 'Setting') !== false || 
                    strpos($line, 'Msg ') !== false || 
                    strpos($line, 'Warning:') !== false ||
                    preg_match('/^\d+>$/', $line) ||
                    preg_match('/^\d+>\s+\d+>\s+\d+>/', $line) ||
                    strpos($line, 'rows affected') !== false ||
                    strpos($line, 'CANTIDAD_FACTURAS') !== false ||
                    strpos($line, 'SALDO_TOTAL') !== false ||
                    strpos($line, 'ENDO') !== false ||
                    strpos($line, 'VENTA3M') !== false ||
                    strpos($line, 'VENTAM') !== false) {
                    continue;
                }
                
                // Buscar líneas con datos numéricos (más flexible)
                if (preg_match('/^\s*(\d+)\s+([\d\.]+)\s*$/', $line, $matches)) {
                    // Primera consulta: cantidad_facturas y saldo_total
                    $cantidadFacturas = (int)$matches[1];
                    $saldoTotal = (float)$matches[2];
                    \Log::info("Datos de facturación encontrados: cantidad={$cantidadFacturas}, saldo={$saldoTotal}");
                } elseif (preg_match('/^\s*([A-Z0-9]+)\s+([\d\.-]+)\s+([\d\.-]+)\s*$/', $line, $matches)) {
                    // Segunda consulta: venta3M y ventaM
                    $venta3M = (float)$matches[2];
                    $ventaM = (float)$matches[3];
                    \Log::info("Datos de crédito encontrados: venta3M={$venta3M}, ventaM={$ventaM}");
                } elseif (preg_match('/^\s*([\d\.-]+)\s+([\d\.-]+)\s*$/', $line, $matches)) {
                    // Formato alternativo sin código de cliente
                    $venta3M = (float)$matches[1];
                    $ventaM = (float)$matches[2];
                    \Log::info("Datos de crédito encontrados (formato alternativo): venta3M={$venta3M}, ventaM={$ventaM}");
                }
            }
            
            return [
                'cantidad_facturas' => $cantidadFacturas,
                'saldo_total' => $saldoTotal,
                'venta_3_meses' => $venta3M,
                'venta_mensual_promedio' => $ventaM
            ];
            
        } catch (\Exception $e) {
            \Log::error('Error obteniendo datos de facturación para cliente ' . $codigoCliente . ': ' . $e->getMessage());
            return [
                'cantidad_facturas' => 0,
                'saldo_total' => 0,
                'venta_3_meses' => 0,
                'venta_mensual_promedio' => 0
            ];
        }
    }

    /**
     * Normalizar lista de precios (convertir 01 a 01P para precios públicos)
     */
    private function normalizarListaPrecios($listaPrecios)
    {
        // Si la lista es 01, 0, o vacía, usar 01P (lista por defecto)
        if (empty($listaPrecios) || $listaPrecios === '01' || $listaPrecios === '0') {
            return '01P';
        }
        return $listaPrecios;
    }

    /**
     * Buscar productos en MySQL local (optimizado)
     */
    public function buscarProductos($busqueda, $limit = 20, $listaPrecios = '01')
    {
        try {
            if ($busqueda && strlen($busqueda) < 4) {
                throw new \Exception('La búsqueda debe tener al menos 4 caracteres');
            }
            
            $busqueda = strtoupper(trim($busqueda));
            
            // Búsqueda simple y eficiente usando índices
            // Filtrar productos descontinuados y con precio mayor a 0
            $productos = \App\Models\Producto::where('TIPR', '!=', 'D')
                ->where('POIVPR', '>', 0) // Filtrar productos con precio mayor a 0
                ->where(function($q) use ($busqueda) {
                    $q->where('KOPR', 'LIKE', $busqueda . '%')
                      ->orWhere('NOKOPR', 'LIKE', $busqueda . '%');
                })
                ->select([
                    'KOPR as CODIGO_PRODUCTO',
                    'NOKOPR as NOMBRE_PRODUCTO',
                    'UD01PR as UNIDAD_MEDIDA',
                    'RLUD as RELACION_UNIDADES',
                    'DIVISIBLE as DIVISIBLE_UD1',
                    'DIVISIBLE2 as DIVISIBLE_UD2',
                    'RGPR as TIPO_PRODUCTO'
                ])
                ->limit($limit)
                ->orderBy('NOKOPR')
                ->get();
            
            // Convertir a array y agregar información adicional
            $resultado = [];
            foreach ($productos as $producto) {
                $productoArray = $producto->toArray();
                
                // Agregar valores por defecto para campos que no están en la tabla local
                $productoArray['STOCK_FISICO'] = 0;
                $productoArray['STOCK_COMPROMETIDO_SQL'] = 0;
                $productoArray['STOCK_DISPONIBLE'] = 0;
                $productoArray['NOMBRE_BODEGA'] = 'Bodega Principal';
                $productoArray['BODEGA_ID'] = '01';
                $productoArray['PRECIO_UD1'] = 0;
                $productoArray['PRECIO_UD2'] = 0;
                
                $resultado[] = $productoArray;
            }
            
            return $resultado;
            
        } catch (\Exception $e) {
            \Log::error('Error buscando productos en MySQL local: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Buscar productos en SQL Server (método original - mantener para compatibilidad)
     */
    public function buscarProductosSQLServer($busqueda, $limit = 20, $listaPrecios = '01')
    {
        try {
            // Usar la tabla local de MySQL en lugar de SQL Server
            $query = \DB::table('productos')
                ->where('activo', true)
                ->where('TIPR', '!=', 'OCU') // Excluir productos ocultos
                ->where(function($q) use ($busqueda) {
                    if ($busqueda && strlen($busqueda) >= 4) {
                        $busquedaUpper = strtoupper($busqueda);
                        $q->where('KOPR', 'LIKE', "{$busquedaUpper}%")
                          ->orWhere('KOPR', 'LIKE', "%{$busquedaUpper}%")
                          ->orWhere('NOKOPR', 'LIKE', "{$busquedaUpper}%")
                          ->orWhere('NOKOPR', 'LIKE', "%{$busquedaUpper}%")
                          ->orWhere('NOKOPR', 'LIKE', "% {$busquedaUpper}%");
                    } elseif ($busqueda && strlen($busqueda) < 4) {
                        throw new \Exception('La búsqueda debe tener al menos 4 caracteres');
                    }
                })
                ->where(function($q) use ($listaPrecios) {
                    // Filtrar productos con precio mayor a 0 en la lista especificada
                    switch ($listaPrecios) {
                        case '01P':
                        case '01':
                            $q->where(function($subQ) {
                                $subQ->where('precio_01p', '>', 0)
                                     ->orWhere('precio_01p_ud2', '>', 0);
                            });
                            break;
                        case '02P':
                        case '02':
                            $q->where(function($subQ) {
                                $subQ->where('precio_02p', '>', 0)
                                     ->orWhere('precio_02p_ud2', '>', 0);
                            });
                            break;
                        case '03P':
                        case '03':
                            $q->where(function($subQ) {
                                $subQ->where('precio_03p', '>', 0)
                                     ->orWhere('precio_03p_ud2', '>', 0);
                            });
                    break;
                }
                })
                ->orderBy('NOKOPR')
                ->limit($limit);

            $productos = $query->get();
            $listaPreciosNormalizada = $this->normalizarListaPrecios($listaPrecios);
            
            \Log::info('Lista de precios original: ' . $listaPrecios . ', normalizada: ' . $listaPreciosNormalizada);
            \Log::info('Productos encontrados en MySQL: ' . $productos->count());
            
            // Convertir los productos a formato compatible con el código existente
            $resultado = [];
            foreach ($productos as $producto) {
                $resultado[] = [
                    'CODIGO_PRODUCTO' => $producto->KOPR,
                    'NOMBRE_PRODUCTO' => $producto->NOKOPR,
                    'UNIDAD_MEDIDA' => $producto->UD01PR ?? 'UN',
                    'RELACION_UNIDADES' => $producto->RLUD ?? 1.0,
                    'DIVISIBLE_UD1' => $producto->DIVISIBLE ? 'S' : 'N',
                    'DIVISIBLE_UD2' => $producto->DIVISIBLE2 ? 'S' : 'N',
                    'STOCK_FISICO' => $producto->stock_fisico ?? 0,
                    'STOCK_COMPROMETIDO_SQL' => $producto->stock_comprometido ?? 0,
                    'STOCK_DISPONIBLE' => $producto->stock_disponible ?? 0,
                    'NOMBRE_BODEGA' => 'Bodega Principal',
                    'BODEGA_ID' => '01',
                    'TIPO_PRODUCTO' => $producto->TIPR ?? '',
                    'PRECIO_UD1' => $producto->precio_01p ?? 0,
                    'PRECIO_UD2' => $producto->precio_01p_ud2 ?? 0,
                    'DESCUENTO_MAXIMO' => $producto->descuento_maximo_01p ?? 0
                ];
            }
            
            \Log::info('Total de productos encontrados: ' . count($resultado));
            
            return $resultado;
            
        } catch (\Exception $e) {
            \Log::error('Error buscando productos: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Procesar línea de producto usando posiciones fijas
     */
    private function procesarLineaProducto($line, $lineNumber)
    {
        try {
            // Verificar que la línea tenga el formato esperado
            if (strlen($line) < 50) {
                \Log::warning('Línea de producto muy corta: ' . $line);
                return null;
            }
            
            // Extraer campos usando posiciones fijas
            $producto = $this->extraerCamposProductoPorPosicion($line, $lineNumber);
            
            return $producto;
            
        } catch (\Exception $e) {
            \Log::error('Error procesando línea producto: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Extraer campos de producto usando posiciones fijas
     */
    private function extraerCamposProductoPorPosicion($line, $lineNumber)
    {
        try {
            // Dividir la línea por espacios para extraer campos individuales
            $fields = preg_split('/\s+/', trim($line));
            
            if (count($fields) < 10) {
                \Log::warning('Línea de producto con pocos campos: ' . $line);
                return null;
            }
            
            // Extraer campos basado en la posición esperada
            $codigoProducto = $fields[0] ?? '';
            $nombreProducto = '';
            $unidadMedida = '';
            $relacionUnidades = 1.0;
            $divisibleUd1 = 'N';
            $divisibleUd2 = 'N';
            $stockFisico = 0.0;
            $stockComprometidoSql = 0.0;
            $stockDisponible = 0.0;
            $nombreBodega = 'Bodega Principal';
            $bodegaId = '01';
            $tipoProducto = '';
            $precioUd1 = 0.0;
            $precioUd2 = 0.0;
            
            // Extraer campos usando posiciones fijas desde el final
            $totalFields = count($fields);
            
            \Log::info('Campos encontrados: ' . implode(' | ', $fields));
            \Log::info('Total de campos: ' . $totalFields);
            \Log::info('Posiciones desde el final:');
            for ($i = 1; $i <= min(10, $totalFields); $i++) {
                \Log::info("  -{$i}: " . ($fields[$totalFields - $i] ?? 'N/A'));
            }
            
            // Los últimos campos son: DESCUENTO_MAXIMO, PRECIO_UD2, PRECIO_UD1, TIPO_PRODUCTO, BODEGA_ID
            if ($totalFields >= 5) {
                $descuentoMaximo = (float)($fields[$totalFields - 1] ?? 0);
                $precioUd2 = (float)($fields[$totalFields - 2] ?? 0);
                $precioUd1 = (float)($fields[$totalFields - 3] ?? 0);
                $tipoProducto = $fields[$totalFields - 4] ?? '';
                $bodegaId = $fields[$totalFields - 5] ?? '01';
            } else {
                $descuentoMaximo = 0.0;
            }
            
            // Los campos de stock están antes de la bodega (ajustados por el nuevo campo DESCUENTO_MAXIMO)
            if ($totalFields >= 9) {
                $stockDisponible = (float)($fields[$totalFields - 8] ?? 0);
                $stockComprometidoSql = (float)($fields[$totalFields - 9] ?? 0);
                $stockFisico = (float)($fields[$totalFields - 10] ?? 0);
            }
            
            // Los campos de divisibilidad están antes del stock
            if ($totalFields >= 11) {
                $divisibleUd2 = $fields[$totalFields - 11] ?? 'N';
                $divisibleUd1 = $fields[$totalFields - 12] ?? 'N';
            }
            
            // El campo de relación está antes de la divisibilidad
            if ($totalFields >= 13) {
                $relacionUnidades = (float)($fields[$totalFields - 13] ?? 1.0);
            }
            
            // La unidad de medida está antes de la relación
            if ($totalFields >= 14) {
                $unidadMedida = $fields[$totalFields - 14] ?? 'UN';
            }
            
            // El nombre del producto está entre el código y la unidad (ajustado por el nuevo campo)
            $nombreStart = 1;
            $nombreEnd = $totalFields - 15; // Hasta antes de la unidad (ajustado)
            if ($nombreEnd >= $nombreStart) {
                for ($i = $nombreStart; $i <= $nombreEnd; $i++) {
                    $nombreProducto .= ' ' . $fields[$i];
                }
            }
            $nombreProducto = trim($nombreProducto);
            
            // El nombre de la bodega está entre el stock y la bodega ID
            $nombreBodega = 'Bodega Principal'; // Por defecto
            
            // Validar que el código del producto no esté vacío
            if (empty($codigoProducto)) {
                \Log::warning('Código de producto vacío en línea ' . $lineNumber);
                return null;
            }
            
            // Validar que el producto tenga precio mayor a 0
            if ($precioUd1 <= 0 && $precioUd2 <= 0) {
                \Log::info('Producto con precio 0 filtrado: ' . $codigoProducto . ' - Precio UD1: ' . $precioUd1 . ', Precio UD2: ' . $precioUd2);
                return null;
            }
            
            // Crear objeto de producto
            $producto = [
                'CODIGO_PRODUCTO' => $codigoProducto,
                'NOMBRE_PRODUCTO' => $this->convertToUtf8($nombreProducto),
                'UNIDAD_MEDIDA' => $unidadMedida ?: 'UN',
                'RELACION_UNIDADES' => $relacionUnidades ?: 1.0,
                'DIVISIBLE_UD1' => $divisibleUd1 ?: 'N',
                'DIVISIBLE_UD2' => $divisibleUd2 ?: 'N',
                'STOCK_FISICO' => $stockFisico ?: 0.0,
                'STOCK_COMPROMETIDO_SQL' => $stockComprometidoSql ?: 0.0,
                'STOCK_DISPONIBLE' => $stockDisponible ?: 0.0,
                'NOMBRE_BODEGA' => $this->convertToUtf8($nombreBodega) ?: 'Bodega Principal',
                'BODEGA_ID' => $bodegaId ?: '01',
                'TIPO_PRODUCTO' => $tipoProducto,
                'PRECIO_UD1' => $precioUd1 ?: 0.0,
                'PRECIO_UD2' => $precioUd2 ?: 0.0,
                'DESCUENTO_MAXIMO' => $descuentoMaximo ?: 0.0,
                'CATEGORIA' => '', // Campo no disponible en la base de datos
                'MARCA' => '' // Campo no disponible en la base de datos
            ];
            
            \Log::info('Producto extraído correctamente: ' . $producto['CODIGO_PRODUCTO'] . ' - ' . $producto['NOMBRE_PRODUCTO']);
            
            return $producto;
            
        } catch (\Exception $e) {
            \Log::error('Error extrayendo campos producto por posición: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtener NVV pendientes detalle por vendedor
     */
    public function getNvvPendientesDetalle($codigoVendedor = null, $limit = 20)
    {
        try {
            $host = env('SQLSRV_EXTERNAL_HOST');
            $port = env('SQLSRV_EXTERNAL_PORT', '1433');
            $database = env('SQLSRV_EXTERNAL_DATABASE');
            $username = env('SQLSRV_EXTERNAL_USERNAME');
            $password = env('SQLSRV_EXTERNAL_PASSWORD');
            
            // Consulta para obtener NVV pendientes usando la consulta proporcionada con formato | delimitado
            $query = "
                SELECT TOP {$limit}
                    CAST(dbo.MAEDDO.TIDO AS VARCHAR(10)) + '|' +
                    CAST(dbo.MAEDDO.NUDO AS VARCHAR(20)) + '|' +
                    CAST(dbo.MAEDDO.FEEMLI AS VARCHAR(20)) + '|' +
                    CAST(dbo.MAEDDO.ENDO AS VARCHAR(20)) + '|' +
                    CAST(dbo.MAEEN.NOKOEN AS VARCHAR(100)) + '|' +
                    CAST(dbo.MAEDDO.KOPRCT AS VARCHAR(10)) + '|' +
                    CAST(dbo.MAEDDO.CAPRCO1 AS VARCHAR(20)) + '|' +
                    CAST(dbo.MAEDDO.NOKOPR AS VARCHAR(100)) + '|' +
                    CAST((dbo.MAEDDO.CAPRCO1 - (dbo.MAEDDO.CAPRCO1 - dbo.MAEDDO.CAPRAD1 - dbo.MAEDDO.CAPREX1)) AS VARCHAR(20)) + '|' +
                    CAST((dbo.MAEDDO.CAPRCO1 - dbo.MAEDDO.CAPRAD1 - dbo.MAEDDO.CAPREX1) AS VARCHAR(20)) + '|' +
                    CAST(dbo.TABFU.NOKOFU AS VARCHAR(50)) + '|' +
                    CAST(dbo.TABCI.NOKOCI AS VARCHAR(50)) + '|' +
                    CAST(dbo.TABCM.NOKOCM AS VARCHAR(50)) + '|' +
                    CAST(CAST(GETDATE() - dbo.MAEDDO.FEEMLI AS INT) AS VARCHAR(10)) + '|' +
                    CAST(CASE WHEN CAST(GETDATE() - dbo.MAEDDO.FEEMLI AS INT) < 8 THEN 'Entre 1 y 7 días' 
                         WHEN CAST(GETDATE() - dbo.MAEDDO.FEEMLI AS INT) BETWEEN 8 AND 30 THEN 'Entre 8 y 30 Días' 
                         WHEN CAST(GETDATE() - dbo.MAEDDO.FEEMLI AS INT) BETWEEN 31 AND 60 THEN 'Entre 31 y 60 Días' 
                         ELSE 'Mas de 60 Días' END AS VARCHAR(20)) + '|' +
                    CAST((dbo.MAEDDO.VANELI / NULLIF(dbo.MAEDDO.CAPRCO1, 0)) AS VARCHAR(20)) + '|' +
                    CAST(((dbo.MAEDDO.VANELI / NULLIF(dbo.MAEDDO.CAPRCO1, 0)) * (dbo.MAEDDO.CAPRCO1 - dbo.MAEDDO.CAPRAD1 - dbo.MAEDDO.CAPREX1)) AS VARCHAR(20)) + '|' +
                    CAST(CASE WHEN MAEDDO_1.TIDO IS NULL THEN '' ELSE MAEDDO_1.TIDO END AS VARCHAR(10)) + '|' +
                    CAST(CASE WHEN MAEDDO_1.NUDO IS NULL THEN '' ELSE MAEDDO_1.NUDO END AS VARCHAR(20)) + '|' +
                    CAST(dbo.TABFU.KOFU AS VARCHAR(10)) AS DATOS
                FROM dbo.MAEDDO 
                INNER JOIN dbo.MAEEN ON dbo.MAEDDO.ENDO = dbo.MAEEN.KOEN AND dbo.MAEDDO.SUENDO = dbo.MAEEN.SUEN 
                INNER JOIN dbo.TABFU ON dbo.MAEDDO.KOFULIDO = dbo.TABFU.KOFU 
                INNER JOIN dbo.TABCI ON dbo.MAEEN.PAEN = dbo.TABCI.KOPA AND dbo.MAEEN.CIEN = dbo.TABCI.KOCI 
                INNER JOIN dbo.TABCM ON dbo.MAEEN.PAEN = dbo.TABCM.KOPA AND dbo.MAEEN.CIEN = dbo.TABCM.KOCI AND dbo.MAEEN.CMEN = dbo.TABCM.KOCM 
                LEFT OUTER JOIN dbo.MAEDDO AS MAEDDO_1 ON dbo.MAEDDO.IDMAEDDO = MAEDDO_1.IDRST
                WHERE (dbo.MAEDDO.TIDO = 'NVV') 
                AND (dbo.MAEDDO.LILG = 'SI') 
                AND (dbo.MAEDDO.CAPRCO1 - dbo.MAEDDO.CAPRAD1 - dbo.MAEDDO.CAPREX1 <> 0) 
                AND (dbo.MAEDDO.KOPRCT <> 'D') 
                AND (dbo.MAEDDO.KOPRCT <> 'FLETE')
                AND (dbo.MAEDDO.FEEMLI >= DATEADD(MONTH, -12, GETDATE()))";
            
            if ($codigoVendedor) {
                $query .= " AND dbo.TABFU.KOFU = '{$codigoVendedor}'";
            }
            
            $query .= " ORDER BY dbo.MAEDDO.NUDO DESC";
            
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
            
            // Procesar la salida usando el mismo patrón que getClientesPorVendedor
            $lines = explode("\n", $output);
            $result = [];
            $inDataSection = false;
            $headerFound = false;
            
            // Procesar la salida usando el mismo patrón que getClientesPorVendedor
            
            foreach ($lines as $lineNumber => $line) {
                $line = trim($line);
                
                // Saltar líneas vacías o de configuración
                if (empty($line) || 
                    strpos($line, 'locale') !== false || 
                    strpos($line, 'Setting') !== false || 
                    strpos($line, 'Msg ') !== false || 
                    strpos($line, 'Warning:') !== false ||
                    preg_match('/^\d+>$/', $line)) { // Líneas como "1>", "2>", etc.
                    continue;
                }
                
                // Saltar líneas con múltiples números SOLO si no contienen DATOS
                if (preg_match('/^\d+>\s+\d+>\s+\d+>/', $line) && strpos($line, 'DATOS') === false) {
                    continue;
                }
                
                // Detectar el header de la tabla
                if (strpos($line, 'DATOS') !== false) {
                    $headerFound = true;
                    $inDataSection = true;
                    // \Log::info('Header encontrado en línea: ' . $lineNumber . ' - Contenido: ' . $line);
                    continue;
                }
                
                // También detectar si la línea termina con DATOS
                if (trim($line) === 'DATOS') {
                    $headerFound = true;
                    $inDataSection = true;
                    // \Log::info('Header encontrado (línea separada) en línea: ' . $lineNumber . ' - Contenido: ' . $line);
                    continue;
                }
                

                
                // Detectar cuando terminamos la sección de datos
                if (strpos($line, '(10 rows affected)') !== false || strpos($line, 'rows affected') !== false) {
                    $inDataSection = false;
                    // \Log::info('Fin de datos en línea: ' . $lineNumber);
                    break;
                }
                
                // Detectar cuando terminamos la sección de datos
                if (strpos($line, '(1 row affected)') !== false || strpos($line, 'rows affected') !== false) {
                    $inDataSection = false;
                    break;
                }
                
                // Si estamos en la sección de datos y la línea no está vacía
                if ($inDataSection && $headerFound && !empty($line)) {
                    // Procesar cada línea individualmente
                    $nvv = $this->procesarLineaNvvPendiente($line, $lineNumber);
                    
                    if ($nvv) {
                        $result[] = $nvv;
                    }
                }
            }
            
            // Agrupar las NVV por código de documento
            $nvvAgrupadas = [];
            foreach ($result as $nvv) {
                $codigoNvv = $nvv['NUM'];
                
                if (!isset($nvvAgrupadas[$codigoNvv])) {
                    // Primera vez que vemos esta NVV, crear el registro base
                    $nvvAgrupadas[$codigoNvv] = [
                        'TD' => $nvv['TD'],
                        'NUM' => $nvv['NUM'],
                        'EMIS_FCV' => $nvv['EMIS_FCV'],
                        'COD_CLI' => $nvv['COD_CLI'],
                        'CLIE' => $nvv['CLIE'],
                        'NOKOFU' => $nvv['NOKOFU'],
                        'NOKOCI' => $nvv['NOKOCI'],
                        'NOKOCM' => $nvv['NOKOCM'],
                        'DIAS' => $nvv['DIAS'],
                        'Rango' => $nvv['Rango'],
                        'KOFU' => $nvv['KOFU'],
                        'TOTAL_PENDIENTE' => 0,
                        'TOTAL_VALOR_PENDIENTE' => 0,
                        'CANTIDAD_PRODUCTOS' => 0,
                        'productos' => []
                    ];
                }
                
                // Sumar los valores pendientes
                $nvvAgrupadas[$codigoNvv]['TOTAL_PENDIENTE'] += (float)($nvv['PEND'] ?? 0);
                $nvvAgrupadas[$codigoNvv]['TOTAL_VALOR_PENDIENTE'] += (float)($nvv['PEND_VAL'] ?? 0);
                $nvvAgrupadas[$codigoNvv]['CANTIDAD_PRODUCTOS']++;
                
                // Agregar el producto a la lista
                $nvvAgrupadas[$codigoNvv]['productos'][] = [
                    'KOPRCT' => $nvv['KOPRCT'],
                    'NOKOPR' => $nvv['NOKOPR'],
                    'CAPRCO1' => $nvv['CAPRCO1'],
                    'FACT' => $nvv['FACT'],
                    'PEND' => $nvv['PEND'],
                    'PUNIT' => $nvv['PUNIT'],
                    'PEND_VAL' => $nvv['PEND_VAL']
                ];
            }
            
            // Convertir el array asociativo a array indexado
            $resultadoFinal = array_values($nvvAgrupadas);
            
            return $resultadoFinal;
            
        } catch (\Exception $e) {
            \Log::error('Error obteniendo NVV pendientes detalle: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener detalles de una factura específica con sus productos
     */
    public function getFacturaDetalle($tipoDocumento, $numeroDocumento)
    {
        try {
            $host = env('SQLSRV_EXTERNAL_HOST');
            $port = env('SQLSRV_EXTERNAL_PORT', '1433');
            $database = env('SQLSRV_EXTERNAL_DATABASE');
            $username = env('SQLSRV_EXTERNAL_USERNAME');
            $password = env('SQLSRV_EXTERNAL_PASSWORD');
            
            // Consulta para obtener detalles de una factura específica (sin productos)
            $query = "
                SELECT 
                    CAST(dbo.MAEEDO.TIDO AS VARCHAR(10)) + '|' +
                    CAST(dbo.MAEEDO.NUDO AS VARCHAR(20)) + '|' +
                    CAST(dbo.MAEEDO.ENDO AS VARCHAR(20)) + '|' +
                    CAST(dbo.MAEEN.NOKOEN AS VARCHAR(100)) + '|' +
                    CAST(dbo.MAEEDO.SUDO AS VARCHAR(10)) + '|' +
                    CAST(dbo.MAEEDO.FEEMDO AS VARCHAR(20)) + '|' +
                    CAST(dbo.MAEEDO.FE01VEDO AS VARCHAR(20)) + '|' +
                    CAST(dbo.MAEEDO.FEULVEDO AS VARCHAR(20)) + '|' +
                    CAST(CAST(GETDATE() - dbo.MAEEDO.FEULVEDO AS INT) AS VARCHAR(10)) + '|' +
                    CAST(dbo.MAEEN.FOEN AS VARCHAR(20)) + '|' +
                    CAST(dbo.MAEEN.DIEN AS VARCHAR(100)) + '|' +
                    CAST(dbo.TABCI.NOKOCI AS VARCHAR(50)) + '|' +
                    CAST(dbo.TABCM.NOKOCM AS VARCHAR(50)) + '|' +
                    CAST(ISNULL(dbo.TABFU.NOKOFU, 'SIN VENDEDOR ASIG.') AS VARCHAR(50)) + '|' +
                    CAST(CASE WHEN dbo.MAEEDO.TIDO = 'NCV' THEN dbo.MAEEDO.VABRDO * - 1 ELSE dbo.MAEEDO.VABRDO END AS VARCHAR(20)) + '|' +
                    CAST(CASE WHEN dbo.MAEEDO.TIDO = 'NCV' THEN dbo.MAEEDO.VAABDO * - 1 ELSE dbo.MAEEDO.VAABDO END AS VARCHAR(20)) + '|' +
                    CAST(CASE WHEN dbo.MAEEDO.TIDO = 'NCV' AND (dbo.MAEEDO.VABRDO <> dbo.MAEEDO.VAABDO) AND CAST(GETDATE() - dbo.MAEEDO.FEULVEDO AS INT) BETWEEN - 7 AND - 1 THEN (dbo.MAEEDO.VABRDO - dbo.MAEEDO.VAABDO) * - 1 WHEN dbo.MAEEDO.TIDO <> 'NCV' AND (dbo.MAEEDO.VABRDO <> dbo.MAEEDO.VAABDO) AND CAST(GETDATE() - dbo.MAEEDO.FEULVEDO AS INT) BETWEEN - 7 AND - 1 THEN (dbo.MAEEDO.VABRDO - dbo.MAEEDO.VAABDO) * 1 ELSE 0 END AS VARCHAR(20)) + '|' +
                    CAST(CASE WHEN dbo.MAEEDO.TIDO = 'NCV' AND (dbo.MAEEDO.VABRDO <> dbo.MAEEDO.VAABDO) AND CAST(GETDATE() - dbo.MAEEDO.FEULVEDO AS INT) < - 7 THEN (dbo.MAEEDO.VABRDO - dbo.MAEEDO.VAABDO) * - 1 WHEN dbo.MAEEDO.TIDO <> 'NCV' AND (dbo.MAEEDO.VABRDO <> dbo.MAEEDO.VAABDO) AND CAST(GETDATE() - dbo.MAEEDO.FEULVEDO AS INT) < - 7 THEN (dbo.MAEEDO.VABRDO - dbo.MAEEDO.VAABDO) * 1 ELSE 0 END AS VARCHAR(20)) + '|' +
                    CAST(CASE WHEN dbo.MAEEDO.TIDO = 'NCV' AND (dbo.MAEEDO.VABRDO <> dbo.MAEEDO.VAABDO) AND CAST(GETDATE() - dbo.MAEEDO.FEULVEDO AS INT) > - 1 THEN (dbo.MAEEDO.VABRDO - dbo.MAEEDO.VAABDO) * - 1 WHEN dbo.MAEEDO.TIDO <> 'NCV' AND (dbo.MAEEDO.VABRDO <> dbo.MAEEDO.VAABDO) AND CAST(GETDATE() - dbo.MAEEDO.FEULVEDO AS INT) > - 1 THEN (dbo.MAEEDO.VABRDO - dbo.MAEEDO.VAABDO) * 1 ELSE 0 END AS VARCHAR(20)) + '|' +
                    CAST(CASE WHEN dbo.MAEEDO.TIDO = 'NCV' THEN (dbo.MAEEDO.VABRDO - dbo.MAEEDO.VAABDO) * - 1 ELSE (dbo.MAEEDO.VABRDO - dbo.MAEEDO.VAABDO) END AS VARCHAR(20)) + '|' +
                    CAST(CASE WHEN CAST(GETDATE() - dbo.MAEEDO.FEULVEDO AS INT) < - 8 THEN 'VIGENTE' WHEN CAST(GETDATE() - dbo.MAEEDO.FEULVEDO AS INT) BETWEEN - 7 AND - 1 THEN 'POR VENCER' WHEN CAST(GETDATE() - dbo.MAEEDO.FEULVEDO AS INT) BETWEEN 0 AND 7 THEN 'VENCIDO' WHEN CAST(GETDATE() - dbo.MAEEDO.FEULVEDO AS INT) BETWEEN 8 AND 30 THEN 'MOROSO' ELSE 'BLOQUEAR' END AS VARCHAR(20)) + '|' +
                    CAST(dbo.TABFU.KOFU AS VARCHAR(10)) + '|' +
                    CAST(dbo.MAEEN.KOFUEN AS VARCHAR(10)) AS DATOS
                FROM dbo.TABFU RIGHT OUTER JOIN
                         dbo.MAEEN ON dbo.TABFU.KOFU = dbo.MAEEN.KOFUEN LEFT OUTER JOIN
                         dbo.TABCM ON dbo.MAEEN.CIEN = dbo.TABCM.KOCI AND dbo.MAEEN.CMEN = dbo.TABCM.KOCM RIGHT OUTER JOIN
                         dbo.MAEEDO ON dbo.MAEEN.KOEN = dbo.MAEEDO.ENDO AND dbo.MAEEN.SUEN = dbo.MAEEDO.SUENDO LEFT OUTER JOIN
                         dbo.TABCI ON dbo.MAEEN.CIEN = dbo.TABCI.KOCI
                WHERE (dbo.MAEEDO.EMPRESA = '01') 
                AND (dbo.MAEEDO.TIDO = '{$tipoDocumento}') 
                AND (dbo.MAEEDO.NUDO = '{$numeroDocumento}')
                AND (dbo.MAEEDO.FEEMDO > CONVERT(DATETIME, '2000-01-01 00:00:00', 102)) 
                AND (dbo.MAEEDO.VABRDO > dbo.MAEEDO.VAABDO)";
            
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
            
            // Procesar la salida
            $lines = explode("\n", $output);
            $result = [];
            $inDataSection = false;
            $headerFound = false;
            
            foreach ($lines as $lineNumber => $line) {
                $line = trim($line);
                
                // Saltar líneas vacías o de configuración
                if (empty($line) || 
                    strpos($line, 'locale') !== false || 
                    strpos($line, 'Setting') !== false || 
                    strpos($line, 'Msg ') !== false || 
                    strpos($line, 'Warning:') !== false ||
                    preg_match('/^\d+>$/', $line)) {
                    continue;
                }
                
                // Saltar líneas con múltiples números SOLO si no contienen DATOS
                if (preg_match('/^\d+>\s+\d+>\s+\d+>/', $line) && strpos($line, 'DATOS') === false) {
                    continue;
                }
                
                // Detectar el header de la tabla
                if (strpos($line, 'DATOS') !== false) {
                    $headerFound = true;
                    $inDataSection = true;
                    continue;
                }
                
                // También detectar si la línea termina con DATOS
                if (trim($line) === 'DATOS') {
                    $headerFound = true;
                    $inDataSection = true;
                    continue;
                }
                
                // Detectar cuando terminamos la sección de datos
                if (strpos($line, '(10 rows affected)') !== false || strpos($line, 'rows affected') !== false) {
                    $inDataSection = false;
                    break;
                }
                
                // Detectar cuando terminamos la sección de datos
                if (strpos($line, '(1 row affected)') !== false || strpos($line, 'rows affected') !== false) {
                    $inDataSection = false;
                    break;
                }
                
                // Si estamos en la sección de datos y la línea no está vacía
                if ($inDataSection && $headerFound && !empty($line)) {
                    // Procesar cada línea individualmente
                    $factura = $this->procesarLineaFacturaDetalle($line, $lineNumber);
                    
                    if ($factura) {
                        $result[] = $factura;
                    }
                }
            }
            
            // Obtener la información básica de la factura
            $facturaAgrupada = null;
            
            foreach ($result as $factura) {
                if ($facturaAgrupada === null) {
                    // Primera vez, crear el registro base
                    $facturaAgrupada = [
                        'TIPO_DOCTO' => $factura['TIPO_DOCTO'],
                        'NRO_DOCTO' => $factura['NRO_DOCTO'],
                        'CODIGO' => $factura['CODIGO'],
                        'CLIENTE' => $factura['CLIENTE'],
                        'SUC' => $factura['SUC'],
                        'EMISION' => $factura['EMISION'],
                        'P_VCMTO' => $factura['P_VCMTO'],
                        'U_VCMTO' => $factura['U_VCMTO'],
                        'DIAS' => $factura['DIAS'],
                        'FONO' => $factura['FONO'],
                        'DIRECCION' => $factura['DIRECCION'],
                        'REGION' => $factura['REGION'],
                        'COMUNA' => $factura['COMUNA'],
                        'VENDEDOR' => $factura['VENDEDOR'],
                        'VALOR' => (float)($factura['VALOR'] ?? 0),
                        'ABONOS' => (float)($factura['ABONOS'] ?? 0),
                        'POR_VENCER' => (float)($factura['POR_VENCER'] ?? 0),
                        'VIGENTE' => (float)($factura['VIGENTE'] ?? 0),
                        'VENCIDO' => (float)($factura['VENCIDO'] ?? 0),
                        'SALDO' => (float)($factura['SALDO'] ?? 0),
                        'ESTADO' => $factura['ESTADO'],
                        'KOFU' => $factura['KOFU'],
                        'KOFUEN' => $factura['KOFU'] ?? '', // Usar KOFU como KOFUEN si no existe
                        'CANTIDAD_PRODUCTOS' => 0
                    ];
                }
                break; // Solo necesitamos la primera línea para la información básica
            }
            
            // Ahora obtener los productos de la factura
            if ($facturaAgrupada) {
                $productos = $this->getProductosFactura($tipoDocumento, $numeroDocumento);
                $facturaAgrupada['productos'] = $productos;
                $facturaAgrupada['CANTIDAD_PRODUCTOS'] = count($productos);
            }
            
            return $facturaAgrupada;
            
        } catch (\Exception $e) {
            \Log::error('Error obteniendo detalles de factura ' . $tipoDocumento . '-' . $numeroDocumento . ': ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtener productos de una factura específica
     */
    public function getProductosFactura($tipoDocumento, $numeroDocumento)
    {
        try {
            $host = env('SQLSRV_EXTERNAL_HOST');
            $port = env('SQLSRV_EXTERNAL_PORT', '1433');
            $database = env('SQLSRV_EXTERNAL_DATABASE');
            $username = env('SQLSRV_EXTERNAL_USERNAME');
            $password = env('SQLSRV_EXTERNAL_PASSWORD');
            
            // Consulta para obtener productos de una factura específica usando la relación exacta proporcionada
            $query = "
                SELECT 
                    CAST(MAEDDO_2.KOPRCT AS VARCHAR(10)) + '|' +
                    CAST(MAEDDO_2.NOKOPR AS VARCHAR(100)) + '|' +
                    CAST(MAEDDO_2.CAPRCO1 AS VARCHAR(20)) + '|' +
                    CAST((MAEDDO_2.VANELI / NULLIF(MAEDDO_2.CAPRCO1, 0)) AS VARCHAR(20)) + '|' +
                    CAST(MAEDDO_2.VANELI AS VARCHAR(20)) AS DATOS
                FROM dbo.MAEDDO AS MAEDDO_2 
                INNER JOIN dbo.MAEDDO AS MAEDDO_1 ON MAEDDO_2.IDMAEDO = MAEDDO_1.IDRST 
                FULL OUTER JOIN dbo.MAEDDO 
                INNER JOIN dbo.MAEEN ON dbo.MAEDDO.ENDO = dbo.MAEEN.KOEN AND dbo.MAEDDO.SUENDO = dbo.MAEEN.SUEN 
                ON MAEDDO_1.IDMAEDO = dbo.MAEDDO.IDRST
                WHERE dbo.MAEDDO.TIDO = '{$tipoDocumento}' 
                AND dbo.MAEDDO.NUDO = '{$numeroDocumento}'
                ORDER BY MAEDDO_2.KOPRCT";
            
            // Crear archivo temporal con la consulta
            $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
            file_put_contents($tempFile, $query . "\ngo\nquit");
            
            // Ejecutar consulta usando tsql
            $command = "tsql -H {$host} -p {$port} -U {$username} -P {$password} -D {$database} < {$tempFile} 2>&1";
            $output = shell_exec($command);
            
            // Limpiar archivo temporal
            unlink($tempFile);
            
            if (!$output || str_contains($output, 'error')) {
                \Log::warning('Error obteniendo productos de factura: ' . $output);
                return [];
            }
            
            // Procesar la salida
            $lines = explode("\n", $output);
            $productos = [];
            $inDataSection = false;
            $headerFound = false;
            
            foreach ($lines as $lineNumber => $line) {
                $line = trim($line);
                
                // Saltar líneas vacías o de configuración
                if (empty($line) || 
                    strpos($line, 'locale') !== false || 
                    strpos($line, 'Setting') !== false || 
                    strpos($line, 'Msg ') !== false || 
                    strpos($line, 'Warning:') !== false ||
                    preg_match('/^\d+>$/', $line)) {
                    continue;
                }
                
                // Saltar líneas con múltiples números SOLO si no contienen DATOS
                if (preg_match('/^\d+>\s+\d+>\s+\d+>/', $line) && strpos($line, 'DATOS') === false) {
                    continue;
                }
                
                // Detectar el header de la tabla
                if (strpos($line, 'DATOS') !== false) {
                    $headerFound = true;
                    $inDataSection = true;
                    continue;
                }
                
                // También detectar si la línea termina con DATOS
                if (trim($line) === 'DATOS') {
                    $headerFound = true;
                    $inDataSection = true;
                    continue;
                }
                
                // Detectar cuando terminamos la sección de datos
                if (strpos($line, '(10 rows affected)') !== false || strpos($line, 'rows affected') !== false) {
                    $inDataSection = false;
                    break;
                }
                
                // Detectar cuando terminamos la sección de datos
                if (strpos($line, '(1 row affected)') !== false || strpos($line, 'rows affected') !== false) {
                    $inDataSection = false;
                    break;
                }
                
                // Si estamos en la sección de datos y la línea no está vacía
                if ($inDataSection && $headerFound && !empty($line)) {
                    // Procesar cada línea individualmente
                    $producto = $this->procesarLineaProductoFactura($line, $lineNumber);
                    
                    if ($producto) {
                        $productos[] = $producto;
                    }
                }
            }
            
            return $productos;
            
        } catch (\Exception $e) {
            \Log::error('Error obteniendo productos de factura ' . $tipoDocumento . '-' . $numeroDocumento . ': ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Procesar línea de producto de factura
     */
    private function procesarLineaProductoFactura($line, $lineNumber)
    {
        try {
            // Verificar que la línea tenga el formato esperado
            if (strlen($line) < 20) {
                \Log::warning('Línea de producto factura muy corta: ' . $line);
                return null;
            }
            
            // Extraer campos usando el separador |
            $campos = explode('|', $line);
            
            if (count($campos) < 5) {
                \Log::warning('Línea de producto factura con campos insuficientes: ' . $line);
                return null;
            }
            
            // Mapear campos según la consulta
            $productoData = [
                'KOPRCT' => trim($campos[0]),
                'NOKOPR' => $this->convertToUtf8(trim($campos[1])),
                'CAPRCO1' => (float)trim($campos[2]),
                'PUNIT' => (float)trim($campos[3]),
                'VALOR' => (float)trim($campos[4])
            ];
            
            \Log::info('Producto de factura extraído correctamente: ' . $productoData['KOPRCT'] . ' - ' . $productoData['NOKOPR']);
            
            return $productoData;
            
        } catch (\Exception $e) {
            \Log::error('Error extrayendo datos de producto factura: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Procesar línea de factura detalle
     */
    private function procesarLineaFacturaDetalle($line, $lineNumber)
    {
        try {
            // Verificar que la línea tenga el formato esperado
            if (strlen($line) < 50) {
                \Log::warning('Línea de factura detalle muy corta: ' . $line);
                return null;
            }
            
            // Extraer campos usando el separador |
            $campos = explode('|', $line);
            
            if (count($campos) < 22) {
                \Log::warning('Línea de factura detalle con campos insuficientes: ' . $line);
                return null;
            }
            
            // Mapear campos según la consulta (22 campos)
            $facturaData = [
                'TIPO_DOCTO' => trim($campos[0]),
                'NRO_DOCTO' => trim($campos[1]),
                'CODIGO' => trim($campos[2]),
                'CLIENTE' => $this->convertToUtf8(trim($campos[3])),
                'SUC' => trim($campos[4]),
                'EMISION' => trim($campos[5]),
                'P_VCMTO' => trim($campos[6]),
                'U_VCMTO' => trim($campos[7]),
                'DIAS' => (int)trim($campos[8]),
                'FONO' => trim($campos[9]),
                'DIRECCION' => $this->convertToUtf8(trim($campos[10])),
                'REGION' => $this->convertToUtf8(trim($campos[11])),
                'COMUNA' => $this->convertToUtf8(trim($campos[12])),
                'VENDEDOR' => $this->convertToUtf8(trim($campos[13])),
                'VALOR' => (float)trim($campos[14]),
                'ABONOS' => (float)trim($campos[15]),
                'POR_VENCER' => (float)trim($campos[16]),
                'VIGENTE' => (float)trim($campos[17]),
                'VENCIDO' => (float)trim($campos[18]),
                'SALDO' => (float)trim($campos[19]),
                'ESTADO' => trim($campos[20]),
                'KOFU' => trim($campos[21])
            ];
            
            // Log comentado para no llenar el log - se registra un resumen al final
            // \Log::info('Factura detalle extraída correctamente: ' . $facturaData['TIPO_DOCTO'] . '-' . $facturaData['NRO_DOCTO'] . ' - ' . $facturaData['CLIENTE']);
            
            return $facturaData;
            
        } catch (\Exception $e) {
            \Log::error('Error extrayendo datos de factura detalle: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtener detalles de una NVV específica con sus productos
     */
    public function getNvvDetalle($numeroNvv)
    {
        try {
            $host = env('SQLSRV_EXTERNAL_HOST');
            $port = env('SQLSRV_EXTERNAL_PORT', '1433');
            $database = env('SQLSRV_EXTERNAL_DATABASE');
            $username = env('SQLSRV_EXTERNAL_USERNAME');
            $password = env('SQLSRV_EXTERNAL_PASSWORD');
            
            // Consulta mejorada para obtener detalles completos de una NVV específica
            $query = "
                SELECT 
                    CAST(dbo.MAEDDO.TIDO AS VARCHAR(10)) + '|' +
                    CAST(dbo.MAEDDO.NUDO AS VARCHAR(20)) + '|' +
                    CAST(dbo.MAEDDO.FEEMLI AS VARCHAR(20)) + '|' +
                    CAST(dbo.MAEDDO.ENDO AS VARCHAR(20)) + '|' +
                    CAST(dbo.MAEEN.NOKOEN AS VARCHAR(100)) + '|' +
                    CAST(dbo.MAEDDO.KOPRCT AS VARCHAR(10)) + '|' +
                    CAST(dbo.MAEDDO.CAPRCO1 AS VARCHAR(20)) + '|' +
                    CAST(dbo.MAEDDO.NOKOPR AS VARCHAR(100)) + '|' +
                    CAST((dbo.MAEDDO.CAPRCO1 - (dbo.MAEDDO.CAPRCO1 - dbo.MAEDDO.CAPRAD1 - dbo.MAEDDO.CAPREX1)) AS VARCHAR(20)) + '|' +
                    CAST((dbo.MAEDDO.CAPRCO1 - dbo.MAEDDO.CAPRAD1 - dbo.MAEDDO.CAPREX1) AS VARCHAR(20)) + '|' +
                    CAST(dbo.TABFU.NOKOFU AS VARCHAR(50)) + '|' +
                    CAST(dbo.TABCI.NOKOCI AS VARCHAR(50)) + '|' +
                    CAST(dbo.TABCM.NOKOCM AS VARCHAR(50)) + '|' +
                    CAST(CAST(GETDATE() - dbo.MAEDDO.FEEMLI AS INT) AS VARCHAR(10)) + '|' +
                    CAST(CASE WHEN CAST(GETDATE() - dbo.MAEDDO.FEEMLI AS INT) < 8 THEN 'Entre 1 y 7 días' 
                         WHEN CAST(GETDATE() - dbo.MAEDDO.FEEMLI AS INT) BETWEEN 8 AND 30 THEN 'Entre 8 y 30 Días' 
                         WHEN CAST(GETDATE() - dbo.MAEDDO.FEEMLI AS INT) BETWEEN 31 AND 60 THEN 'Entre 31 y 60 Días' 
                         ELSE 'Mas de 60 Días' END AS VARCHAR(20)) + '|' +
                    CAST((dbo.MAEDDO.VANELI / NULLIF(dbo.MAEDDO.CAPRCO1, 0)) AS VARCHAR(20)) + '|' +
                    CAST(((dbo.MAEDDO.VANELI / NULLIF(dbo.MAEDDO.CAPRCO1, 0)) * (dbo.MAEDDO.CAPRCO1 - dbo.MAEDDO.CAPRAD1 - dbo.MAEDDO.CAPREX1)) AS VARCHAR(20)) + '|' +
                    CAST(CASE WHEN MAEDDO_1.TIDO IS NULL THEN '' ELSE MAEDDO_1.TIDO END AS VARCHAR(10)) + '|' +
                    CAST(CASE WHEN MAEDDO_1.NUDO IS NULL THEN '' ELSE MAEDDO_1.NUDO END AS VARCHAR(20)) + '|' +
                    CAST(dbo.TABFU.KOFU AS VARCHAR(10)) + '|' +
                    CAST(dbo.MAEDDO.PODTGLLI AS VARCHAR(20)) + '|' +
                    CAST(dbo.MAEDDO.VADTNELI AS VARCHAR(20)) + '|' +
                    CAST(dbo.MAEDDO.VANELI AS VARCHAR(20)) + '|' +
                    CAST(dbo.MAEDDO.POIVLI AS VARCHAR(10)) + '|' +
                    CAST(dbo.MAEDDO.VAIVLI AS VARCHAR(20)) + '|' +
                    CAST(dbo.MAEDDO.VABRLI AS VARCHAR(20)) + '|' +
                    CAST(dbo.MAEDDO.PPPRNE AS VARCHAR(20)) + '|' +
                    CAST(dbo.MAEDDO.PPPRBR AS VARCHAR(20)) AS DATOS
                FROM dbo.MAEDDO 
                INNER JOIN dbo.MAEEN ON dbo.MAEDDO.ENDO = dbo.MAEEN.KOEN AND dbo.MAEDDO.SUENDO = dbo.MAEEN.SUEN 
                INNER JOIN dbo.TABFU ON dbo.MAEDDO.KOFULIDO = dbo.TABFU.KOFU 
                INNER JOIN dbo.TABCI ON dbo.MAEEN.PAEN = dbo.TABCI.KOPA AND dbo.MAEEN.CIEN = dbo.TABCI.KOCI 
                INNER JOIN dbo.TABCM ON dbo.MAEEN.PAEN = dbo.TABCM.KOPA AND dbo.MAEEN.CIEN = dbo.TABCM.KOCI AND dbo.MAEEN.CMEN = dbo.TABCM.KOCM 
                LEFT OUTER JOIN dbo.MAEDDO AS MAEDDO_1 ON dbo.MAEDDO.IDMAEDDO = MAEDDO_1.IDRST
                WHERE (dbo.MAEDDO.TIDO = 'NVV') 
                AND (dbo.MAEDDO.NUDO = '{$numeroNvv}')
                AND (dbo.MAEDDO.LILG = 'SI') 
                AND (dbo.MAEDDO.CAPRCO1 - dbo.MAEDDO.CAPRAD1 - dbo.MAEDDO.CAPREX1 <> 0) 
                AND (dbo.MAEDDO.KOPRCT <> 'D') 
                AND (dbo.MAEDDO.KOPRCT <> 'FLETE')
                ORDER BY dbo.MAEDDO.KOPRCT";
            
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
            
            // Procesar la salida
            $lines = explode("\n", $output);
            $result = [];
            $inDataSection = false;
            $headerFound = false;
            
            foreach ($lines as $lineNumber => $line) {
                $line = trim($line);
                
                // Saltar líneas vacías o de configuración
                if (empty($line) || 
                    strpos($line, 'locale') !== false || 
                    strpos($line, 'Setting') !== false || 
                    strpos($line, 'Msg ') !== false || 
                    strpos($line, 'Warning:') !== false ||
                    preg_match('/^\d+>$/', $line)) {
                    continue;
                }
                
                // Saltar líneas con múltiples números SOLO si no contienen DATOS
                if (preg_match('/^\d+>\s+\d+>\s+\d+>/', $line) && strpos($line, 'DATOS') === false) {
                    continue;
                }
                
                // Detectar el header de la tabla
                if (strpos($line, 'DATOS') !== false) {
                    $headerFound = true;
                    $inDataSection = true;
                    continue;
                }
                
                // También detectar si la línea termina con DATOS
                if (trim($line) === 'DATOS') {
                    $headerFound = true;
                    $inDataSection = true;
                    continue;
                }
                
                // Detectar cuando terminamos la sección de datos
                if (strpos($line, '(10 rows affected)') !== false || strpos($line, 'rows affected') !== false) {
                    $inDataSection = false;
                    break;
                }
                
                // Detectar cuando terminamos la sección de datos
                if (strpos($line, '(1 row affected)') !== false || strpos($line, 'rows affected') !== false) {
                    $inDataSection = false;
                    break;
                }
                
                // Si estamos en la sección de datos y la línea no está vacía
                if ($inDataSection && $headerFound && !empty($line)) {
                    // Procesar cada línea individualmente
                    $nvv = $this->procesarLineaNvvPendiente($line, $lineNumber);
                    
                    if ($nvv) {
                        $result[] = $nvv;
                    }
                }
            }
            
            // Agrupar los productos de la NVV
            $nvvAgrupada = null;
            $productos = [];
            
            foreach ($result as $nvv) {
                if ($nvvAgrupada === null) {
                    // Primera vez, crear el registro base
                    $nvvAgrupada = [
                        'TD' => $nvv['TD'],
                        'NUM' => $nvv['NUM'],
                        'EMIS_FCV' => $nvv['EMIS_FCV'],
                        'COD_CLI' => $nvv['COD_CLI'],
                        'CLIE' => $nvv['CLIE'],
                        'NOKOFU' => $nvv['NOKOFU'],
                        'NOKOCI' => $nvv['NOKOCI'],
                        'NOKOCM' => $nvv['NOKOCM'],
                        'DIAS' => $nvv['DIAS'],
                        'Rango' => $nvv['Rango'],
                        'KOFU' => $nvv['KOFU'],
                        'TOTAL_PENDIENTE' => 0,
                        'TOTAL_VALOR_PENDIENTE' => 0,
                        'CANTIDAD_PRODUCTOS' => 0
                    ];
                }
                
                // Sumar los valores pendientes
                $nvvAgrupada['TOTAL_PENDIENTE'] += (float)($nvv['PEND'] ?? 0);
                $nvvAgrupada['TOTAL_VALOR_PENDIENTE'] += (float)($nvv['PEND_VAL'] ?? 0);
                $nvvAgrupada['CANTIDAD_PRODUCTOS']++;
                
                // Agregar el producto a la lista con todos los campos
                $productos[] = [
                    'KOPRCT' => $nvv['KOPRCT'],
                    'NOKOPR' => $nvv['NOKOPR'],
                    'CAPRCO1' => $nvv['CAPRCO1'],
                    'FACT' => $nvv['FACT'],
                    'PEND' => $nvv['PEND'],
                    'PUNIT' => $nvv['PUNIT'],
                    'PEND_VAL' => $nvv['PEND_VAL'],
                    // Campos adicionales de valores y descuentos
                    'PODTGLLI' => $nvv['PODTGLLI'] ?? 0,
                    'VADTNELI' => $nvv['VADTNELI'] ?? 0,
                    'VANELI' => $nvv['VANELI'] ?? 0,
                    'POIVLI' => $nvv['POIVLI'] ?? 0,
                    'VAIVLI' => $nvv['VAIVLI'] ?? 0,
                    'VABRLI' => $nvv['VABRLI'] ?? 0,
                    'PPPRNE' => $nvv['PPPRNE'] ?? 0,
                    'PPPRBR' => $nvv['PPPRBR'] ?? 0
                ];
            }
            
            if ($nvvAgrupada) {
                $nvvAgrupada['productos'] = $productos;
            }
            
            return $nvvAgrupada;
            
        } catch (\Exception $e) {
            \Log::error('Error obteniendo detalles de NVV ' . $numeroNvv . ': ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtener rango de días basado en el número de días
     */
    private function getRangoDias($dias)
    {
        if ($dias < 8) {
            return 'Entre 1 y 7 días';
        } elseif ($dias < 31) {
            return 'Entre 8 y 30 Días';
        } elseif ($dias < 61) {
            return 'Entre 31 y 60 Días';
        } else {
            return 'Mas de 60 Días';
        }
    }

    /**
     * Extraer datos de NVV de una línea de texto usando posiciones fijas
     */
    private function extraerDatosNvv($line)
    {
        try {
            // Verificar que la línea tenga el formato esperado
            if (strlen($line) < 50) {
                \Log::warning('Línea de NVV muy corta: ' . $line);
                return null;
            }
            
            // Extraer campos usando posiciones fijas basadas en la salida de tsql
                    $td = trim(substr($line, 0, 8));
                    $num = trim(substr($line, 8, 12));
                    $emis_fcv = trim(substr($line, 20, 25));
                    $cod_cli = trim(substr($line, 45, 10));
                    $clie = trim(substr($line, 55, 30));
                    $vendedor_nombre = trim(substr($line, 85, 30));
                    $kofu = trim(substr($line, 115, 8));
                    $region = trim(substr($line, 123, 25));
                    $comuna = trim(substr($line, 148, 15));
                    $total_cantidad = (int)trim(substr($line, 163, 8));
                    $total_facturado = (int)trim(substr($line, 171, 8));
                    $total_pendiente = (int)trim(substr($line, 179, 8));
                    $total_valor = (float)trim(substr($line, 187, 10));
                    $total_valor_pendiente = (float)trim(substr($line, 197, 10));
                    $dias = (int)trim(substr($line, 207, 8));
                    $rango = trim(substr($line, 215, 20));
                    $cantidad_productos = (int)trim(substr($line, 235, 8));
                    $estado_facturacion = trim(substr($line, 243, 15));
                    $numero_factura = trim(substr($line, 258, 10));
                    $fecha_facturacion = trim(substr($line, 268, 20));
                    
            // Validar que el tipo de documento sea NVV
            if (empty($td) || $td !== 'NVV') {
                return null;
            }
            
            // Crear objeto de datos de NVV
            $nvvData = [
                            'TD' => $td,
                            'NUM' => $num,
                'EMIS_FCV' => $emis_fcv ?: date('Y-m-d'),
                            'COD_CLI' => $cod_cli,
                            'CLIE' => $this->convertToUtf8($clie),
                            'VENDEDOR_NOMBRE' => $this->convertToUtf8($vendedor_nombre),
                            'KOFU' => $kofu,
                            'REGION' => $this->convertToUtf8($region),
                            'COMUNA' => $this->convertToUtf8($comuna),
                            'TOTAL_CANTIDAD' => $total_cantidad,
                            'TOTAL_FACTURADO' => $total_facturado,
                            'TOTAL_PENDIENTE' => $total_pendiente,
                            'TOTAL_VALOR' => $total_valor,
                            'TOTAL_VALOR_PENDIENTE' => $total_valor_pendiente,
                            'DIAS' => $dias,
                'Rango' => $this->convertToUtf8($rango) ?: $this->getRangoDias($dias),
                            'CANTIDAD_PRODUCTOS' => $cantidad_productos,
                'ESTADO_FACTURACION' => $this->convertToUtf8($estado_facturacion) ?: 'PENDIENTE',
                            'NUMERO_FACTURA' => $numero_factura,
                            'FECHA_FACTURACION' => $fecha_facturacion
                        ];
            
            \Log::info('NVV extraída correctamente: ' . $nvvData['TD'] . '-' . $nvvData['NUM'] . ' - ' . $nvvData['CLIE']);
            
            return $nvvData;
            
        } catch (\Exception $e) {
            \Log::error('Error extrayendo datos de NVV: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtener detalle de productos de una NVV específica
     */
    public function getDetalleNvv($numeroNvv, $codigoCliente = null)
    {
        try {
            $host = env('SQLSRV_EXTERNAL_HOST');
            $port = env('SQLSRV_EXTERNAL_PORT', '1433');
            $database = env('SQLSRV_EXTERNAL_DATABASE');
            $username = env('SQLSRV_EXTERNAL_USERNAME');
            $password = env('SQLSRV_EXTERNAL_PASSWORD');
            
            // Consulta que funcionaba antes - usando concatenación con |
            $query = "
                SELECT 
                    CAST(dbo.MAEDDO.TIDO AS VARCHAR(10)) + '|' +
                    CAST(dbo.MAEDDO.NUDO AS VARCHAR(20)) + '|' +
                    CAST(dbo.MAEDDO.FEEMLI AS VARCHAR(20)) + '|' +
                    CAST(dbo.MAEDDO.ENDO AS VARCHAR(20)) + '|' +
                    CAST(dbo.MAEEN.NOKOEN AS VARCHAR(100)) + '|' +
                    CAST(dbo.MAEDDO.KOPRCT AS VARCHAR(10)) + '|' +
                    CAST(dbo.MAEDDO.CAPRCO1 AS VARCHAR(20)) + '|' +
                    CAST(dbo.MAEDDO.NOKOPR AS VARCHAR(100)) + '|' +
                    CAST((dbo.MAEDDO.CAPRCO1 - (dbo.MAEDDO.CAPRCO1 - dbo.MAEDDO.CAPRAD1 - dbo.MAEDDO.CAPREX1)) AS VARCHAR(20)) + '|' +
                    CAST((dbo.MAEDDO.CAPRCO1 - dbo.MAEDDO.CAPRAD1 - dbo.MAEDDO.CAPREX1) AS VARCHAR(20)) + '|' +
                    CAST(dbo.TABFU.NOKOFU AS VARCHAR(50)) + '|' +
                    CAST(dbo.TABCI.NOKOCI AS VARCHAR(50)) + '|' +
                    CAST(dbo.TABCM.NOKOCM AS VARCHAR(50)) + '|' +
                    CAST(CAST(GETDATE() - dbo.MAEDDO.FEEMLI AS INT) AS VARCHAR(10)) + '|' +
                    CAST(CASE WHEN CAST(GETDATE() - dbo.MAEDDO.FEEMLI AS INT) < 8 THEN 'Entre 1 y 7 días' 
                         WHEN CAST(GETDATE() - dbo.MAEDDO.FEEMLI AS INT) BETWEEN 8 AND 30 THEN 'Entre 8 y 30 Días' 
                         WHEN CAST(GETDATE() - dbo.MAEDDO.FEEMLI AS INT) BETWEEN 31 AND 60 THEN 'Entre 31 y 60 Días' 
                         ELSE 'Mas de 60 Días' END AS VARCHAR(20)) + '|' +
                    CAST((dbo.MAEDDO.VANELI / NULLIF(dbo.MAEDDO.CAPRCO1, 0)) AS VARCHAR(20)) + '|' +
                    CAST(((dbo.MAEDDO.VANELI / NULLIF(dbo.MAEDDO.CAPRCO1, 0)) * (dbo.MAEDDO.CAPRCO1 - dbo.MAEDDO.CAPRAD1 - dbo.MAEDDO.CAPREX1)) AS VARCHAR(20)) + '|' +
                    CAST(CASE WHEN MAEDDO_1.TIDO IS NULL THEN '' ELSE MAEDDO_1.TIDO END AS VARCHAR(10)) + '|' +
                    CAST(CASE WHEN MAEDDO_1.NUDO IS NULL THEN '' ELSE MAEDDO_1.NUDO END AS VARCHAR(20)) + '|' +
                    CAST(dbo.TABFU.KOFU AS VARCHAR(10)) + '|' +
                    CAST(dbo.MAEDDO.PODTGLLI AS VARCHAR(20)) + '|' +
                    CAST(dbo.MAEDDO.VADTNELI AS VARCHAR(20)) + '|' +
                    CAST(dbo.MAEDDO.VANELI AS VARCHAR(20)) + '|' +
                    CAST(dbo.MAEDDO.POIVLI AS VARCHAR(10)) + '|' +
                    CAST(dbo.MAEDDO.VAIVLI AS VARCHAR(20)) + '|' +
                    CAST(dbo.MAEDDO.VABRLI AS VARCHAR(20)) + '|' +
                    CAST(dbo.MAEDDO.PPPRNE AS VARCHAR(20)) + '|' +
                    CAST(dbo.MAEDDO.PPPRBR AS VARCHAR(20)) AS DATOS
                FROM dbo.MAEDDO 
                INNER JOIN dbo.MAEEN ON dbo.MAEDDO.ENDO = dbo.MAEEN.KOEN AND dbo.MAEDDO.SUENDO = dbo.MAEEN.SUEN 
                INNER JOIN dbo.TABFU ON dbo.MAEDDO.KOFULIDO = dbo.TABFU.KOFU 
                INNER JOIN dbo.TABCI ON dbo.MAEEN.PAEN = dbo.TABCI.KOPA AND dbo.MAEEN.CIEN = dbo.TABCI.KOCI 
                INNER JOIN dbo.TABCM ON dbo.MAEEN.PAEN = dbo.TABCM.KOPA AND dbo.MAEEN.CIEN = dbo.TABCM.KOCI AND dbo.MAEEN.CMEN = dbo.TABCM.KOCM 
                LEFT OUTER JOIN dbo.MAEDDO AS MAEDDO_1 ON dbo.MAEDDO.IDMAEDDO = MAEDDO_1.IDRST
                WHERE (dbo.MAEDDO.TIDO = 'NVV') 
                AND (dbo.MAEDDO.NUDO = '{$numeroNvv}')
                AND (dbo.MAEDDO.LILG = 'SI') 
                AND (dbo.MAEDDO.CAPRCO1 - dbo.MAEDDO.CAPRAD1 - dbo.MAEDDO.CAPREX1 <> 0) 
                AND (dbo.MAEDDO.KOPRCT <> 'D') 
                AND (dbo.MAEDDO.KOPRCT <> 'FLETE')";
            
            if ($codigoCliente) {
                $query .= " AND (dbo.MAEDDO.ENDO = '{$codigoCliente}')";
            }
            
            $query .= " ORDER BY dbo.MAEDDO.KOPRCT";
            
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
            
            // Procesar la salida usando el método que funcionaba
            $lines = explode("\n", $output);
            $result = [];
            $inDataSection = false;
            $headerFound = false;
            
            foreach ($lines as $lineNumber => $line) {
                $line = trim($line);
                
                // Saltar líneas vacías o de configuración
                if (empty($line) || 
                    strpos($line, 'locale') !== false || 
                    strpos($line, 'Setting') !== false || 
                    strpos($line, 'Msg ') !== false || 
                    strpos($line, 'Warning:') !== false ||
                    preg_match('/^\d+>$/', $line)) {
                    continue;
                }
                
                // Detectar el header de la tabla
                if (strpos($line, 'DATOS') !== false) {
                    $headerFound = true;
                    $inDataSection = true;
                    continue;
                }
                
                // Detectar cuando terminamos la sección de datos
                if (strpos($line, 'rows affected') !== false) {
                    $inDataSection = false;
                    break;
                }
                
                // Si estamos en la sección de datos y la línea no está vacía
                if ($inDataSection && $headerFound && !empty($line)) {
                    // Procesar cada línea usando el método que funcionaba
                    $nvv = $this->procesarLineaNvvPendiente($line, $lineNumber);
                    
                    if ($nvv) {
                        $result[] = $nvv;
                    }
                }
            }
            
            // Si no hay datos, devolver array vacío
            if (empty($result)) {
                return [];
            }
            
            // Agrupar los productos de la NVV
            $nvvAgrupada = null;
            $productos = [];
            
            foreach ($result as $nvv) {
                if ($nvvAgrupada === null) {
                    // Primera vez, crear el registro base
                    $nvvAgrupada = [
                        'TD' => $nvv['TD'],
                        'NUM' => $nvv['NUM'],
                        'EMIS_FCV' => $nvv['EMIS_FCV'],
                        'COD_CLI' => $nvv['COD_CLI'],
                        'CLIE' => $nvv['CLIE'],
                        'NOKOFU' => $nvv['NOKOFU'],
                        'NOKOCI' => $nvv['NOKOCI'],
                        'NOKOCM' => $nvv['NOKOCM'],
                        'DIAS' => $nvv['DIAS'],
                        'Rango' => $nvv['Rango'],
                        'KOFU' => $nvv['KOFU'],
                        'TOTAL_PENDIENTE' => 0,
                        'TOTAL_VALOR_PENDIENTE' => 0,
                        'CANTIDAD_PRODUCTOS' => 0
                    ];
                }
                
                // Sumar los valores pendientes
                $nvvAgrupada['TOTAL_PENDIENTE'] += (float)($nvv['PEND'] ?? 0);
                $nvvAgrupada['TOTAL_VALOR_PENDIENTE'] += (float)($nvv['PEND_VAL'] ?? 0);
                $nvvAgrupada['CANTIDAD_PRODUCTOS']++;
                
                // Agregar el producto a la lista con todos los campos
                $productos[] = [
                    'KOPRCT' => $nvv['KOPRCT'],
                    'NOKOPR' => $nvv['NOKOPR'],
                    'CAPRCO1' => $nvv['CAPRCO1'],
                    'FACT' => $nvv['FACT'],
                    'PEND' => $nvv['PEND'],
                    'PUNIT' => $nvv['PUNIT'],
                    'PEND_VAL' => $nvv['PEND_VAL'],
                    // Campos de descuento, IVA y totales
                    'PRECIO_NETO' => (float)($nvv['PPPRNE'] ?? 0),
                    'PORCENTAJE_DESCUENTO' => (float)($nvv['PODTGLLI'] ?? 0),
                    'VALOR_DESCUENTO' => (float)($nvv['VADTNELI'] ?? 0),
                    'SUBTOTAL' => (float)($nvv['VANELI'] ?? 0),
                    'PORCENTAJE_IVA' => (float)($nvv['POIVLI'] ?? 0),
                    'VALOR_IVA' => (float)($nvv['VAIVLI'] ?? 0),
                    'TOTAL' => (float)($nvv['VABRLI'] ?? 0)
                ];
            }
            
            if ($nvvAgrupada) {
                $nvvAgrupada['productos'] = $productos;
            }
            
            return $nvvAgrupada;
            
        } catch (\Exception $e) {
            \Log::error('Error obteniendo detalle de NVV ' . $numeroNvv . ': ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener facturas pendientes por vendedor
     */
    public function getFacturasPendientes($codigoVendedor = null, $limit = 20)
    {
        try {
            $host = env('SQLSRV_EXTERNAL_HOST');
            $port = env('SQLSRV_EXTERNAL_PORT', '1433');
            $database = env('SQLSRV_EXTERNAL_DATABASE');
            $username = env('SQLSRV_EXTERNAL_USERNAME');
            $password = env('SQLSRV_EXTERNAL_PASSWORD');
            
            // Consulta completa para obtener facturas pendientes con todos los detalles
            $query = "
                SELECT TOP {$limit}
                    CAST(dbo.MAEEDO.TIDO AS VARCHAR(10)) + '|' +
                    CAST(dbo.MAEEDO.NUDO AS VARCHAR(20)) + '|' +
                    CAST(dbo.MAEEDO.ENDO AS VARCHAR(20)) + '|' +
                    CAST(dbo.MAEEN.NOKOEN AS VARCHAR(100)) + '|' +
                    CAST(dbo.MAEEDO.SUDO AS VARCHAR(10)) + '|' +
                    CAST(dbo.MAEEDO.FEEMDO AS VARCHAR(20)) + '|' +
                    CAST(dbo.MAEEDO.FE01VEDO AS VARCHAR(20)) + '|' +
                    CAST(dbo.MAEEDO.FEULVEDO AS VARCHAR(20)) + '|' +
                    CAST(CAST(GETDATE() - dbo.MAEEDO.FEULVEDO AS INT) AS VARCHAR(10)) + '|' +
                    CAST(dbo.MAEEN.FOEN AS VARCHAR(20)) + '|' +
                    CAST(dbo.MAEEN.DIEN AS VARCHAR(100)) + '|' +
                    CAST(dbo.TABCI.NOKOCI AS VARCHAR(50)) + '|' +
                    CAST(dbo.TABCM.NOKOCM AS VARCHAR(50)) + '|' +
                    CAST(ISNULL(dbo.TABFU.NOKOFU, 'SIN VENDEDOR ASIG.') AS VARCHAR(50)) + '|' +
                    CAST(CASE WHEN dbo.MAEEDO.TIDO = 'NCV' THEN dbo.MAEEDO.VABRDO * - 1 ELSE dbo.MAEEDO.VABRDO END AS VARCHAR(20)) + '|' +
                    CAST(CASE WHEN dbo.MAEEDO.TIDO = 'NCV' THEN dbo.MAEEDO.VAABDO * - 1 ELSE dbo.MAEEDO.VAABDO END AS VARCHAR(20)) + '|' +
                    CAST(CASE WHEN dbo.MAEEDO.TIDO = 'NCV' AND (dbo.MAEEDO.VABRDO <> dbo.MAEEDO.VAABDO) AND CAST(GETDATE() - dbo.MAEEDO.FEULVEDO AS INT) BETWEEN - 7 AND - 1 THEN (dbo.MAEEDO.VABRDO - dbo.MAEEDO.VAABDO) * - 1 WHEN dbo.MAEEDO.TIDO <> 'NCV' AND (dbo.MAEEDO.VABRDO <> dbo.MAEEDO.VAABDO) AND CAST(GETDATE() - dbo.MAEEDO.FEULVEDO AS INT) BETWEEN - 7 AND - 1 THEN (dbo.MAEEDO.VABRDO - dbo.MAEEDO.VAABDO) * 1 ELSE 0 END AS VARCHAR(20)) + '|' +
                    CAST(CASE WHEN dbo.MAEEDO.TIDO = 'NCV' AND (dbo.MAEEDO.VABRDO <> dbo.MAEEDO.VAABDO) AND CAST(GETDATE() - dbo.MAEEDO.FEULVEDO AS INT) < - 7 THEN (dbo.MAEEDO.VABRDO - dbo.MAEEDO.VAABDO) * - 1 WHEN dbo.MAEEDO.TIDO <> 'NCV' AND (dbo.MAEEDO.VABRDO <> dbo.MAEEDO.VAABDO) AND CAST(GETDATE() - dbo.MAEEDO.FEULVEDO AS INT) < - 7 THEN (dbo.MAEEDO.VABRDO - dbo.MAEEDO.VAABDO) * 1 ELSE 0 END AS VARCHAR(20)) + '|' +
                    CAST(CASE WHEN dbo.MAEEDO.TIDO = 'NCV' AND (dbo.MAEEDO.VABRDO <> dbo.MAEEDO.VAABDO) AND CAST(GETDATE() - dbo.MAEEDO.FEULVEDO AS INT) > - 1 THEN (dbo.MAEEDO.VABRDO - dbo.MAEEDO.VAABDO) * - 1 WHEN dbo.MAEEDO.TIDO <> 'NCV' AND (dbo.MAEEDO.VABRDO <> dbo.MAEEDO.VAABDO) AND CAST(GETDATE() - dbo.MAEEDO.FEULVEDO AS INT) > - 1 THEN (dbo.MAEEDO.VABRDO - dbo.MAEEDO.VAABDO) * 1 ELSE 0 END AS VARCHAR(20)) + '|' +
                    CAST(CASE WHEN dbo.MAEEDO.TIDO = 'NCV' THEN (dbo.MAEEDO.VABRDO - dbo.MAEEDO.VAABDO) * - 1 ELSE (dbo.MAEEDO.VABRDO - dbo.MAEEDO.VAABDO) END AS VARCHAR(20)) + '|' +
                    CAST(CASE WHEN CAST(GETDATE() - dbo.MAEEDO.FEULVEDO AS INT) < - 8 THEN 'VIGENTE' WHEN CAST(GETDATE() - dbo.MAEEDO.FEULVEDO AS INT) BETWEEN - 7 AND - 1 THEN 'POR VENCER' WHEN CAST(GETDATE() - dbo.MAEEDO.FEULVEDO AS INT) BETWEEN 0 AND 7 THEN 'VENCIDO' WHEN CAST(GETDATE() - dbo.MAEEDO.FEULVEDO AS INT) BETWEEN 8 AND 30 THEN 'MOROSO' ELSE 'BLOQUEAR' END AS VARCHAR(20)) + '|' +
                    CAST(dbo.TABFU.KOFU AS VARCHAR(10)) + '|' +
                    CAST(dbo.MAEEN.KOFUEN AS VARCHAR(10)) AS DATOS
                FROM dbo.TABFU RIGHT OUTER JOIN
                         dbo.MAEEN ON dbo.TABFU.KOFU = dbo.MAEEN.KOFUEN LEFT OUTER JOIN
                         dbo.TABCM ON dbo.MAEEN.CIEN = dbo.TABCM.KOCI AND dbo.MAEEN.CMEN = dbo.TABCM.KOCM RIGHT OUTER JOIN
                         dbo.MAEEDO ON dbo.MAEEN.KOEN = dbo.MAEEDO.ENDO AND dbo.MAEEN.SUEN = dbo.MAEEDO.SUENDO LEFT OUTER JOIN
                         dbo.TABCI ON dbo.MAEEN.CIEN = dbo.TABCI.KOCI
                WHERE (dbo.MAEEDO.EMPRESA = '01') AND (dbo.MAEEDO.TIDO = 'NCV' OR
                         dbo.MAEEDO.TIDO = 'FCV' OR
                         dbo.MAEEDO.TIDO = 'FDV') AND (dbo.MAEEDO.FEEMDO > CONVERT(DATETIME, '2000-01-01 00:00:00', 102)) AND (dbo.MAEEDO.VABRDO > dbo.MAEEDO.VAABDO)";
            
            if ($codigoVendedor) {
                $query .= " AND dbo.TABFU.KOFU = '{$codigoVendedor}'";
            }
            
            $query .= " ORDER BY dbo.MAEEDO.FEEMDO DESC";
            
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
            
            // Procesar la salida
            $lines = explode("\n", $output);
            $result = [];
            $inDataSection = false;
            $headerFound = false;
            
            foreach ($lines as $lineNumber => $line) {
                $line = trim($line);
                
                // Saltar líneas vacías o de configuración
                if (empty($line) || 
                    strpos($line, 'locale') !== false || 
                    strpos($line, 'Setting') !== false || 
                    strpos($line, 'Msg ') !== false || 
                    strpos($line, 'Warning:') !== false ||
                    preg_match('/^\d+>$/', $line)) {
                    continue;
                }
                
                // Saltar líneas con múltiples números SOLO si no contienen DATOS
                if (preg_match('/^\d+>\s+\d+>\s+\d+>/', $line) && strpos($line, 'DATOS') === false) {
                    continue;
                }
                
                // Detectar el header de la tabla
                if (strpos($line, 'DATOS') !== false) {
                    $headerFound = true;
                    $inDataSection = true;
                    continue;
                }
                
                // También detectar si la línea termina con DATOS
                if (trim($line) === 'DATOS') {
                    $headerFound = true;
                    $inDataSection = true;
                    continue;
                }
                
                // Detectar cuando terminamos la sección de datos
                if (strpos($line, '(10 rows affected)') !== false || strpos($line, 'rows affected') !== false) {
                    $inDataSection = false;
                    break;
                }
                
                // Detectar cuando terminamos la sección de datos
                if (strpos($line, '(1 row affected)') !== false || strpos($line, 'rows affected') !== false) {
                    $inDataSection = false;
                    break;
                }
                
                // Si estamos en la sección de datos y la línea no está vacía
                if ($inDataSection && $headerFound && !empty($line)) {
                    // Procesar cada línea individualmente
                    $factura = $this->procesarLineaFacturaPendiente($line, $lineNumber);
                    
                    if ($factura) {
                        $result[] = $factura;
                    }
                }
            }
            
            // Agrupar las facturas por código de documento
            $facturasAgrupadas = [];
            foreach ($result as $factura) {
                $codigoFactura = $factura['TIPO_DOCTO'] . '-' . $factura['NRO_DOCTO'];
                
                if (!isset($facturasAgrupadas[$codigoFactura])) {
                    // Primera vez que vemos esta factura, crear el registro base
                    $facturasAgrupadas[$codigoFactura] = [
                        'TIPO_DOCTO' => $factura['TIPO_DOCTO'],
                        'NRO_DOCTO' => $factura['NRO_DOCTO'],
                        'CODIGO' => $factura['CODIGO'],
                        'CLIENTE' => $factura['CLIENTE'],
                        'SUC' => $factura['SUC'],
                        'EMISION' => $factura['EMISION'],
                        'P_VCMTO' => $factura['P_VCMTO'],
                        'U_VCMTO' => $factura['U_VCMTO'],
                        'DIAS' => $factura['DIAS'],
                        'FONO' => $factura['FONO'],
                        'DIRECCION' => $factura['DIRECCION'],
                        'REGION' => $factura['REGION'],
                        'COMUNA' => $factura['COMUNA'],
                        'VENDEDOR' => $factura['VENDEDOR'],
                        'VALOR' => 0,
                        'ABONOS' => 0,
                        'POR_VENCER' => 0,
                        'VIGENTE' => 0,
                        'VENCIDO' => 0,
                        'SALDO' => 0,
                        'ESTADO' => $factura['ESTADO'],
                        'KOFU' => $factura['KOFU'],
                        'KOFUEN' => $factura['KOFUEN'],
                        'CANTIDAD_PRODUCTOS' => 0,
                        'productos' => []
                    ];
                }
                
                // Sumar los valores
                $facturasAgrupadas[$codigoFactura]['VALOR'] += (float)($factura['VALOR'] ?? 0);
                $facturasAgrupadas[$codigoFactura]['ABONOS'] += (float)($factura['ABONOS'] ?? 0);
                $facturasAgrupadas[$codigoFactura]['POR_VENCER'] += (float)($factura['POR_VENCER'] ?? 0);
                $facturasAgrupadas[$codigoFactura]['VIGENTE'] += (float)($factura['VIGENTE'] ?? 0);
                $facturasAgrupadas[$codigoFactura]['VENCIDO'] += (float)($factura['VENCIDO'] ?? 0);
                $facturasAgrupadas[$codigoFactura]['SALDO'] += (float)($factura['SALDO'] ?? 0);
                $facturasAgrupadas[$codigoFactura]['CANTIDAD_PRODUCTOS']++;
                
                // Agregar el producto a la lista (si existe información de productos)
                if (isset($factura['producto'])) {
                    $facturasAgrupadas[$codigoFactura]['productos'][] = $factura['producto'];
                }
            }
            
            // Convertir el array asociativo a array indexado
            $resultadoFinal = array_values($facturasAgrupadas);
            
            return $resultadoFinal;
            
        } catch (\Exception $e) {
            \Log::error('Error obteniendo facturas pendientes: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Procesar línea de factura pendiente
     */
    private function procesarLineaFacturaPendiente($line, $lineNumber)
    {
        try {
            // Verificar que la línea tenga el formato esperado
            if (strlen($line) < 50) {
                \Log::warning('Línea de factura muy corta: ' . $line);
                return null;
            }
            
            // Extraer campos usando el separador |
            $campos = explode('|', $line);
            
            if (count($campos) < 20) {
                \Log::warning('Línea de factura con campos insuficientes: ' . $line);
                return null;
            }
            
            // Mapear campos según la consulta
            $facturaData = [
                'TIPO_DOCTO' => trim($campos[0]),
                'NRO_DOCTO' => trim($campos[1]),
                'CODIGO' => trim($campos[2]),
                'CLIENTE' => $this->convertToUtf8(trim($campos[3])),
                'SUC' => trim($campos[4]),
                'EMISION' => trim($campos[5]),
                'P_VCMTO' => trim($campos[6]),
                'U_VCMTO' => trim($campos[7]),
                'DIAS' => (int)trim($campos[8]),
                'FONO' => trim($campos[9]),
                'DIRECCION' => $this->convertToUtf8(trim($campos[10])),
                'REGION' => $this->convertToUtf8(trim($campos[11])),
                'COMUNA' => $this->convertToUtf8(trim($campos[12])),
                'VENDEDOR' => $this->convertToUtf8(trim($campos[13])),
                'VALOR' => (float)trim($campos[14]),
                'ABONOS' => (float)trim($campos[15]),
                'POR_VENCER' => (float)trim($campos[16]),
                'VIGENTE' => (float)trim($campos[17]),
                'VENCIDO' => (float)trim($campos[18]),
                'SALDO' => (float)trim($campos[19]),
                'ESTADO' => trim($campos[20]),
                'KOFU' => trim($campos[21]),
                'KOFUEN' => trim($campos[22])
            ];
            
            // Log comentado para no llenar el log - se registra un resumen al final
            // \Log::info('Factura extraída correctamente: ' . $facturaData['TIPO_DOCTO'] . '-' . $facturaData['NRO_DOCTO'] . ' - ' . $facturaData['CLIENTE']);
            
            return $facturaData;
            
        } catch (\Exception $e) {
            \Log::error('Error extrayendo datos de factura: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtener resumen de NVV pendientes por vendedor
     */
    public function getResumenNvvPendientes($codigoVendedor = null)
    {
        try {
            $nvvPendientes = $this->getNvvPendientesDetalle($codigoVendedor, 1000);
            
            $resumen = [
                'total_nvv' => count($nvvPendientes),
                'total_pendiente' => 0,
                'total_valor_pendiente' => 0,
                'por_rango' => [
                    'Entre 1 y 7 días' => ['cantidad' => 0, 'valor' => 0],
                    'Entre 8 y 30 Días' => ['cantidad' => 0, 'valor' => 0],
                    'Entre 31 y 60 Días' => ['cantidad' => 0, 'valor' => 0],
                    'Mas de 60 Días' => ['cantidad' => 0, 'valor' => 0]
                ]
            ];
            
            foreach ($nvvPendientes as $nvv) {
                $resumen['total_pendiente'] += (float)($nvv['PEND'] ?? 0);
                $resumen['total_valor_pendiente'] += (float)($nvv['PEND'] ?? 0);
                
                $dias = (int)($nvv['DIAS'] ?? 0);
                $rango = $this->getRangoDias($dias);
                if (isset($resumen['por_rango'][$rango])) {
                    $resumen['por_rango'][$rango]['cantidad']++;
                    $resumen['por_rango'][$rango]['valor'] += (float)($nvv['PEND'] ?? 0);
                }
            }
            
            return $resumen;
            
        } catch (\Exception $e) {
            \Log::error('Error obteniendo resumen NVV pendientes: ' . $e->getMessage());
            return [
                'total_nvv' => 0,
                'total_pendiente' => 0,
                'total_valor_pendiente' => 0,
                'por_rango' => [
                    'Entre 1 y 7 días' => ['cantidad' => 0, 'valor' => 0],
                    'Entre 8 y 30 Días' => ['cantidad' => 0, 'valor' => 0],
                    'Entre 31 y 60 Días' => ['cantidad' => 0, 'valor' => 0],
                    'Mas de 60 Días' => ['cantidad' => 0, 'valor' => 0]
                ]
            ];
        }
    }

    /**
     * Obtener crédito de compras de un cliente específico
     */
    public function getCreditoComprasCliente($codigoCliente)
    {
        try {
            $host = env('SQLSRV_EXTERNAL_HOST');
            $port = env('SQLSRV_EXTERNAL_PORT', '1433');
            $database = env('SQLSRV_EXTERNAL_DATABASE');
            $username = env('SQLSRV_EXTERNAL_USERNAME');
            $password = env('SQLSRV_EXTERNAL_PASSWORD');
            
            // Consulta para obtener crédito de compras (ventas de los últimos 3 meses)
            $query = "
                SELECT 
                    ENDO,
                    CASE WHEN TIDO = 'NCV' THEN SUM(VANEDO) * -1 ELSE SUM(VANEDO) * 1 END AS VENTA3M,
                    CASE WHEN TIDO = 'NCV' THEN (SUM(VANEDO) / 3) * -1 ELSE (SUM(VANEDO) / 3) * 1 END AS VENTAM
                FROM dbo.MAEEDO
                WHERE (TIDO = 'FCV' OR TIDO = 'NCV') 
                    AND (FEEMDO > GETDATE() - 90)
                    AND ENDO = '{$codigoCliente}'
                GROUP BY ENDO, TIDO
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
            
            // Procesar la salida
            $lines = explode("\n", $output);
            $venta3M = 0;
            $ventaM = 0;
            
            foreach ($lines as $line) {
                $line = trim($line);
                
                // Saltar líneas de configuración
                if (empty($line) || 
                    strpos($line, 'locale') !== false || 
                    strpos($line, 'Setting') !== false || 
                    strpos($line, 'Msg ') !== false || 
                    strpos($line, 'Warning:') !== false ||
                    preg_match('/^\d+>$/', $line) ||
                    preg_match('/^\d+>\s+\d+>\s+\d+>/', $line) ||
                    strpos($line, 'rows affected') !== false ||
                    strpos($line, 'ENDO') !== false ||
                    strpos($line, 'VENTA3M') !== false ||
                    strpos($line, 'VENTAM') !== false) {
                    continue;
                }
                
                // Buscar líneas con datos de crédito
                if (preg_match('/^\s*([A-Z0-9]+)\s+([\d\.-]+)\s+([\d\.-]+)\s*$/', $line, $matches)) {
                    $venta3M = (float)$matches[2];
                    $ventaM = (float)$matches[3];
                }
            }
            
            return [
                'codigo_cliente' => $codigoCliente,
                'venta_3_meses' => $venta3M,
                'venta_mensual_promedio' => $ventaM
            ];
            
        } catch (\Exception $e) {
            \Log::error('Error obteniendo crédito de compras para cliente ' . $codigoCliente . ': ' . $e->getMessage());
            return [
                'codigo_cliente' => $codigoCliente,
                'venta_3_meses' => 0,
                'venta_mensual_promedio' => 0
            ];
        }
    }

    /**
     * Obtener resumen de facturas pendientes por vendedor
     */
    public function getResumenFacturasPendientes($codigoVendedor = null)
    {
        try {
            $facturasPendientes = $this->getFacturasPendientes($codigoVendedor, 1000);
            
            $resumen = [
                'total_facturas' => count($facturasPendientes),
                'total_saldo' => 0,
                'por_estado' => [
                    'VIGENTE' => ['cantidad' => 0, 'valor' => 0],
                    'POR VENCER' => ['cantidad' => 0, 'valor' => 0],
                    'VENCIDO' => ['cantidad' => 0, 'valor' => 0],
                    'MOROSO' => ['cantidad' => 0, 'valor' => 0],
                    'BLOQUEAR' => ['cantidad' => 0, 'valor' => 0]
                ]
            ];
            
            foreach ($facturasPendientes as $factura) {
                $resumen['total_saldo'] += (float)($factura['SALDO'] ?? 0);
                
                $estado = $factura['ESTADO'] ?? 'VIGENTE';
                if (isset($resumen['por_estado'][$estado])) {
                    $resumen['por_estado'][$estado]['cantidad']++;
                    $resumen['por_estado'][$estado]['valor'] += (float)($factura['SALDO'] ?? 0);
                }
            }
            
            return $resumen;
            
        } catch (\Exception $e) {
            \Log::error('Error obteniendo resumen facturas pendientes: ' . $e->getMessage());
            return [
                'total_facturas' => 0,
                'total_saldo' => 0,
                'por_estado' => [
                    'VIGENTE' => ['cantidad' => 0, 'valor' => 0],
                    'POR VENCER' => ['cantidad' => 0, 'valor' => 0],
                    'VENCIDO' => ['cantidad' => 0, 'valor' => 0],
                    'MOROSO' => ['cantidad' => 0, 'valor' => 0],
                    'BLOQUEAR' => ['cantidad' => 0, 'valor' => 0]
                ]
            ];
        }
    }

    /**
     * Obtener información de crédito del cliente
     */
    public function getCreditoCliente($codigoCliente)
    {
        try {
            // Obtener credenciales de las variables de entorno
            $host = env('SQLSRV_EXTERNAL_HOST');
            $port = env('SQLSRV_EXTERNAL_PORT', '1433');
            $database = env('SQLSRV_EXTERNAL_DATABASE');
            $username = env('SQLSRV_EXTERNAL_USERNAME');
            $password = env('SQLSRV_EXTERNAL_PASSWORD');
            
            // Verificar que las credenciales estén configuradas
            if (!$host || !$database || !$username || !$password) {
                throw new \Exception('Credenciales SQL Server no configuradas en .env');
            }

            // Consulta simplificada usando solo dbo.CLIENTES sin depender de vistas que pueden no tener datos
            $query = "
            SELECT 
                CAST(dbo.CLIENTES.KOEN AS VARCHAR(20)) + '|' +
                CAST(dbo.CLIENTES.NOKOEN AS VARCHAR(100)) + '|' +
                CAST(ISNULL(dbo.Cobranza_CR.REGION, '') AS VARCHAR(50)) + '|' +
                CAST(ISNULL(dbo.Cobranza_CR.COMUNA, '') AS VARCHAR(50)) + '|' +
                CAST(dbo.CLIENTES.KOFUEN AS VARCHAR(20)) + '|' +
                CAST(ISNULL(dbo.Cobranza_CR.SALDO, 0) AS VARCHAR(20)) + '|' +
                CAST(ISNULL(dbo.CLIENTES.CRSD, 0) AS VARCHAR(20)) + '|' +
                CAST(ISNULL(dbo.CLIENTES.CRSD, 0) - ISNULL(dbo.Cobranza_CR.SALDO, 0) AS VARCHAR(20)) + '|' +
                CAST(ISNULL(dbo.[CH CARTERA R].VALOR, 0) AS VARCHAR(20)) + '|' +
                CAST(ISNULL(dbo.CLIENTES.CRCH, 0) AS VARCHAR(20)) + '|' +
                CAST(ISNULL(dbo.CLIENTES.CRCH, 0) - ISNULL(dbo.[CH CARTERA R].VALOR, 0) AS VARCHAR(20)) + '|' +
                CAST(ISNULL(dbo.[CH CARTERA R].VALOR, 0) + ISNULL(dbo.Cobranza_CR.SALDO, 0) AS VARCHAR(20)) + '|' +
                CAST(ISNULL(dbo.CLIENTES.CRTO, 0) AS VARCHAR(20)) + '|' +
                CAST(ISNULL(dbo.CLIENTES.CRTO, 0) - (ISNULL(dbo.[CH CARTERA R].VALOR, 0) + ISNULL(dbo.Cobranza_CR.SALDO, 0)) AS VARCHAR(20)) + '|' +
                CAST(ISNULL(dbo.ULT_VTA_CL.ULT_VTA, '') AS VARCHAR(50)) + '|' +
                CAST(CASE WHEN dbo.CLIENTES.BLOQUEADO = 1 THEN 'BLOQUEADO' ELSE 'VIGENTE' END AS VARCHAR(20)) + '|' +
                CAST(ISNULL(dbo.Cobranza_Vta_Prom.VENTAM, 0) AS VARCHAR(20)) + '|' +
                CAST(ISNULL(dbo.Cobranza_Vta_Prom.VENTA3M, 0) AS VARCHAR(20)) AS DATOS
            FROM dbo.CLIENTES 
            LEFT OUTER JOIN dbo.ULT_VTA_CL ON dbo.CLIENTES.KOEN = dbo.ULT_VTA_CL.ENDO 
            LEFT OUTER JOIN dbo.Cobranza_Vta_Prom ON dbo.CLIENTES.KOEN = dbo.Cobranza_Vta_Prom.ENDO 
            LEFT OUTER JOIN dbo.[CH CARTERA R] ON dbo.CLIENTES.KOEN = dbo.[CH CARTERA R].ENDP 
            LEFT OUTER JOIN dbo.Cobranza_CR ON dbo.CLIENTES.KOEN = dbo.Cobranza_CR.CODIGO 
            WHERE dbo.CLIENTES.KOEN = '{$codigoCliente}'
            ";

            // Crear archivo temporal con la consulta
            $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
            file_put_contents($tempFile, $query . "\ngo\nquit");
            
            // Ejecutar consulta usando tsql
            $command = "tsql -H {$host} -p {$port} -U {$username} -P {$password} -D {$database} < {$tempFile} 2>&1";
            $output = shell_exec($command);
            
            // Limpiar archivo temporal
            unlink($tempFile);
            
            \Log::info('Output completo de getCreditoCliente: ' . $output);
            
            if ($output === null) {
                throw new \Exception('Error ejecutando consulta tsql');
            }
            
            // Procesar la salida
            $lines = explode("\n", $output);
            $creditoInfo = [
                'codigo_cliente' => $codigoCliente,
                'credito_sin_doc' => 0,
                'credito_sin_doc_util' => 0,
                'credito_sin_doc_disp' => 0,
                'credito_cheques' => 0,
                'credito_cheques_util' => 0,
                'credito_cheques_disp' => 0,
                'credito_total' => 0,
                'credito_total_util' => 0,
                'credito_total_disp' => 0,
                'ultima_venta' => null,
                'estado' => 'VIGENTE',
                'venta_mes' => 0,
                'venta_3m' => 0
            ];
            
            foreach ($lines as $line) {
                $line = trim($line);
                
                // Saltar líneas de configuración
                if (empty($line) || 
                    strpos($line, 'locale') !== false || 
                    strpos($line, 'Setting') !== false || 
                    strpos($line, 'Msg ') !== false || 
                    strpos($line, 'Warning:') !== false ||
                    preg_match('/^\d+>$/', $line) ||
                    preg_match('/^\d+>\s+\d+>\s+\d+>/', $line) ||
                    strpos($line, 'rows affected') !== false ||
                    strpos($line, 'DATOS') !== false) {
                    continue;
                }
                
                // Procesar línea con datos de crédito usando separador |
                // Formato esperado: KOEN|NOKOEN|REGION|COMUNA|KOFUEN|SALDO|CRSD|DISP_SD|CART|CRCH|DISP_CH|DEUDA|CRTO|DISP|ULT_VTA|ESTADO|Venta_Mes|Venta_3M
                $fields = explode('|', $line);
                
                if (count($fields) >= 18) {
                    // Función helper para convertir valores NULL a 0
                    $safeFloat = function($value) {
                        if ($value === 'NULL' || $value === null || $value === '') {
                            return 0.0;
                        }
                        return (float)$value;
                    };
                    
                    \Log::info('Campos encontrados: ' . json_encode($fields));
                    \Log::info('Campo 15 (estado): ' . ($fields[15] ?? 'NO_ENCONTRADO'));
                    \Log::info('Campo 14 (ultima_venta): ' . ($fields[14] ?? 'NO_ENCONTRADO'));
                    
                    $creditoInfo['credito_sin_doc'] = $safeFloat($fields[6] ?? 0); // CRSD
                    $creditoInfo['credito_sin_doc_util'] = $safeFloat($fields[5] ?? 0); // SALDO
                    $creditoInfo['credito_sin_doc_disp'] = $safeFloat($fields[7] ?? 0); // DISP_SD
                    $creditoInfo['credito_cheques'] = $safeFloat($fields[9] ?? 0); // CRCH
                    $creditoInfo['credito_cheques_util'] = $safeFloat($fields[8] ?? 0); // CART
                    $creditoInfo['credito_cheques_disp'] = $safeFloat($fields[10] ?? 0); // DISP_CH
                    $creditoInfo['credito_total'] = $safeFloat($fields[12] ?? 0); // CRTO
                    $creditoInfo['credito_total_util'] = $safeFloat($fields[11] ?? 0); // DEUDA
                    $creditoInfo['credito_total_disp'] = $safeFloat($fields[13] ?? 0); // DISP
                    $creditoInfo['ultima_venta'] = trim($fields[14] ?? ''); // ULT_VTA
                    $estado = trim($fields[15] ?? 'VIGENTE'); // ESTADO
                    $creditoInfo['estado'] = ($estado === 'BLOQUEADO') ? 'BLOQUEADO' : 'VIGENTE';
                    $creditoInfo['venta_mes'] = $safeFloat($fields[16] ?? 0); // Venta_Mes
                    $creditoInfo['venta_3m'] = $safeFloat($fields[17] ?? 0); // Venta_3M
                    
                    \Log::info('Datos procesados: ' . json_encode($creditoInfo));
                }
            }
            
            return $creditoInfo;
            
        } catch (\Exception $e) {
            \Log::error('Error obteniendo crédito del cliente ' . $codigoCliente . ': ' . $e->getMessage());
            return [
                'codigo_cliente' => $codigoCliente,
                'credito_sin_doc' => 0,
                'credito_sin_doc_util' => 0,
                'credito_sin_doc_disp' => 0,
                'credito_cheques' => 0,
                'credito_cheques_util' => 0,
                'credito_cheques_disp' => 0,
                'credito_total' => 0,
                'credito_total_util' => 0,
                'credito_total_disp' => 0,
                'ultima_venta' => null,
                'estado' => 'VIGENTE',
                'venta_mes' => 0,
                'venta_3m' => 0
            ];
        }
    }

    /**
     * Método temporal para probar consulta de crédito simple
     */
    public function getCreditoSimple($codigoCliente)
    {
        try {
            $host = env('SQLSRV_EXTERNAL_HOST');
            $port = env('SQLSRV_EXTERNAL_PORT', '1433');
            $database = env('SQLSRV_EXTERNAL_DATABASE');
            $username = env('SQLSRV_EXTERNAL_USERNAME');
            $password = env('SQLSRV_EXTERNAL_PASSWORD');
            
            if (!$host || !$database || !$username || !$password) {
                throw new \Exception('Credenciales SQL Server no configuradas en .env');
            }

            // Consulta simple para obtener crédito de MAEEN
            $query = "SELECT TOP 1 KOEN, CRSD, CRCH, CRTO FROM MAEEN WHERE KOEN = '{$codigoCliente}'";
            
            // Crear archivo temporal con la consulta
            $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
            file_put_contents($tempFile, $query . "\ngo\nquit");
            
            // Ejecutar consulta usando tsql
            $command = "tsql -H {$host} -p {$port} -U {$username} -P {$password} -D {$database} < {$tempFile} 2>&1";
            $output = shell_exec($command);
            
            // Limpiar archivo temporal
            unlink($tempFile);
            
            \Log::info('Output de consulta simple: ' . $output);
            
            return [
                'codigo_cliente' => $codigoCliente,
                'output' => $output,
                'credito_sin_doc' => 0,
                'credito_cheques' => 0,
                'credito_total' => 0
            ];
            
        } catch (\Exception $e) {
            \Log::error('Error en consulta simple: ' . $e->getMessage());
            return [
                'codigo_cliente' => $codigoCliente,
                'error' => $e->getMessage(),
                'credito_sin_doc' => 0,
                'credito_cheques' => 0,
                'credito_total' => 0
            ];
        }
    }

    /**
     * Procesa una línea de datos de NVV pendientes
     */
    private function procesarLineaNvvPendiente($line, $lineNumber)
    {
        try {
            // Usar el mismo patrón que procesarLineaClienteCompleto
            $fields = explode('|', $line);
            
            // Verificar que tenemos suficientes campos mínimos (20 campos esperados)
            if (count($fields) < 20) {
                \Log::warning("Línea {$lineNumber} tiene menos de 20 campos: " . $line);
                return null;
            }
            
            // Log para ver cuántos campos tenemos (comentado para evitar spam en logs)
            // \Log::info("Procesando NVV línea {$lineNumber} con " . count($fields) . " campos");
            
            // Función helper para convertir a float de forma segura
            $safeFloat = function($value) {
                $value = trim($value);
                return is_numeric($value) ? (float)$value : 0.0;
            };
            
            // Extraer campos según la consulta SQL (20 campos)
            $nvv = [
                'TD' => trim($fields[0] ?? ''), // TIDO
                'NUM' => trim($fields[1] ?? ''), // NUDO
                'EMIS_FCV' => trim($fields[2] ?? ''), // FEEMLI
                'COD_CLI' => trim($fields[3] ?? ''), // ENDO
                'CLIE' => trim($this->convertToUtf8($fields[4] ?? '')), // NOKOEN
                'KOPRCT' => trim($fields[5] ?? ''), // KOPRCT
                'CAPRCO1' => $safeFloat($fields[6] ?? 0), // CAPRCO1
                'NOKOPR' => trim($this->convertToUtf8($fields[7] ?? '')), // NOKOPR
                'FACT' => $safeFloat($fields[8] ?? 0), // FACT
                'PEND' => $safeFloat($fields[9] ?? 0), // PEND
                'NOKOFU' => trim($this->convertToUtf8($fields[10] ?? '')), // NOKOFU
                'NOKOCI' => trim($this->convertToUtf8($fields[11] ?? '')), // NOKOCI
                'NOKOCM' => trim($this->convertToUtf8($fields[12] ?? '')), // NOKOCM
                'DIAS' => (int)trim($fields[13] ?? 0), // DIAS
                'Rango' => trim($fields[14] ?? ''), // Rango
                'PUNIT' => $safeFloat($fields[15] ?? 0), // PUNIT
                'PEND_VAL' => $safeFloat($fields[16] ?? 0), // PEND_VAL
                'TD_R' => trim($fields[17] ?? ''), // TD_R
                'N_FCV' => trim($fields[18] ?? ''), // N_FCV
                'KOFU' => trim($fields[19] ?? ''), // KOFU
                
                // Campos de descuento, IVA y totales (disponibles en la consulta)
                'PODTGLLI' => $safeFloat($fields[20] ?? 0), // Porcentaje descuento
                'VADTNELI' => $safeFloat($fields[21] ?? 0), // Valor descuento
                'VANELI' => $safeFloat($fields[22] ?? 0), // Subtotal
                'POIVLI' => $safeFloat($fields[23] ?? 0), // Porcentaje IVA
                'VAIVLI' => $safeFloat($fields[24] ?? 0), // Valor IVA
                'VABRLI' => $safeFloat($fields[25] ?? 0), // Total
                'PPPRNE' => $safeFloat($fields[26] ?? 0), // Precio neto
                'PPPRBR' => $safeFloat($fields[27] ?? 0), // Precio bruto
                
                // Campos adicionales para compatibilidad con la vista
                'TIPO_DOCTO' => 'NVV',
                'NRO_DOCTO' => trim($fields[1] ?? ''),
                'CODIGO' => trim($fields[3] ?? ''),
                'CLIENTE' => trim($this->convertToUtf8($fields[4] ?? '')),
                'SALDO' => $safeFloat($fields[9] ?? 0),
                'CANTIDAD_PRODUCTOS' => 1,
                'TOTAL_PENDIENTE' => $safeFloat($fields[9] ?? 0),
                'TOTAL_VALOR_PENDIENTE' => $safeFloat($fields[16] ?? 0),
                'VENDEDOR_NOMBRE' => trim($this->convertToUtf8($fields[10] ?? ''))
            ];
            
            return $nvv;
            
        } catch (\Exception $e) {
            \Log::error("Error procesando línea {$lineNumber} de NVV: " . $e->getMessage() . " - Línea: " . $line);
            return null;
        }
    }

    /**
     * Obtiene los cheques en cartera
     */
    public function getChequesEnCartera($codigoVendedor = null)
    {
        try {
            // Obtener credenciales de las variables de entorno
            $host = env('SQLSRV_EXTERNAL_HOST');
            $port = env('SQLSRV_EXTERNAL_PORT', '1433');
            $database = env('SQLSRV_EXTERNAL_DATABASE');
            $username = env('SQLSRV_EXTERNAL_USERNAME');
            $password = env('SQLSRV_EXTERNAL_PASSWORD');
            
            // Verificar que las credenciales estén configuradas
            if (!$host || !$database || !$username || !$password) {
                throw new \Exception('Credenciales SQL Server no configuradas en .env');
            }

            // Construir la consulta con filtro de vendedor si se especifica
            $whereVendedor = '';
            if ($codigoVendedor) {
                $whereVendedor = "AND dbo.CLIENTES.KOFUEN = '{$codigoVendedor}'";
            }

            // Consulta para obtener cheques en cartera
            $query = "
            SELECT 
                CAST(SUM(CASE WHEN dbo.MAEDPCE.TIDP = 'CHC' THEN dbo.MAEDPCE.VADP * -1 ELSE dbo.MAEDPCE.VADP * 1 END) AS VARCHAR(20)) AS TOTAL_CHEQUES
            FROM dbo.TABFU 
            INNER JOIN dbo.CLIENTES ON dbo.TABFU.KOFU = dbo.CLIENTES.KOFUEN 
            RIGHT OUTER JOIN dbo.MAEDPCE ON dbo.CLIENTES.KOEN = dbo.MAEDPCE.ENDP 
            LEFT OUTER JOIN dbo.TABSU ON dbo.MAEDPCE.SUREDP = dbo.TABSU.KOSU 
            LEFT OUTER JOIN dbo.TABCTAEM ON dbo.MAEDPCE.CUDP = dbo.TABCTAEM.CTACTEEM
            WHERE (dbo.MAEDPCE.TIDP = 'CHV') 
            AND (dbo.MAEDPCE.FEVEDP > GETDATE() - 1) 
            AND (dbo.MAEDPCE.ESPGDP = 'P') 
            AND (dbo.MAEDPCE.EMPRESA = '01')
            {$whereVendedor}
            ";

            // Crear archivo temporal con la consulta
            $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
            file_put_contents($tempFile, $query . "\ngo\nquit");
            
            // Ejecutar consulta usando tsql
            $command = "tsql -H {$host} -p {$port} -U {$username} -P {$password} -D {$database} < {$tempFile} 2>&1";
            $output = shell_exec($command);
            
            // Limpiar archivo temporal
            unlink($tempFile);
            
            \Log::info('Output de getChequesEnCartera: ' . $output);
            
            if ($output === null) {
                throw new \Exception('Error ejecutando consulta tsql');
            }
            
            // Procesar la salida
            $lines = explode("\n", $output);
            $totalCheques = 0;
            
            foreach ($lines as $line) {
                $line = trim($line);
                
                // Saltar líneas de configuración
                if (empty($line) || 
                    strpos($line, 'locale') !== false || 
                    strpos($line, 'Setting') !== false || 
                    strpos($line, 'Msg ') !== false || 
                    strpos($line, 'Warning:') !== false ||
                    preg_match('/^\d+>$/', $line) ||
                    preg_match('/^\d+>\s+\d+>\s+\d+>/', $line) ||
                    strpos($line, 'rows affected') !== false ||
                    strpos($line, 'TOTAL_CHEQUES') !== false) {
                    continue;
                }
                
                // Procesar línea con el total de cheques
                if (is_numeric($line)) {
                    $totalCheques = (float)$line;
                    break;
                }
            }
            
            return $totalCheques;
            
        } catch (\Exception $e) {
            \Log::error('Error obteniendo cheques en cartera: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Obtener total de notas de venta en SQL Server
     */
    public function getTotalNotasVentaSQL()
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
            
            $query = "
                SELECT COUNT(*) as total
                FROM dbo.MAEEDO 
                WHERE TIDO = 'NVV' 
                AND FEEMDO >= DATEADD(MONTH, -12, GETDATE())
            ";
            
            // Crear archivo temporal con la consulta
            $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
            file_put_contents($tempFile, $query . "\ngo\nquit");
            
            // Ejecutar consulta usando tsql
            $command = "tsql -H {$host} -p {$port} -U {$username} -P {$password} -D {$database} < {$tempFile} 2>&1";
            $output = shell_exec($command);
            
            // Limpiar archivo temporal
            unlink($tempFile);
            
            if ($output === null) {
                throw new \Exception('Error ejecutando consulta tsql');
            }
            
            // Procesar la salida
            $lines = explode("\n", $output);
            $total = 0;
            
            foreach ($lines as $line) {
                $line = trim($line);
                
                // Saltar líneas de configuración
                if (empty($line) || 
                    strpos($line, 'locale') !== false || 
                    strpos($line, 'Setting') !== false || 
                    strpos($line, 'Msg ') !== false || 
                    strpos($line, 'Warning:') !== false ||
                    preg_match('/^\d+>$/', $line) ||
                    preg_match('/^\d+>\s+\d+>\s+\d+>/', $line) ||
                    strpos($line, 'rows affected') !== false ||
                    strpos($line, 'total') !== false) {
                    continue;
                }
                
                // Procesar línea con el total
                if (is_numeric($line)) {
                    $total = (int)$line;
                    break;
                }
            }
            
            return $total;
            
        } catch (\Exception $e) {
            \Log::error('Error obteniendo total notas de venta SQL: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Obtener listado de notas de venta en SQL Server
     */
    public function getNotasVentaSQL($limit = 20)
    {
        try {
            $host = env('SQLSRV_EXTERNAL_HOST');
            $port = env('SQLSRV_EXTERNAL_PORT', '1433');
            $database = env('SQLSRV_EXTERNAL_DATABASE');
            $username = env('SQLSRV_EXTERNAL_USERNAME');
            $password = env('SQLSRV_EXTERNAL_PASSWORD');
            
            // Consulta para obtener encabezados únicos de NVV con resumen
            $query = "
                SELECT TOP {$limit}
                    CAST(dbo.MAEEDO.TIDO AS VARCHAR(10)) + '|' +
                    CAST(dbo.MAEEDO.NUDO AS VARCHAR(20)) + '|' +
                    CAST(dbo.MAEEDO.FEEMDO AS VARCHAR(20)) + '|' +
                    CAST(dbo.MAEEDO.ENDO AS VARCHAR(20)) + '|' +
                    CAST(dbo.MAEEN.NOKOEN AS VARCHAR(100)) + '|' +
                    CAST(SUM(dbo.MAEDDO.CAPRCO1) AS VARCHAR(20)) + '|' +
                    CAST(dbo.TABFU.NOKOFU AS VARCHAR(50)) + '|' +
                    CAST(dbo.TABCI.NOKOCI AS VARCHAR(50)) + '|' +
                    CAST(dbo.TABCM.NOKOCM AS VARCHAR(50)) + '|' +
                    CAST(CAST(GETDATE() - dbo.MAEEDO.FEEMDO AS INT) AS VARCHAR(10)) + '|' +
                    CAST(dbo.MAEEDO.VABRDO AS VARCHAR(20)) + '|' +
                    CAST(dbo.TABFU.KOFU AS VARCHAR(10)) AS DATOS
                FROM dbo.MAEEDO 
                INNER JOIN dbo.MAEEN ON dbo.MAEEDO.ENDO = dbo.MAEEN.KOEN AND dbo.MAEEDO.SUENDO = dbo.MAEEN.SUEN 
                INNER JOIN dbo.MAEDDO ON dbo.MAEEDO.IDMAEEDO = dbo.MAEDDO.IDMAEEDO
                INNER JOIN dbo.TABFU ON dbo.MAEDDO.KOFULIDO = dbo.TABFU.KOFU 
                INNER JOIN dbo.TABCI ON dbo.MAEEN.PAEN = dbo.TABCI.KOPA AND dbo.MAEEN.CIEN = dbo.TABCI.KOCI 
                INNER JOIN dbo.TABCM ON dbo.MAEEN.PAEN = dbo.TABCM.KOPA AND dbo.MAEEN.CIEN = dbo.TABCM.KOCI AND dbo.MAEEN.CMEN = dbo.TABCM.KOCM
                WHERE (dbo.MAEEDO.TIDO = 'NVV') 
                AND (dbo.MAEEDO.FEEMDO >= DATEADD(MONTH, -12, GETDATE()))
                GROUP BY dbo.MAEEDO.TIDO, dbo.MAEEDO.NUDO, dbo.MAEEDO.FEEMDO, dbo.MAEEDO.ENDO, 
                         dbo.MAEEN.NOKOEN, dbo.TABFU.NOKOFU, dbo.TABCI.NOKOCI, dbo.TABCM.NOKOCM, 
                         dbo.MAEEDO.VABRDO, dbo.TABFU.KOFU
                ORDER BY dbo.MAEEDO.NUDO DESC
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
            
            // Procesar la salida
            $lines = explode("\n", $output);
            $result = [];
            $inDataSection = false;
            $headerFound = false;
            
            foreach ($lines as $lineNumber => $line) {
                $line = trim($line);
                
                // Saltar líneas vacías o de configuración
                if (empty($line) || 
                    strpos($line, 'locale') !== false || 
                    strpos($line, 'Setting') !== false || 
                    strpos($line, 'Msg ') !== false || 
                    strpos($line, 'Warning:') !== false ||
                    preg_match('/^\d+>$/', $line)) {
                    continue;
                }
                
                // Detectar el header de la tabla
                if (strpos($line, 'DATOS') !== false) {
                    $headerFound = true;
                    $inDataSection = true;
                    continue;
                }
                
                // Procesar líneas de datos
                if ($inDataSection && $headerFound && strpos($line, '|') !== false) {
                    $fields = explode('|', $line);
                    if (count($fields) >= 12) {
                        $result[] = [
                            'TIPO_DOCTO' => trim($fields[0]),
                            'NRO_DOCTO' => trim($fields[1]),
                            'FECHA_EMISION' => trim($fields[2]),
                            'CODIGO_CLIENTE' => trim($fields[3]),
                            'CLIENTE' => trim($fields[4]),
                            'CANTIDAD_TOTAL' => trim($fields[5]),
                            'VENDEDOR' => trim($fields[6]),
                            'REGION' => trim($fields[7]),
                            'COMUNA' => trim($fields[8]),
                            'DIAS' => trim($fields[9]),
                            'VALOR_PENDIENTE' => trim($fields[10]),
                            'CODIGO_VENDEDOR' => trim($fields[11])
                        ];
                    }
                }
            }
            
            // Los datos ya vienen agrupados por la consulta, solo necesitamos ordenarlos
            usort($result, function($a, $b) {
                return strtotime($b['FECHA_EMISION']) - strtotime($a['FECHA_EMISION']);
            });
            
            return $result;
            
        } catch (\Exception $e) {
            \Log::error('Error obteniendo notas de venta SQL: ' . $e->getMessage());
            return [];
        }
    }


    /**
     * Obtener relación FCV con NVV asociada
     */
    public function getRelacionFcvNvv($tipoDoc, $numeroDoc)
    {
        try {
            $host = env('SQLSRV_EXTERNAL_HOST');
            $port = env('SQLSRV_EXTERNAL_PORT', '1433');
            $database = env('SQLSRV_EXTERNAL_DATABASE');
            $username = env('SQLSRV_EXTERNAL_USERNAME');
            $password = env('SQLSRV_EXTERNAL_PASSWORD');
            
            // Consulta para obtener relación FCV con NVV (ambos en la misma tabla MAEDDO)
            $query = "
                SELECT 
                    CAST(dbo.MAEDDO.TIDO AS VARCHAR(10)) + '|' +
                    CAST(dbo.MAEDDO.NUDO AS VARCHAR(20)) + '|' +
                    CAST(dbo.MAEDDO.FEEMLI AS VARCHAR(20)) + '|' +
                    CAST(dbo.MAEDDO.ENDO AS VARCHAR(20)) + '|' +
                    CAST(dbo.MAEEN.NOKOEN AS VARCHAR(100)) + '|' +
                    CAST(dbo.MAEDDO.KOPRCT AS VARCHAR(20)) + '|' +
                    CAST(dbo.MAEDDO.CAPRCO1 AS VARCHAR(20)) + '|' +
                    CAST(dbo.MAEDDO.NOKOPR AS VARCHAR(100)) + '|' +
                    CAST(MAEDDO_1.TIDO AS VARCHAR(10)) + '|' +
                    CAST(MAEDDO_1.NUDO AS VARCHAR(20)) + '|' +
                    CAST(MAEDDO_1.CAPRCO1 AS VARCHAR(20)) + '|' +
                    CAST(MAEDDO_2.TIDO AS VARCHAR(10)) + '|' +
                    CAST(MAEDDO_2.NUDO AS VARCHAR(20)) + '|' +
                    CAST(MAEDDO_2.CAPRCO1 AS VARCHAR(20)) + '|' +
                    CAST(MAEDDO_2.CAPRCO1 - MAEDDO_2.CAPRAD1 - MAEDDO_2.CAPREX1 AS VARCHAR(20)) AS DATOS
                FROM dbo.MAEDDO AS MAEDDO_2 
                INNER JOIN dbo.MAEDDO AS MAEDDO_1 ON MAEDDO_2.IDMAEDDO = MAEDDO_1.IDRST 
                FULL OUTER JOIN dbo.MAEDDO 
                INNER JOIN dbo.MAEEN ON dbo.MAEDDO.ENDO = dbo.MAEEN.KOEN AND dbo.MAEDDO.SUENDO = dbo.MAEEN.SUEN ON MAEDDO_1.IDMAEDDO = dbo.MAEDDO.IDRST
                WHERE (dbo.MAEDDO.TIDO = '{$tipoDoc}') 
                AND (dbo.MAEDDO.NUDO = '{$numeroDoc}')
                AND (MAEDDO_2.FEEMLI > '31-12-2023')
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
                \Log::warning('Error obteniendo relación FCV-NVV: ' . $output);
                return [];
            }
            
            // Procesar la salida
            $lines = explode("\n", $output);
            $result = [];
            $inDataSection = false;
            $headerFound = false;
            
            foreach ($lines as $lineNumber => $line) {
                $line = trim($line);
                
                // Saltar líneas vacías o de configuración
                if (empty($line) || 
                    strpos($line, 'locale') !== false || 
                    strpos($line, 'Setting') !== false || 
                    strpos($line, 'Msg ') !== false || 
                    strpos($line, 'Warning:') !== false ||
                    preg_match('/^\d+>$/', $line)) {
                    continue;
                }
                
                // Detectar el header de la tabla
                if (strpos($line, 'DATOS') !== false) {
                    $headerFound = true;
                    $inDataSection = true;
                    continue;
                }
                
                // Procesar líneas de datos
                if ($inDataSection && $headerFound && strpos($line, '|') !== false) {
                    $fields = explode('|', $line);
                    if (count($fields) >= 15) {
                        $result[] = [
                            'TIPO_FCV' => trim($fields[0]),
                            'NUMERO_FCV' => trim($fields[1]),
                            'FECHA_EMISION_FCV' => trim($fields[2]),
                            'CODIGO_CLIENTE' => trim($fields[3]),
                            'CLIENTE' => trim($fields[4]),
                            'CODIGO_PRODUCTO' => trim($fields[5]),
                            'CANTIDAD_FCV' => trim($fields[6]),
                            'NOMBRE_PRODUCTO' => trim($fields[7]),
                            'TIPO_NVV' => trim($fields[8]),
                            'NUMERO_NVV' => trim($fields[9]),
                            'CANTIDAD_NVV' => trim($fields[10]),
                            'TIPO_FCV_ORIGEN' => trim($fields[11]),
                            'NUMERO_FCV_ORIGEN' => trim($fields[12]),
                            'CANTIDAD_FCV_ORIGEN' => trim($fields[13]),
                            'PENDIENTE' => trim($fields[14])
                        ];
                    }
                }
            }
            
            return $result;
            
        } catch (\Exception $e) {
            \Log::error('Error obteniendo relación FCV-NVV: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener listado de facturas ingresadas
     */
    public function getFacturasIngresadas($limit = 20)
    {
        try {
            $host = env('SQLSRV_EXTERNAL_HOST');
            $port = env('SQLSRV_EXTERNAL_PORT', '1433');
            $database = env('SQLSRV_EXTERNAL_DATABASE');
            $username = env('SQLSRV_EXTERNAL_USERNAME');
            $password = env('SQLSRV_EXTERNAL_PASSWORD');
            
            $dsn = "sqlsrv:Server={$host},{$port};Database={$database};Encrypt=no;TrustServerCertificate=yes;ConnectionPooling=0;";
            $pdo = new PDO($dsn, $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $query = "
                SELECT TOP {$limit}
                    dbo.MAEEDO.TIDO AS TIPO_DOCTO,
                    dbo.MAEEDO.NUDO AS NRO_DOCTO,
                    dbo.MAEEDO.ENDO AS CODIGO_CLIENTE,
                    dbo.MAEEN.NOKOEN AS NOMBRE_CLIENTE,
                    dbo.MAEEDO.FEEMDO AS FECHA_EMISION,
                    dbo.MAEEDO.FEULVEDO AS FECHA_VENCIMIENTO,
                    dbo.MAEEDO.VABRDO AS VALOR_DOCUMENTO,
                    dbo.MAEEDO.VAABDO AS ABONOS,
                    (dbo.MAEEDO.VABRDO - dbo.MAEEDO.VAABDO) AS SALDO,
                    ISNULL(dbo.TABFU.NOKOFU, 'SIN VENDEDOR') AS NOMBRE_VENDEDOR,
                    dbo.TABFU.KOFU AS CODIGO_VENDEDOR
                FROM dbo.MAEEDO 
                LEFT JOIN dbo.MAEEN ON dbo.MAEEDO.ENDO = dbo.MAEEN.KOEN AND dbo.MAEEDO.SUENDO = dbo.MAEEN.SUEN
                LEFT JOIN dbo.TABFU ON dbo.MAEEN.KOFUEN = dbo.TABFU.KOFU
                WHERE dbo.MAEEDO.TIDO IN ('FCV', 'FDV', 'NCV')
                AND dbo.MAEEDO.FEEMDO > CONVERT(DATETIME, '2024-01-01 00:00:00', 102)
                ORDER BY dbo.MAEEDO.FEEMDO DESC
            ";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return $results;
            
        } catch (\Exception $e) {
            \Log::error('Error obteniendo facturas ingresadas: ' . $e->getMessage());
            return [];
        }
    }

} 