<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cliente;
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
        
        if (!$user->hasRole('Vendedor') && !$user->hasRole('Supervisor') && !$user->hasRole('Super Admin')) {
            return redirect()->route('dashboard')->with('error', 'Acceso no autorizado');
        }

        // Supervisores y Super Admin usan la vista de Cobranza con buscador robusto
        if ($user->hasRole('Supervisor') || $user->hasRole('Super Admin')) {
            return redirect()->route('cobranza.index');
        }

        // Si es Vendedor, solo sus clientes
        $clientes = Cliente::getClientesActivosPorVendedor($user->codigo_vendedor);
        
        return view('clientes.index', compact('clientes'))->with('pageSlug', 'clientes');
    }

    public function buscar(Request $request)
    {
        $request->validate([
            'codigo_cliente' => 'required|string|max:20'
        ]);

        $codigoCliente = $request->codigo_cliente;
        $user = auth()->user();

        // PRIMERO: Buscar en base de datos local
        // Si es Supervisor, puede buscar cualquier cliente; si es Vendedor, solo los suyos
        $codigoVendedor = ($user->hasRole('Supervisor') || $user->hasRole('Super Admin')) ? null : $user->codigo_vendedor;
        $cliente = Cliente::buscarPorCodigo($codigoCliente, $codigoVendedor);
        
        if ($cliente) {
            // Obtener información completa (sincroniza si es necesario)
            $cliente = $cliente->obtenerInformacionCompleta();
            
            // Verificar si puede vender al cliente
            $validacion = $cliente->puedeGenerarCotizacion();
            
            // Obtener facturas pendientes desde SQL Server
            $facturasPendientes = $this->cobranzaService->getFacturasPendientesCliente($codigoCliente);

            return response()->json([
                'success' => true,
                'cliente' => [
                    'codigo' => $cliente->codigo_cliente,
                    'nombre' => $cliente->nombre_cliente,
                    'direccion' => $cliente->direccion,
                    'telefono' => $cliente->telefono,
                    'region' => $cliente->region,
                    'comuna' => $cliente->comuna,
                    'vendedor' => $cliente->codigo_vendedor,
                    'lista_precios_codigo' => $cliente->lista_precios_codigo,
                    'lista_precios_nombre' => $cliente->lista_precios_nombre,
                    'bloqueado' => $cliente->bloqueado,
                    'puede_vender' => $validacion['puede'],
                    'motivo_rechazo' => $validacion['motivo'],
                    'facturas_pendientes' => $facturasPendientes
                ]
            ]);
        }

        // SEGUNDO: Si no está en local, buscar en SQL Server
        $clienteExterno = $this->cobranzaService->getClienteInfo($codigoCliente);
        
        if (!$clienteExterno) {
            return response()->json([
                'success' => false,
                'message' => 'Cliente no encontrado'
            ]);
        }

        // Verificar que el cliente pertenece al vendedor (solo para vendedores, no para supervisores)
        if (!$user->hasRole('Supervisor') && !$user->hasRole('Super Admin') && $clienteExterno['CODIGO_VENDEDOR'] !== $user->codigo_vendedor) {
            return response()->json([
                'success' => false,
                'message' => 'Este cliente no está asignado a usted'
            ]);
        }

        // Crear cliente en base local para futuras consultas
        $nuevoCliente = Cliente::create([
            'codigo_cliente' => $clienteExterno['CODIGO_CLIENTE'],
            'nombre_cliente' => $clienteExterno['NOMBRE_CLIENTE'],
            'direccion' => $clienteExterno['DIRECCION'] ?? '',
            'telefono' => $clienteExterno['TELEFONO'] ?? '',
            'codigo_vendedor' => $clienteExterno['CODIGO_VENDEDOR'],
            'region' => $clienteExterno['REGION'] ?? '',
            'comuna' => $clienteExterno['COMUNA'] ?? '',
            'lista_precios_codigo' => $clienteExterno['LISTA_PRECIOS_CODIGO'] ?? '01',
            'lista_precios_nombre' => $clienteExterno['LISTA_PRECIOS_NOMBRE'] ?? 'Lista General',
            'bloqueado' => !empty($clienteExterno['BLOQUEADO']) && $clienteExterno['BLOQUEADO'] != '0',
            'activo' => true,
            'ultima_sincronizacion' => now()
        ]);

        // Validar si puede vender al cliente
        $validacion = $this->cobranzaService->validarClienteParaVenta($codigoCliente);
        
        // Obtener facturas pendientes
        $facturasPendientes = $this->cobranzaService->getFacturasPendientesCliente($codigoCliente);

        return response()->json([
            'success' => true,
            'cliente' => [
                'codigo' => $clienteExterno['CODIGO_CLIENTE'],
                'nombre' => $clienteExterno['NOMBRE_CLIENTE'],
                'direccion' => $clienteExterno['DIRECCION'] ?? '',
                'telefono' => $clienteExterno['TELEFONO'] ?? '',
                'region' => $clienteExterno['REGION'] ?? '',
                'comuna' => $clienteExterno['COMUNA'] ?? '',
                'vendedor' => $clienteExterno['CODIGO_VENDEDOR'],
                'lista_precios_codigo' => $clienteExterno['LISTA_PRECIOS_CODIGO'] ?? '01',
                'lista_precios_nombre' => $clienteExterno['LISTA_PRECIOS_NOMBRE'] ?? 'Lista General',
                'bloqueado' => !empty($clienteExterno['BLOQUEADO']) && $clienteExterno['BLOQUEADO'] != '0',
                'puede_vender' => $validacion['puede_vender'],
                'motivo_rechazo' => $validacion['motivo_rechazo'],
                'facturas_pendientes' => $facturasPendientes
            ]
        ]);
    }

    public function buscarPorNombre(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|min:3'
        ]);

        $nombre = $request->nombre;
        $user = auth()->user();

        // Buscar en base de datos local
        // Si es Supervisor, puede buscar cualquier cliente; si es Vendedor, solo los suyos
        $codigoVendedor = ($user->hasRole('Supervisor') || $user->hasRole('Super Admin')) ? null : $user->codigo_vendedor;
        $clientes = Cliente::buscarPorNombre($nombre, $codigoVendedor);

        return response()->json([
            'success' => true,
            'clientes' => $clientes->map(function($cliente) {
                return [
                    'codigo' => $cliente->codigo_cliente,
                    'nombre' => $cliente->nombre_cliente,
                    'direccion' => $cliente->direccion,
                    'telefono' => $cliente->telefono,
                    'region' => $cliente->region,
                    'comuna' => $cliente->comuna,
                    'bloqueado' => $cliente->bloqueado
                ];
            })
        ]);
    }

    public function sincronizar(Request $request)
    {
        $user = auth()->user();
        
        if (!$user->hasRole('Vendedor') && !$user->hasRole('Supervisor') && !$user->hasRole('Super Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Acceso no autorizado'
            ], 403);
        }

        try {
            // Si es Supervisor, sincronizar todos los clientes
            if ($user->hasRole('Supervisor') || $user->hasRole('Super Admin')) {
                $resultado = \App\Console\Commands\SincronizarClientesSimple::sincronizarTodosLosClientes();
            } else {
                // Si es Vendedor, solo sus clientes
                $resultado = \App\Console\Commands\SincronizarClientesSimple::sincronizarVendedorDirecto($user->codigo_vendedor);
            }
            
            if ($resultado['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Sincronización completada exitosamente',
                    'nuevos' => $resultado['nuevos'],
                    'actualizados' => $resultado['actualizados'],
                    'total' => $resultado['total']
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Error en la sincronización: ' . $resultado['message']
                ], 500);
            }
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error en la sincronización: ' . $e->getMessage()
            ], 500);
        }
    }

    public function buscarAjax(Request $request)
    {
        $request->validate([
            'q' => 'required|string|min:2'
        ]);

        $termino = $request->q;
        $user = auth()->user();

        try {
            // Buscar clientes por nombre o código
            $clientes = \App\Models\Cliente::where('activo', true)
                ->where(function($query) use ($termino) {
                    $query->where('nombre_cliente', 'LIKE', "%{$termino}%")
                          ->orWhere('codigo_cliente', 'LIKE', "%{$termino}%");
                });

            // Si es Vendedor, solo sus clientes
            if ($user->hasRole('Vendedor')) {
                $clientes->where('codigo_vendedor', $user->codigo_vendedor);
            }

            $resultados = $clientes->limit(20)->get();

            return response()->json($resultados);

        } catch (\Exception $e) {
            \Log::error('Error en búsqueda de clientes: ' . $e->getMessage());
            return response()->json([], 500);
        }
    }

    public function estadisticas()
    {
        $user = auth()->user();
        
        if (!$user->hasRole('Vendedor')) {
            return response()->json([
                'success' => false,
                'message' => 'Acceso no autorizado'
            ], 403);
        }

        $totalClientes = Cliente::where('codigo_vendedor', $user->codigo_vendedor)
                               ->where('activo', true)
                               ->count();
        
        $clientesBloqueados = Cliente::where('codigo_vendedor', $user->codigo_vendedor)
                                    ->where('activo', true)
                                    ->where('bloqueado', true)
                                    ->count();
        
        $clientesSinListaPrecios = Cliente::where('codigo_vendedor', $user->codigo_vendedor)
                                         ->where('activo', true)
                                         ->where(function($query) {
                                             $query->whereNull('lista_precios_codigo')
                                                   ->orWhere('lista_precios_codigo', '')
                                                   ->orWhere('lista_precios_codigo', '0');
                                         })
                                         ->count();

        return response()->json([
            'success' => true,
            'estadisticas' => [
                'total_clientes' => $totalClientes,
                'clientes_bloqueados' => $clientesBloqueados,
                'clientes_sin_lista_precios' => $clientesSinListaPrecios,
                'clientes_activos' => $totalClientes - $clientesBloqueados - $clientesSinListaPrecios
            ]
        ]);
    }

    /**
     * Mostrar página de cliente con toda su información
     */
    public function show($codigo)
    {
        $user = auth()->user();
        
        if (!$user->hasRole('Vendedor') && !$user->hasRole('Supervisor') && !$user->hasRole('Super Admin')) {
            return redirect()->route('dashboard')->with('error', 'Acceso no autorizado');
        }

        // Si es Supervisor, puede ver cualquier cliente
        if ($user->hasRole('Supervisor') || $user->hasRole('Super Admin')) {
            $cliente = Cliente::where('codigo_cliente', $codigo)->first();
        } else {
            // Si es Vendedor, solo sus clientes
            $cliente = Cliente::buscarPorCodigo($codigo, $user->codigo_vendedor);
        }
        
        if (!$cliente) {
            // Si no está en local, buscar en SQL Server
            $clienteExterno = $this->cobranzaService->getClienteInfo($codigo);
            
            if (!$clienteExterno) {
                return redirect()->route('dashboard')->with('error', 'Cliente no encontrado');
            }

            // Si es Vendedor, verificar que el cliente le pertenece
            if ($user->hasRole('Vendedor') && $clienteExterno['CODIGO_VENDEDOR'] !== $user->codigo_vendedor) {
                return redirect()->route('dashboard')->with('error', 'Cliente no autorizado');
            }

            // Crear cliente en base local
            $cliente = Cliente::create([
                'codigo_cliente' => $clienteExterno['CODIGO_CLIENTE'],
                'nombre_cliente' => $clienteExterno['NOMBRE_CLIENTE'],
                'direccion' => $clienteExterno['DIRECCION'] ?? '',
                'telefono' => $clienteExterno['TELEFONO'] ?? '',
                'codigo_vendedor' => $clienteExterno['CODIGO_VENDEDOR'],
                'region' => $clienteExterno['REGION'] ?? '',
                'comuna' => $clienteExterno['COMUNA'] ?? '',
                'activo' => true,
                'bloqueado' => false
            ]);
        }

        // Obtener información completa del cliente
        $cliente = $cliente->obtenerInformacionCompleta();

        // Obtener facturas pendientes
        $facturasPendientes = $this->cobranzaService->getFacturasPendientesCliente($codigo);

        // Obtener notas de venta del cliente (SQL Server)
        $notasVenta = $this->cobranzaService->getNotasVentaCliente($codigo);

        // Obtener NVV creadas en el sistema (MySQL)
        $nvvSistema = \App\Models\Cotizacion::where('cliente_codigo', $codigo)
            ->with(['productos', 'user'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function($cotizacion) {
                return [
                    'id' => $cotizacion->id,
                    'numero' => $cotizacion->numero_cotizacion,
                    'fecha_creacion' => $cotizacion->created_at->format('d/m/Y H:i'),
                    'vendedor' => $cotizacion->user->name ?? 'N/A',
                    'estado_aprobacion' => $cotizacion->estado_aprobacion,
                    'tiene_problemas_credito' => $cotizacion->tiene_problemas_credito,
                    'tiene_problemas_stock' => $cotizacion->tiene_problemas_stock,
                    'aprobado_por_supervisor' => $cotizacion->aprobado_por_supervisor,
                    'aprobado_por_compras' => $cotizacion->aprobado_por_compras,
                    'aprobado_por_picking' => $cotizacion->aprobado_por_picking,
                    'total_productos' => $cotizacion->productos->count(),
                    'valor_total' => $cotizacion->productos->sum(function($p) {
                        return $p->cantidad * $p->precio_unitario;
                    }),
                    'estado' => $this->determinarEstadoNVV($cotizacion)
                ];
            });

        // Obtener crédito de compras (ventas de los últimos 3 meses)
        $creditoCompras = $this->cobranzaService->getCreditoComprasCliente($codigo);

        // Obtener información de crédito del cliente
        $creditoCliente = $this->cobranzaService->getCreditoCliente($codigo);

        // Verificar si puede generar cotización
        $validacion = $cliente->puedeGenerarCotizacion();

        return view('clientes.show', compact(
            'cliente',
            'facturasPendientes',
            'notasVenta',
            'nvvSistema',
            'creditoCompras',
            'creditoCliente',
            'validacion'
        ))->with('pageSlug', 'cliente');
    }

    /**
     * Determinar el estado de una NVV del sistema
     */
    private function determinarEstadoNVV($cotizacion)
    {
        if ($cotizacion->aprobado_por_picking) {
            return 'Ingresada';
        } elseif ($cotizacion->aprobado_por_compras) {
            return 'Pendiente Picking';
        } elseif ($cotizacion->aprobado_por_supervisor) {
            if ($cotizacion->tiene_problemas_stock) {
                return 'Pendiente Compras';
            } else {
                return 'Pendiente Picking';
            }
        } else {
            if ($cotizacion->tiene_problemas_credito || $cotizacion->tiene_problemas_stock) {
                return 'Pendiente Supervisor';
            } else {
                return 'Pendiente Supervisor';
            }
        }
    }
}
