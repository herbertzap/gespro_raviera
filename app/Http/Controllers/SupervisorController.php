<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Services\CobranzaService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SupervisorController extends Controller
{
    protected $cobranzaService;

    public function __construct(CobranzaService $cobranzaService)
    {
        $this->cobranzaService = $cobranzaService;
        $this->middleware('auth');
        $this->middleware('role:Supervisor|Super Admin');
    }

    /**
     * Vista principal del Supervisor
     */
    public function index()
    {
        $user = Auth::user();
        
        if (!$user->hasRole('Supervisor') && !$user->hasRole('Super Admin')) {
            return redirect()->route('dashboard')->with('error', 'No tienes permisos para acceder a esta sección');
        }

        // Resumen de estadísticas
        $totalClientes = Cliente::count();
        $totalNotasPendientes = Cotizacion::whereIn('estado_aprobacion', ['pendiente', 'pendiente_picking'])->count();
        $notasConProblemasCredito = Cotizacion::where('tiene_problemas_credito', true)->count();
        $notasConProblemasStock = Cotizacion::where('tiene_problemas_stock', true)->count();

        return view('supervisor.index', compact(
            'totalClientes',
            'totalNotasPendientes',
            'notasConProblemasCredito',
            'notasConProblemasStock'
        ));
    }

    /**
     * Lista todos los clientes del sistema
     */
    public function clientes(Request $request)
    {
        $user = Auth::user();
        
        if (!$user->hasRole('Supervisor') && !$user->hasRole('Super Admin')) {
            return redirect()->route('dashboard')->with('error', 'No tienes permisos para acceder a esta sección');
        }

        // Parámetros de búsqueda
        $buscar = $request->get('buscar', '');
        $ordenarPor = $request->get('ordenar_por', 'codigo_cliente');
        $orden = $request->get('orden', 'asc');
        $porPagina = $request->get('por_pagina', 15);

        $query = Cliente::query();

        // Aplicar filtros de búsqueda
        if ($buscar) {
            $query->where(function($q) use ($buscar) {
                $q->where('codigo_cliente', 'LIKE', "%{$buscar}%")
                  ->orWhere('nombre', 'LIKE', "%{$buscar}%")
                  ->orWhere('direccion', 'LIKE', "%{$buscar}%");
            });
        }

        // Aplicar ordenamiento
        $query->orderBy($ordenarPor, $orden);

        $clientes = $query->paginate($porPagina);

        return view('supervisor.clientes', compact('clientes', 'buscar', 'ordenarPor', 'orden', 'porPagina'));
    }

    /**
     * Ver detalles de un cliente específico
     */
    public function verCliente($codigoCliente)
    {
        $user = Auth::user();
        
        if (!$user->hasRole('Supervisor') && !$user->hasRole('Super Admin')) {
            return redirect()->route('dashboard')->with('error', 'No tienes permisos para acceder a esta sección');
        }

        // Obtener cliente
        $cliente = Cliente::where('codigo_cliente', $codigoCliente)->first();
        
        if (!$cliente) {
            return redirect()->route('supervisor.clientes')->with('error', 'Cliente no encontrado');
        }

        // Obtener notas de venta del cliente
        $notasVenta = Cotizacion::where('cliente_codigo', $codigoCliente)
            ->with(['user', 'productos'])
            ->latest()
            ->get();

        // Obtener información de crédito del cliente
        $infoCredito = $this->cobranzaService->getCreditoCliente($codigoCliente);

        // Obtener facturas pendientes del cliente
        $facturasPendientes = $this->cobranzaService->getFacturasPendientesPorCliente($codigoCliente);

        return view('supervisor.ver-cliente', compact(
            'cliente',
            'notasVenta',
            'infoCredito',
            'facturasPendientes'
        ));
    }

    /**
     * Lista todas las notas de venta del sistema
     */
    public function notasVenta(Request $request)
    {
        $user = Auth::user();
        
        if (!$user->hasRole('Supervisor') && !$user->hasRole('Super Admin')) {
            return redirect()->route('dashboard')->with('error', 'No tienes permisos para acceder a esta sección');
        }

        // Parámetros de búsqueda
        $buscar = $request->get('buscar', '');
        $estado = $request->get('estado', '');
        $vendedor = $request->get('vendedor', '');
        $cliente = $request->get('cliente', '');
        $porPagina = $request->get('por_pagina', 15);

        $query = Cotizacion::with(['user', 'productos']);

        // Aplicar filtros
        if ($buscar) {
            $query->where(function($q) use ($buscar) {
                $q->where('id', 'LIKE', "%{$buscar}%")
                  ->orWhere('cliente_codigo', 'LIKE', "%{$buscar}%")
                  ->orWhere('cliente_nombre', 'LIKE', "%{$buscar}%");
            });
        }

        if ($estado) {
            $query->where('estado_aprobacion', $estado);
        }

        if ($vendedor) {
            $query->whereHas('user', function($q) use ($vendedor) {
                $q->where('name', 'LIKE', "%{$vendedor}%");
            });
        }

        if ($cliente) {
            $query->where('cliente_codigo', 'LIKE', "%{$cliente}%");
        }

        $notasVenta = $query->latest()->paginate($porPagina);

        // Obtener opciones para filtros
        $estados = Cotizacion::select('estado_aprobacion')
            ->distinct()
            ->pluck('estado_aprobacion')
            ->filter();

        $vendedores = \App\Models\User::select('name')
            ->distinct()
            ->pluck('name')
            ->filter();

        return view('supervisor.notas-venta', compact(
            'notasVenta',
            'buscar',
            'estado',
            'vendedor',
            'cliente',
            'porPagina',
            'estados',
            'vendedores'
        ));
    }

    /**
     * Ver detalles de una nota de venta específica
     */
    public function verNotaVenta($id)
    {
        $user = Auth::user();
        
        if (!$user->hasRole('Supervisor') && !$user->hasRole('Super Admin')) {
            return redirect()->route('dashboard')->with('error', 'No tienes permisos para acceder a esta sección');
        }

        $notaVenta = Cotizacion::with(['user', 'productos'])->findOrFail($id);

        return view('supervisor.ver-nota-venta', compact('notaVenta'));
    }

    /**
     * Lista todas las facturas del sistema
     */
    public function facturas(Request $request)
    {
        $user = Auth::user();
        
        if (!$user->hasRole('Supervisor') && !$user->hasRole('Super Admin')) {
            return redirect()->route('dashboard')->with('error', 'No tienes permisos para acceder a esta sección');
        }

        // Obtener facturas pendientes
        $facturasPendientes = $this->cobranzaService->getFacturasPendientes(null, 50);
        
        // Obtener resumen de facturas
        $resumenFacturas = $this->cobranzaService->getResumenFacturasPendientes(null);

        return view('supervisor.facturas', compact('facturasPendientes', 'resumenFacturas'));
    }

    /**
     * Ver detalles de una factura específica
     */
    public function verFactura($tipoDocumento, $numeroDocumento)
    {
        $user = Auth::user();
        
        if (!$user->hasRole('Supervisor') && !$user->hasRole('Super Admin')) {
            return redirect()->route('dashboard')->with('error', 'No tienes permisos para acceder a esta sección');
        }

        $factura = $this->cobranzaService->getFacturaDetalle($tipoDocumento, $numeroDocumento);

        if (!$factura) {
            return redirect()->route('supervisor.facturas')->with('error', 'Factura no encontrada');
        }

        return view('supervisor.ver-factura', compact('factura'));
    }

    /**
     * Dashboard específico del Supervisor
     */
    public function dashboard()
    {
        $user = Auth::user();
        
        if (!$user->hasRole('Supervisor') && !$user->hasRole('Super Admin')) {
            return redirect()->route('dashboard')->with('error', 'No tienes permisos para acceder a esta sección');
        }

        // Notas de venta pendientes de aprobación
        $notasPendientes = Cotizacion::whereIn('estado_aprobacion', ['pendiente', 'pendiente_picking'])
            ->with(['user', 'productos'])
            ->latest()
            ->take(10)
            ->get();

        // Resumen de aprobaciones
        $resumenAprobaciones = [
            'total_pendientes' => Cotizacion::whereIn('estado_aprobacion', ['pendiente', 'pendiente_picking'])->count(),
            'pendientes_credito' => Cotizacion::where('estado_aprobacion', 'pendiente')->where('tiene_problemas_credito', true)->count(),
            'pendientes_stock' => Cotizacion::where('estado_aprobacion', 'pendiente_picking')->where('tiene_problemas_stock', true)->count(),
            'aprobadas_hoy' => Cotizacion::whereDate('updated_at', today())->where('estado_aprobacion', 'like', 'aprobada%')->count()
        ];

        // Clientes con más problemas
        $clientesProblematicos = Cliente::where('requiere_autorizacion_credito', true)
            ->orWhere('requiere_autorizacion_retraso', true)
            ->take(5)
            ->get();

        return view('supervisor.dashboard', compact(
            'notasPendientes',
            'resumenAprobaciones',
            'clientesProblematicos'
        ));
    }
}
