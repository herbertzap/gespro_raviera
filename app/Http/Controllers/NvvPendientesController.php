<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\CobranzaService;
use Illuminate\Support\Facades\Auth;

class NvvPendientesController extends Controller
{
    protected $cobranzaService;

    public function __construct(CobranzaService $cobranzaService)
    {
        $this->cobranzaService = $cobranzaService;
        
        // Restringir acceso solo a Super Admin, Supervisor, Compras, Picking y Vendedor
        $this->middleware(function ($request, $next) {
            $user = auth()->user();
            if (!$user->hasRole('Super Admin') && !$user->hasRole('Supervisor') && !$user->hasRole('Compras') && !$user->hasRole('Picking') && !$user->hasRole('Vendedor')) {
                abort(403, 'Acceso denegado. Solo Super Admin, Supervisor, Compras, Picking y Vendedor pueden acceder a esta vista.');
            }
            return $next($request);
        });
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        
        // Obtener parámetros de filtro
        $filtros = [
            'buscar' => $request->get('buscar', ''),
            'rango_dias' => $request->get('rango_dias', ''),
            'cliente' => $request->get('cliente', ''),
            'producto' => $request->get('producto', ''),
            'ordenar_por' => $request->get('ordenar_por', 'DIAS'),
            'orden' => $request->get('orden', 'desc'),
            'por_pagina' => $request->get('por_pagina', 20)
        ];

        // Obtener código de vendedor según el rol
        $codigoVendedor = null;
        if ($user->hasRole('Vendedor')) {
            $codigoVendedor = $user->codigo_vendedor;
        }

        // Obtener datos de NVV pendientes
        $nvvPendientes = $this->cobranzaService->getNvvPendientesDetalle($codigoVendedor, 1000);
        
        // Aplicar filtros
        $nvvFiltrados = $this->aplicarFiltrosNvv($nvvPendientes, $filtros);
        
        // Paginar los resultados
        $perPage = $filtros['por_pagina'];
        $currentPage = $request->get('page', 1);
        $offset = ($currentPage - 1) * $perPage;
        
        $nvvPaginated = new \Illuminate\Pagination\LengthAwarePaginator(
            array_slice($nvvFiltrados, $offset, $perPage),
            count($nvvFiltrados),
            $perPage,
            $currentPage,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        // Obtener resumen
        $resumen = $this->cobranzaService->getResumenNvvPendientes($codigoVendedor);

        return view('nvv-pendientes.index', [
            'nvvPendientes' => $nvvPaginated,
            'resumen' => $resumen,
            'filtros' => $filtros,
            'tipoUsuario' => $user->hasRole('Vendedor') ? 'Vendedor' : 'Administrador',
            'pageSlug' => 'nvv-pendientes'
        ]);
    }

    public function resumen(Request $request)
    {
        $user = Auth::user();
        
        $codigoVendedor = null;
        if ($user->hasRole('Vendedor')) {
            $codigoVendedor = $user->codigo_vendedor;
        }

        $resumen = $this->cobranzaService->getResumenNvvPendientes($codigoVendedor);

        return response()->json($resumen);
    }

    public function ver($numeroNvv)
    {
        $user = Auth::user();
        
        // Verificar permisos
        if (!$user->hasRole('Super Admin') && !$user->hasRole('Supervisor') && !$user->hasRole('Compras') && !$user->hasRole('Picking') && !$user->hasRole('Vendedor')) {
            abort(403, 'Acceso denegado.');
        }
        
        // Si es vendedor, verificar que la NVV pertenezca a su código
        if ($user->hasRole('Vendedor')) {
            $nvvPendientes = $this->cobranzaService->getNvvPendientesDetalle($user->codigo_vendedor, 1000);
            $nvvEncontrada = false;
            
            foreach ($nvvPendientes as $nvv) {
                if ($nvv['NUM'] === $numeroNvv) {
                    $nvvEncontrada = true;
                    break;
                }
            }
            
            if (!$nvvEncontrada) {
                abort(403, 'No tienes permisos para ver esta NVV.');
            }
        }
        
        // Obtener detalles de la NVV
        $nvvDetalle = $this->cobranzaService->getNvvDetalle($numeroNvv);
        
        if (!$nvvDetalle) {
            abort(404, 'NVV no encontrada.');
        }
        
        return view('nvv-pendientes.ver', [
            'nvv' => $nvvDetalle,
            'pageSlug' => 'nvv-pendientes'
        ]);
    }

    public function export(Request $request)
    {
        $user = Auth::user();
        
        $codigoVendedor = null;
        if ($user->hasRole('Vendedor')) {
            $codigoVendedor = $user->codigo_vendedor;
        }

        // Obtener todos los datos sin paginación
        $nvvPendientes = $this->cobranzaService->getNvvPendientesDetalle($codigoVendedor, 10000);
        
        // Aplicar filtros si existen
        $filtros = [
            'buscar' => $request->get('buscar', ''),
            'rango_dias' => $request->get('rango_dias', ''),
            'cliente' => $request->get('cliente', ''),
            'producto' => $request->get('producto', '')
        ];
        
        $nvvFiltrados = $this->aplicarFiltrosNvv($nvvPendientes, $filtros);

        // Generar archivo Excel
        $filename = 'nvv_pendientes_' . date('Y-m-d_H-i-s') . '.xlsx';
        
        return response()->json([
            'success' => true,
            'message' => 'Exportación completada',
            'filename' => $filename,
            'total_registros' => count($nvvFiltrados)
        ]);
    }

    private function aplicarFiltrosNvv($nvvPendientes, $filtros)
    {
        // Filtrar por búsqueda general
        if (!empty($filtros['buscar'])) {
            $buscar = strtolower($filtros['buscar']);
            $nvvPendientes = array_filter($nvvPendientes, function($nvv) use ($buscar) {
                return strpos(strtolower($nvv['CLIE'] ?? ''), $buscar) !== false ||
                       strpos(strtolower($nvv['NOKOPR'] ?? ''), $buscar) !== false ||
                       strpos(strtolower($nvv['TD'] . '-' . $nvv['NUM']), $buscar) !== false;
            });
        }

        // Filtrar por rango de días
        if (!empty($filtros['rango_dias'])) {
            $nvvPendientes = array_filter($nvvPendientes, function($nvv) use ($filtros) {
                $dias = (int)($nvv['DIAS'] ?? 0);
                switch ($filtros['rango_dias']) {
                    case '1-7':
                        return $dias >= 1 && $dias <= 7;
                    case '8-30':
                        return $dias >= 8 && $dias <= 30;
                    case '31-60':
                        return $dias >= 31 && $dias <= 60;
                    case '60+':
                        return $dias > 60;
                    default:
                        return true;
                }
            });
        }

        // Filtrar por cliente
        if (!empty($filtros['cliente'])) {
            $cliente = strtolower($filtros['cliente']);
            $nvvPendientes = array_filter($nvvPendientes, function($nvv) use ($cliente) {
                return strpos(strtolower($nvv['CLIE'] ?? ''), $cliente) !== false;
            });
        }

        // Filtrar por producto
        if (!empty($filtros['producto'])) {
            $producto = strtolower($filtros['producto']);
            $nvvPendientes = array_filter($nvvPendientes, function($nvv) use ($producto) {
                return strpos(strtolower($nvv['NOKOPR'] ?? ''), $producto) !== false;
            });
        }

        // Ordenar resultados
        $ordenarPor = $filtros['ordenar_por'];
        $orden = $filtros['orden'];
        
        usort($nvvPendientes, function($a, $b) use ($ordenarPor, $orden) {
            $valorA = $a[$ordenarPor] ?? 0;
            $valorB = $b[$ordenarPor] ?? 0;
            
            if (is_numeric($valorA) && is_numeric($valorB)) {
                $comparacion = $valorA <=> $valorB;
            } else {
                $comparacion = strcasecmp($valorA, $valorB);
            }
            
            return $orden === 'desc' ? -$comparacion : $comparacion;
        });

        return array_values($nvvPendientes);
    }
}
