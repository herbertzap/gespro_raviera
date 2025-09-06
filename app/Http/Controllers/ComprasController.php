<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ComprasController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('role:Compras|Super Admin');
    }

    /**
     * Mostrar el índice de compras
     */
    public function index()
    {
        $user = auth()->user();
        
        // Obtener productos con bajo stock
        $productosBajoStock = $this->getProductosBajoStock();
        
        // Obtener resumen de compras
        $resumenCompras = $this->getResumenCompras();
        
        // Obtener compras recientes
        $comprasRecientes = $this->getComprasRecientes();
        
        // Obtener proveedores
        $proveedores = $this->getProveedores();

        return view('compras.index', compact(
            'productosBajoStock',
            'resumenCompras', 
            'comprasRecientes',
            'proveedores'
        ))->with('pageSlug', 'compras');
    }

    /**
     * Mostrar productos con bajo stock
     */
    public function productosBajoStock()
    {
        $productos = $this->getProductosBajoStock();
        
        return view('compras.productos-bajo-stock', compact('productos'))
            ->with('pageSlug', 'compras');
    }

    /**
     * Mostrar historial de compras
     */
    public function historial(Request $request)
    {
        $filtros = [
            'fecha_desde' => $request->get('fecha_desde', date('Y-m-01')),
            'fecha_hasta' => $request->get('fecha_hasta', date('Y-m-d')),
            'proveedor' => $request->get('proveedor', ''),
            'estado' => $request->get('estado', ''),
            'buscar' => $request->get('buscar', '')
        ];

        $compras = $this->getComprasConFiltros($filtros);
        
        return view('compras.historial', compact('compras', 'filtros'))
            ->with('pageSlug', 'compras');
    }

    /**
     * Crear nueva orden de compra
     */
    public function crear()
    {
        $productosBajoStock = $this->getProductosBajoStock();
        $proveedores = $this->getProveedores();
        
        return view('compras.crear', compact('productosBajoStock', 'proveedores'))
            ->with('pageSlug', 'compras');
    }

    /**
     * Guardar nueva orden de compra
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'proveedor' => 'required|string|max:255',
            'fecha_compra' => 'required|date',
            'productos' => 'required|array|min:1',
            'productos.*.codigo' => 'required|string',
            'productos.*.cantidad' => 'required|integer|min:1',
            'productos.*.precio' => 'required|numeric|min:0',
            'observaciones' => 'nullable|string|max:1000'
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            DB::connection('sqlsrv')->beginTransaction();

            // Generar número de orden de compra
            $numeroOrden = $this->generarNumeroOrden();
            
            // Calcular total
            $total = 0;
            foreach ($request->productos as $producto) {
                $total += $producto['cantidad'] * $producto['precio'];
            }

            // Insertar orden de compra
            $ordenId = DB::connection('sqlsrv')->table('ORDENES_COMPRA')->insertGetId([
                'NUMERO_ORDEN' => $numeroOrden,
                'PROVEEDOR' => $request->proveedor,
                'FECHA_COMPRA' => $request->fecha_compra,
                'TOTAL' => $total,
                'ESTADO' => 'PENDIENTE',
                'OBSERVACIONES' => $request->observaciones,
                'USUARIO_CREACION' => auth()->user()->name,
                'FECHA_CREACION' => now()
            ]);

            // Insertar detalles de la orden
            foreach ($request->productos as $producto) {
                DB::connection('sqlsrv')->table('ORDENES_COMPRA_DETALLE')->insert([
                    'ORDEN_ID' => $ordenId,
                    'CODIGO_PRODUCTO' => $producto['codigo'],
                    'CANTIDAD' => $producto['cantidad'],
                    'PRECIO_UNITARIO' => $producto['precio'],
                    'SUBTOTAL' => $producto['cantidad'] * $producto['precio']
                ]);
            }

            DB::connection('sqlsrv')->commit();

            return redirect()->route('compras.historial')
                ->with('success', "Orden de compra {$numeroOrden} creada exitosamente.");

        } catch (\Exception $e) {
            DB::connection('sqlsrv')->rollback();
            
            return redirect()->back()
                ->with('error', 'Error al crear la orden de compra: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Mostrar detalle de una orden de compra
     */
    public function show($id)
    {
        $orden = DB::connection('sqlsrv')
            ->table('ORDENES_COMPRA as oc')
            ->leftJoin('ORDENES_COMPRA_DETALLE as ocd', 'oc.ID', '=', 'ocd.ORDEN_ID')
            ->leftJoin('PRODUCTOS as p', 'ocd.CODIGO_PRODUCTO', '=', 'p.CODIGO_PRODUCTO')
            ->select([
                'oc.*',
                'ocd.CODIGO_PRODUCTO',
                'ocd.CANTIDAD',
                'ocd.PRECIO_UNITARIO',
                'ocd.SUBTOTAL',
                'p.NOMBRE_PRODUCTO'
            ])
            ->where('oc.ID', $id)
            ->get();

        if ($orden->isEmpty()) {
            return redirect()->route('compras.historial')
                ->with('error', 'Orden de compra no encontrada.');
        }

        $ordenPrincipal = $orden->first();
        $detalles = $orden->skip(1);

        return view('compras.show', compact('ordenPrincipal', 'detalles'))
            ->with('pageSlug', 'compras');
    }

    /**
     * Actualizar estado de orden de compra
     */
    public function actualizarEstado(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'estado' => 'required|in:PENDIENTE,APROBADA,RECIBIDA,CANCELADA',
            'observaciones' => 'nullable|string|max:1000'
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator);
        }

        try {
            DB::connection('sqlsrv')->table('ORDENES_COMPRA')
                ->where('ID', $id)
                ->update([
                    'ESTADO' => $request->estado,
                    'OBSERVACIONES' => $request->observaciones,
                    'USUARIO_MODIFICACION' => auth()->user()->name,
                    'FECHA_MODIFICACION' => now()
                ]);

            return redirect()->back()
                ->with('success', 'Estado de la orden actualizado exitosamente.');

        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Error al actualizar el estado: ' . $e->getMessage());
        }
    }

    /**
     * Obtener productos con bajo stock
     */
    private function getProductosBajoStock()
    {
        $productos = DB::connection('sqlsrv')
            ->table('PRODUCTOS')
            ->select([
                'CODIGO_PRODUCTO',
                'NOMBRE_PRODUCTO', 
                'STOCK_ACTUAL',
                'STOCK_MINIMO',
                'PRECIO_COMPRA',
                'PROVEEDOR'
            ])
            ->whereRaw('STOCK_ACTUAL <= STOCK_MINIMO')
            ->orderBy('STOCK_ACTUAL', 'asc')
            ->get();

        return $productos->map(function($producto) {
            return [
                'codigo' => $producto->CODIGO_PRODUCTO,
                'nombre' => $producto->NOMBRE_PRODUCTO,
                'stock_actual' => $producto->STOCK_ACTUAL,
                'stock_minimo' => $producto->STOCK_MINIMO,
                'precio_compra' => $producto->PRECIO_COMPRA,
                'proveedor' => $producto->PROVEEDOR,
                'diferencia' => $producto->STOCK_ACTUAL - $producto->STOCK_MINIMO,
                'estado' => $producto->STOCK_ACTUAL <= 0 ? 'Sin Stock' : 'Bajo Stock'
            ];
        });
    }

    /**
     * Obtener resumen de compras
     */
    private function getResumenCompras()
    {
        $añoActual = date('Y');
        
        $comprasMensuales = DB::connection('sqlsrv')
            ->table('ORDENES_COMPRA')
            ->selectRaw('MONTH(FECHA_COMPRA) as mes, COUNT(*) as cantidad, SUM(TOTAL) as total')
            ->whereYear('FECHA_COMPRA', $añoActual)
            ->groupBy('MONTH(FECHA_COMPRA)')
            ->get();

        $totalCompras = DB::connection('sqlsrv')
            ->table('ORDENES_COMPRA')
            ->whereYear('FECHA_COMPRA', $añoActual)
            ->count();

        $totalMontoCompras = DB::connection('sqlsrv')
            ->table('ORDENES_COMPRA')
            ->whereYear('FECHA_COMPRA', $añoActual)
            ->sum('TOTAL');

        $productosBajoStock = DB::connection('sqlsrv')
            ->table('PRODUCTOS')
            ->whereRaw('STOCK_ACTUAL <= STOCK_MINIMO')
            ->count();

        return [
            'año_actual' => $añoActual,
            'total_compras' => $totalCompras,
            'total_monto' => $totalMontoCompras,
            'compras_mensuales' => $comprasMensuales,
            'productos_bajo_stock' => $productosBajoStock,
            'promedio_mensual' => $totalCompras > 0 ? round($totalCompras / 12, 2) : 0
        ];
    }

    /**
     * Obtener compras recientes
     */
    private function getComprasRecientes()
    {
        return DB::connection('sqlsrv')
            ->table('ORDENES_COMPRA')
            ->select([
                'ID',
                'NUMERO_ORDEN',
                'PROVEEDOR',
                'FECHA_COMPRA',
                'TOTAL',
                'ESTADO'
            ])
            ->orderBy('FECHA_COMPRA', 'desc')
            ->limit(10)
            ->get();
    }

    /**
     * Obtener proveedores
     */
    private function getProveedores()
    {
        return DB::connection('sqlsrv')
            ->table('PRODUCTOS')
            ->select('PROVEEDOR')
            ->whereNotNull('PROVEEDOR')
            ->where('PROVEEDOR', '!=', '')
            ->distinct()
            ->orderBy('PROVEEDOR')
            ->pluck('PROVEEDOR');
    }

    /**
     * Obtener compras con filtros
     */
    private function getComprasConFiltros($filtros)
    {
        $query = DB::connection('sqlsrv')
            ->table('ORDENES_COMPRA')
            ->select([
                'ID',
                'NUMERO_ORDEN',
                'PROVEEDOR',
                'FECHA_COMPRA',
                'TOTAL',
                'ESTADO',
                'OBSERVACIONES'
            ]);

        if (!empty($filtros['fecha_desde'])) {
            $query->whereDate('FECHA_COMPRA', '>=', $filtros['fecha_desde']);
        }

        if (!empty($filtros['fecha_hasta'])) {
            $query->whereDate('FECHA_COMPRA', '<=', $filtros['fecha_hasta']);
        }

        if (!empty($filtros['proveedor'])) {
            $query->where('PROVEEDOR', 'like', '%' . $filtros['proveedor'] . '%');
        }

        if (!empty($filtros['estado'])) {
            $query->where('ESTADO', $filtros['estado']);
        }

        if (!empty($filtros['buscar'])) {
            $query->where(function($q) use ($filtros) {
                $q->where('NUMERO_ORDEN', 'like', '%' . $filtros['buscar'] . '%')
                  ->orWhere('PROVEEDOR', 'like', '%' . $filtros['buscar'] . '%');
            });
        }

        return $query->orderBy('FECHA_COMPRA', 'desc')->paginate(20);
    }

    /**
     * Generar número de orden de compra
     */
    private function generarNumeroOrden()
    {
        $año = date('Y');
        $mes = date('m');
        
        $ultimaOrden = DB::connection('sqlsrv')
            ->table('ORDENES_COMPRA')
            ->where('NUMERO_ORDEN', 'like', "OC{$año}{$mes}%")
            ->orderBy('NUMERO_ORDEN', 'desc')
            ->first();

        if ($ultimaOrden) {
            $ultimoNumero = (int) substr($ultimaOrden->NUMERO_ORDEN, -4);
            $nuevoNumero = $ultimoNumero + 1;
        } else {
            $nuevoNumero = 1;
        }

        return "OC{$año}{$mes}" . str_pad($nuevoNumero, 4, '0', STR_PAD_LEFT);
    }
}
