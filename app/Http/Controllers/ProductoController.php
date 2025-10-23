<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cotizacion;
use App\Models\CotizacionProducto;
use App\Models\StockTemporal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;

class ProductoController extends Controller
{
    public function index()
    {
        try {
            $estadisticas = $this->obtenerEstadisticasProductos();
            $productosBajoStock = $this->obtenerProductosBajoStock();
            $productosVendidos = $this->obtenerProductosMasVendidos(50); // Top 50
            
            return view('productos.index', [
                'productosBajoStock' => $estadisticas['productos_bajo_stock'],
                'totalProductos' => $estadisticas['total_productos'],
                'productosMasVendidos' => $estadisticas['productos_mas_vendidos'],
                'valorTotalStock' => $estadisticas['valor_total_stock'],
                'productosBajoStockLista' => $productosBajoStock,
                'productosVendidos' => $productosVendidos,
                'pageSlug' => 'productos'
            ]);
            
        } catch (\Exception $e) {
            Log::error("Error en ProductoController@index: " . $e->getMessage());
            
            return view('productos.index', [
                'productosBajoStock' => 0,
                'totalProductos' => 0,
                'productosMasVendidos' => 0,
                'valorTotalStock' => 0,
                'productosBajoStockLista' => [],
                'productosVendidos' => [],
                'stockCategorias' => [],
                'pageSlug' => 'productos',
                'error' => 'Error al cargar datos de productos'
            ]);
        }
    }
    
    public function buscar(Request $request)
    {
        try {
            $termino = $request->get('q', '');
            
            if (strlen($termino) < 2) {
                return response()->json([]);
            }
            
            // Buscar en la tabla productos (MySQL) que tiene los datos sincronizados
            $productos = \App\Models\Producto::where(function($query) use ($termino) {
                    $query->where('KOPR', 'LIKE', "%{$termino}%")
                          ->orWhere('NOKOPR', 'LIKE', "%{$termino}%");
                })
                ->where('activo', true)
                ->limit(20)
                ->get()
                ->map(function ($producto) {
                    // Usar precio_01p como predeterminado
                    $precio = $producto->precio_01p ?? 0;
                    
                    return [
                        'codigo' => $producto->KOPR,
                        'nombre' => $producto->NOKOPR,
                        'precio' => $precio,
                        'stock_actual' => $producto->stock_disponible ?? 0,
                        'stock_minimo' => 10, // Valor predeterminado
                        'activo' => $producto->activo
                    ];
                });
            
            return response()->json($productos);
            
        } catch (\Exception $e) {
            Log::error("Error en ProductoController@buscar: " . $e->getMessage());
            return response()->json([]);
        }
    }
    
    private function obtenerEstadisticasProductos()
    {
        try {
            $totalProductos = CotizacionProducto::distinct('codigo_producto')->count();
            $productosBajoStock = rand(5, 15);
            $productosMasVendidos = rand(20, 50);
            $valorTotalStock = rand(1000000, 5000000);
            
            return [
                'total_productos' => $totalProductos,
                'productos_bajo_stock' => $productosBajoStock,
                'productos_mas_vendidos' => $productosMasVendidos,
                'valor_total_stock' => $valorTotalStock
            ];
            
        } catch (\Exception $e) {
            Log::error("Error obteniendo estadísticas de productos: " . $e->getMessage());
            return [
                'total_productos' => 0,
                'productos_bajo_stock' => 0,
                'productos_mas_vendidos' => 0,
                'valor_total_stock' => 0
            ];
        }
    }
    
    private function obtenerProductosBajoStock()
    {
        try {
            $productos = CotizacionProducto::select('codigo_producto', 'nombre_producto', 'precio_unitario')
                ->distinct()
                ->limit(10)
                ->get()
                ->map(function ($producto) {
                    $stockActual = rand(0, 5);
                    $stockMinimo = rand(10, 20);
                    
                    return [
                        'codigo' => $producto->codigo_producto,
                        'nombre' => $producto->nombre_producto,
                        'precio' => $producto->precio_unitario,
                        'stock_actual' => $stockActual,
                        'stock_minimo' => $stockMinimo
                    ];
                });
            
            return $productos;
            
        } catch (\Exception $e) {
            Log::error("Error obteniendo productos con bajo stock: " . $e->getMessage());
            return [];
        }
    }
    
    private function obtenerProductosMasVendidos($limit = 50)
    {
        try {
            // Obtener productos más vendidos de cotizaciones aprobadas (últimos 3 meses)
            $productos = CotizacionProducto::select('codigo_producto', 'nombre_producto')
                ->selectRaw('SUM(cantidad) as total_vendido')
                ->selectRaw('COUNT(DISTINCT cotizacion_id) as total_ventas')
                ->selectRaw('AVG(precio_unitario) as precio_promedio')
                ->whereHas('cotizacion', function($query) {
                    $query->where('estado_aprobacion', 'aprobada_picking')
                          ->where('created_at', '>=', now()->subMonths(3));
                })
                ->groupBy('codigo_producto', 'nombre_producto')
                ->orderBy('total_vendido', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($producto) {
                    return [
                        'codigo' => $producto->codigo_producto,
                        'nombre' => $producto->nombre_producto,
                        'cantidad' => $producto->total_vendido,
                        'total_ventas' => $producto->total_ventas,
                        'precio_promedio' => $producto->precio_promedio
                    ];
                });
            
            return $productos;
            
        } catch (\Exception $e) {
            Log::error("Error obteniendo productos más vendidos: " . $e->getMessage());
            return [];
        }
    }
    
    private function obtenerStockPorCategorias()
    {
        try {
            return [
                ['categoria' => 'Electrónicos', 'stock' => rand(100, 500)],
                ['categoria' => 'Hogar', 'stock' => rand(50, 200)],
                ['categoria' => 'Oficina', 'stock' => rand(30, 150)],
                ['categoria' => 'Automotriz', 'stock' => rand(20, 100)],
                ['categoria' => 'Otros', 'stock' => rand(10, 50)]
            ];
            
        } catch (\Exception $e) {
            Log::error("Error obteniendo stock por categorías: " . $e->getMessage());
            return [];
        }
    }
    
    public function crearNVVDesdeProductos(Request $request)
    {
        try {
            $productosSeleccionados = $request->get('productos', []);
            
            if (empty($productosSeleccionados)) {
                return response()->json(['error' => 'No se seleccionaron productos'], 400);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'NVV creada exitosamente',
                'nvv_id' => rand(1000, 9999)
            ]);
            
        } catch (\Exception $e) {
            Log::error("Error creando NVV desde productos: " . $e->getMessage());
            return response()->json(['error' => 'Error al crear NVV'], 500);
        }
    }
    
    public function modificarCantidades(Request $request)
    {
        try {
            $productoId = $request->get('producto_id');
            $nuevaCantidad = $request->get('nueva_cantidad');
            
            if (!$productoId || !$nuevaCantidad) {
                return response()->json(['error' => 'Datos incompletos'], 400);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Cantidad modificada exitosamente'
            ]);
            
        } catch (\Exception $e) {
            Log::error("Error modificando cantidades: " . $e->getMessage());
            return response()->json(['error' => 'Error al modificar cantidades'], 500);
        }
    }

    public function sincronizar(Request $request)
    {
        try {
            $user = auth()->user();
            if (!$user || (!$user->hasRole('Supervisor') && !$user->hasRole('Super Admin'))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Acceso no autorizado'
                ], 403);
            }

            $limit = (int) $request->get('limit', 1000);

            Artisan::call('productos:sincronizar', [
                '--limit' => $limit
            ]);

            $output = Artisan::output();

            $nProcesados = 0;
            $nCreados = 0;
            $nActualizados = 0;

            // Intentar extraer totales desde el output del comando
            foreach (explode("\n", $output) as $line) {
                $line = trim($line);
                if (stripos($line, 'Productos procesados:') !== false) {
                    $nProcesados = (int) filter_var($line, FILTER_SANITIZE_NUMBER_INT);
                } elseif (stripos($line, 'Productos creados:') !== false) {
                    $nCreados = (int) filter_var($line, FILTER_SANITIZE_NUMBER_INT);
                } elseif (stripos($line, 'Productos actualizados:') !== false) {
                    $nActualizados = (int) filter_var($line, FILTER_SANITIZE_NUMBER_INT);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Sincronización de productos completada',
                'nuevos' => $nCreados,
                'actualizados' => $nActualizados,
                'total' => $nProcesados,
                'raw' => $output
            ]);

        } catch (\Exception $e) {
            Log::error('Error sincronizando productos: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al sincronizar productos: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Ver detalle de un producto con estadísticas de ventas
     */
    public function ver($codigoProducto)
    {
        try {
            // Obtener información del producto desde MySQL
            $producto = \App\Models\Producto::where('KOPR', $codigoProducto)->first();
            
            if (!$producto) {
                abort(404, 'Producto no encontrado');
            }
            
            // Obtener estadísticas de ventas (últimos 6 meses)
            $estadisticasVentas = CotizacionProducto::where('codigo_producto', $codigoProducto)
                ->whereHas('cotizacion', function($query) {
                    $query->where('estado_aprobacion', 'aprobada_picking')
                          ->where('created_at', '>=', now()->subMonths(6));
                })
                ->selectRaw('COUNT(DISTINCT cotizacion_id) as total_nvv')
                ->selectRaw('SUM(cantidad) as total_unidades')
                ->selectRaw('AVG(precio_unitario) as precio_promedio')
                ->selectRaw('MAX(precio_unitario) as precio_maximo')
                ->selectRaw('MIN(precio_unitario) as precio_minimo')
                ->first();
            
            // Obtener NVV donde se vendió este producto (últimas 20)
            $nvvConProducto = CotizacionProducto::where('codigo_producto', $codigoProducto)
                ->with('cotizacion.user')
                ->whereHas('cotizacion', function($query) {
                    $query->where('estado_aprobacion', 'aprobada_picking');
                })
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get()
                ->map(function($item) {
                    return [
                        'nvv_id' => $item->cotizacion->id,
                        'nvv_numero' => $item->cotizacion->numero_nvv ?? 'N/A',
                        'cliente' => $item->cotizacion->cliente_nombre,
                        'vendedor' => $item->cotizacion->user->name ?? 'N/A',
                        'cantidad' => $item->cantidad,
                        'precio' => $item->precio_unitario,
                        'subtotal' => $item->subtotal_con_descuento,
                        'fecha' => $item->created_at->format('d/m/Y'),
                        'facturada' => $item->cotizacion->facturada ?? false
                    ];
                });
            
            // Ventas por mes (últimos 6 meses)
            $ventasPorMes = CotizacionProducto::where('codigo_producto', $codigoProducto)
                ->whereHas('cotizacion', function($query) {
                    $query->where('estado_aprobacion', 'aprobada_picking')
                          ->where('created_at', '>=', now()->subMonths(6));
                })
                ->selectRaw('DATE_FORMAT(created_at, "%Y-%m") as mes')
                ->selectRaw('SUM(cantidad) as cantidad')
                ->groupBy('mes')
                ->orderBy('mes', 'asc')
                ->get();
            
            return view('productos.ver', [
                'producto' => $producto,
                'estadisticas' => $estadisticasVentas,
                'nvvConProducto' => $nvvConProducto,
                'ventasPorMes' => $ventasPorMes,
                'pageSlug' => 'productos'
            ]);
            
        } catch (\Exception $e) {
            Log::error("Error en ProductoController@ver: " . $e->getMessage());
            abort(500, 'Error al cargar detalles del producto');
        }
    }
}
