<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\StockConsultaService;
use App\Services\StockComprometidoService;

class CotizacionBusquedaMejoradaController extends Controller
{
    protected $stockConsultaService;
    protected $stockComprometidoService;

    public function __construct()
    {
        $this->stockConsultaService = new StockConsultaService();
        $this->stockComprometidoService = new StockComprometidoService();
    }

    /**
     * Búsqueda mejorada de productos con consulta a SQL Server para stock
     */
    public function buscarProductos(Request $request)
    {
        try {
            // Aceptar diferentes nombres de parámetros para la búsqueda
            $busqueda = $request->get('busqueda') ?? 
                       $request->get('q') ?? 
                       $request->get('search') ?? 
                       $request->get('term') ?? 
                       $request->get('termino') ?? '';
            
            // Obtener lista de precios del cliente
            $listaPrecios = $request->get('lista_precios', '01');
            
            if (empty($busqueda)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Debe proporcionar un término de búsqueda'
                ]);
            }
            
            // Validar longitud mínima para búsqueda
            if (strlen($busqueda) < 3) {
                return response()->json([
                    'success' => false,
                    'message' => 'Debe ingresar al menos 3 caracteres para buscar'
                ]);
            }
            
            // Validar que se proporcione una lista de precios válida
            if (empty($listaPrecios) || $listaPrecios === '00' || $listaPrecios === '0') {
                $listaPrecios = '01P';
            }
            
            // Dividir la búsqueda en términos individuales
            $terminos = array_filter(explode(' ', trim($busqueda)));
            
            // PASO 1: Buscar productos en tabla local MySQL (consulta optimizada)
            $query = DB::table('productos')->where('activo', true);
            
            if (count($terminos) > 1) {
                // Búsqueda con múltiples términos: todos los términos deben estar en el nombre
                $query->where(function($q) use ($terminos) {
                    foreach ($terminos as $termino) {
                        $q->where('NOKOPR', 'LIKE', "%{$termino}%");
                    }
                });
            } else {
                // Búsqueda simple: por código o nombre
                $query->where(function($q) use ($busqueda) {
                    $q->where('KOPR', 'LIKE', "{$busqueda}%")
                      ->orWhere('NOKOPR', 'LIKE', "%{$busqueda}%");
                });
            }
            
            $productosMySQL = $query->limit(15)->get();
            
            if ($productosMySQL->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontraron productos con el término de búsqueda: ' . $busqueda
                ]);
            }
            
            // PASO 2: Extraer códigos de productos encontrados
            $codigosProductos = $productosMySQL->pluck('KOPR')->toArray();
            
            // PASO 3: Consultar stock desde SQL Server (con cache) solo para productos encontrados
            $stocksSQL = $this->stockConsultaService->consultarStockDesdeSQLServer($codigosProductos);
            
            // PASO 4: Procesar productos y actualizar stock si es diferente
            $productos = [];
            
            foreach ($productosMySQL as $producto) {
                $codigo = $producto->KOPR;
                
                // Obtener stock desde SQL Server (si está disponible)
                $stockSQL = $stocksSQL[$codigo] ?? null;
                
                if ($stockSQL) {
                    // Comparar y actualizar stock si es diferente
                    $this->stockConsultaService->actualizarStockSiEsDiferente(
                        $codigo,
                        $stockSQL['stock_fisico'],
                        $stockSQL['stock_comprometido']
                    );
                    
                    // Usar valores actualizados desde MySQL (ya fueron actualizados)
                    $productoActualizado = DB::table('productos')->where('KOPR', $codigo)->first();
                    $stockFisico = (float)($productoActualizado->stock_fisico ?? 0);
                    $stockComprometido = (float)($productoActualizado->stock_comprometido ?? 0);
                } else {
                    // Si no hay stock en SQL Server, usar valores de MySQL
                    $stockFisico = (float)($producto->stock_fisico ?? 0);
                    $stockComprometido = (float)($producto->stock_comprometido ?? 0);
                }
                
                // Mapear precios según la lista
                $precio = 0;
                $precioUd2 = 0;
                $descuentoMaximo = 0;
                
                if ($listaPrecios === '01P' || $listaPrecios === '01') {
                    $precio = $producto->precio_01p ?? 0;
                    $precioUd2 = $producto->precio_01p_ud2 ?? 0;
                    $descuentoMaximo = $producto->descuento_maximo_01p ?? 0;
                } elseif ($listaPrecios === '02P' || $listaPrecios === '02') {
                    $precio = $producto->precio_02p ?? 0;
                    $precioUd2 = $producto->precio_02p_ud2 ?? 0;
                    $descuentoMaximo = $producto->descuento_maximo_02p ?? 0;
                } elseif ($listaPrecios === '03P' || $listaPrecios === '03') {
                    $precio = $producto->precio_03p ?? 0;
                    $precioUd2 = $producto->precio_03p_ud2 ?? 0;
                    $descuentoMaximo = $producto->descuento_maximo_03p ?? 0;
                } else {
                    $precio = $producto->precio_01p ?? 0;
                    $precioUd2 = $producto->precio_01p_ud2 ?? 0;
                    $descuentoMaximo = $producto->descuento_maximo_01p ?? 0;
                }
                
                // Calcular stock real considerando stock comprometido local (NVV)
                $stockReal = $this->stockComprometidoService->obtenerStockDisponibleReal($codigo);
                
                $precioValido = ($precio > 0);
                
                // Solo agregar productos con precio mayor a 0
                if ($precio > 0) {
                    $productos[] = [
                        'CODIGO_PRODUCTO' => $codigo,
                        'NOMBRE_PRODUCTO' => $producto->NOKOPR,
                        'UNIDAD_MEDIDA' => $producto->UD01PR,
                        'PRECIO_UD1' => $precio,
                        'PRECIO_UD2' => $precioUd2,
                        'DESCUENTO_MAXIMO' => $descuentoMaximo,
                        'STOCK_DISPONIBLE' => $stockReal,
                        'STOCK_DISPONIBLE_REAL' => $stockReal,
                        'STOCK_FISICO' => $stockFisico,
                        'STOCK_COMPROMETIDO' => $stockComprometido,
                        'CANTIDAD_MINIMA' => 1,
                        'MULTIPLO_VENTA' => $producto->multiplo_venta ?? 1,
                        'LISTA_PRECIOS' => $listaPrecios,
                        'PRECIO_VALIDO' => $precioValido,
                        'MOTIVO_BLOQUEO' => $precioValido ? null : 'Precio no disponible',
                        'TIENE_STOCK' => $stockReal > 0,
                        'STOCK_INSUFICIENTE' => $stockReal < 1,
                        'CLASE_STOCK' => $stockReal <= 0 ? 'text-danger' : ($stockReal < 10 ? 'text-warning' : 'text-success'),
                        'ESTADO_STOCK' => $stockReal <= 0 ? 'Sin stock' : ($stockReal < 10 ? 'Stock bajo' : 'Stock disponible')
                    ];
                }
            }
            
            // Si después de filtrar no quedan productos, retornar error
            if (empty($productos)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontraron productos con precio disponible para el término de búsqueda: ' . $busqueda
                ]);
            }
            
            Log::info('Búsqueda mejorada completada: ' . count($productos) . ' productos encontrados (productos con precio 0 excluidos)');
            
            return response()->json([
                'success' => true,
                'data' => $productos,
                'total' => count($productos),
                'search_term' => $busqueda,
                'method' => 'mejorada' // Indicador de que usó el método mejorado
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error en búsqueda mejorada de productos: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al buscar productos: ' . $e->getMessage()
            ], 500);
        }
    }
}

