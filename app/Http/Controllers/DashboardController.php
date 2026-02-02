<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\CobranzaService;
use App\Models\Cotizacion;
use App\Models\NotaVenta;
use App\Models\StockTemporal;
use App\Models\User;
use App\Models\Cliente;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    protected $cobranzaService;

    public function __construct(CobranzaService $cobranzaService)
    {
        $this->cobranzaService = $cobranzaService;
    }

    /**
     * Determina si el usuario debe ver datos filtrados por su código de vendedor
     * @param User $user
     * @return string|null Código de vendedor si debe filtrar, null si debe ver datos generales
     */
    private function getCodigoVendedorFiltro($user)
    {
        // Si tiene el permiso de filtrar por código Y tiene código de vendedor, filtrar
        if ($user->can('dashboard_filtrar_por_codigo') && $user->codigo_vendedor) {
            return $user->codigo_vendedor;
        }
        
        // Si es Super Admin, nunca filtrar
        if ($user->hasRole('Super Admin')) {
            return null;
        }
        
        // Por defecto, no filtrar (mostrar datos generales)
        return null;
    }

    public function index()
    {
        $user = auth()->user();
        
        if (!$user) {
            return redirect()->route('login');
        }
        
        // Verificar si el usuario tiene acceso al dashboard
        if (!$user->can('ver_dashboard')) {
            abort(403, 'No tienes permiso para acceder al dashboard.');
        }

        $data = [];

        // Para usuarios con roles específicos, usar sus dashboards especializados
        if ($user->hasRole('Supervisor')) {
            $data = $this->getSupervisorDashboard($user);
            $data['pageSlug'] = 'dashboard';
            return view('dashboard.supervisor', $data);
        } elseif ($user->hasRole('Compras')) {
            $data = $this->getComprasDashboard($user);
            $data['pageSlug'] = 'dashboard';
            return view('dashboard.compras', $data);
        } elseif ($user->hasRole('Finanzas')) {
            $data = $this->getSupervisorDashboard($user);
            $data['pageSlug'] = 'dashboard';
            return view('dashboard.finanzas', $data);
        } elseif ($user->hasRole('Picking')) {
            $data = $this->getPickingDashboard($user);
            $data['pageSlug'] = 'dashboard';
            return view('dashboard.picking', $data);
        } elseif ($user->hasRole('Super Admin')) {
            // Super Admin ve todo - cargar todos los datos
            $data = $this->getSuperAdminDashboard($user);
        } elseif ($user->hasRole('Vendedor')) {
            // Vendedor tiene su propio dashboard
            $data = $this->getVendedorDashboard($user);
        } else {
            // Para otros usuarios (incluyendo Administrativo), cargar dashboard basado en permisos granulares
            $data = $this->getDashboardByPermissions($user);
        }

        // Agregar pageSlug para el sidebar
        $data['pageSlug'] = 'dashboard';
        
        return view('dashboard.index', $data);
    }

    /**
     * Carga datos del dashboard basado en los permisos del usuario
     */
    private function getDashboardByPermissions($user)
    {
        $codigoVendedor = $this->getCodigoVendedorFiltro($user);
        
        $data = [
            'tipoUsuario' => $user->getRolPrincipalAttribute(),
            'resumenCobranza' => [],
            'resumenNvvPendientes' => [],
            'resumenCompras' => [],
            'totalUsuarios' => 0,
            'usuariosPorRol' => [],
        ];

        try {
            // Si tiene permiso para ver resumen de usuarios, cargar datos
            if ($user->can('ver_dashboard_card_total_usuarios') || $user->hasRole('Super Admin')) {
                $data['totalUsuarios'] = User::count();
                $roles = ['Super Admin', 'Vendedor', 'Supervisor', 'Compras', 'Picking'];
                foreach ($roles as $rol) {
                    try {
                        $data['usuariosPorRol'][$rol] = User::role($rol)->count();
                    } catch (\Exception $e) {
                        $data['usuariosPorRol'][$rol] = 0;
                    }
                }
            }

            // Cargar datos de cobranza si tiene permisos
            if ($user->can('ver_dashboard_card_total_documentos_pendientes') || 
                $user->can('ver_dashboard_card_total_documentos_vencidos') ||
                $user->can('ver_dashboard_card_cheques_cartera') ||
                $user->can('ver_dashboard_card_cheques_protestados') ||
                $user->hasRole('Super Admin')) {
                
                $resumenFacturasPendientes = $this->cobranzaService->getResumenFacturasPendientes($codigoVendedor);
                $facturasPendientes = $this->cobranzaService->getFacturasPendientes($codigoVendedor, 10);
                $chequesEnCartera = $this->cobranzaService->getChequesEnCartera($codigoVendedor);
                $chequesProtestados = $this->cobranzaService->getChequesProtestados($codigoVendedor);

                $data['resumenCobranza'] = [
                    'TOTAL_FACTURAS' => $resumenFacturasPendientes['total_facturas'] ?? 0,
                    'TOTAL_NOTAS_VENTA' => $this->cobranzaService->getTotalNotasVentaSQL(),
                    'SALDO_VENCIDO' => ($resumenFacturasPendientes['por_estado']['VENCIDO']['valor'] ?? 0) + 
                                       ($resumenFacturasPendientes['por_estado']['MOROSO']['valor'] ?? 0) + 
                                       ($resumenFacturasPendientes['por_estado']['BLOQUEAR']['valor'] ?? 0),
                    'CHEQUES_EN_CARTERA' => $chequesEnCartera,
                    'CHEQUES_PROTESTADOS' => $chequesProtestados,
                ];
                $data['facturasPendientes'] = $facturasPendientes;
                $data['resumenFacturasPendientes'] = $resumenFacturasPendientes;
            }

            // Cargar datos de NVV si tiene permisos
            if ($user->can('ver_dashboard_card_nvv_pendientes') || 
                $user->can('ver_dashboard_card_valor_nvv_pendientes') ||
                $user->hasRole('Super Admin')) {
                
                $data['resumenNvvPendientes'] = $this->cobranzaService->getResumenNvvPendientes($codigoVendedor);
                $data['nvvPendientes'] = $this->cobranzaService->getNvvPendientesDetalle($codigoVendedor, 10);
            }

            // Cargar datos adicionales para otras cards si tiene permisos
            if ($user->can('ver_dashboard_card_total_notas_venta_aprobacion') || $user->hasRole('Super Admin')) {
                $data['resumenCobranza']['TOTAL_NOTAS_VENTA'] = $this->cobranzaService->getTotalNotasVentaSQL();
            }

            if ($user->can('ver_dashboard_card_nvv_por_validar') || $user->hasRole('Super Admin')) {
                $data['resumenCobranza']['TOTAL_NOTAS_PENDIENTES_VALIDAR'] = Cotizacion::where(function($query) {
                    $query->where('estado_aprobacion', 'pendiente')
                          ->orWhere('estado_aprobacion', 'pendiente_picking')
                          ->orWhere('estado_aprobacion', 'aprobada_supervisor');
                })->count();
            }

            if ($user->can('ver_dashboard_card_nvv_sistema_mes') || $user->hasRole('Super Admin')) {
                $data['resumenCobranza']['TOTAL_NOTAS_VENTA_MES_ACTUAL'] = $this->cobranzaService->getTotalNotasVentaMesActual();
            }

            if ($user->can('ver_dashboard_card_facturas_mes') || $user->hasRole('Super Admin')) {
                $facturasMesActual = $this->cobranzaService->getFacturasPendientesMesActual($codigoVendedor);
                $data['resumenCobranza']['TOTAL_FACTURAS_MES_ACTUAL'] = count($facturasMesActual);
            }

            // Cargar datos de productos bajo stock si tiene permisos
            if ($user->can('ver_dashboard_card_productos_bajo_stock') || $user->hasRole('Super Admin')) {
                try {
                    $productosBajoStock = $this->getProductosBajoStockMySQL();
                    $resumenCompras = $this->getResumenComprasMySQL();
                    $data['productosBajoStock'] = $productosBajoStock;
                    $data['resumenCompras'] = $resumenCompras;
                } catch (\Exception $e) {
                    Log::error("Error al cargar productos bajo stock: " . $e->getMessage());
                    $data['productosBajoStock'] = [];
                    $data['resumenCompras'] = [
                        'productos_bajo_stock' => 0,
                        'total_compras_mes' => 0,
                        'compras_pendientes' => 0,
                        'mes_actual' => date('Y-m')
                    ];
                }
            }

            // Datos para Vendedor (clientes asignados)
            if ($user->hasRole('Vendedor') && $codigoVendedor) {
                $filtros = [
                    'buscar' => request()->get('buscar', ''),
                    'ordenar_por' => request()->get('ordenar_por', 'NOMBRE_CLIENTE'),
                    'orden' => request()->get('orden', 'asc'),
                ];
                $clientesAsignados = $this->cobranzaService->getClientesPorVendedor($codigoVendedor);
                $clientesFiltrados = $this->aplicarFiltrosClientes($clientesAsignados, $filtros);
                $perPage = 10;
                $currentPage = request()->get('page', 1);
                $offset = ($currentPage - 1) * $perPage;
                
                $data['clientesAsignados'] = new \Illuminate\Pagination\LengthAwarePaginator(
                    array_slice($clientesFiltrados, $offset, $perPage),
                    count($clientesFiltrados),
                    $perPage,
                    $currentPage,
                    ['path' => request()->url(), 'query' => request()->query()]
                );
                $data['filtros'] = $filtros;
            }

        } catch (\Exception $e) {
            Log::error("Error en getDashboardByPermissions: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());
        }

        return $data;
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
        
        // Obtener clientes vencidos, bloqueados y morosos
        $clientesVencidosBloqueadosMorosos = $this->cobranzaService->getClientesVencidosBloqueadosMorosos($user->codigo_vendedor);
        
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
        
        // Obtener cheques protestados
        $chequesProtestados = $this->getChequesProtestadosVendedor($user->codigo_vendedor);

        // Calcular resumen de cobranza con datos reales
        $resumenCobranza = [
            'TOTAL_FACTURAS' => $resumenFacturasPendientes['total_facturas'],
            'TOTAL_NOTAS_VENTA' => $totalNotasVenta,
            'SALDO_VENCIDO' => $resumenFacturasPendientes['por_estado']['VENCIDO']['valor'] + $resumenFacturasPendientes['por_estado']['MOROSO']['valor'] + $resumenFacturasPendientes['por_estado']['BLOQUEAR']['valor'],
            'CHEQUES_EN_CARTERA' => $chequesEnCartera,
            'CHEQUES_PROTESTADOS' => $chequesProtestados['valor_total']
        ];

        return [
            'clientesAsignados' => $clientesPaginated,
            'clientesVencidosBloqueadosMorosos' => $clientesVencidosBloqueadosMorosos,
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
        try {
            // 1. FACTURAS PENDIENTES (listado) - Solo 10 más recientes para la tabla
            $facturasPendientes = $this->cobranzaService->getFacturasPendientes(null, 10);
            
            // 2. RESUMEN DE FACTURAS PENDIENTES - TOTAL REAL de todas las facturas del sistema
            $resumenFacturasPendientes = $this->cobranzaService->getResumenFacturasPendientes(null);
            $totalFacturasPendientes = $resumenFacturasPendientes['total_facturas'] ?? 0;
            
            // 3. FACTURAS DEL MES ACTUAL
            $facturasMesActual = $this->cobranzaService->getFacturasPendientesMesActual(null);
            
            // 4. TOTAL NOTAS DE VENTAS PENDIENTES POR VALIDAR (cantidad) - TODAS las notas pendientes
            $notasPendientesSupervisor = Cotizacion::where(function($query) {
                $query->where('estado_aprobacion', 'pendiente')
                      ->orWhere('estado_aprobacion', 'pendiente_picking')
                      ->orWhere('estado_aprobacion', 'aprobada_supervisor');
            })->count();

            // 5. NOTAS DE VENTA PENDIENTES (listado limitado) - Solo 10 más recientes
            $notasPendientes = Cotizacion::where(function($query) {
                $query->where('estado_aprobacion', 'pendiente')
                      ->orWhere('estado_aprobacion', 'pendiente_picking')
                      ->orWhere('estado_aprobacion', 'aprobada_supervisor');
            })
                ->with(['user', 'cliente'])
                ->latest()
                ->take(10)
                ->get();

            // 6. NOTAS DE VENTA EN SQL (listado limitado) - Solo 10 más recientes
            $notasVentaSQL = $this->cobranzaService->getNotasVentaSQL(10);
            
            // 7. NVV DEL MES ACTUAL (cantidad)
            $nvvMesActual = $this->cobranzaService->getTotalNotasVentaMesActual();

            // 8. CHEQUES EN CARTERA - TODOS los cheques del sistema
            $chequesEnCartera = $this->cobranzaService->getChequesEnCartera(null);

            // 9. CHEQUES PROTESTADOS - TODOS los cheques protestados del sistema
            $chequesProtestados = $this->cobranzaService->getChequesProtestados(null);

            // Resumen para las tarjetas principales
            $resumenCobranza = [
                'TOTAL_FACTURAS_PENDIENTES' => $totalFacturasPendientes, // Total real, no solo 10
                'TOTAL_NOTAS_VENTA_SQL' => $this->cobranzaService->getTotalNotasVentaSQL(),
                'TOTAL_NOTAS_VENTA_MES_ACTUAL' => $nvvMesActual,
                'TOTAL_FACTURAS_MES_ACTUAL' => count($facturasMesActual),
                'TOTAL_NOTAS_PENDIENTES_VALIDAR' => $notasPendientesSupervisor,
                'TOTAL_FACTURAS' => $totalFacturasPendientes,
                'TOTAL_NOTAS_VENTA' => $this->cobranzaService->getTotalNotasVentaSQL(),
                'CHEQUES_EN_CARTERA' => $chequesEnCartera,
                'CHEQUES_PROTESTADOS' => $chequesProtestados,
                'SALDO_VENCIDO' => ($resumenFacturasPendientes['por_estado']['VENCIDO']['valor'] ?? 0) + 
                                   ($resumenFacturasPendientes['por_estado']['MOROSO']['valor'] ?? 0) + 
                                   ($resumenFacturasPendientes['por_estado']['BLOQUEAR']['valor'] ?? 0)
            ];

            return [
                'notasPendientes' => $notasPendientes,
                'notasVentaSQL' => $notasVentaSQL,
                'facturasPendientes' => $facturasPendientes, // Solo 10 para la tabla
                'resumenFacturasPendientes' => $resumenFacturasPendientes, // Resumen completo
                'resumenCobranza' => $resumenCobranza,
                'tipoUsuario' => 'Supervisor'
            ];

        } catch (\Exception $e) {
            Log::error("Error en getSupervisorDashboard: " . $e->getMessage());
            
            // Fallback con datos básicos
            return [
                'notasPendientes' => collect(),
                'notasVentaSQL' => [],
                'facturasPendientes' => [],
                'resumenFacturasPendientes' => [
                    'total_facturas' => 0,
                    'total_saldo' => 0,
                    'por_estado' => [
                        'VIGENTE' => ['cantidad' => 0, 'valor' => 0],
                        'POR VENCER' => ['cantidad' => 0, 'valor' => 0],
                        'VENCIDO' => ['cantidad' => 0, 'valor' => 0],
                        'MOROSO' => ['cantidad' => 0, 'valor' => 0],
                        'BLOQUEAR' => ['cantidad' => 0, 'valor' => 0]
                    ]
                ],
                'resumenCobranza' => [
                    'TOTAL_FACTURAS_PENDIENTES' => 0,
                    'TOTAL_NOTAS_VENTA_SQL' => 0,
                    'TOTAL_NOTAS_VENTA_MES_ACTUAL' => 0,
                    'TOTAL_FACTURAS_MES_ACTUAL' => 0,
                    'TOTAL_NOTAS_PENDIENTES_VALIDAR' => 0,
                    'TOTAL_FACTURAS' => 0,
                    'TOTAL_NOTAS_VENTA' => 0,
                    'CHEQUES_EN_CARTERA' => 0,
                    'CHEQUES_PROTESTADOS' => 0
                ],
                'tipoUsuario' => 'Supervisor'
            ];
        }
    }

    private function getComprasDashboard($user)
    {
        try {
            // Obtener TODAS las facturas pendientes del sistema (sin filtro de vendedor)
            $facturasPendientes = $this->cobranzaService->getFacturasPendientes(null, 10);
            $resumenFacturasPendientes = $this->cobranzaService->getResumenFacturasPendientes(null);

            // Obtener TODAS las NVV del sistema (sin filtro de vendedor)
            $nvvSistema = $this->cobranzaService->getNotasVentaSQL(10);
            $totalNvvSistema = $this->cobranzaService->getTotalNotasVentaSQL();

            // Obtener NVV pendientes de aprobación por Compras
            $nvvPendientes = $this->getNvvPendientesCompras();

            // Productos con bajo stock
            $productosBajoStock = $this->getProductosBajoStockMySQL();

            // Resumen básico de compras desde MySQL
            $resumenCompras = $this->getResumenComprasMySQL();

            // Crear resumen de cobranza para las tarjetas del dashboard
            $resumenCobranza = [
                'TOTAL_FACTURAS_PENDIENTES' => $resumenFacturasPendientes['total_facturas'] ?? 0,
                'TOTAL_NOTAS_VENTA_SQL' => $totalNvvSistema,
                'TOTAL_NOTAS_PENDIENTES_VALIDAR' => count($nvvPendientes),
                'SALDO_VENCIDO' => $resumenFacturasPendientes['por_estado']['VENCIDO']['valor'] + $resumenFacturasPendientes['por_estado']['MOROSO']['valor'] + $resumenFacturasPendientes['por_estado']['BLOQUEAR']['valor']
            ];

            return [
                'facturasPendientes' => $facturasPendientes,
                'resumenFacturasPendientes' => $resumenFacturasPendientes,
                'nvvSistema' => $nvvSistema,
                'totalNvvSistema' => $totalNvvSistema,
                'nvvPendientes' => $nvvPendientes,
                'productosBajoStock' => $productosBajoStock,
                'resumenCompras' => $resumenCompras,
                'resumenCobranza' => $resumenCobranza,
                'tipoUsuario' => 'Compras'
            ];
        } catch (\Exception $e) {
            \Log::error("Error en dashboard de Compras: " . $e->getMessage());
            
            // Retornar datos básicos en caso de error
            return [
                'facturasPendientes' => [],
                'resumenFacturasPendientes' => [
                    'total_facturas' => 0,
                    'por_estado' => []
                ],
                'nvvSistema' => [],
                'totalNvvSistema' => 0,
                'nvvPendientes' => [],
                'productosBajoStock' => [],
                'resumenCompras' => [
                    'total_compras_mes' => 0,
                    'productos_bajo_stock' => 0,
                    'compras_pendientes' => 0
                ],
                'resumenCobranza' => [
                    'TOTAL_FACTURAS_PENDIENTES' => 0,
                    'TOTAL_NOTAS_VENTA_SQL' => 0,
                    'TOTAL_NOTAS_PENDIENTES_VALIDAR' => 0
                ],
                'tipoUsuario' => 'Compras',
                'error' => 'Error al cargar datos del dashboard'
            ];
        }
    }

    private function aplicarFiltrosClientes($clientes, $filtros)
    {
        // Filtrar por búsqueda (código o nombre) con soporte para múltiples términos
        if (!empty($filtros['buscar'])) {
            $buscar = strtolower($filtros['buscar']);
            $terminos = array_filter(explode(' ', trim($buscar)));
            
            $clientes = array_filter($clientes, function($cliente) use ($terminos) {
                $codigoCliente = strtolower($cliente['CODIGO_CLIENTE']);
                $nombreCliente = strtolower($cliente['NOMBRE_CLIENTE']);
                
                if (count($terminos) > 1) {
                    // Búsqueda con múltiples términos: todos deben estar presentes
                    foreach ($terminos as $termino) {
                        if (strpos($codigoCliente, $termino) === false && 
                            strpos($nombreCliente, $termino) === false) {
                            return false;
                        }
                    }
                    return true;
                } else {
                    // Búsqueda simple
                    return strpos($codigoCliente, $buscar) !== false ||
                           strpos($nombreCliente, $buscar) !== false;
                }
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
        try {
            // 1. FACTURAS PENDIENTES (cantidad y listado) - Solo 10 más recientes
            $facturasPendientes = $this->cobranzaService->getFacturasPendientes(null, 10); // Sin filtro de vendedor, limitado a 10
            $totalFacturasPendientes = count($facturasPendientes);

            // 2. TOTAL NOTAS DE VENTAS EN SQL (cantidad) - TODAS las notas del sistema
            $totalNotasVentaSQL = $this->cobranzaService->getTotalNotasVentaSQL();

            // 3. TOTAL NOTAS DE VENTAS PENDIENTES POR VALIDAR (cantidad) - TODAS las notas pendientes
            $notasPendientesSupervisor = Cotizacion::where('estado_aprobacion', 'pendiente')
                ->orWhere('estado_aprobacion', 'pendiente_picking')
                ->orWhere('estado_aprobacion', 'aprobada_supervisor')
                ->count();

            // 4. NOTAS DE VENTA PENDIENTES (listado limitado) - Solo 10 más recientes
            $notasPendientes = Cotizacion::where('estado_aprobacion', 'pendiente')
                ->orWhere('estado_aprobacion', 'pendiente_picking')
                ->orWhere('estado_aprobacion', 'aprobada_supervisor')
                ->with(['user', 'cliente'])
                ->latest()
                ->take(10)
                ->get();

            // 5. NOTAS DE VENTA EN SQL (listado limitado) - Solo 10 más recientes
            $notasVentaSQL = $this->cobranzaService->getNotasVentaSQL(10);

            // 6. CHEQUES EN CARTERA - TODOS los cheques del sistema
            $chequesEnCartera = $this->cobranzaService->getChequesEnCartera(null); // Sin filtro de vendedor

            // 7. RESUMEN DE FACTURAS PENDIENTES - TODAS las facturas del sistema
            $resumenFacturasPendientes = $this->cobranzaService->getResumenFacturasPendientes(null); // Sin filtro de vendedor

            // 8. INFORMACIÓN DE USUARIOS
            $totalUsuarios = User::count();
            $usuariosPorRol = [];
            
            $roles = ['Super Admin', 'Vendedor', 'Supervisor', 'Compras', 'Picking'];
            foreach ($roles as $rol) {
                try {
                    $usuariosPorRol[$rol] = User::role($rol)->count();
                } catch (\Exception $e) {
                    $usuariosPorRol[$rol] = 0;
                }
            }

            // Resumen para las tarjetas principales
            $resumenCobranza = [
                'TOTAL_FACTURAS_PENDIENTES' => $totalFacturasPendientes,
                'TOTAL_NOTAS_VENTA_SQL' => $totalNotasVentaSQL,
                'TOTAL_NOTAS_PENDIENTES_VALIDAR' => $notasPendientesSupervisor,
                'TOTAL_FACTURAS' => $totalFacturasPendientes,
                'TOTAL_NOTAS_VENTA' => $totalNotasVentaSQL,
                'CHEQUES_EN_CARTERA' => $chequesEnCartera,
                'SALDO_VENCIDO' => $resumenFacturasPendientes['por_estado']['VENCIDO']['valor'] + $resumenFacturasPendientes['por_estado']['MOROSO']['valor'] + $resumenFacturasPendientes['por_estado']['BLOQUEAR']['valor']
            ];

            return [
                'notasPendientes' => $notasPendientes,
                'notasVentaSQL' => $notasVentaSQL,
                'facturasPendientes' => $facturasPendientes, // Agregado para la tabla
                'resumenCobranza' => $resumenCobranza,
                'totalUsuarios' => $totalUsuarios,
                'usuariosPorRol' => $usuariosPorRol,
                'tipoUsuario' => 'Super Admin'
            ];

        } catch (\Exception $e) {
            Log::error("Error en getSuperAdminDashboard: " . $e->getMessage());
            
            // Fallback con datos básicos
            return [
                'notasPendientes' => collect(),
                'notasVentaSQL' => [],
                'facturasPendientes' => [],
                'resumenCobranza' => [
                    'TOTAL_FACTURAS_PENDIENTES' => 0,
                    'TOTAL_NOTAS_VENTA_SQL' => 0,
                    'TOTAL_NOTAS_PENDIENTES_VALIDAR' => 0,
                    'TOTAL_FACTURAS' => 0,
                    'TOTAL_NOTAS_VENTA' => 0,
                    'CHEQUES_EN_CARTERA' => 0
                ],
                'totalUsuarios' => 0,
                'usuariosPorRol' => [],
                'tipoUsuario' => 'Super Admin'
            ];
        }
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
        try {
            // Obtener facturas pendientes (limitado a 5 para dashboard)
            $facturasPendientes = $this->cobranzaService->getFacturasPendientes(null, 5);
            $resumenFacturasPendientes = $this->cobranzaService->getResumenFacturasPendientes(null);

            // Obtener NVV del sistema SQL - TODAS con límite de 5 para tabla
            $nvvSistema = $this->cobranzaService->getNotasVentaSQL(5);
            $totalNvvSistema = $this->cobranzaService->getTotalNotasVentaSQL();

            // Obtener NVV pendientes de aprobación por Picking (limitado a 5)
            $nvvPendientes = $this->getNvvPendientesPicking();

            // Crear resumen de cobranza para las tarjetas del dashboard
            $resumenCobranza = [
                'TOTAL_FACTURAS_PENDIENTES' => $resumenFacturasPendientes['total_facturas'] ?? 0,
                'TOTAL_NOTAS_VENTA_SQL' => $totalNvvSistema, // Este es el TOTAL correcto de todas las NVV
                'TOTAL_NOTAS_PENDIENTES_VALIDAR' => count($nvvPendientes),
                'SALDO_VENCIDO' => $resumenFacturasPendientes['por_estado']['VENCIDO']['valor'] + $resumenFacturasPendientes['por_estado']['MOROSO']['valor'] + $resumenFacturasPendientes['por_estado']['BLOQUEAR']['valor']
            ];

            return [
                'facturasPendientes' => $facturasPendientes,
                'resumenFacturasPendientes' => $resumenFacturasPendientes,
                'nvvSistema' => $nvvSistema, // Solo 5 para la tabla
                'totalNvvSistema' => $totalNvvSistema, // Total real de todas las NVV
                'notasPendientes' => $nvvPendientes,
                'resumenCobranza' => $resumenCobranza,
                'tipoUsuario' => 'Picking'
            ];
        } catch (\Exception $e) {
            \Log::error("Error en dashboard de Picking: " . $e->getMessage());
            \Log::error("Stack trace: " . $e->getTraceAsString());
            
            // Retornar datos básicos en caso de error
            return [
                'facturasPendientes' => [],
                'resumenFacturasPendientes' => [
                    'total_facturas' => 0,
                    'por_estado' => []
                ],
                'nvvSistema' => [],
                'totalNvvSistema' => 0,
                'notasPendientes' => [],
                'resumenCobranza' => [
                    'TOTAL_FACTURAS_PENDIENTES' => 0,
                    'TOTAL_NOTAS_VENTA_SQL' => 0,
                    'TOTAL_NOTAS_PENDIENTES_VALIDAR' => 0
                ],
                'tipoUsuario' => 'Picking',
                'error' => 'Error al cargar datos del dashboard'
            ];
        }
    }

    private function getNvvPendientesPicking()
    {
        try {
            // Obtener NVV pendientes de Picking:
            // 1. Estado 'pendiente_picking' (sin problemas)
            // 2. Estado 'aprobada_compras' (con problemas resueltos por Compras)
            $nvvPendientes = Cotizacion::with(['user', 'cliente'])
                ->where(function($query) {
                    $query->where('estado_aprobacion', 'pendiente_picking')
                          ->orWhere('estado_aprobacion', 'aprobada_compras');
                })
                ->whereNull('aprobado_por_picking')
                ->where('tipo_documento', 'nota_venta') // Solo notas de venta
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            // No mapear, devolver la colección directamente con las relaciones cargadas
            return $nvvPendientes->map(function($cotizacion) {
                // Agregar campos adicionales como atributos temporales
                $cotizacion->numero_nota_venta = 'N°' . $cotizacion->id;
                $cotizacion->nombre_cliente = $cotizacion->cliente_nombre; // Ya viene de la tabla cotizaciones
                return $cotizacion;
            });
        } catch (\Exception $e) {
            \Log::error("Error al obtener NVV pendientes de Picking: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Métodos optimizados para el dashboard de Compras usando solo MySQL
     */
    private function getProductosBajoStockMySQL()
    {
        try {
            // Consulta desde MySQL (tabla productos local)
            // Usar stock <= 5 como criterio de "bajo stock" ya que no hay columna stock_minimo
            $productos = \DB::table('productos')
                ->select([
                    'NOKOPR as codigo',
                    'NOKOPR as nombre',
                    'stock_fisico as stock_actual',
                    'stock_comprometido',
                    'stock_disponible',
                    'precio_01p as precio_compra'
                ])
                ->where('activo', true)
                ->where('stock_fisico', '<=', 5) // Stock bajo si es <= 5
                ->orderBy('stock_fisico', 'asc')
                ->limit(10)
                ->get();

            return $productos->map(function($producto) {
                return [
                    'codigo' => $producto->codigo,
                    'nombre' => $producto->nombre,
                    'stock_actual' => $producto->stock_actual,
                    'stock_comprometido' => $producto->stock_comprometido,
                    'stock_disponible' => $producto->stock_disponible,
                    'precio_compra' => $producto->precio_compra,
                    'diferencia' => 5 - $producto->stock_actual // Diferencia con stock mínimo de 5
                ];
            })->toArray();
        } catch (\Exception $e) {
            \Log::error("Error al obtener productos bajo stock desde MySQL: " . $e->getMessage());
            return [];
        }
    }

    private function getResumenComprasMySQL()
    {
        try {
            $mesActual = date('Y-m');
            
            // Consultas desde MySQL (tabla productos local)
            // Usar stock <= 5 como criterio de "bajo stock"
            $productosBajoStock = \DB::table('productos')
                ->where('activo', true)
                ->where('stock_fisico', '<=', 5)
                ->count();

            // Obtener cotizaciones pendientes como "compras pendientes"
            $comprasPendientes = \App\Models\Cotizacion::where('estado_aprobacion', 'aprobada_supervisor')
                ->whereNull('aprobado_por_compras')
                ->count();

            // Obtener total de cotizaciones del mes
            $totalComprasMes = \App\Models\Cotizacion::where('created_at', 'like', $mesActual . '%')
                ->count();

            return [
                'total_compras_mes' => $totalComprasMes,
                'productos_bajo_stock' => $productosBajoStock,
                'compras_pendientes' => $comprasPendientes,
                'mes_actual' => $mesActual
            ];
        } catch (\Exception $e) {
            \Log::error("Error al obtener resumen de compras desde MySQL: " . $e->getMessage());
            return [
                'total_compras_mes' => 0,
                'productos_bajo_stock' => 0,
                'compras_pendientes' => 0,
                'mes_actual' => date('Y-m')
            ];
        }
    }

    /**
     * Obtener NVV pendientes de aprobación por Compras
     */
    private function getNvvPendientesCompras()
    {
        try {
            // Obtener cotizaciones pendientes de aprobación por Compras
            // Incluir todas las cotizaciones que necesitan aprobación por Compras:
            // 1. Las que ya fueron aprobadas por supervisor pero no por compras
            // 2. Las que están pendientes (necesitan aprobación por Compras)
            // 3. Las que están pendiente_picking (necesitan aprobación por Compras)
            $cotizaciones = \App\Models\Cotizacion::with(['user', 'productos'])
                ->where(function($query) {
                    $query->where(function($q) {
                        // Ya aprobadas por supervisor pero no por compras
                        $q->where('estado_aprobacion', 'aprobada_supervisor')
                          ->whereNull('aprobado_por_compras');
                    })->orWhere(function($q) {
                        // Pendientes que necesitan aprobación por Compras
                        $q->where('estado_aprobacion', 'pendiente');
                    })->orWhere(function($q) {
                        // Pendientes de picking que necesitan aprobación por Compras
                        $q->where('estado_aprobacion', 'pendiente_picking');
                    });
                })
                ->orderBy('created_at', 'desc')
                ->limit(20) // Aumentar límite para incluir más cotizaciones
                ->get();

            return $cotizaciones->map(function($cotizacion) {
                return [
                    'id' => $cotizacion->id,
                    'numero' => 'N°' . $cotizacion->id,
                    'cliente_codigo' => $cotizacion->cliente_codigo,
                    'cliente_nombre' => $cotizacion->cliente_nombre,
                    'total' => $cotizacion->total,
                    'fecha_creacion' => $cotizacion->created_at->format('d/m/Y H:i'),
                    'vendedor' => $cotizacion->user->name ?? 'N/A',
                    'productos_count' => $cotizacion->productos->count(),
                    'tiene_problemas_stock' => $cotizacion->tiene_problemas_stock ?? false,
                    'tiene_problemas_credito' => $cotizacion->tiene_problemas_credito ?? false,
                    'estado' => $cotizacion->estado_aprobacion,
                    'url' => route('aprobaciones.show', $cotizacion->id)
                ];
            })->toArray();
        } catch (\Exception $e) {
            \Log::error("Error al obtener NVV pendientes de Compras: " . $e->getMessage());
            return [];
        }
    }
    
    private function getChequesProtestadosVendedor($codigoVendedor)
    {
        try {
            $cheques = \DB::table('cheques_protestados')
                ->where('codigo_vendedor', $codigoVendedor)
                ->get();
            
            $valorTotal = $cheques->sum('valor');
            $cantidad = $cheques->count();
            
            return [
                'cantidad' => $cantidad,
                'valor_total' => $valorTotal,
                'cheques' => $cheques->toArray()
            ];
            
        } catch (\Exception $e) {
            \Log::error('Error obteniendo cheques protestados para vendedor: ' . $e->getMessage());
            return [
                'cantidad' => 0,
                'valor_total' => 0,
                'cheques' => []
            ];
        }
    }
}
