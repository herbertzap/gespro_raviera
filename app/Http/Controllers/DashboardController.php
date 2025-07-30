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
        // Obtener clientes asignados al vendedor
        $clientesAsignados = $this->cobranzaService->getClientesPorVendedor($user->codigo_vendedor);
        
        // Obtener cotizaciones del vendedor
        $cotizaciones = Cotizacion::where('user_id', $user->id)
            ->where('estado', '!=', 'borrador')
            ->latest()
            ->take(5)
            ->get();

        // Obtener notas de venta pendientes
        $notasVenta = NotaVenta::where('user_id', $user->id)
            ->where('estado', 'por_aprobar')
            ->latest()
            ->take(5)
            ->get();

        // Obtener resumen de cobranza del vendedor
        $resumenCobranza = $this->cobranzaService->getResumenCobranzaPorVendedor($user->codigo_vendedor);

        return [
            'clientesAsignados' => $clientesAsignados,
            'cotizaciones' => $cotizaciones,
            'notasVenta' => $notasVenta,
            'resumenCobranza' => $resumenCobranza,
            'tipoUsuario' => 'Vendedor'
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
