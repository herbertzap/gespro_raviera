<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PickingController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('role:Picking|Super Admin');
    }

    /**
     * Mostrar el índice de picking
     */
    public function index()
    {
        $user = auth()->user();
        
        // Obtener pedidos pendientes de preparación
        $pedidosPendientes = $this->getPedidosPendientesPicking();
        
        // Obtener pedidos en preparación
        $pedidosEnPreparacion = $this->getPedidosEnPreparacion();
        
        // Obtener resumen de picking del día
        $resumenPicking = $this->getResumenPickingDia();
        
        // Obtener productos con stock insuficiente
        $productosStockInsuficiente = $this->getProductosStockInsuficiente();

        return view('picking.index', compact(
            'pedidosPendientes',
            'pedidosEnPreparacion',
            'resumenPicking',
            'productosStockInsuficiente'
        ))->with('pageSlug', 'picking');
    }

    /**
     * Mostrar pedidos pendientes de preparación
     */
    public function pendientes()
    {
        $pedidos = $this->getPedidosPendientesPicking();
        
        return view('picking.pendientes', compact('pedidos'))
            ->with('pageSlug', 'picking');
    }

    /**
     * Mostrar pedidos en preparación
     */
    public function enPreparacion()
    {
        $pedidos = $this->getPedidosEnPreparacion();
        
        return view('picking.en-preparacion', compact('pedidos'))
            ->with('pageSlug', 'picking');
    }

    /**
     * Iniciar preparación de un pedido
     */
    public function iniciarPreparacion(Request $request, $numeroNvv)
    {
        $validator = Validator::make($request->all(), [
            'preparador' => 'required|string|max:255'
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator);
        }

        try {
            // Verificar que el pedido esté pendiente
            $pedido = DB::connection('sqlsrv')
                ->table('NOTAS_VENTA')
                ->where('NUMERO_NVV', $numeroNvv)
                ->where('ESTADO_PICKING', 'PENDIENTE')
                ->first();

            if (!$pedido) {
                return redirect()->back()
                    ->with('error', 'El pedido no está disponible para preparación.');
            }

            // Actualizar estado del pedido
            DB::connection('sqlsrv')
                ->table('NOTAS_VENTA')
                ->where('NUMERO_NVV', $numeroNvv)
                ->update([
                    'ESTADO_PICKING' => 'EN_PREPARACION',
                    'PREPARADOR' => $request->preparador,
                    'FECHA_INICIO_PICKING' => now()
                ]);

            return redirect()->route('picking.en-preparacion')
                ->with('success', "Preparación del pedido {$numeroNvv} iniciada exitosamente.");

        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Error al iniciar la preparación: ' . $e->getMessage());
        }
    }

    /**
     * Mostrar detalle de un pedido para preparación
     */
    public function preparar($numeroNvv)
    {
        // Obtener información del pedido
        $pedido = DB::connection('sqlsrv')
            ->table('NOTAS_VENTA')
            ->where('NUMERO_NVV', $numeroNvv)
            ->first();

        if (!$pedido) {
            return redirect()->route('picking.pendientes')
                ->with('error', 'Pedido no encontrado.');
        }

        // Obtener detalle del pedido
        $detalle = DB::connection('sqlsrv')
            ->table('NVV_DETALLE as d')
            ->join('PRODUCTOS as p', 'd.CODIGO_PRODUCTO', '=', 'p.CODIGO_PRODUCTO')
            ->select([
                'd.CODIGO_PRODUCTO',
                'd.CANTIDAD_SOLICITADA',
                'd.PRECIO_UNITARIO',
                'd.SUBTOTAL',
                'p.NOMBRE_PRODUCTO',
                'p.STOCK_ACTUAL',
                'p.UBICACION_BODEGA'
            ])
            ->where('d.NUMERO_NVV', $numeroNvv)
            ->get();

        // Verificar stock disponible
        $productosSinStock = $detalle->filter(function($item) {
            return $item->STOCK_ACTUAL < $item->CANTIDAD_SOLICITADA;
        });

        return view('picking.preparar', compact('pedido', 'detalle', 'productosSinStock'))
            ->with('pageSlug', 'picking');
    }

    /**
     * Completar preparación de un pedido
     */
    public function completarPreparacion(Request $request, $numeroNvv)
    {
        $validator = Validator::make($request->all(), [
            'estado' => 'required|in:COMPLETADO,PARCIAL',
            'cantidad_bultos' => 'required_if:estado,COMPLETADO|integer|min:1',
            'motivo_parcial' => 'required_if:estado,PARCIAL|string|max:500',
            'observaciones' => 'nullable|string|max:1000'
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator);
        }

        try {
            DB::connection('sqlsrv')->beginTransaction();

            // Actualizar estado del pedido
            $updateData = [
                'ESTADO_PICKING' => $request->estado,
                'FECHA_COMPLETADO_PICKING' => now(),
                'OBSERVACIONES_PICKING' => $request->observaciones
            ];

            if ($request->estado === 'COMPLETADO') {
                $updateData['CANTIDAD_BULTOS'] = $request->cantidad_bultos;
            } else {
                $updateData['MOTIVO_PARCIAL'] = $request->motivo_parcial;
            }

            DB::connection('sqlsrv')
                ->table('NOTAS_VENTA')
                ->where('NUMERO_NVV', $numeroNvv)
                ->update($updateData);

            // Si está completado, actualizar stock
            if ($request->estado === 'COMPLETADO') {
                $this->actualizarStockPedido($numeroNvv);
            }

            DB::connection('sqlsrv')->commit();

            $mensaje = $request->estado === 'COMPLETADO' 
                ? "Pedido {$numeroNvv} completado exitosamente."
                : "Pedido {$numeroNvv} marcado como parcial.";

            return redirect()->route('picking.en-preparacion')
                ->with('success', $mensaje);

        } catch (\Exception $e) {
            DB::connection('sqlsrv')->rollback();
            
            return redirect()->back()
                ->with('error', 'Error al completar la preparación: ' . $e->getMessage());
        }
    }

    /**
     * Mostrar historial de picking
     */
    public function historial(Request $request)
    {
        $filtros = [
            'fecha_desde' => $request->get('fecha_desde', date('Y-m-01')),
            'fecha_hasta' => $request->get('fecha_hasta', date('Y-m-d')),
            'preparador' => $request->get('preparador', ''),
            'estado' => $request->get('estado', ''),
            'buscar' => $request->get('buscar', '')
        ];

        $pedidos = $this->getPedidosConFiltros($filtros);
        
        return view('picking.historial', compact('pedidos', 'filtros'))
            ->with('pageSlug', 'picking');
    }

    /**
     * Imprimir picking list
     */
    public function imprimirPicking($numeroNvv)
    {
        // Obtener información del pedido
        $pedido = DB::connection('sqlsrv')
            ->table('NOTAS_VENTA')
            ->where('NUMERO_NVV', $numeroNvv)
            ->first();

        if (!$pedido) {
            return redirect()->back()
                ->with('error', 'Pedido no encontrado.');
        }

        // Obtener detalle del pedido ordenado por ubicación
        $detalle = DB::connection('sqlsrv')
            ->table('NVV_DETALLE as d')
            ->join('PRODUCTOS as p', 'd.CODIGO_PRODUCTO', '=', 'p.CODIGO_PRODUCTO')
            ->select([
                'd.CODIGO_PRODUCTO',
                'd.CANTIDAD_SOLICITADA',
                'p.NOMBRE_PRODUCTO',
                'p.UBICACION_BODEGA'
            ])
            ->where('d.NUMERO_NVV', $numeroNvv)
            ->orderBy('p.UBICACION_BODEGA')
            ->get();

        return view('picking.imprimir', compact('pedido', 'detalle'))
            ->with('pageSlug', 'picking');
    }

    /**
     * Obtener pedidos pendientes de preparación
     */
    private function getPedidosPendientesPicking()
    {
        $pedidos = DB::connection('sqlsrv')
            ->table('NOTAS_VENTA')
            ->select([
                'NUMERO_NVV',
                'FECHA_NVV',
                'CODIGO_CLIENTE',
                'NOMBRE_CLIENTE',
                'TOTAL_NVV',
                'ESTADO'
            ])
            ->where('ESTADO', 'APROBADO')
            ->where('ESTADO_PICKING', 'PENDIENTE')
            ->orderBy('FECHA_NVV', 'asc')
            ->get();

        return $pedidos->map(function($pedido) {
            return [
                'numero_nvv' => $pedido->NUMERO_NVV,
                'fecha' => $pedido->FECHA_NVV,
                'cliente_codigo' => $pedido->CODIGO_CLIENTE,
                'cliente_nombre' => $pedido->NOMBRE_CLIENTE,
                'total' => $pedido->TOTAL_NVV,
                'estado' => $pedido->ESTADO,
                'prioridad' => $this->calcularPrioridad($pedido->FECHA_NVV)
            ];
        });
    }

    /**
     * Obtener pedidos en preparación
     */
    private function getPedidosEnPreparacion()
    {
        $pedidos = DB::connection('sqlsrv')
            ->table('NOTAS_VENTA')
            ->select([
                'NUMERO_NVV',
                'FECHA_NVV',
                'CODIGO_CLIENTE',
                'NOMBRE_CLIENTE',
                'TOTAL_NVV',
                'PREPARADOR',
                'FECHA_INICIO_PICKING'
            ])
            ->where('ESTADO_PICKING', 'EN_PREPARACION')
            ->orderBy('FECHA_INICIO_PICKING', 'asc')
            ->get();

        return $pedidos->map(function($pedido) {
            return [
                'numero_nvv' => $pedido->NUMERO_NVV,
                'fecha' => $pedido->FECHA_NVV,
                'cliente_codigo' => $pedido->CODIGO_CLIENTE,
                'cliente_nombre' => $pedido->NOMBRE_CLIENTE,
                'total' => $pedido->TOTAL_NVV,
                'preparador' => $pedido->PREPARADOR,
                'tiempo_transcurrido' => $this->calcularTiempoTranscurrido($pedido->FECHA_INICIO_PICKING)
            ];
        });
    }

    /**
     * Obtener resumen de picking del día
     */
    private function getResumenPickingDia()
    {
        $hoy = date('Y-m-d');
        
        $pedidosCompletados = DB::connection('sqlsrv')
            ->table('NOTAS_VENTA')
            ->whereDate('FECHA_COMPLETADO_PICKING', $hoy)
            ->count();

        $pedidosIniciados = DB::connection('sqlsrv')
            ->table('NOTAS_VENTA')
            ->whereDate('FECHA_INICIO_PICKING', $hoy)
            ->count();

        $pedidosPendientes = DB::connection('sqlsrv')
            ->table('NOTAS_VENTA')
            ->where('ESTADO_PICKING', 'PENDIENTE')
            ->count();

        $tiempoPromedio = DB::connection('sqlsrv')
            ->table('NOTAS_VENTA')
            ->whereDate('FECHA_COMPLETADO_PICKING', $hoy)
            ->whereNotNull('FECHA_INICIO_PICKING')
            ->selectRaw('AVG(DATEDIFF(MINUTE, FECHA_INICIO_PICKING, FECHA_COMPLETADO_PICKING)) as tiempo_promedio')
            ->first();

        return [
            'fecha' => $hoy,
            'pedidos_completados' => $pedidosCompletados,
            'pedidos_iniciados' => $pedidosIniciados,
            'pedidos_pendientes' => $pedidosPendientes,
            'tiempo_promedio_minutos' => $tiempoPromedio->tiempo_promedio ?? 0,
            'eficiencia' => $pedidosIniciados > 0 ? round(($pedidosCompletados / $pedidosIniciados) * 100, 2) : 0
        ];
    }

    /**
     * Obtener productos con stock insuficiente
     */
    private function getProductosStockInsuficiente()
    {
        $productos = DB::connection('sqlsrv')
            ->table('PRODUCTOS as p')
            ->join('NVV_DETALLE as d', 'p.CODIGO_PRODUCTO', '=', 'd.CODIGO_PRODUCTO')
            ->join('NOTAS_VENTA as n', 'd.NUMERO_NVV', '=', 'n.NUMERO_NVV')
            ->select([
                'p.CODIGO_PRODUCTO',
                'p.NOMBRE_PRODUCTO',
                'p.STOCK_ACTUAL',
                'd.CANTIDAD_SOLICITADA',
                'n.NUMERO_NVV',
                'n.NOMBRE_CLIENTE'
            ])
            ->where('n.ESTADO_PICKING', 'PENDIENTE')
            ->whereRaw('p.STOCK_ACTUAL < d.CANTIDAD_SOLICITADA')
            ->orderBy('p.STOCK_ACTUAL', 'asc')
            ->get();

        return $productos->map(function($producto) {
            return [
                'codigo_producto' => $producto->CODIGO_PRODUCTO,
                'nombre_producto' => $producto->NOMBRE_PRODUCTO,
                'stock_actual' => $producto->STOCK_ACTUAL,
                'cantidad_solicitada' => $producto->CANTIDAD_SOLICITADA,
                'numero_nvv' => $producto->NUMERO_NVV,
                'cliente' => $producto->NOMBRE_CLIENTE,
                'diferencia' => $producto->CANTIDAD_SOLICITADA - $producto->STOCK_ACTUAL
            ];
        });
    }

    /**
     * Obtener pedidos con filtros
     */
    private function getPedidosConFiltros($filtros)
    {
        $query = DB::connection('sqlsrv')
            ->table('NOTAS_VENTA')
            ->select([
                'NUMERO_NVV',
                'FECHA_NVV',
                'CODIGO_CLIENTE',
                'NOMBRE_CLIENTE',
                'TOTAL_NVV',
                'ESTADO_PICKING',
                'PREPARADOR',
                'FECHA_INICIO_PICKING',
                'FECHA_COMPLETADO_PICKING',
                'CANTIDAD_BULTOS'
            ]);

        if (!empty($filtros['fecha_desde'])) {
            $query->whereDate('FECHA_NVV', '>=', $filtros['fecha_desde']);
        }

        if (!empty($filtros['fecha_hasta'])) {
            $query->whereDate('FECHA_NVV', '<=', $filtros['fecha_hasta']);
        }

        if (!empty($filtros['preparador'])) {
            $query->where('PREPARADOR', 'like', '%' . $filtros['preparador'] . '%');
        }

        if (!empty($filtros['estado'])) {
            $query->where('ESTADO_PICKING', $filtros['estado']);
        }

        if (!empty($filtros['buscar'])) {
            $query->where(function($q) use ($filtros) {
                $q->where('NUMERO_NVV', 'like', '%' . $filtros['buscar'] . '%')
                  ->orWhere('NOMBRE_CLIENTE', 'like', '%' . $filtros['buscar'] . '%');
            });
        }

        return $query->orderBy('FECHA_NVV', 'desc')->paginate(20);
    }

    /**
     * Actualizar stock después de completar pedido
     */
    private function actualizarStockPedido($numeroNvv)
    {
        $detalle = DB::connection('sqlsrv')
            ->table('NVV_DETALLE')
            ->where('NUMERO_NVV', $numeroNvv)
            ->get();

        foreach ($detalle as $item) {
            DB::connection('sqlsrv')
                ->table('PRODUCTOS')
                ->where('CODIGO_PRODUCTO', $item->CODIGO_PRODUCTO)
                ->decrement('STOCK_ACTUAL', $item->CANTIDAD_SOLICITADA);
        }
    }

    /**
     * Calcular prioridad del pedido
     */
    private function calcularPrioridad($fechaNvv)
    {
        $diasTranscurridos = now()->diffInDays($fechaNvv);
        
        if ($diasTranscurridos >= 3) return 'Alta';
        if ($diasTranscurridos >= 1) return 'Media';
        return 'Baja';
    }

    /**
     * Calcular tiempo transcurrido
     */
    private function calcularTiempoTranscurrido($fechaInicio)
    {
        if (!$fechaInicio) return '0 min';
        
        $minutos = now()->diffInMinutes($fechaInicio);
        
        if ($minutos < 60) return $minutos . ' min';
        
        $horas = floor($minutos / 60);
        $minutosRestantes = $minutos % 60;
        
        return $horas . 'h ' . $minutosRestantes . 'min';
    }
}
