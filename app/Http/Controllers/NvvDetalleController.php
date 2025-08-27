<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\CobranzaService;
use Illuminate\Support\Facades\Auth;

class NvvDetalleController extends Controller
{
    protected $cobranzaService;

    public function __construct(CobranzaService $cobranzaService)
    {
        $this->cobranzaService = $cobranzaService;
    }

    /**
     * Mostrar el detalle de una NVV específica
     */
    public function show($numeroNvv, $codigoCliente = null)
    {
        $user = Auth::user();
        
        if (!$user) {
            return redirect()->route('login');
        }

        // Obtener el detalle de la NVV
        $detalleNvv = $this->cobranzaService->getDetalleNvv($numeroNvv, $codigoCliente);
        
        if (empty($detalleNvv)) {
            return redirect()->back()->with('error', 'NVV no encontrada o no tienes permisos para verla.');
        }

        // Verificar que el usuario tenga acceso a esta NVV
        $primerRegistro = $detalleNvv[0];
        if ($user->hasRole('Vendedor') && $primerRegistro['VENDEDOR_CODIGO'] !== $user->codigo_vendedor) {
            return redirect()->back()->with('error', 'No tienes permisos para ver esta NVV.');
        }

        // Agrupar información de la NVV
        $infoNvv = [
            'numero' => $primerRegistro['NUM'],
            'fecha' => $primerRegistro['EMIS_FCV'],
            'cliente_codigo' => $primerRegistro['COD_CLI'],
            'cliente_nombre' => $primerRegistro['CLIE'],
            'vendedor_nombre' => $primerRegistro['VENDEDOR_NOMBRE'],
            'region' => $primerRegistro['REGION'],
            'comuna' => $primerRegistro['COMUNA'],
            'dias' => $primerRegistro['DIAS'],
            'total_productos' => count($detalleNvv),
            'total_cantidad' => array_sum(array_column($detalleNvv, 'CANTIDAD_TOTAL')),
            'total_facturado' => array_sum(array_column($detalleNvv, 'CANTIDAD_FACTURADA')),
            'total_pendiente' => array_sum(array_column($detalleNvv, 'CANTIDAD_PENDIENTE')),
            'total_valor' => array_sum(array_column($detalleNvv, 'VALOR_TOTAL')),
            'total_valor_pendiente' => array_sum(array_column($detalleNvv, 'VALOR_PENDIENTE'))
        ];

        return view('nvv-detalle.show', [
            'nvv' => $infoNvv,
            'productos' => $detalleNvv,
            'tipoUsuario' => $user->hasRole('Vendedor') ? 'Vendedor' : 'Administrador',
            'pageSlug' => 'nvv-detalle'
        ]);
    }

    /**
     * Obtener datos de NVV para AJAX
     */
    public function getNvvData($numeroNvv)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['error' => 'No autenticado'], 401);
        }

        $detalleNvv = $this->cobranzaService->getDetalleNvv($numeroNvv);
        
        if (empty($detalleNvv)) {
            return response()->json(['error' => 'NVV no encontrada'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $detalleNvv
        ]);
    }
}
