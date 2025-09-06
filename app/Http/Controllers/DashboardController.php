<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\CobranzaService;
use App\Models\Cotizacion;
use App\Models\NotaVenta;
use App\Models\StockTemporal;
use App\Models\User;

class DashboardController extends Controller
{
    protected $cobranzaService;

    public function __construct(CobranzaService $cobranzaService)
    {
        $this->cobranzaService = $cobranzaService;
    }

    public function index()
    {
        $user = auth()->user();
        
        if (!$user) {
            return redirect()->route('login');
        }
        
        $data = [];

        // Datos según el rol del usuario
        if ($user->hasRole('Super Admin')) {
            $data = $this->getSuperAdminDashboard($user);
        } elseif ($user->hasRole('Vendedor')) {
            $data = $this->getVendedorDashboard($user);
        } elseif ($user->hasRole('Supervisor')) {
            $data = $this->getSupervisorDashboard($user);
        } elseif ($user->hasRole('Compras')) {
            $data = $this->getComprasDashboard($user);
        } elseif ($user->hasRole('Picking')) {
            $data = $this->getPickingDashboard($user);
        } elseif ($user->hasRole('Bodega')) {
            $data = $this->getBodegaDashboard($user);
        }

        // Agregar pageSlug para el sidebar
        $data['pageSlug'] = 'dashboard';
        
        return view('dashboard.index', $data);
    }

    private function getVendedorDashboard($user)
    {
        // Obtener parámetros de filtro
        $filtros = [
            'buscar' => request()->get('buscar', ''),
            'ordenar_por' => request()->get('ordenar_por', 'NOMBRE_CLIENTE'),
            'orden' => request()->get('orden', 'asc'),
            'saldo_min' => request()->get('saldo_min', ''),
            'saldo_max' => request()->get('saldo_max', ''),
            'facturas_min' => request()->get('facturas_min', ''),
            'facturas_max' => request()->get('facturas_max', '')
        ];
        
        // Obtener clientes asignados al vendedor
        $clientesAsignados = $this->cobranzaService->getClientesPorVendedor($user->codigo_vendedor);
        
        // Aplicar filtros
        $clientesFiltrados = $this->aplicarFiltrosClientes($clientesAsignados, $filtros);
        
        // Paginar los clientes manualmente
        $perPage = 10;
        $currentPage = request()->get('page', 1);
        $offset = ($currentPage - 1) * $perPage;
        
        $clientesPaginated = new \Illuminate\Pagination\LengthAwarePaginator(
            array_slice($clientesFiltrados, $offset, $perPage),
            count($clientesFiltrados),
            $perPage,
            $currentPage,
            ['path' => request()->url(), 'query' => request()->query()]
        );
        


        // Obtener notas de venta pendientes
        $notasVenta = NotaVenta::where('user_id', $user->id)
            ->where('estado', 'por_aprobar')
            ->latest()
            ->take(5)
            ->get();

        // Obtener NVV pendientes detalle
        $nvvPendientes = $this->cobranzaService->getNvvPendientesDetalle($user->codigo_vendedor, 10);
        $resumenNvvPendientes = $this->cobranzaService->getResumenNvvPendientes($user->codigo_vendedor);

        // Obtener facturas pendientes
        $facturasPendientes = $this->cobranzaService->getFacturasPendientes($user->codigo_vendedor, 10);
        $resumenFacturasPendientes = $this->cobranzaService->getResumenFacturasPendientes($user->codigo_vendedor);

        // Obtener total de notas de venta
        $totalNotasVenta = Cotizacion::where('user_id', $user->id)->count();

        // Obtener cheques en cartera
        $chequesEnCartera = $this->cobranzaService->getChequesEnCartera($user->codigo_vendedor);

        // Calcular resumen de cobranza con datos reales
        $resumenCobranza = [
            'TOTAL_FACTURAS' => $resumenFacturasPendientes['total_facturas'],
            'TOTAL_NOTAS_VENTA' => $totalNotasVenta,
            'SALDO_VENCIDO' => $resumenFacturasPendientes['por_estado']['VENCIDO']['valor'] + $resumenFacturasPendientes['por_estado']['MOROSO']['valor'],
            'CHEQUES_EN_CARTERA' => $chequesEnCartera
        ];

        return [
            'clientesAsignados' => $clientesPaginated,
            'notasVenta' => $notasVenta,
            'resumenCobranza' => $resumenCobranza,
            'nvvPendientes' => $nvvPendientes,
            'resumenNvvPendientes' => $resumenNvvPendientes,
            'facturasPendientes' => $facturasPendientes,
            'resumenFacturasPendientes' => $resumenFacturasPendientes,
            'tipoUsuario' => 'Vendedor',
            'filtros' => $filtros
        ];
    }

    private function getSupervisorDashboard($user)
    {
        // Notas de venta pendientes de aprobación
        $notasPendientes = NotaVenta::where('estado', 'por_aprobar')
            ->with('user')
            ->latest()
            ->take(10)
            ->get();

        // Obtener total de notas de venta
        $totalNotasVenta = Cotizacion::count();

        // Obtener cheques en cartera
        $chequesEnCartera = $this->cobranzaService->getChequesEnCartera();

        // Resumen general de cobranza
        $resumenCobranza = $this->cobranzaService->getResumenCobranza();
        
        // Agregar los nuevos campos al resumen
        $resumenCobranza['TOTAL_NOTAS_VENTA'] = $totalNotasVenta;
        $resumenCobranza['CHEQUES_EN_CARTERA'] = $chequesEnCartera;

        // Stock temporal activo
        $stockTemporal = StockTemporal::where('estado', 'activa')
            ->with('user')
            ->latest()
            ->take(10)
            ->get();

        return [
            'notasPendientes' => $notasPendientes,
            'resumenCobranza' => $resumenCobranza,
            'stockTemporal' => $stockTemporal,
            'tipoUsuario' => 'Supervisor'
        ];
    }

    private function getComprasDashboard($user)
    {
        // Productos con bajo stock
        $productosBajoStock = $this->getProductosBajoStock();

        // Resumen de compras del año
        $resumenCompras = $this->getResumenCompras();

        return [
            'productosBajoStock' => $productosBajoStock,
            'resumenCompras' => $resumenCompras,
            'tipoUsuario' => 'Compras'
        ];
    }

    private function aplicarFiltrosClientes($clientes, $filtros)
    {
        // Filtrar por búsqueda (código o nombre)
        if (!empty($filtros['buscar'])) {
            $buscar = strtolower($filtros['buscar']);
            $clientes = array_filter($clientes, function($cliente) use ($buscar) {
                return strpos(strtolower($cliente['CODIGO_CLIENTE']), $buscar) !== false ||
                       strpos(strtolower($cliente['NOMBRE_CLIENTE']), $buscar) !== false;
            });
        }
        
        // Filtrar por saldo mínimo
        if (!empty($filtros['saldo_min'])) {
            $saldoMin = (float)$filtros['saldo_min'];
            $clientes = array_filter($clientes, function($cliente) use ($saldoMin) {
                return $cliente['SALDO_TOTAL'] >= $saldoMin;
            });
        }
        
        // Filtrar por saldo máximo
        if (!empty($filtros['saldo_max'])) {
            $saldoMax = (float)$filtros['saldo_max'];
            $clientes = array_filter($clientes, function($cliente) use ($saldoMax) {
                return $cliente['SALDO_TOTAL'] <= $saldoMax;
            });
        }
        
        // Filtrar por cantidad de facturas mínimo
        if (!empty($filtros['facturas_min'])) {
            $facturasMin = (int)$filtros['facturas_min'];
            $clientes = array_filter($clientes, function($cliente) use ($facturasMin) {
                return $cliente['CANTIDAD_FACTURAS'] >= $facturasMin;
            });
        }
        
        // Filtrar por cantidad de facturas máximo
        if (!empty($filtros['facturas_max'])) {
            $facturasMax = (int)$filtros['facturas_max'];
            $clientes = array_filter($clientes, function($cliente) use ($facturasMax) {
                return $cliente['CANTIDAD_FACTURAS'] <= $facturasMax;
            });
        }
        
        // Ordenar los resultados
        $ordenarPor = $filtros['ordenar_por'];
        $orden = $filtros['orden'];
        
        usort($clientes, function($a, $b) use ($ordenarPor, $orden) {
            $valorA = $a[$ordenarPor];
            $valorB = $b[$ordenarPor];
            
            // Si son números, comparar como números
            if (is_numeric($valorA) && is_numeric($valorB)) {
                $comparacion = $valorA <=> $valorB;
            } else {
                // Si son strings, comparar como strings
                $comparacion = strcasecmp($valorA, $valorB);
            }
            
            return $orden === 'desc' ? -$comparacion : $comparacion;
        });
        
        return array_values($clientes);
    }

    private function getSuperAdminDashboard($user)
    {
        // Resumen general de todo el sistema
        $totalUsuarios = User::count();
        $usuariosPorRol = [];
        
        $roles = ['Super Admin', 'Vendedor', 'Supervisor', 'Compras', 'Bodega'];
        foreach ($roles as $rol) {
            $usuariosPorRol[$rol] = User::role($rol)->count();
        }

        // Obtener total de notas de venta
        $totalNotasVenta = Cotizacion::count();

        // Obtener cheques en cartera
        $chequesEnCartera = $this->cobranzaService->getChequesEnCartera();

        // Resumen general de cobranza
        $resumenCobranza = $this->cobranzaService->getResumenCobranza();
        
        // Agregar los nuevos campos al resumen
        $resumenCobranza['TOTAL_NOTAS_VENTA'] = $totalNotasVenta;
        $resumenCobranza['CHEQUES_EN_CARTERA'] = $chequesEnCartera;

        // Notas de venta pendientes de aprobación
        $notasPendientes = NotaVenta::where('estado', 'por_aprobar')
            ->with('user')
            ->latest()
            ->take(10)
            ->get();

        return [
            'totalUsuarios' => $totalUsuarios,
            'usuariosPorRol' => $usuariosPorRol,
            'resumenCobranza' => $resumenCobranza,
            'notasPendientes' => $notasPendientes,
            'tipoUsuario' => 'Super Admin'
        ];
    }

    private function getBodegaDashboard($user)
    {
        // Stock temporal activo
        $stockTemporal = StockTemporal::where('estado', 'activa')
            ->with('user')
            ->latest()
            ->take(10)
            ->get();

        // Productos con stock crítico
        $productosStockCritico = $this->getProductosStockCritico();

        return [
            'stockTemporal' => $stockTemporal,
            'productosStockCritico' => $productosStockCritico,
            'tipoUsuario' => 'Bodega'
        ];
    }

    private function getProductosBajoStock()
    {
        // Obtener productos con stock bajo desde la base de datos
        $productos = \DB::connection('sqlsrv')
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
            ->limit(20)
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

    private function getResumenCompras()
    {
        // Obtener resumen de compras del año actual
        $añoActual = date('Y');
        
        $comprasMensuales = \DB::connection('sqlsrv')
            ->table('COMPRAS')
            ->selectRaw('MONTH(FECHA_COMPRA) as mes, COUNT(*) as cantidad, SUM(TOTAL) as total')
            ->whereYear('FECHA_COMPRA', $añoActual)
            ->groupBy('MONTH(FECHA_COMPRA)')
            ->get();

        $totalCompras = \DB::connection('sqlsrv')
            ->table('COMPRAS')
            ->whereYear('FECHA_COMPRA', $añoActual)
            ->count();

        $totalMontoCompras = \DB::connection('sqlsrv')
            ->table('COMPRAS')
            ->whereYear('FECHA_COMPRA', $añoActual)
            ->sum('TOTAL');

        $productosBajoStock = \DB::connection('sqlsrv')
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

    private function getProductosStockCritico()
    {
        // Implementar lógica para productos con stock crítico
        return [];
    }

    private function getPedidosPendientesPicking()
    {
        // Obtener pedidos aprobados que están pendientes de preparación
        $pedidos = \DB::connection('sqlsrv')
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
            ->limit(20)
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

    private function getPedidosEnPreparacion()
    {
        // Obtener pedidos que están siendo preparados
        $pedidos = \DB::connection('sqlsrv')
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
            ->limit(15)
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

    private function getResumenPickingDia()
    {
        $hoy = date('Y-m-d');
        
        $pedidosCompletados = \DB::connection('sqlsrv')
            ->table('NOTAS_VENTA')
            ->whereDate('FECHA_COMPLETADO_PICKING', $hoy)
            ->count();

        $pedidosIniciados = \DB::connection('sqlsrv')
            ->table('NOTAS_VENTA')
            ->whereDate('FECHA_INICIO_PICKING', $hoy)
            ->count();

        $pedidosPendientes = \DB::connection('sqlsrv')
            ->table('NOTAS_VENTA')
            ->where('ESTADO_PICKING', 'PENDIENTE')
            ->count();

        $tiempoPromedio = \DB::connection('sqlsrv')
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

    private function getProductosStockInsuficiente()
    {
        // Obtener productos que no tienen stock suficiente para pedidos pendientes
        $productos = \DB::connection('sqlsrv')
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
            ->limit(15)
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

    private function getPedidosCompletadosHoy()
    {
        $hoy = date('Y-m-d');
        
        $pedidos = \DB::connection('sqlsrv')
            ->table('NOTAS_VENTA')
            ->select([
                'NUMERO_NVV',
                'CODIGO_CLIENTE',
                'NOMBRE_CLIENTE',
                'TOTAL_NVV',
                'PREPARADOR',
                'FECHA_COMPLETADO_PICKING',
                'CANTIDAD_BULTOS'
            ])
            ->whereDate('FECHA_COMPLETADO_PICKING', $hoy)
            ->orderBy('FECHA_COMPLETADO_PICKING', 'desc')
            ->limit(10)
            ->get();

        return $pedidos->map(function($pedido) {
            return [
                'numero_nvv' => $pedido->NUMERO_NVV,
                'cliente_codigo' => $pedido->CODIGO_CLIENTE,
                'cliente_nombre' => $pedido->NOMBRE_CLIENTE,
                'total' => $pedido->TOTAL_NVV,
                'preparador' => $pedido->PREPARADOR,
                'fecha_completado' => $pedido->FECHA_COMPLETADO_PICKING,
                'cantidad_bultos' => $pedido->CANTIDAD_BULTOS
            ];
        });
    }

    private function calcularPrioridad($fechaNvv)
    {
        $diasTranscurridos = now()->diffInDays($fechaNvv);
        
        if ($diasTranscurridos >= 3) return 'Alta';
        if ($diasTranscurridos >= 1) return 'Media';
        return 'Baja';
    }

    private function calcularTiempoTranscurrido($fechaInicio)
    {
        if (!$fechaInicio) return '0 min';
        
        $minutos = now()->diffInMinutes($fechaInicio);
        
        if ($minutos < 60) return $minutos . ' min';
        
        $horas = floor($minutos / 60);
        $minutosRestantes = $minutos % 60;
        
        return $horas . 'h ' . $minutosRestantes . 'min';
    }

    private function getPickingDashboard($user)
    {
        // Obtener pedidos pendientes de preparación
        $pedidosPendientes = $this->getPedidosPendientesPicking();
        
        // Obtener pedidos en preparación
        $pedidosEnPreparacion = $this->getPedidosEnPreparacion();
        
        // Obtener resumen de picking del día
        $resumenPicking = $this->getResumenPickingDia();
        
        // Obtener productos con stock insuficiente para pedidos
        $productosStockInsuficiente = $this->getProductosStockInsuficiente();
        
        // Obtener pedidos completados hoy
        $pedidosCompletadosHoy = $this->getPedidosCompletadosHoy();

        return [
            'pedidosPendientes' => $pedidosPendientes,
            'pedidosEnPreparacion' => $pedidosEnPreparacion,
            'resumenPicking' => $resumenPicking,
            'productosStockInsuficiente' => $productosStockInsuficiente,
            'pedidosCompletadosHoy' => $pedidosCompletadosHoy,
            'tipoUsuario' => 'Picking'
        ];
    }
}
