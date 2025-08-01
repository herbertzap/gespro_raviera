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
        
        // Obtener cotizaciones reales del vendedor desde SQL Server
        $cotizaciones = $this->cobranzaService->getCotizacionesPorVendedor($user->codigo_vendedor, 5);

        // Obtener notas de venta pendientes
        $notasVenta = NotaVenta::where('user_id', $user->id)
            ->where('estado', 'por_aprobar')
            ->latest()
            ->take(5)
            ->get();

        // Obtener resumen de cobranza del vendedor
        $resumenCobranza = $this->cobranzaService->getResumenCobranzaPorVendedor($user->codigo_vendedor);

        return [
            'clientesAsignados' => $clientesPaginated,
            'cotizaciones' => $cotizaciones,
            'notasVenta' => $notasVenta,
            'resumenCobranza' => $resumenCobranza,
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

        // Resumen general de cobranza
        $resumenCobranza = $this->cobranzaService->getResumenCobranza();

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

        // Resumen general de cobranza
        $resumenCobranza = $this->cobranzaService->getResumenCobranza();

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
        // Implementar lógica para obtener productos con bajo stock
        return [];
    }

    private function getResumenCompras()
    {
        // Implementar lógica para resumen de compras
        return [];
    }

    private function getProductosStockCritico()
    {
        // Implementar lógica para productos con stock crítico
        return [];
    }
}
