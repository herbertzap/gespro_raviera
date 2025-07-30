<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\CobranzaService;

class CobranzaController extends Controller
{
    protected $cobranzaService;

    public function __construct(CobranzaService $cobranzaService)
    {
        $this->cobranzaService = $cobranzaService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $fechaInicio = $request->get('fecha_inicio');
        $fechaFin = $request->get('fecha_fin');
        $vendedor = $request->get('vendedor');
        $cliente = $request->get('cliente');

        try {
            $cobranza = $this->cobranzaService->getCobranza($fechaInicio, $fechaFin, $vendedor, $cliente);
            $resumen = $this->cobranzaService->getResumenCobranza();
            $porVendedor = $this->cobranzaService->getCobranzaPorVendedor();

            return view('cobranza.index', compact('cobranza', 'resumen', 'porVendedor'));
        } catch (\Exception $e) {
            return back()->with('error', 'Error al cargar los datos de cobranza: ' . $e->getMessage());
        }
    }

    /**
     * Exportar datos a Excel
     */
    public function export(Request $request)
    {
        $fechaInicio = $request->get('fecha_inicio');
        $fechaFin = $request->get('fecha_fin');
        $vendedor = $request->get('vendedor');
        $cliente = $request->get('cliente');

        try {
            $cobranza = $this->cobranzaService->getCobranza($fechaInicio, $fechaFin, $vendedor, $cliente);
            
            // Aquí implementarías la exportación a Excel
            // Por ahora retornamos JSON
            return response()->json($cobranza);
        } catch (\Exception $e) {
            return back()->with('error', 'Error al exportar: ' . $e->getMessage());
        }
    }
}
