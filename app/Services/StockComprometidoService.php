<?php

namespace App\Services;

use App\Models\StockComprometido;
use App\Models\Cotizacion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StockComprometidoService
{
    /**
     * Comprometer stock para una cotización
     */
    public function comprometerStock($cotizacionId, $productos)
    {
        try {
            DB::beginTransaction();
            
            $cotizacion = Cotizacion::with('detalles')->findOrFail($cotizacionId);
            $stockComprometido = [];
            
            foreach ($productos as $producto) {
                // Verificar stock disponible real
                $stockDisponible = $this->obtenerStockDisponible($producto['codigo']);
                $stockComprometidoActual = StockComprometido::calcularStockComprometido($producto['codigo']);
                $stockRealDisponible = $stockDisponible - $stockComprometidoActual;
                
                if ($stockRealDisponible < $producto['cantidad']) {
                    throw new \Exception("Stock insuficiente para el producto {$producto['codigo']}. Disponible: {$stockRealDisponible}, Solicitado: {$producto['cantidad']}");
                }
                
                // Crear registro de stock comprometido
                $stockComprometido[] = StockComprometido::create([
                    'producto_codigo' => $producto['codigo'],
                    'producto_nombre' => $producto['nombre'],
                    'bodega_codigo' => '001', // Bodega por defecto
                    'bodega_nombre' => 'Bodega Principal',
                    'cantidad_comprometida' => $producto['cantidad'],
                    'stock_disponible_original' => $stockDisponible,
                    'stock_disponible_actual' => $stockRealDisponible,
                    'unidad_medida' => $producto['unidad_medida'] ?? 'UN',
                    'cotizacion_id' => $cotizacionId,
                    'cotizacion_estado' => $cotizacion->estado,
                    'vendedor_id' => $cotizacion->vendedor_id,
                    'vendedor_nombre' => $cotizacion->vendedor_nombre,
                    'cliente_codigo' => $cotizacion->cliente_codigo,
                    'cliente_nombre' => $cotizacion->cliente_nombre,
                    'fecha_compromiso' => now(),
                    'observaciones' => 'Stock comprometido por cotización'
                ]);
                
                Log::info("Stock comprometido: Producto {$producto['codigo']}, Cantidad: {$producto['cantidad']}, Cotización: {$cotizacionId}");
            }
            
            DB::commit();
            
            return [
                'success' => true,
                'stock_comprometido' => $stockComprometido,
                'message' => 'Stock comprometido exitosamente'
            ];
            
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error comprometiendo stock: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Stock físico y comprometido ERP sumando todas las filas MAEST del SKU (misma lógica que detalle manejo-stock sin bodega).
     * La tabla MySQL productos suele sincronizarse solo con KOBO = '01', lo que puede marcar 0 disponible si el stock está en otra bodega.
     */
    private function obtenerStockMaestAgregadoSqlsrv(string $productoCodigo): ?array
    {
        try {
            $trim = trim($productoCodigo);
            $row = DB::connection('sqlsrv_external')
                ->table('MAEST')
                ->selectRaw('SUM(CAST(ISNULL(STFI1, 0) AS FLOAT)) AS sf, SUM(CAST(ISNULL(STOCNV1, 0) AS FLOAT)) AS sc')
                ->whereRaw('RTRIM(KOPR) = ?', [$trim])
                ->first();

            if ($row === null) {
                return null;
            }

            return [
                'stock_fisico' => (float) ($row->sf ?? 0),
                'stock_comprometido' => abs((float) ($row->sc ?? 0)),
            ];
        } catch (\Throwable $e) {
            Log::debug('Stock MAEST agregado (sqlsrv) no disponible para '.$productoCodigo.': '.$e->getMessage());

            return null;
        }
    }

    /**
     * Mismo agregado MAEST vía tsql (servidores sin PDO sqlsrv / Encrypt=no).
     */
    private function obtenerStockMaestAgregadoTsql(string $productoCodigo): ?array
    {
        $host = env('SQLSRV_EXTERNAL_HOST');
        if (empty($host)) {
            return null;
        }

        $port = env('SQLSRV_EXTERNAL_PORT', '1433');
        $database = env('SQLSRV_EXTERNAL_DATABASE');
        $username = env('SQLSRV_EXTERNAL_USERNAME');
        $password = env('SQLSRV_EXTERNAL_PASSWORD');

        $skuEscapado = str_replace("'", "''", trim($productoCodigo));
        $query = "
            SELECT CAST(ISNULL(SUM(STFI1), 0) AS VARCHAR(40)) + '|' + CAST(ISNULL(SUM(STOCNV1), 0) AS VARCHAR(40)) AS DATOS_STOCK_MAEST
            FROM MAEST
            WHERE RTRIM(KOPR) = '{$skuEscapado}'
        ";

        try {
            $tempFile = tempnam(sys_get_temp_dir(), 'sql_maest_sum_');
            file_put_contents($tempFile, $query."\ngo\nquit");
            $command = "tsql -H {$host} -p {$port} -U {$username} -P {$password} -D {$database} < {$tempFile} 2>&1";
            $output = shell_exec($command);
            unlink($tempFile);

            if (! $output || str_contains(strtolower((string) $output), 'error')) {
                return null;
            }

            $lines = explode("\n", $output);
            $headerOk = false;

            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' ||
                    strpos($line, 'locale') !== false ||
                    strpos($line, 'Setting') !== false ||
                    strpos($line, 'using default') !== false ||
                    stripos($line, 'charset') !== false ||
                    strpos($line, 'Msg ') !== false ||
                    strpos($line, 'Warning:') !== false ||
                    strpos($line, 'rows affected') !== false ||
                    preg_match('/^\d+>$/', $line)) {
                    continue;
                }

                if (stripos($line, 'DATOS_STOCK_MAEST') !== false) {
                    $headerOk = true;
                    continue;
                }

                if ($headerOk && str_contains($line, '|')) {
                    $lineData = preg_replace('/^\d+>\s*/', '', $line);
                    $lineData = trim($lineData);
                    $parts = explode('|', $lineData, 2);
                    if (count($parts) >= 2) {
                        $sf = (float) str_replace(',', '.', trim($parts[0]));
                        $sc = abs((float) str_replace(',', '.', trim($parts[1])));

                        return [
                            'stock_fisico' => $sf,
                            'stock_comprometido' => $sc,
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::debug('Stock MAEST agregado (tsql) falló para '.$productoCodigo.': '.$e->getMessage());
        }

        return null;
    }

    /**
     * Stock físico/comprometido ERP: PDO sqlsrv si existe; si no, tsql.
     */
    private function obtenerStockMaestAgregado(string $productoCodigo): ?array
    {
        $via = $this->obtenerStockMaestAgregadoSqlsrv($productoCodigo);
        if ($via !== null) {
            return $via;
        }

        return $this->obtenerStockMaestAgregadoTsql($productoCodigo);
    }

    /**
     * Verificar si un producto está oculto (ATPR = 'OCU') consultando SQL Server
     */
    public function verificarProductoOculto($codigoProducto)
    {
        try {
            $trim = trim((string) $codigoProducto);

            try {
                $atpr = DB::connection('sqlsrv_external')
                    ->table('MAEPR')
                    ->whereRaw('RTRIM(KOPR) = ?', [$trim])
                    ->value('ATPR');
                if ($atpr !== null && $atpr !== '') {
                    $oculto = strtoupper(trim((string) $atpr)) === 'OCU';
                    if ($oculto) {
                        Log::warning("⚠️ Producto {$trim} está OCULTO (ATPR = 'OCU') vía sqlsrv");
                    }

                    return $oculto;
                }
            } catch (\Throwable $e) {
                Log::debug('ATPR vía sqlsrv no disponible, fallback tsql: '.$e->getMessage());
            }

            $host = env('SQLSRV_EXTERNAL_HOST');
            $port = env('SQLSRV_EXTERNAL_PORT', '1433');
            $database = env('SQLSRV_EXTERNAL_DATABASE');
            $username = env('SQLSRV_EXTERNAL_USERNAME');
            $password = env('SQLSRV_EXTERNAL_PASSWORD');

            $codigoEscapado = str_replace("'", "''", $trim);

            // Consultar ATPR desde SQL Server
            $query = "
                SELECT TOP 1 ATPR
                FROM MAEPR
                WHERE KOPR = '{$codigoEscapado}'
            ";
            
            $tempFile = tempnam(sys_get_temp_dir(), 'sql_oculto_');
            file_put_contents($tempFile, $query . "\ngo\nquit");
            
            $command = "tsql -H {$host} -p {$port} -U {$username} -P {$password} -D {$database} < {$tempFile} 2>&1";
            $output = shell_exec($command);
            unlink($tempFile);
            
            if (!$output) {
                Log::warning("Output vacío al verificar si producto está oculto: " . $codigoProducto);
                return false; // Si hay error, asumimos que no está oculto para no bloquear
            }
            
            // Buscar el valor de ATPR en el output
            $lines = explode("\n", $output);
            $headerFound = false;
            
            foreach ($lines as $line) {
                $line = trim($line);
                
                // Saltar líneas vacías y de configuración
                if (empty($line)) {
                    continue;
                }
                
                // Saltar mensajes de configuración y warnings (incl. freeTDS "using default charset")
                if (strpos($line, 'locale') !== false ||
                    strpos($line, 'Setting') !== false ||
                    strpos($line, 'rows affected') !== false ||
                    strpos($line, 'Msg ') !== false ||
                    strpos($line, 'Warning:') !== false ||
                    strpos($line, 'using default') !== false ||
                    stripos($line, 'charset') !== false ||
                    preg_match('/^\d+>$/', $line)) {
                    continue;
                }

                // Buscar header de columna ATPR
                if (stripos($line, 'ATPR') !== false && (stripos($line, 'ATPR') === 0 || strpos($line, 'ATPR') < 10)) {
                    $headerFound = true;
                    Log::info("Header ATPR encontrado en línea: {$line}");
                    continue;
                }

                // Tras el header ATPR: primera línea no vacía es el valor de la columna
                if ($headerFound) {
                    $lineData = preg_replace('/^\d+>\s*/', '', $line);
                    $lineData = trim($lineData);
                    if ($lineData === '') {
                        continue;
                    }
                    $isOculto = strtoupper($lineData) === 'OCU';
                    if ($isOculto) {
                        Log::warning("⚠️ Producto {$codigoProducto} está OCULTO (ATPR = 'OCU', tsql)");
                    }

                    return $isOculto;
                }
            }
            
            Log::info("✅ Producto {$codigoProducto} NO está oculto (no se encontró 'OCU' en ATPR)");
            
            return false;
            
        } catch (\Exception $e) {
            Log::error('Error verificando producto oculto: ' . $e->getMessage());
            return false; // En caso de error, asumimos que no está oculto
        }
    }
    
    /**
     * Liberar stock comprometido
     */
    public function liberarStock($cotizacionId, $motivo = null)
    {
        try {
            DB::beginTransaction();
            
            $stockComprometido = StockComprometido::porCotizacion($cotizacionId)->activo()->get();
            
            foreach ($stockComprometido as $stock) {
                $stock->liberar($motivo);
                Log::info("Stock liberado: Producto {$stock->producto_codigo}, Cantidad: {$stock->cantidad_comprometida}, Cotización: {$cotizacionId}");
            }
            
            DB::commit();
            
            return [
                'success' => true,
                'stock_liberado' => $stockComprometido->count(),
                'message' => 'Stock liberado exitosamente'
            ];
            
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error liberando stock: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Actualizar estado del stock comprometido cuando cambia el estado de la cotización
     */
    public function actualizarEstadoStock($cotizacionId, $nuevoEstado)
    {
        try {
            $stockComprometido = StockComprometido::porCotizacion($cotizacionId)->activo()->get();
            
            foreach ($stockComprometido as $stock) {
                $stock->update(['cotizacion_estado' => $nuevoEstado]);
            }
            
            Log::info("Estado de stock actualizado: Cotización {$cotizacionId}, Nuevo estado: {$nuevoEstado}");
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('Error actualizando estado de stock: ' . $e->getMessage());
            return false;
        }
    }
    

    
    /**
     * Obtener stock disponible real considerando stock comprometido local
     */
    public function obtenerStockDisponibleReal($productoCodigo, $bodegaCodigo = '01')
    {
        $codigoNorm = trim((string) $productoCodigo);

        $sqlAgg = $this->obtenerStockMaestAgregado($codigoNorm);

        if ($sqlAgg !== null) {
            $stockFisicoLocal = $sqlAgg['stock_fisico'];
            $stockComprometidoSQL = $sqlAgg['stock_comprometido'];
        } else {
            // Fallback: tabla MySQL (KOPR a menudo viene con espacios de relleno desde el ERP)
            $producto = DB::table('productos')->whereRaw('TRIM(KOPR) = ?', [$codigoNorm])->first();

            if (! $producto) {
                Log::warning("Producto {$codigoNorm} no encontrado en tabla local ni MAEST agregado (sqlsrv/tsql)");

                return 0;
            }

            $stockFisicoLocal = (float) $producto->stock_fisico;
            $stockComprometidoSQL = (float) $producto->stock_comprometido;
        }

        // Obtener stock comprometido local adicional (por cotizaciones)
        $stockComprometidoLocal = (float) StockComprometido::calcularStockComprometido($codigoNorm, $bodegaCodigo);

        // Stock disponible real = Stock físico local - Stock comprometido SQL - Stock comprometido local
        $stockDisponibleReal = $stockFisicoLocal - $stockComprometidoSQL - $stockComprometidoLocal;

        Log::debug("Stock real para producto {$codigoNorm}: Físico ERP={$stockFisicoLocal}, Comprometido SQL={$stockComprometidoSQL}, Comprometido Local={$stockComprometidoLocal}, Disponible Real={$stockDisponibleReal}");
        
        return max(0, $stockDisponibleReal); // No puede ser negativo
    }

    /**
     * Stock disponible real para muchos SKU en pocas consultas (evita N×tsql en vistas como aprobaciones/show).
     *
     * @param  array<int, string>  $codigosProducto
     * @return array<string, float> clave = TRIM(código), valor = stock disponible
     */
    public function mapaStockDisponibleReal(array $codigosProducto, string $bodegaCodigo = '01'): array
    {
        $lista = [];
        foreach ($codigosProducto as $c) {
            $t = trim((string) $c);
            if ($t !== '') {
                $lista[$t] = true;
            }
        }
        $codigos = array_keys($lista);
        if ($codigos === []) {
            return [];
        }

        $maestMap = $this->obtenerStockMaestAgregadoBatch($codigos);
        $compMap = $this->obtenerComprometidoLocalBatch($codigos, $bodegaCodigo);

        $placeholders = implode(',', array_fill(0, count($codigos), '?'));
        $mysqlRows = DB::table('productos')
            ->selectRaw('TRIM(KOPR) as k, stock_fisico, stock_comprometido')
            ->whereRaw('TRIM(KOPR) IN ('.$placeholders.')', $codigos)
            ->get();
        $mysqlMap = [];
        foreach ($mysqlRows as $r) {
            $k = trim((string) ($r->k ?? ''));
            if ($k !== '') {
                $mysqlMap[$k] = $r;
            }
        }

        $out = [];
        foreach ($codigos as $k) {
            if (isset($maestMap[$k])) {
                $stockFisicoLocal = (float) $maestMap[$k]['stock_fisico'];
                $stockComprometidoSQL = (float) $maestMap[$k]['stock_comprometido'];
            } elseif (isset($mysqlMap[$k])) {
                $stockFisicoLocal = (float) $mysqlMap[$k]->stock_fisico;
                $stockComprometidoSQL = (float) $mysqlMap[$k]->stock_comprometido;
            } else {
                $out[$k] = 0.0;
                continue;
            }
            $stockComprometidoLocal = (float) ($compMap[$k] ?? 0);
            $stockDisponibleReal = $stockFisicoLocal - $stockComprometidoSQL - $stockComprometidoLocal;
            $out[$k] = max(0.0, $stockDisponibleReal);
        }

        return $out;
    }

    /**
     * @param  array<int, string>  $codigos  códigos ya normalizados (trim)
     * @return array<string, array{stock_fisico: float, stock_comprometido: float}>
     */
    private function obtenerStockMaestAgregadoBatch(array $codigos): array
    {
        if ($codigos === []) {
            return [];
        }
        $map = $this->obtenerStockMaestAgregadoBatchSqlsrv($codigos);
        $missing = array_values(array_diff($codigos, array_keys($map)));
        foreach ($missing as $c) {
            $one = $this->obtenerStockMaestAgregado($c);
            if ($one !== null) {
                $map[$c] = $one;
            }
        }

        return $map;
    }

    /**
     * @param  array<int, string>  $codigos
     * @return array<string, array{stock_fisico: float, stock_comprometido: float}>
     */
    private function obtenerStockMaestAgregadoBatchSqlsrv(array $codigos): array
    {
        $out = [];
        if ($codigos === []) {
            return $out;
        }
        try {
            foreach (array_chunk($codigos, 150) as $chunk) {
                $placeholders = implode(',', array_fill(0, count($chunk), '?'));
                $sql = "
                    SELECT RTRIM(KOPR) AS kopr,
                           SUM(CAST(ISNULL(STFI1, 0) AS FLOAT)) AS sf,
                           SUM(CAST(ISNULL(STOCNV1, 0) AS FLOAT)) AS sc
                    FROM MAEST
                    WHERE RTRIM(KOPR) IN ({$placeholders})
                    GROUP BY RTRIM(KOPR)
                ";
                $rows = DB::connection('sqlsrv_external')->select($sql, $chunk);
                foreach ($rows as $row) {
                    $k = trim((string) ($row->kopr ?? ''));
                    if ($k === '') {
                        continue;
                    }
                    $out[$k] = [
                        'stock_fisico' => (float) ($row->sf ?? 0),
                        'stock_comprometido' => abs((float) ($row->sc ?? 0)),
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::debug('Stock MAEST batch (sqlsrv) no disponible: '.$e->getMessage());

            return [];
        }

        return $out;
    }

    /**
     * @param  array<int, string>  $codigos
     * @return array<string, float>
     */
    private function obtenerComprometidoLocalBatch(array $codigos, string $bodegaCodigo): array
    {
        if ($codigos === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($codigos), '?'));
        $bindings = array_merge([$bodegaCodigo], $codigos);
        $sql = "
            SELECT TRIM(producto_codigo) AS c, SUM(cantidad_comprometida) AS t
            FROM stock_comprometidos
            WHERE estado = 'activo' AND bodega_codigo = ?
              AND TRIM(producto_codigo) IN ({$placeholders})
            GROUP BY TRIM(producto_codigo)
        ";
        $rows = DB::select($sql, $bindings);
        $map = [];
        foreach ($rows as $row) {
            $k = trim((string) ($row->c ?? ''));
            if ($k !== '') {
                $map[$k] = (float) ($row->t ?? 0);
            }
        }

        return $map;
    }

    /**
     * Obtener stock disponible desde tabla productos local (método privado)
     */
    private function obtenerStockDisponible($productoCodigo, $bodegaCodigo = '01')
    {
        try {
            // Obtener stock desde tabla productos local (ya sincronizada)
            $producto = DB::table('productos')->whereRaw('TRIM(KOPR) = ?', [trim((string) $productoCodigo)])->first();
            
            if (!$producto) {
                Log::warning("Producto {$productoCodigo} no encontrado en tabla local");
                return 0;
            }
            
            $stockFisico = (float)$producto->stock_fisico;
            $stockComprometido = (float)$producto->stock_comprometido;
            $stockDisponible = (float)$producto->stock_disponible;
            
            Log::info("Stock para producto {$productoCodigo}: Físico={$stockFisico}, Comprometido SQL={$stockComprometido}, Disponible SQL={$stockDisponible}");
            
            return $stockFisico; // Devolvemos el stock físico
            
        } catch (\Exception $e) {
            Log::error('Error obteniendo stock disponible: ' . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Obtener resumen de stock comprometido por producto
     */
    public function obtenerResumenStockComprometido($productoCodigo = null, $bodegaCodigo = '01')
    {
        try {
            $query = StockComprometido::activo();
            
            if ($productoCodigo) {
                $query->where('producto_codigo', $productoCodigo);
            }
            
            if ($bodegaCodigo) {
                $query->where('bodega_codigo', $bodegaCodigo);
            }
            
            return $query->selectRaw('
                    producto_codigo,
                    producto_nombre,
                    bodega_codigo,
                    SUM(cantidad_comprometida) as total_comprometido,
                    COUNT(*) as cotizaciones_activas,
                    MIN(fecha_compromiso) as fecha_compromiso_mas_antiguo,
                    MAX(fecha_compromiso) as fecha_compromiso_mas_reciente
                ')
                ->groupBy('producto_codigo', 'producto_nombre', 'bodega_codigo')
                ->orderBy('total_comprometido', 'desc')
                ->get();
                
        } catch (\Exception $e) {
            Log::error('Error obteniendo resumen de stock comprometido: ' . $e->getMessage());
            return collect();
        }
    }
} 