<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\CobranzaService;
use Illuminate\Support\Facades\Auth;

class CobranzaController extends Controller
{
    protected $cobranzaService;

    public function __construct(CobranzaService $cobranzaService)
    {
        $this->cobranzaService = $cobranzaService;
        $this->middleware('auth');
    }

    public function index()
    {
        $user = Auth::user();
        
        // Obtener parámetros de filtro
        $filtros = [
            'buscar' => request()->get('buscar', ''),
            'ordenar_por' => request()->get('ordenar_por', 'NOMBRE_CLIENTE'),
            'orden' => request()->get('orden', 'asc'),
            'saldo_min' => request()->get('saldo_min', ''),
            'saldo_max' => request()->get('saldo_max', ''),
            'facturas_min' => request()->get('facturas_min', ''),
            'facturas_max' => request()->get('facturas_max', ''),
            'cheques_protestados' => request()->get('cheques_protestados', '')
        ];
        
        // Obtener clientes asignados al vendedor
        $clientes = $this->cobranzaService->getClientesPorVendedor($user->codigo_vendedor);
        
        // Aplicar filtros
        $clientesFiltrados = $this->aplicarFiltrosClientes($clientes, $filtros);
        
        // Paginar los clientes
        $perPage = 15;
        $currentPage = request()->get('page', 1);
        $offset = ($currentPage - 1) * $perPage;
        
        $clientesPaginated = new \Illuminate\Pagination\LengthAwarePaginator(
            array_slice($clientesFiltrados, $offset, $perPage),
            count($clientesFiltrados),
            $perPage,
            $currentPage,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        return view('cobranza.index', [
            'clientes' => $clientesPaginated,
            'filtros' => $filtros,
            'pageSlug' => 'buscar-clientes'
        ]);
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
        
        // Filtrar por cheques protestados
        if (!empty($filtros['cheques_protestados'])) {
            $clientes = array_filter($clientes, function($cliente) use ($filtros) {
                $tieneChequesProtestados = $this->verificarChequesProtestados($cliente['CODIGO_CLIENTE']);
                
                if ($filtros['cheques_protestados'] === 'si') {
                    return $tieneChequesProtestados['tiene_cheques_protestados'];
                } elseif ($filtros['cheques_protestados'] === 'no') {
                    return !$tieneChequesProtestados['tiene_cheques_protestados'];
                }
                
                return true;
            });
        }
        
        // Agregar información de cheques protestados a cada cliente
        foreach ($clientes as &$cliente) {
            $cliente['cheques_protestados'] = $this->verificarChequesProtestados($cliente['CODIGO_CLIENTE']);
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
    
    private function verificarChequesProtestados($codigoCliente)
    {
        try {
            $cheques = \DB::table('cheques_protestados')
                ->where('codigo_cliente', $codigoCliente)
                ->get();
            
            if ($cheques->isEmpty()) {
                return [
                    'tiene_cheques_protestados' => false,
                    'cantidad' => 0,
                    'valor_total' => 0,
                    'cheques' => []
                ];
            }
            
            $valorTotal = $cheques->sum('valor');
            
            return [
                'tiene_cheques_protestados' => true,
                'cantidad' => $cheques->count(),
                'valor_total' => $valorTotal,
                'cheques' => $cheques->toArray()
            ];
            
        } catch (\Exception $e) {
            \Log::error('Error verificando cheques protestados en cobranza: ' . $e->getMessage());
            return [
                'tiene_cheques_protestados' => false,
                'cantidad' => 0,
                'valor_total' => 0,
                'cheques' => []
            ];
        }
    }
}
