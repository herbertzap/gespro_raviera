<?php

namespace App\Services;

use App\Models\StockLocal;
use Illuminate\Support\Facades\Log;

class StockService
{
    /**
     * Sincronizar stock desde SQL Server
     */
    public function sincronizarStockDesdeSQLServer()
    {
        try {
            $host = env('SQLSRV_EXTERNAL_HOST');
            $port = env('SQLSRV_EXTERNAL_PORT', '1433');
            $database = env('SQLSRV_EXTERNAL_DATABASE');
            $username = env('SQLSRV_EXTERNAL_USERNAME');
            $password = env('SQLSRV_EXTERNAL_PASSWORD');
            
            $query = "
                SELECT 
                    CAST(MAEPR.KOPR AS VARCHAR(4000)) + '|' +
                    CAST(MAEPR.NOKOPR AS VARCHAR(4000)) + '|' +
                    CAST(ISNULL(TABBO.NOKOBO, 'BODEGA LIB') AS VARCHAR(4000)) + '|' +
                    CAST(ISNULL(MAEST.KOBO, 'LIB') AS VARCHAR(4000)) + '|' +
                    CAST(ISNULL(MAEST.STFI1, 0) AS VARCHAR(4000)) + '|' +
                    CAST(ISNULL(MAEST.STOCNV1, 0) AS VARCHAR(4000)) + '|' +
                    CAST(ISNULL(MAEPR.UD01PR, 'UN') AS VARCHAR(4000)) + '|' +
                    CAST(ISNULL(TABPRE01.PP01UD, ISNULL(MAEPR.POIVPR, 0)) AS VARCHAR(4000)) + '|' +
                    CAST(ISNULL(TABPRE01.PP02UD, 0) AS VARCHAR(4000)) + '|' +
                    CAST(ISNULL(TABPRE01.DTMA01UD, 0) AS VARCHAR(4000)) + '|' +
                    CAST(ISNULL(TABPRE02.PP01UD, ISNULL(MAEPR.POIVPR, 0)) AS VARCHAR(4000)) + '|' +
                    CAST(ISNULL(TABPRE02.PP02UD, 0) AS VARCHAR(4000)) + '|' +
                    CAST(ISNULL(TABPRE02.DTMA01UD, 0) AS VARCHAR(4000)) + '|' +
                    CAST(ISNULL(TABPRE03.PP01UD, ISNULL(MAEPR.POIVPR, 0)) AS VARCHAR(4000)) + '|' +
                    CAST(ISNULL(TABPRE03.PP02UD, 0) AS VARCHAR(4000)) + '|' +
                    CAST(ISNULL(TABPRE03.DTMA01UD, 0) AS VARCHAR(4000)) AS DATOS
                FROM MAEST
                LEFT JOIN MAEPR ON MAEPR.KOPR = MAEST.KOPR
                LEFT JOIN TABBO ON MAEST.KOBO = TABBO.KOBO
                LEFT JOIN TABPRE AS TABPRE01 ON MAEPR.KOPR = TABPRE01.KOPR AND TABPRE01.KOLT = '01P'
                LEFT JOIN TABPRE AS TABPRE02 ON MAEPR.KOPR = TABPRE02.KOPR AND TABPRE02.KOLT = '02P'
                LEFT JOIN TABPRE AS TABPRE03 ON MAEPR.KOPR = TABPRE03.KOPR AND TABPRE03.KOLT = '03P'
                WHERE MAEST.KOBO = 'LIB'
                ORDER BY MAEPR.KOPR
            ";
            
            $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
            file_put_contents($tempFile, $query . "\ngo\nquit");
            
            $command = "tsql -H {$host} -p {$port} -U {$username} -P {$password} -D {$database} < {$tempFile} 2>&1";
            $output = shell_exec($command);
            
            unlink($tempFile);
            
            $productos = $this->parsearProductosStock($output);
            
            Log::info('Productos parseados: ' . count($productos));
            if (count($productos) > 0) {
                Log::info('Primer producto: ' . json_encode($productos[0]));
            }
            
            foreach ($productos as $producto) {
                $this->actualizarStockLocal($producto);
            }
            
            Log::info('✅ Stock sincronizado desde SQL Server: ' . count($productos) . ' productos');
            return count($productos);
            
        } catch (\Exception $e) {
            Log::error('Error sincronizando stock: ' . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Parsear la salida de tsql para productos
     */
    private function parsearProductosStock($output)
    {
        $productos = [];
        $lines = explode("\n", $output);
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Saltar líneas de configuración
            if (empty($line) || strpos($line, 'locale') !== false || 
                strpos($line, 'Setting') !== false || strpos($line, 'rows affected') !== false ||
                strpos($line, 'Msg ') !== false || strpos($line, 'Warning:') !== false ||
                strpos($line, 'DATOS') !== false || strpos($line, '1>') !== false) {
                continue;
            }
            
            // Parsear línea de producto usando '|'
            if (strpos($line, '|') !== false) {
                $fields = explode('|', $line);
                
                // Función helper para convertir a float manteniendo decimales
                $convertToFloat = function($value) {
                    $value = trim($value);
                    return $value === '' || $value === null ? 0.0 : (float)$value;
                };
                
                if (count($fields) >= 8) {
                    $stockFisico = (float)$fields[4];
                    $stockComprometidoSQL = (float)$fields[5];
                    
                    // Obtener stock NVV comprometido local (MySQL) para este producto
                    $stockComprometidoLocal = \App\Models\StockComprometido::calcularStockComprometido(trim($fields[0]));
                    
                    // Calcular stock disponible: STFI1 - (STOCNV1 + stock_nvv_mysql)
                    $stockDisponible = $stockFisico - ($stockComprometidoSQL + $stockComprometidoLocal);
                    
                    // Limpiar nombre del producto removiendo información adicional como "Múltiplo: X" y unidades
                    $nombreProducto = $this->limpiarNombreProducto(trim($fields[1]));
                    
                    $producto = [
                        'codigo_producto' => trim($fields[0]),
                        'nombre_producto' => $nombreProducto,
                        'nombre_bodega' => (trim($fields[2]) ?: 'BODEGA LIB'),
                        'codigo_bodega' => (trim($fields[3]) ?: 'LIB'),
                        'stock_fisico' => $stockFisico,
                        'stock_comprometido' => $stockComprometidoSQL,
                        'stock_disponible' => $stockDisponible,
                        'unidad_medida' => trim($fields[6]) ?: 'UN',
                        'precio_venta' => $convertToFloat($fields[7])
                    ];
                    
                    // Si hay 16 o más campos, incluir precios de las tres listas (01P, 02P, 03P)
                    // Formato: KOPR|NOKOPR|NOKOBO|KOBO|STFI1|STOCNV1|UD01PR|PP01UD(01P)|PP02UD(01P)|DTMA01UD(01P)|PP01UD(02P)|PP02UD(02P)|DTMA01UD(02P)|PP01UD(03P)|PP02UD(03P)|DTMA01UD(03P)
                    // NOTA: La consulta SQL ahora usa POIVPR como fallback cuando las listas están en NULL
                    if (count($fields) >= 16) {
                        // Lista 01P: campos 7, 8, 9
                        $precio01p = $convertToFloat($fields[7]);
                        $producto['precio_01p'] = $precio01p;
                        $producto['precio_01p_ud2'] = $convertToFloat($fields[8]);
                        $producto['descuento_maximo_01p'] = $convertToFloat($fields[9]);
                        
                        // Lista 02P: campos 10, 11, 12
                        $precio02p = $convertToFloat($fields[10]);
                        $producto['precio_02p'] = $precio02p;
                        $producto['precio_02p_ud2'] = $convertToFloat($fields[11]);
                        $producto['descuento_maximo_02p'] = $convertToFloat($fields[12]);
                        
                        // Lista 03P: campos 13, 14, 15
                        $precio03p = $convertToFloat($fields[13]);
                        $producto['precio_03p'] = $precio03p;
                        $producto['precio_03p_ud2'] = $convertToFloat($fields[14]);
                        $producto['descuento_maximo_03p'] = $convertToFloat($fields[15]);
                        
                        // precio_venta es el precio de la lista 01P (o el mayor entre las listas si 01P es 0)
                        if ($precio01p > 0) {
                            $producto['precio_venta'] = $precio01p;
                        } elseif ($precio02p > 0) {
                            $producto['precio_venta'] = $precio02p;
                        } elseif ($precio03p > 0) {
                            $producto['precio_venta'] = $precio03p;
                        } else {
                            // Si todas las listas están en 0, usar el campo 7 que ya tiene POIVPR como fallback
                            $producto['precio_venta'] = $precio01p;
                        }
                    } else {
                        // Formato antiguo: campo 7 es precio_venta de lista '01'
                        $producto['precio_venta'] = (float)$fields[7];
                    }
                    
                    // Log para debuggear el primer producto problemático
                    if (strpos($fields[0], '1002225000000') !== false) {
                        Log::info('Producto problemático parseado: ' . json_encode($producto));
                        Log::info('Fields originales: ' . json_encode($fields));
                    }
                    
                    $productos[] = $producto;
                }
            }
        }
        
        return $productos;
    }
    
    /**
     * Actualizar o crear stock local
     */
    private function actualizarStockLocal($producto)
    {
        $stockLocal = StockLocal::updateOrCreate(
            [
                'codigo_producto' => $producto['codigo_producto'],
                'codigo_bodega' => $producto['codigo_bodega']
            ],
            [
                'nombre_producto' => $producto['nombre_producto'],
                'nombre_bodega' => $producto['nombre_bodega'],
                'stock_fisico' => $producto['stock_fisico'],
                'stock_disponible' => $producto['stock_disponible'],
                'unidad_medida' => $producto['unidad_medida'],
                'precio_venta' => $producto['precio_venta'],
                'activo' => true,
                'ultima_actualizacion' => now()
            ]
        );
        
        // Actualizar o crear en la tabla productos (usada por el cotizador)
        try {
            $productoExistente = \DB::table('productos')->where('KOPR', $producto['codigo_producto'])->first();
            
            $data = [
                'NOKOPR' => $producto['nombre_producto'],
                'UD01PR' => $producto['unidad_medida'],
                'stock_fisico' => $producto['stock_fisico'],
                'stock_comprometido' => $producto['stock_comprometido'] ?? 0,
                'stock_disponible' => $producto['stock_disponible'],
                'updated_at' => now()
            ];
            
            // Agregar precios de las listas si están disponibles
            // Los precios ya vienen con POIVPR como fallback desde la consulta SQL
            if (isset($producto['precio_01p'])) {
                $data['precio_01p'] = $producto['precio_01p'];
                $data['precio_01p_ud2'] = $producto['precio_01p_ud2'] ?? 0;
                $data['descuento_maximo_01p'] = $producto['descuento_maximo_01p'] ?? 0;
                // Actualizar POIVPR con el precio 01P si es mayor a 0
                if ($producto['precio_01p'] > 0) {
                    $data['POIVPR'] = $producto['precio_01p'];
                }
            }
            if (isset($producto['precio_02p'])) {
                $data['precio_02p'] = $producto['precio_02p'];
                $data['precio_02p_ud2'] = $producto['precio_02p_ud2'] ?? 0;
                $data['descuento_maximo_02p'] = $producto['descuento_maximo_02p'] ?? 0;
            }
            if (isset($producto['precio_03p'])) {
                $data['precio_03p'] = $producto['precio_03p'];
                $data['precio_03p_ud2'] = $producto['precio_03p_ud2'] ?? 0;
                $data['descuento_maximo_03p'] = $producto['descuento_maximo_03p'] ?? 0;
            }
            
            // Si hay precio_venta, también actualizar POIVPR
            if (isset($producto['precio_venta']) && $producto['precio_venta'] > 0) {
                $data['POIVPR'] = $producto['precio_venta'];
            }
            
            if ($productoExistente) {
                // Actualizar producto existente
                \DB::table('productos')
                    ->where('KOPR', $producto['codigo_producto'])
                    ->update($data);
            } else {
                // Crear nuevo producto
                $data['KOPR'] = $producto['codigo_producto'];
                $data['TIPR'] = null; // Se actualizará en sincronización completa
                $data['POIVPR'] = 0; // Se actualizará en sincronización completa
                $data['activo'] = true;
                $data['created_at'] = now();
                \DB::table('productos')->insert($data);
                Log::info('✅ Producto creado en tabla productos: ' . $producto['codigo_producto']);
            }
        } catch (\Exception $e) {
            Log::warning('No se pudo actualizar/crear tabla productos para ' . $producto['codigo_producto'] . ': ' . $e->getMessage());
        }
        
        return $stockLocal;
    }
    
    /**
     * Validar stock para una cotización
     */
    public function validarStockCotizacion($productos)
    {
        $resultado = [
            'valida' => true,
            'productos_sin_stock' => [],
            'productos_insuficientes' => [],
            'mensajes' => []
        ];
        
        foreach ($productos as $producto) {
            $stockLocal = StockLocal::where('codigo_producto', $producto['codigo'])
                                   ->where('codigo_bodega', 'LIB')
                                   ->first();
            
            if (!$stockLocal) {
                $resultado['productos_sin_stock'][] = $producto['codigo'];
                $resultado['valida'] = false;
                $resultado['mensajes'][] = "Producto {$producto['codigo']} no encontrado en stock local";
                continue;
            }
            
            if (!$stockLocal->tieneStockSuficiente($producto['cantidad'])) {
                $resultado['productos_insuficientes'][] = [
                    'codigo' => $producto['codigo'],
                    'nombre' => $stockLocal->nombre_producto,
                    'stock_disponible' => $stockLocal->stock_disponible,
                    'cantidad_solicitada' => $producto['cantidad']
                ];
                $resultado['valida'] = false;
                $resultado['mensajes'][] = "Stock insuficiente para {$producto['codigo']}: disponible {$stockLocal->stock_disponible}, solicitado {$producto['cantidad']}";
            }
        }
        
        return $resultado;
    }
    
    /**
     * Validar cliente para cotización
     */
    public function validarClienteParaCotizacion($codigoCliente)
    {
        try {
            // Por ahora, validación simple sin depender de métodos que no existen
            $resultado = [
                'valido' => true,
                'alertas' => [],
                'mensajes' => []
            ];
            
            // Aquí se pueden agregar validaciones específicas del cliente
            // Por ejemplo, verificar si está bloqueado, etc.
            
            return $resultado;
            
        } catch (\Exception $e) {
            Log::error('Error validando cliente: ' . $e->getMessage());
            return [
                'valido' => false,
                'alertas' => [],
                'mensajes' => ['Error validando cliente']
            ];
        }
    }
    
    /**
     * Reservar stock para cotización
     */
    public function reservarStockCotizacion($productos)
    {
        $reservas = [];
        
        foreach ($productos as $producto) {
            $stockLocal = StockLocal::where('codigo_producto', $producto['codigo'])
                                   ->where('codigo_bodega', 'LIB')
                                   ->first();
            
            if ($stockLocal && $stockLocal->reservarStock($producto['cantidad'])) {
                $reservas[] = [
                    'producto' => $producto['codigo'],
                    'cantidad' => $producto['cantidad'],
                    'reservado' => true
                ];
            } else {
                $reservas[] = [
                    'producto' => $producto['codigo'],
                    'cantidad' => $producto['cantidad'],
                    'reservado' => false,
                    'error' => 'No se pudo reservar stock'
                ];
            }
        }
        
        return $reservas;
    }
    
    /**
     * Liberar stock reservado
     */
    public function liberarStockCotizacion($productos)
    {
        foreach ($productos as $producto) {
            $stockLocal = StockLocal::where('codigo_producto', $producto['codigo'])
                                   ->where('codigo_bodega', 'LIB')
                                   ->first();
            
            if ($stockLocal) {
                $stockLocal->liberarStock($producto['cantidad']);
            }
        }
    }
    
    /**
     * Limpiar nombre del producto removiendo información adicional como "Múltiplo: X" y unidades
     */
    private function limpiarNombreProducto($nombreProducto)
    {
        if (empty($nombreProducto)) {
            return $nombreProducto;
        }
        
        $nombreLimpio = $nombreProducto;
        
        // Remover patrones entre corchetes como "[30Unid.X.Paq]Múltiplo: 30"
        $nombreLimpio = preg_replace('/\[[^\]]*\]\s*[Mm][úu]?ltiplo:\s*\d+.*$/i', '', $nombreLimpio);
        // Remover patrones entre corchetes como "[30Unid.X.Paq]"
        $nombreLimpio = preg_replace('/\[[^\]]*\]/i', '', $nombreLimpio);
        
        // Remover patrones como "KILOMúltiplo: 20" (sin espacio antes de Múltiplo)
        $nombreLimpio = preg_replace('/[Kk][Ii][Ll][Oo][Mm][úu]?ltiplo:\s*\d+.*$/i', '', $nombreLimpio);
        
        // Remover patrones como "xxxxxxxmultiplo: X" o "xxxxxmultiplo: X" al final (case insensitive)
        $nombreLimpio = preg_replace('/\s*xxxxxxx?multiplo:\s*\d+.*$/i', '', $nombreLimpio);
        
        // Remover "Múltiplo: X" o "multiplo: X" al final (con o sin acento, case insensitive)
        $nombreLimpio = preg_replace('/\s*[Mm][úu]ltiplo:\s*\d+.*$/i', '', $nombreLimpio);
        
        // Remover "MULTIPLO: X" al final
        $nombreLimpio = preg_replace('/\s*MULTIPLO:\s*\d+.*$/i', '', $nombreLimpio);
        
        // Remover "UN.Múltiplo: X" o "UN.MULTIPLO: X" al final
        $nombreLimpio = preg_replace('/\s*UN\.\s*[Mm][úu]?ltiplo:\s*\d+.*$/i', '', $nombreLimpio);
        
        // Remover patrones como "Unidad [30Unid.X.Paq]" o "Unidad [30Unid.X.Paq]Múltiplo: 30"
        $nombreLimpio = preg_replace('/\s*[Uu]nidad\s*\[[^\]]*\].*$/i', '', $nombreLimpio);
        
        // Remover la palabra "adicional" si aparece
        $nombreLimpio = preg_replace('/\s*adicional\s*/i', ' ', $nombreLimpio);
        
        // Limpiar espacios múltiples y recortar
        $nombreLimpio = preg_replace('/\s+/', ' ', trim($nombreLimpio));
        
        return $nombreLimpio;
    }
} 