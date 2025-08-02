<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\CobranzaService;

class ClienteController extends Controller
{
    protected $cobranzaService;

    public function __construct(CobranzaService $cobranzaService)
    {
        $this->cobranzaService = $cobranzaService;
    }

    public function index()
    {
        $user = auth()->user();
        
        if (!$user->hasRole('Vendedor')) {
            return redirect()->route('dashboard')->with('error', 'Acceso no autorizado');
        }

        $clientes = $this->cobranzaService->getClientesPorVendedor($user->codigo_vendedor);
        
        return view('clientes.index', compact('clientes'));
    }

    public function buscar(Request $request)
    {
        $request->validate([
            'codigo_cliente' => 'required|string|max:20'
        ]);

        $codigoCliente = $request->codigo_cliente;
        $user = auth()->user();

        // Obtener información del cliente
        $cliente = $this->cobranzaService->getClienteInfo($codigoCliente);
        
        if (!$cliente) {
            return response()->json([
                'success' => false,
                'message' => 'Cliente no encontrado'
            ]);
        }

        // Verificar que el cliente pertenece al vendedor
        if ($cliente['CODIGO_VENDEDOR'] !== $user->codigo_vendedor) {
            return response()->json([
                'success' => false,
                'message' => 'Este cliente no está asignado a usted'
            ]);
        }

        // Validar si puede vender al cliente
        $validacion = $this->cobranzaService->validarClienteParaVenta($codigoCliente);
        
        // Obtener facturas pendientes
        $facturasPendientes = $this->cobranzaService->getFacturasPendientesCliente($codigoCliente);

        return response()->json([
            'success' => true,
            'cliente' => $cliente,
            'validacion' => $validacion,
            'facturas_pendientes' => $facturasPendientes
        ]);
    }

    public function validar(Request $request)
    {
        $request->validate([
            'codigo_cliente' => 'required|string|max:20'
        ]);

        $codigoCliente = $request->codigo_cliente;
        $user = auth()->user();

        // Verificar que el cliente pertenece al vendedor
        $cliente = $this->cobranzaService->getClienteInfo($codigoCliente);
        
        if (!$cliente || $cliente['CODIGO_VENDEDOR'] !== $user->codigo_vendedor) {
            return response()->json([
                'success' => false,
                'message' => 'Cliente no autorizado'
            ]);
        }

        // Validar si puede vender
        $validacion = $this->cobranzaService->validarClienteParaVenta($codigoCliente);

        if ($validacion['puede_vender']) {
            // Redirigir a la página de cotización
            return response()->json([
                'success' => true,
                'redirect' => route('cotizacion.create', ['cliente' => $codigoCliente])
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => $validacion['mensaje'],
                'facturas_pendientes' => $validacion['facturas_pendientes']
            ]);
        }
    }

    public function facturasPendientes($codigoCliente)
    {
        $user = auth()->user();
        
        // Verificar que el cliente pertenece al vendedor
        $cliente = $this->cobranzaService->getClienteInfo($codigoCliente);
        
        if (!$cliente || $cliente['CODIGO_VENDEDOR'] !== $user->codigo_vendedor) {
            return redirect()->route('clientes.index')->with('error', 'Cliente no autorizado');
        }

        $facturasPendientes = $this->cobranzaService->getFacturasPendientesCliente($codigoCliente);
        
        return view('clientes.facturas', compact('cliente', 'facturasPendientes'));
    }
}
