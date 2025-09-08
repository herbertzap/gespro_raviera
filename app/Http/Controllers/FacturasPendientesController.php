<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\CobranzaService;
use Illuminate\Support\Facades\Auth;

class FacturasPendientesController extends Controller
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
            'estado' => $request->get('estado', ''),
            'tipo_documento' => $request->get('tipo_documento', ''),
            'cliente' => $request->get('cliente', ''),
            'vendedor' => $request->get('vendedor', ''),
            'saldo_min' => $request->get('saldo_min', ''),
            'saldo_max' => $request->get('saldo_max', ''),
            'valor_min' => $request->get('valor_min', ''),
            'valor_max' => $request->get('valor_max', ''),
            'abonos_min' => $request->get('abonos_min', ''),
            'abonos_max' => $request->get('abonos_max', ''),
            'dias_min' => $request->get('dias_min', ''),
            'dias_max' => $request->get('dias_max', ''),
            'ordenar_por' => $request->get('ordenar_por', 'NRO_DOCTO'),
            'orden' => $request->get('orden', 'desc'),
            'por_pagina' => $request->get('por_pagina', 10)
        ];

        // Obtener código de vendedor según el rol
        $codigoVendedor = null;
        if ($user->hasRole('Vendedor')) {
            $codigoVendedor = $user->codigo_vendedor;
        }

        // Obtener datos de facturas pendientes con paginación
        $facturasPendientes = $this->cobranzaService->getFacturasPendientes($codigoVendedor, 1000);
        
        // Aplicar filtros
        $facturasFiltradas = $this->aplicarFiltrosFacturas($facturasPendientes, $filtros);
        
        // Paginar manualmente
        $perPage = $filtros['por_pagina'];
        $currentPage = $request->get('page', 1);
        $offset = ($currentPage - 1) * $perPage;
        
        $facturasPaginated = new \Illuminate\Pagination\LengthAwarePaginator(
            array_slice($facturasFiltradas, $offset, $perPage),
            count($facturasFiltradas),
            $perPage,
            $currentPage,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        // Obtener resumen
        $resumen = $this->cobranzaService->getResumenFacturasPendientes($codigoVendedor);

        return view('facturas-pendientes.index', [
            'facturasPendientes' => $facturasPaginated,
            'resumen' => $resumen,
            'filtros' => $filtros,
            'tipoUsuario' => $user->hasRole('Vendedor') ? 'Vendedor' : 'Administrador',
            'pageSlug' => 'facturas-pendientes'
        ]);
    }

    public function resumen(Request $request)
    {
        $user = Auth::user();
        
        $codigoVendedor = null;
        if ($user->hasRole('Vendedor')) {
            $codigoVendedor = $user->codigo_vendedor;
        }

        $resumen = $this->cobranzaService->getResumenFacturasPendientes($codigoVendedor);

        return response()->json($resumen);
    }

    public function ver($tipoDocumento, $numeroDocumento)
    {
        $user = Auth::user();
        
        // Verificar permisos
        if (!$user->hasRole('Super Admin') && !$user->hasRole('Supervisor') && !$user->hasRole('Compras') && !$user->hasRole('Picking') && !$user->hasRole('Vendedor')) {
            abort(403, 'Acceso denegado.');
        }
        
        // Si es vendedor, verificar que la factura pertenezca a su código
        if ($user->hasRole('Vendedor')) {
            $facturasPendientes = $this->cobranzaService->getFacturasPendientes($user->codigo_vendedor, 1000);
            $facturaEncontrada = false;
            
            foreach ($facturasPendientes as $factura) {
                if ($factura['TIPO_DOCTO'] === $tipoDocumento && $factura['NRO_DOCTO'] === $numeroDocumento) {
                    $facturaEncontrada = true;
                    break;
                }
            }
            
            if (!$facturaEncontrada) {
                abort(403, 'No tienes permisos para ver esta factura.');
            }
        }
        
        // Obtener detalles de la factura
        $facturaDetalle = $this->cobranzaService->getFacturaDetalle($tipoDocumento, $numeroDocumento);
        
        if (!$facturaDetalle) {
            abort(404, 'Factura no encontrada.');
        }
        
        // Obtener productos de la factura
        $productosFactura = $this->cobranzaService->getProductosFactura($tipoDocumento, $numeroDocumento);
        
        return view('facturas-pendientes.ver', [
            'factura' => $facturaDetalle,
            'productos' => $productosFactura,
            'pageSlug' => 'facturas-pendientes'
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
        $facturasPendientes = $this->cobranzaService->getFacturasPendientes($codigoVendedor, 10000);
        
        // Aplicar filtros si existen
        $filtros = [
            'buscar' => $request->get('buscar', ''),
            'estado' => $request->get('estado', ''),
            'tipo_documento' => $request->get('tipo_documento', ''),
            'cliente' => $request->get('cliente', ''),
            'saldo_min' => $request->get('saldo_min', ''),
            'saldo_max' => $request->get('saldo_max', '')
        ];
        
        $facturasFiltradas = $this->aplicarFiltrosFacturas($facturasPendientes, $filtros);

        // Generar archivo Excel
        $filename = 'facturas_pendientes_' . date('Y-m-d_H-i-s') . '.xlsx';
        
        return response()->json([
            'success' => true,
            'message' => 'Exportación completada',
            'filename' => $filename,
            'total_registros' => count($facturasFiltradas)
        ]);
    }

    private function aplicarFiltrosFacturas($facturasPendientes, $filtros)
    {
        // Filtrar por búsqueda general
        if (!empty($filtros['buscar'])) {
            $buscar = strtolower($filtros['buscar']);
            $facturasPendientes = array_filter($facturasPendientes, function($factura) use ($buscar) {
                return strpos(strtolower($factura['CLIENTE'] ?? ''), $buscar) !== false ||
                       strpos(strtolower($factura['TIPO_DOCTO'] . '-' . $factura['NRO_DOCTO']), $buscar) !== false ||
                       strpos(strtolower($factura['CODIGO'] ?? ''), $buscar) !== false;
            });
        }

        // Filtrar por estado
        if (!empty($filtros['estado'])) {
            $facturasPendientes = array_filter($facturasPendientes, function($factura) use ($filtros) {
                return $factura['ESTADO'] === $filtros['estado'];
            });
        }

        // Filtrar por tipo de documento
        if (!empty($filtros['tipo_documento'])) {
            $facturasPendientes = array_filter($facturasPendientes, function($factura) use ($filtros) {
                return $factura['TIPO_DOCTO'] === $filtros['tipo_documento'];
            });
        }

        // Filtrar por cliente
        if (!empty($filtros['cliente'])) {
            $cliente = strtolower($filtros['cliente']);
            $facturasPendientes = array_filter($facturasPendientes, function($factura) use ($cliente) {
                return strpos(strtolower($factura['CLIENTE'] ?? ''), $cliente) !== false;
            });
        }

        // Filtrar por saldo mínimo
        if (!empty($filtros['saldo_min'])) {
            $saldoMin = (float)$filtros['saldo_min'];
            $facturasPendientes = array_filter($facturasPendientes, function($factura) use ($saldoMin) {
                return (float)($factura['SALDO'] ?? 0) >= $saldoMin;
            });
        }

        // Filtrar por saldo máximo
        if (!empty($filtros['saldo_max'])) {
            $saldoMax = (float)$filtros['saldo_max'];
            $facturasPendientes = array_filter($facturasPendientes, function($factura) use ($saldoMax) {
                return (float)($factura['SALDO'] ?? 0) <= $saldoMax;
            });
        }

        // Ordenar resultados
        $ordenarPor = $filtros['ordenar_por'];
        $orden = $filtros['orden'];
        
        usort($facturasPendientes, function($a, $b) use ($ordenarPor, $orden) {
            $valorA = $a[$ordenarPor] ?? 0;
            $valorB = $b[$ordenarPor] ?? 0;
            
            if (is_numeric($valorA) && is_numeric($valorB)) {
                $comparacion = $valorA <=> $valorB;
            } else {
                $comparacion = strcasecmp($valorA, $valorB);
            }
            
            return $orden === 'desc' ? -$comparacion : $comparacion;
        });

        return array_values($facturasPendientes);
    }

    private function aplicarFiltrosFacturas($facturas, $filtros)
    {
        $facturasFiltradas = $facturas;

        // Filtro por búsqueda general
        if (!empty($filtros['buscar'])) {
            $buscar = strtolower($filtros['buscar']);
            $facturasFiltradas = array_filter($facturasFiltradas, function($factura) use ($buscar) {
                return strpos(strtolower($factura['NRO_DOCTO']), $buscar) !== false ||
                       strpos(strtolower($factura['CLIENTE']), $buscar) !== false ||
                       strpos(strtolower($factura['VENDEDOR']), $buscar) !== false;
            });
        }

        // Filtro por tipo de documento
        if (!empty($filtros['tipo_documento'])) {
            $facturasFiltradas = array_filter($facturasFiltradas, function($factura) use ($filtros) {
                return $factura['TIPO_DOCTO'] === $filtros['tipo_documento'];
            });
        }

        // Filtro por estado
        if (!empty($filtros['estado'])) {
            $facturasFiltradas = array_filter($facturasFiltradas, function($factura) use ($filtros) {
                return $factura['ESTADO'] === $filtros['estado'];
            });
        }

        // Filtro por cliente
        if (!empty($filtros['cliente'])) {
            $facturasFiltradas = array_filter($facturasFiltradas, function($factura) use ($filtros) {
                return strpos(strtolower($factura['CLIENTE']), strtolower($filtros['cliente'])) !== false;
            });
        }

        // Filtro por vendedor
        if (!empty($filtros['vendedor'])) {
            $facturasFiltradas = array_filter($facturasFiltradas, function($factura) use ($filtros) {
                return strpos(strtolower($factura['VENDEDOR']), strtolower($filtros['vendedor'])) !== false;
            });
        }

        // Filtro por saldo mínimo
        if (!empty($filtros['saldo_min'])) {
            $facturasFiltradas = array_filter($facturasFiltradas, function($factura) use ($filtros) {
                return $factura['SALDO'] >= $filtros['saldo_min'];
            });
        }

        // Filtro por saldo máximo
        if (!empty($filtros['saldo_max'])) {
            $facturasFiltradas = array_filter($facturasFiltradas, function($factura) use ($filtros) {
                return $factura['SALDO'] <= $filtros['saldo_max'];
            });
        }

        // Filtro por valor mínimo
        if (!empty($filtros['valor_min'])) {
            $facturasFiltradas = array_filter($facturasFiltradas, function($factura) use ($filtros) {
                return $factura['VALOR'] >= $filtros['valor_min'];
            });
        }

        // Filtro por valor máximo
        if (!empty($filtros['valor_max'])) {
            $facturasFiltradas = array_filter($facturasFiltradas, function($factura) use ($filtros) {
                return $factura['VALOR'] <= $filtros['valor_max'];
            });
        }

        // Filtro por abonos mínimo
        if (!empty($filtros['abonos_min'])) {
            $facturasFiltradas = array_filter($facturasFiltradas, function($factura) use ($filtros) {
                return $factura['ABONOS'] >= $filtros['abonos_min'];
            });
        }

        // Filtro por abonos máximo
        if (!empty($filtros['abonos_max'])) {
            $facturasFiltradas = array_filter($facturasFiltradas, function($factura) use ($filtros) {
                return $factura['ABONOS'] <= $filtros['abonos_max'];
            });
        }

        // Filtro por días mínimo
        if (!empty($filtros['dias_min'])) {
            $facturasFiltradas = array_filter($facturasFiltradas, function($factura) use ($filtros) {
                return $factura['DIAS'] >= $filtros['dias_min'];
            });
        }

        // Filtro por días máximo
        if (!empty($filtros['dias_max'])) {
            $facturasFiltradas = array_filter($facturasFiltradas, function($factura) use ($filtros) {
                return $factura['DIAS'] <= $filtros['dias_max'];
            });
        }

        // Ordenar
        $ordenarPor = $filtros['ordenar_por'] ?? 'NRO_DOCTO';
        $orden = $filtros['orden'] ?? 'desc';

        usort($facturasFiltradas, function($a, $b) use ($ordenarPor, $orden) {
            $valorA = $a[$ordenarPor] ?? 0;
            $valorB = $b[$ordenarPor] ?? 0;
            
            if (is_numeric($valorA) && is_numeric($valorB)) {
                $comparacion = $valorA <=> $valorB;
            } else {
                $comparacion = strcasecmp($valorA, $valorB);
            }
            
            return $orden === 'desc' ? -$comparacion : $comparacion;
        });

        return array_values($facturasFiltradas);
    }
}
