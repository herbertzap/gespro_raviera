<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cotizacion;
use App\Models\Cliente;
use App\Services\CobranzaService;
use App\Services\StockService;
use App\Services\StockConsultaService;
use App\Services\ClienteValidacionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class AprobacionController extends Controller
{
    protected $cobranzaService;

    public function __construct(CobranzaService $cobranzaService)
    {
        $this->cobranzaService = $cobranzaService;
        $this->middleware('auth');
    }

    /**
     * Vista principal de aprobaciones según el rol del usuario
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $cotizaciones = collect();

        // Obtener filtros del request
        $filtros = [
            'buscar' => $request->get('buscar'),
            'region' => $request->get('region'),
            'comuna' => $request->get('comuna'),
            'fecha_desde' => $request->get('fecha_desde'),
            'fecha_hasta' => $request->get('fecha_hasta'),
            'vendedor' => $request->get('vendedor'),
            'estado' => $request->get('estado'),
            'cliente' => $request->get('cliente'),
        ];

        // Función para aplicar filtros comunes
        $aplicarFiltros = function($query) use ($filtros) {
            // Filtro de búsqueda por cliente o código
            if (!empty($filtros['buscar'])) {
                $buscar = $filtros['buscar'];
                $query->where(function($q) use ($buscar) {
                    $q->where('cliente_codigo', 'like', '%' . $buscar . '%')
                      ->orWhere('cliente_nombre', 'like', '%' . $buscar . '%')
                      ->orWhereHas('cliente', function($clienteQuery) use ($buscar) {
                          $clienteQuery->where('codigo_cliente', 'like', '%' . $buscar . '%')
                                      ->orWhere('nombre_cliente', 'like', '%' . $buscar . '%');
                      });
                });
            }
            
            // Filtro por región
            if (!empty($filtros['region'])) {
                $query->whereHas('cliente', function($q) use ($filtros) {
                    $q->where('region', $filtros['region']);
                });
            }
            
            // Filtro por comuna
            if (!empty($filtros['comuna'])) {
                $query->whereHas('cliente', function($q) use ($filtros) {
                    $q->where('comuna', $filtros['comuna']);
                });
            }
            
            // Filtro por fecha desde
            if (!empty($filtros['fecha_desde'])) {
                $query->whereDate('created_at', '>=', $filtros['fecha_desde']);
            }
            
            // Filtro por fecha hasta
            if (!empty($filtros['fecha_hasta'])) {
                $query->whereDate('created_at', '<=', $filtros['fecha_hasta']);
            }
            
            // Filtro por vendedor (buscando por código de vendedor en usuarios)
            if (!empty($filtros['vendedor'])) {
                $userIds = \App\Models\User::where('codigo_vendedor', $filtros['vendedor'])->pluck('id');
                if ($userIds->count() > 0) {
                    $query->whereIn('user_id', $userIds);
                } else {
                    // Si no hay usuarios con ese código, no mostrar resultados
                    $query->whereRaw('1 = 0');
                }
            }
            
            // Filtro por estado
            if (!empty($filtros['estado'])) {
                $query->where('estado_aprobacion', $filtros['estado']);
            }
            
            // Filtro por cliente (código exacto)
            if (!empty($filtros['cliente'])) {
                $query->where('cliente_codigo', $filtros['cliente']);
            }
        };

        if ($user->hasRole('Supervisor')) {
            // Supervisor solo ve notas pendientes de su aprobación
            // Solo estado 'pendiente' con problemas de crédito (que requieren aprobación del supervisor)
            // NO debe ver: pendiente_picking, pendiente_compras, aprobada_supervisor, aprobada_compras, etc.
            // NO debe ver: NVV que solo tienen problemas de stock (esas van a Compras)
            $query = Cotizacion::where('estado_aprobacion', 'pendiente')
                ->where('tipo_documento', 'nota_venta')
                ->where('tiene_problemas_credito', true) // SOLO las que tienen problemas de crédito
                ->whereNull('aprobado_por_supervisor'); // Que aún no haya sido aprobada por supervisor
            
            $aplicarFiltros($query);
            
            $cotizaciones = $query->with(['user', 'productos', 'cliente'])
                ->latest()
                ->paginate(15)
                ->appends($request->query());
            $tipoAprobacion = 'supervisor';
        } elseif ($user->hasRole('Compras')) {
            // Compras ve:
            // 1. Notas aprobadas por supervisor (que tienen problemas de stock)
            // 2. Notas pendientes que tienen problemas de stock
            // 3. NVV separadas por Picking (tienen nota_original_id) - estas requieren compras porque fueron separadas por falta de stock
            $query = Cotizacion::where('tipo_documento', 'nota_venta')
                ->where(function($q) {
                    // Caso 1 y 2: NVV con problemas de stock (normales)
                    $q->where('tiene_problemas_stock', true)
                      ->where(function($subQ) {
                          $subQ->where('estado_aprobacion', 'aprobada_supervisor')
                               ->orWhere('estado_aprobacion', 'pendiente');
                      })
                      // Caso 3: NVV separadas por Picking (siempre tienen nota_original_id y problemas de stock)
                      ->orWhere(function($separadasQ) {
                          $separadasQ->whereNotNull('nota_original_id')
                                     ->where('tiene_problemas_stock', true);
                      });
                });
            
            $aplicarFiltros($query);
            
            $cotizaciones = $query->with(['user', 'productos', 'cliente'])
                ->latest()
                ->paginate(15)
                ->appends($request->query());
            $tipoAprobacion = 'compras';
        } elseif ($this->tieneRolPicking($user)) {
            // Picking y Picking Operativo ven tanto notas con problemas de stock como sin problemas
            $queryConProblemas = Cotizacion::pendientesPicking();
            $aplicarFiltros($queryConProblemas);
            $cotizacionesConProblemas = $queryConProblemas->with(['user', 'productos', 'cliente'])
                ->latest()
                ->get();
            
            $querySinProblemas = Cotizacion::pendientesPickingSinProblemas();
            $aplicarFiltros($querySinProblemas);
            $cotizacionesSinProblemas = $querySinProblemas->with(['user', 'productos', 'cliente'])
                ->latest()
                ->get();
            
            $queryPendientesEntrega = Cotizacion::pendientesEntrega();
            $aplicarFiltros($queryPendientesEntrega);
            $cotizacionesPendientesEntrega = $queryPendientesEntrega->with(['user', 'productos', 'cliente'])
                ->latest()
                ->get();
            
            $cotizaciones = $cotizacionesConProblemas->merge($cotizacionesSinProblemas)->merge($cotizacionesPendientesEntrega);
            $tipoAprobacion = 'picking';
        } else {
            return redirect()->route('dashboard')->with('error', 'No tienes permisos para aprobar notas de venta');
        }

        // Obtener regiones y comunas únicas de las cotizaciones para los filtros
        // Obtener desde las cotizaciones que tienen cliente asociado
        $regiones = Cotizacion::whereNotNull('cliente_codigo')
            ->join('clientes', 'cotizaciones.cliente_codigo', '=', 'clientes.codigo_cliente')
            ->whereNotNull('clientes.region')
            ->where('clientes.region', '!=', '')
            ->distinct()
            ->pluck('clientes.region')
            ->sort()
            ->values();
        
        $comunas = Cotizacion::whereNotNull('cliente_codigo')
            ->join('clientes', 'cotizaciones.cliente_codigo', '=', 'clientes.codigo_cliente')
            ->whereNotNull('clientes.comuna')
            ->where('clientes.comuna', '!=', '')
            ->distinct()
            ->pluck('clientes.comuna')
            ->sort()
            ->values();

        // Obtener vendedores únicos con código y nombre
        $vendedores = [];
        try {
            $vendedoresList = $this->cobranzaService->getVendedores();
            foreach ($vendedoresList as $vend) {
                $vendedores[] = [
                    'codigo' => $vend['codigo'] ?? '',
                    'nombre' => $vend['nombre'] ?? ''
                ];
            }
        } catch (\Exception $e) {
            \Log::error('Error obteniendo vendedores para filtros: ' . $e->getMessage());
        }
        
        // Obtener clientes únicos con código y nombre
        $clientes = [];
        try {
            $clientesList = Cotizacion::whereNotNull('cliente_codigo')
                ->whereNotNull('cliente_nombre')
                ->select('cliente_codigo', 'cliente_nombre')
                ->distinct()
                ->orderBy('cliente_nombre')
                ->get();
            
            foreach ($clientesList as $cli) {
                $clientes[] = [
                    'codigo' => $cli->cliente_codigo,
                    'nombre' => $cli->cliente_nombre
                ];
            }
        } catch (\Exception $e) {
            \Log::error('Error obteniendo clientes para filtros: ' . $e->getMessage());
        }

        // Estados posibles para el filtro
        $estados = [
            'pendiente' => 'Pendiente',
            'aprobada_supervisor' => 'Aprobada Supervisor',
            'pendiente_picking' => 'Pendiente Picking',
            'aprobada_compras' => 'Aprobada Compras',
            'aprobada_picking' => 'Aprobada Picking',
            'rechazada' => 'Rechazada'
        ];

        return view('aprobaciones.index', compact('cotizaciones', 'tipoAprobacion', 'filtros', 'regiones', 'comunas', 'vendedores', 'clientes', 'estados'));
    }

    /**
     * Aprobar nota de venta por Supervisor
     */
    public function aprobarSupervisor(Request $request, $id)
    {
        $request->validate([
            'comentarios' => 'nullable|string|max:500'
        ]);

        $cotizacion = Cotizacion::findOrFail($id);
        $user = Auth::user();

        if (!$user->hasRole('Supervisor')) {
            return redirect()->route('aprobaciones.show', $id)
                ->with('error', 'No tienes permisos para aprobar como supervisor');
        }

        if (!$cotizacion->puedeAprobarSupervisor()) {
            return redirect()->route('aprobaciones.show', $id)
                ->with('error', 'La nota de venta no puede ser aprobada por supervisor');
        }

        try {
            $cotizacion->aprobarPorSupervisor($user->id, $request->comentarios);
            
            // Registrar en el historial
            \App\Services\HistorialCotizacionService::registrarAprobacionSupervisor($cotizacion, $request->comentarios);
            
            Log::info("Nota de venta {$cotizacion->id} aprobada por supervisor {$user->id}");
            
            return redirect()->route('aprobaciones.show', $id)
                ->with('success', 'Nota de venta aprobada por supervisor correctamente');
        } catch (\Exception $e) {
            Log::error("Error aprobando nota de venta por supervisor: " . $e->getMessage());
            return redirect()->route('aprobaciones.show', $id)
                ->with('error', 'Error al aprobar la nota de venta');
        }
    }

    /**
     * Aprobar nota de venta por Compras
     */
    public function aprobarCompras(Request $request, $id)
    {
        $request->validate([
            'comentarios' => 'nullable|string|max:500'
        ]);

        $cotizacion = Cotizacion::findOrFail($id);
        $user = Auth::user();

        if (!$user->hasRole('Compras')) {
            return redirect()->route('aprobaciones.show', $id)
                ->with('error', 'No tienes permisos para aprobar como compras');
        }

        if (!$cotizacion->puedeAprobarCompras()) {
            return redirect()->route('aprobaciones.show', $id)
                ->with('error', 'La nota de venta no puede ser aprobada por compras');
        }

        try {
            $cotizacion->aprobarPorCompras($user->id, $request->comentarios);
            
            // Registrar en el historial
            \App\Services\HistorialCotizacionService::registrarAprobacionCompras($cotizacion, $request->comentarios);
            
            Log::info("Nota de venta {$cotizacion->id} aprobada por compras {$user->id}");
            
            return redirect()->route('aprobaciones.show', $id)
                ->with('success', 'Nota de venta aprobada por compras correctamente');
        } catch (\Exception $e) {
            Log::error("Error aprobando nota de venta por compras: " . $e->getMessage());
            return redirect()->route('aprobaciones.show', $id)
                ->with('error', 'Error al aprobar la nota de venta');
        }
    }

    /**
     * Guardar como pendiente de entrega (nuevo método para Picking)
     */
    public function guardarPendienteEntrega(Request $request, $id)
    {
        Log::info("========================================");
        Log::info("🚀 INICIO GUARDAR PENDIENTE ENTREGA");
        Log::info("========================================");
        Log::info("Cotización ID: {$id}");
        Log::info("Usuario ID: " . auth()->id());
        Log::info("Request data: " . json_encode($request->all()));
        
        $request->validate([
            'observaciones_picking' => 'required|string|max:1000',
            'productos_pendientes' => 'array',
            'productos_pendientes.*' => 'integer',
            'guia_picking_bodega' => 'nullable|string|max:100',
            'guia_picking_separado_por' => 'nullable|string|max:150',
            'guia_picking_revisado_por' => 'nullable|string|max:150',
            'guia_picking_numero_bultos' => 'nullable|string|max:50',
            'guia_picking_firma' => 'nullable|string|max:150'
        ]);

        $cotizacion = Cotizacion::findOrFail($id);
        $user = Auth::user();

        // Tanto Picking como Picking Operativo pueden guardar pendiente de entrega
        if (!$this->tieneRolPicking($user)) {
            return redirect()->route('aprobaciones.show', $id)
                ->with('error', 'No tienes permisos para esta acción');
        }

        if (!$cotizacion->puedeAprobarPicking() && $cotizacion->estado_aprobacion !== 'pendiente_picking') {
            return redirect()->route('aprobaciones.show', $id)
                ->with('error', 'La nota de venta no puede ser procesada');
        }

        try {
            // Actualizar observación, estado y campos de guía picking
            $cotizacion->guardarPendienteEntrega(
                $user->id, 
                $request->observaciones_picking,
                $request->guia_picking_bodega,
                $request->guia_picking_separado_por,
                $request->guia_picking_revisado_por,
                $request->guia_picking_numero_bultos,
                $request->guia_picking_firma
            );

            // Marcar productos pendientes vs embalados
            $idsPendientes = collect($request->input('productos_pendientes', []))->map(fn($v) => (int)$v)->all();
            foreach ($cotizacion->productos as $producto) {
                $producto->pendiente_entrega = in_array((int)$producto->id, $idsPendientes, true);
                $producto->save();
            }
            
            Log::info("✅ Nota de venta {$cotizacion->id} guardada como pendiente de entrega por picking {$user->id}");
            
            return redirect()->route('aprobaciones.show', $id)
                ->with('success', 'Nota de venta guardada como pendiente de entrega. Los productos llegarán en los próximos días.');
                
        } catch (\Exception $e) {
            Log::error("Error guardando como pendiente de entrega: " . $e->getMessage());
            return redirect()->route('aprobaciones.show', $id)
                ->with('error', 'Error al guardar la nota de venta');
        }
    }

    /**
     * Agregar observaciones de picking (sin cambiar estado)
     */
    public function agregarObservacionesPicking(Request $request, $id)
    {
        Log::info("========================================");
        Log::info("🔍 AGREGAR OBSERVACIONES PICKING");
        Log::info("========================================");
        Log::info("Cotización ID: {$id}");
        Log::info("Usuario ID: " . auth()->id());
        Log::info("Request data: " . json_encode($request->all()));
        Log::info("observaciones_picking value: " . ($request->input('observaciones_picking') ?? 'NULL'));
        Log::info("observaciones_picking length: " . strlen($request->input('observaciones_picking') ?? ''));
        Log::info("Is AJAX: " . ($request->ajax() ? 'SI' : 'NO'));
        Log::info("Wants JSON: " . ($request->wantsJson() ? 'SI' : 'NO'));
        Log::info("Header X-Requested-With: " . ($request->header('X-Requested-With') ?? 'NO'));
        Log::info("Accept header: " . ($request->header('Accept') ?? 'NO'));
        
        $request->validate([
            'observaciones_picking' => 'nullable|string|max:1000'
        ]);

        $cotizacion = Cotizacion::findOrFail($id);
        $user = Auth::user();
        
        // Obtener el valor directamente del request
        $observaciones = $request->input('observaciones_picking');
        Log::info("Valor de observaciones antes de guardar: " . ($observaciones ?? 'NULL'));

        // Tanto Picking como Picking Operativo pueden agregar observaciones
        if (!$this->tieneRolPicking($user)) {
            Log::warning("Usuario {$user->id} no tiene rol Picking o Picking Operativo");
            if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permisos para esta acción'
                ], 403);
            }
            return redirect()->route('aprobaciones.show', $id)
                ->with('error', 'No tienes permisos para esta acción');
        }

        try {
            // Guardar observaciones (puede ser null o string vacío)
            $cotizacion->observaciones_picking = $observaciones ? trim($observaciones) : null;
            $cotizacion->save();
            
            Log::info("Observaciones guardadas en BD: " . ($cotizacion->observaciones_picking ?? 'NULL'));

            Log::info("✅ Observaciones de picking agregadas a cotización {$id} por usuario {$user->id}");
            Log::info("Observaciones guardadas: " . ($cotizacion->observaciones_picking ?? 'vacío'));

            // Detectar si es petición AJAX de múltiples formas
            $isAjax = $request->ajax() || 
                     $request->wantsJson() || 
                     $request->header('X-Requested-With') === 'XMLHttpRequest' ||
                     $request->header('Accept') === 'application/json';
            
            if ($isAjax) {
                Log::info("Respondiendo con JSON");
                return response()->json([
                    'success' => true,
                    'message' => 'Observaciones guardadas exitosamente',
                    'observaciones' => $cotizacion->observaciones_picking ?: 'Sin observaciones adicionales'
                ]);
            }

            Log::info("Respondiendo con redirección");
            return redirect()->route('aprobaciones.show', $id)
                ->with('success', 'Observaciones guardadas exitosamente');

        } catch (\Exception $e) {
            Log::error("❌ Error guardando observaciones de picking: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());
            
            $isAjax = $request->ajax() || 
                     $request->wantsJson() || 
                     $request->header('X-Requested-With') === 'XMLHttpRequest' ||
                     $request->header('Accept') === 'application/json';
            
            if ($isAjax) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al guardar observaciones: ' . $e->getMessage()
                ], 500);
            }

            return redirect()->route('aprobaciones.show', $id)
                ->with('error', 'Error al guardar observaciones');
        }
    }

    /**
     * Aprobar nota de venta por Picking
     */
    public function aprobarPicking(Request $request, $id)
    {
        Log::info("========================================");
        Log::info("🚀 INICIO APROBAR PICKING");
        Log::info("========================================");
        Log::info("Cotización ID: {$id}");
        Log::info("Usuario ID: " . auth()->id());
        Log::info("Usuario Email: " . auth()->user()->email);
        Log::info("Request data: " . json_encode($request->all()));
        Log::info("========================================");
        
        // Establecer timeout de 30 segundos para evitar cuelgues
        set_time_limit(30);
        
        // IMPORTANTE: Cerrar la sesión inmediatamente para no bloquear otras peticiones
        session()->save();
        
        Log::info("📝 PASO 1: Validando request...");
        $request->validate([
            'comentarios' => 'nullable|string|max:500',
            'validar_stock_real' => 'nullable|boolean',
            'guia_picking_bodega' => 'nullable|string|max:100',
            'guia_picking_separado_por' => 'nullable|string|max:150',
            'guia_picking_revisado_por' => 'nullable|string|max:150',
            'guia_picking_numero_bultos' => 'nullable|string|max:50',
            'guia_picking_firma' => 'nullable|string|max:150'
        ]);
        Log::info("✅ PASO 1: Validación OK");

        Log::info("📝 PASO 2: Buscando cotización...");
        $cotizacion = Cotizacion::findOrFail($id);
        Log::info("✅ PASO 2: Cotización encontrada - Cliente: {$cotizacion->cliente_nombre}");
        
        Log::info("📝 PASO 2.1: Obteniendo usuario autenticado...");
        $user = Auth::user();
        Log::info("✅ PASO 2.1: Usuario obtenido - ID: {$user->id}, Email: {$user->email}");
        
        Log::info("Cotización encontrada: #{$cotizacion->id}");
        Log::info("Estado actual: {$cotizacion->estado_aprobacion}");
        Log::info("Usuario tiene rol Picking: " . ($user->hasRole('Picking') ? 'SI' : 'NO'));
        
        Log::info("📝 PASO 3: Validando permisos...");
        Log::info("Usuario ID: {$user->id}");
        Log::info("Usuario roles: " . json_encode($user->getRoleNames()));

        // Solo Picking puede aprobar, Picking Operativo NO puede aprobar
        if (!$this->puedeAprobarPicking($user)) {
            Log::error("❌ ERROR: Usuario no tiene rol Picking (solo puede aprobar Picking, no Picking Operativo)");
            return redirect()->route('aprobaciones.show', $id)
                ->with('error', 'No tienes permisos para aprobar como picking');
        }
        Log::info("✅ PASO 3: Permisos validados correctamente");
        
        Log::info("📝 PASO 4: Validando estado de la cotización...");
        Log::info("Estado actual: {$cotizacion->estado_aprobacion}");
        Log::info("Puede aprobar picking: " . ($cotizacion->puedeAprobarPicking() ? 'SI' : 'NO'));

        if (!$cotizacion->puedeAprobarPicking() && $cotizacion->estado_aprobacion !== 'pendiente_picking') {
            Log::error("❌ ERROR: La cotización no puede ser aprobada por picking");
            return redirect()->route('aprobaciones.show', $id)
                ->with('error', 'La nota de venta no puede ser aprobada por picking');
        }
        Log::info("✅ PASO 4: Estado validado correctamente");

        try {
            // Si se requiere validar stock real
            if ($request->validar_stock_real) {
                Log::info("Validando stock real...");
                $stockValidado = $this->validarStockReal($cotizacion);
                Log::info("Resultado validación stock: " . json_encode($stockValidado));
                
                if (!$stockValidado['valido']) {
                    $mensajeError = "Stock insuficiente en algunos productos:\n";
                    foreach ($stockValidado['detalle'] as $detalle) {
                        $mensajeError .= "- {$detalle['nombre']}: Requerido {$detalle['cantidad_solicitada']}, Disponible {$detalle['stock_disponible']}\n";
                    }
                    Log::warning("Stock insuficiente, redirigiendo con error");
                    return redirect()->route('aprobaciones.show', $id)
                        ->with('error', $mensajeError);
                }
                Log::info("✓ Stock validado correctamente");
            } else {
                Log::info("Validación de stock omitida (validar_stock_real = false)");
            }

            Log::info("Iniciando aprobación por picking para cotización {$cotizacion->id}");
            
            // ==================================================================
            // MODO REAL: Insert en SQL Server (HABILITADO)
            // IMPORTANTE: Primero insertamos en SQL Server, si falla NO cambiamos el estado
            // ==================================================================
            Log::info("📝 PASO CRÍTICO: Iniciando insert en SQL Server...");
            Log::info("🔥 MODO REAL ACTIVADO - SE INSERTARÁ EN SQL SERVER");
            $startTime = microtime(true);
            
            // Pasar los datos de picking del request a insertarEnSQLServer
            $datosPicking = [
                'separado_por' => $request->guia_picking_separado_por,
                'revisado_por' => $request->guia_picking_revisado_por,
                'numero_bultos' => $request->guia_picking_numero_bultos,
            ];
            $resultado = $this->insertarEnSQLServer($cotizacion, $datosPicking);
            
            $endTime = microtime(true);
            $duration = $endTime - $startTime;
            Log::info("⏱️ PASO CRÍTICO: Insert completado en " . round($duration, 2) . " segundos");
            
            // Solo si el insert fue exitoso, aprobamos en MySQL
            if ($resultado['success']) {
                Log::info("✅ Insert en SQL Server exitoso, aprobando en MySQL...");
                $cotizacion->aprobarPorPicking(
                    $user->id, 
                    $request->comentarios,
                    $request->guia_picking_bodega,
                    $request->guia_picking_separado_por,
                    $request->guia_picking_revisado_por,
                    $request->guia_picking_numero_bultos,
                    $request->guia_picking_firma
                );
                Log::info("✅ Cotización aprobada en MySQL");
            } else {
                // Si falló el insert, lanzar excepción para que se capture en el catch
                throw new \Exception($resultado['message'] ?? 'Error desconocido en SQL Server');
            }
            
            // ==================================================================
            // MODO PREVISUALIZACIÓN (COMENTADO - USAR SOLO PARA DEBUG)
            // ==================================================================
            /*
            Log::info("📝 PASO CRÍTICO: Iniciando previsualización...");
            Log::info("👁️ MODO PREVISUALIZACIÓN ACTIVADO - REVISAR DATOS ANTES DE INSERTAR");
            
            try {
                $resultado = $this->previsualizarInsertSQL($cotizacion);
                
                return redirect()->route('aprobaciones.show', $id)
                    ->with('success', '✅ Previsualización generada exitosamente')
                    ->with('info', 'NUDO que se usará: ' . $resultado['nudo'])
                    ->with('warning', '⚠️ MODO PREVISUALIZACIÓN: Revisa los logs para ver todos los datos. No se insertó nada en SQL Server.');
                    
            } catch (Exception $e) {
                Log::error("❌ ERROR en previsualización: " . $e->getMessage());
                return redirect()->route('aprobaciones.show', $id)
                    ->with('error', 'Error generando previsualización: ' . $e->getMessage());
            }
            */
            
            if ($resultado['success']) {
                Log::info("Nota de venta {$cotizacion->id} aprobada por picking {$user->id} y insertada en SQL Server con ID {$resultado['nota_venta_id']}");
                
                // Refrescar la cotización para obtener el número_nvv actualizado
                $cotizacion->refresh();
                
                $numeroNVV = $resultado['numero_correlativo'] ?? $resultado['nota_venta_id'];
                
                Log::info("✅ Nota de venta {$cotizacion->id} aprobada por picking {$user->id}");
                Log::info("📋 NVV N° {$numeroNVV} (ID: {$resultado['nota_venta_id']}) insertada en SQL Server");
                
                // Registrar en el historial con el número de NVV y timestamp
                \App\Services\HistorialCotizacionService::registrarAprobacionPicking(
                    $cotizacion, 
                    $request->comentarios ?? 'Aprobado por Picking - NVV N° ' . $numeroNVV,
                    $resultado['nota_venta_id']
                );
                
                // Crear mensaje de éxito detallado
                $mensajeExito = "✅ Nota de Venta aprobada exitosamente\n\n";
                $mensajeExito .= "📋 NVV N° {$numeroNVV} creada en SQL Server\n";
                $mensajeExito .= "🔢 ID Interno: {$resultado['nota_venta_id']}\n";
                $mensajeExito .= "👤 Cliente: {$cotizacion->cliente_nombre}\n";
                $mensajeExito .= "💰 Total: $" . number_format($cotizacion->total, 0, ',', '.') . "\n";
                $mensajeExito .= "📦 Productos: " . $cotizacion->productos->count() . "\n";
                $mensajeExito .= "⏰ Fecha: " . now()->format('d/m/Y H:i:s');
                
                return redirect()->route('aprobaciones.show', $id)
                    ->with('success', $mensajeExito)
                    ->with('numero_nvv', $numeroNVV)
                    ->with('id_nvv_interno', $resultado['nota_venta_id']);
            } else {
                throw new \Exception('Error al insertar en SQL Server');
            }
            
        } catch (\Exception $e) {
            Log::error("Error aprobando nota de venta por picking: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());
            
            return redirect()->route('aprobaciones.show', $id)
                ->with('error', 'Error al aprobar la nota de venta: ' . $e->getMessage());
        }
    }

    /**
     * Previsualizar datos que se insertarán en SQL Server (sin ejecutar)
     */
    private function previsualizarInsertSQL($cotizacion)
    {
        try {
            Log::info("👁️ === PREVISUALIZACIÓN DE DATOS A INSERTAR ===");
            
            // Obtener siguiente correlativo para IDMAEEDO (COPIADO DEL MÉTODO QUE FUNCIONA)
            $queryCorrelativo = "SELECT TOP 1 ISNULL(MAX(IDMAEEDO), 0) + 1 AS siguiente_id FROM MAEEDO WHERE EMPRESA = '01'";
            $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
            file_put_contents($tempFile, $queryCorrelativo . "\ngo\nquit");
            $command = "tsql -H " . env('SQLSRV_EXTERNAL_HOST') . " -p " . env('SQLSRV_EXTERNAL_PORT') . " -U " . env('SQLSRV_EXTERNAL_USERNAME') . " -P " . env('SQLSRV_EXTERNAL_PASSWORD') . " -D " . env('SQLSRV_EXTERNAL_DATABASE') . " < {$tempFile} 2>&1";
            $result = shell_exec($command);
            unlink($tempFile);
            
            $siguienteId = 1;
            if ($result && !str_contains($result, 'error')) {
                if (preg_match('/siguiente_id\s*\n\s*(\d+)/', $result, $matches)) {
                    $siguienteId = (int)$matches[1];
                } else {
                    $lines = explode("\n", $result);
                    $maxNumber = 0;
                    foreach ($lines as $line) {
                        $line = trim($line);
                        if (is_numeric($line) && (int)$line > $maxNumber && (int)$line > 1000) {
                            $maxNumber = (int)$line;
                        }
                    }
                    if ($maxNumber > 0) {
                        $siguienteId = $maxNumber;
                    }
                }
            }
            
            // Obtener el último NUDO de NVV y sumarle 1 (COPIADO DEL MÉTODO QUE FUNCIONA)
            Log::info("🔍 DEBUG: Iniciando obtención de NUDO...");
            
            // Obtener valores del .env
            $hostNudo = env('SQLSRV_EXTERNAL_HOST');
            $portNudo = env('SQLSRV_EXTERNAL_PORT');
            $userNudo = env('SQLSRV_EXTERNAL_USERNAME');
            $passNudo = env('SQLSRV_EXTERNAL_PASSWORD');
            $dbNudo = env('SQLSRV_EXTERNAL_DATABASE');
            
            Log::info("🔍 DEBUG: Credenciales SQL:");
            Log::info("   Host: " . ($hostNudo ?? 'NULL'));
            Log::info("   Port: " . ($portNudo ?? 'NULL'));
            Log::info("   User: " . ($userNudo ?? 'NULL'));
            Log::info("   Pass: " . (empty($passNudo) ? 'VACIO' : 'OK'));
            Log::info("   DB: " . ($dbNudo ?? 'NULL'));
            
            // Query para obtener el último NUDO de NVV (ordenado por NUDO DESC)
            $queryNudo = "SELECT TOP 1 NUDO FROM MAEEDO WHERE TIDO = 'NVV' AND ISNUMERIC(NUDO) = 1 ORDER BY NUDO DESC";
            Log::info("🔍 DEBUG: Query NUDO (último NUDO): {$queryNudo}");
            
            $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
            file_put_contents($tempFile, $queryNudo . "\ngo\nquit");
            Log::info("🔍 DEBUG: Temp file creado: {$tempFile}");
            
            $command = "tsql -H {$hostNudo} -p {$portNudo} -U {$userNudo} -P {$passNudo} -D {$dbNudo} < {$tempFile} 2>&1";
            Log::info("🔍 DEBUG: Comando tsql: " . str_replace($passNudo, '***', $command));
            
            $result = shell_exec($command);
            unlink($tempFile);
            
            Log::info("🔍 DEBUG: Resultado query NUDO:");
            Log::info($result);
            
            // Debug más detallado - mostrar líneas del resultado
            $lines = explode("\n", $result);
            Log::info("🔍 DEBUG: Líneas del resultado:");
            foreach ($lines as $i => $line) {
                Log::info("   Línea {$i}: " . trim($line));
            }
            
            $ultimoNudoStr = '';
            if (preg_match('/(\d{10})/', $result, $matches)) {
                $ultimoNudoStr = $matches[1];
                Log::info("🔍 DEBUG: NUDO encontrado con regex: {$ultimoNudoStr}");
            } else {
                Log::error("🔍 DEBUG: NO se encontró NUDO con regex");
                // Intentar buscar cualquier número de 10 dígitos
                if (preg_match_all('/(\d{10})/', $result, $allMatches)) {
                    Log::info("🔍 DEBUG: Números de 10 dígitos encontrados: " . implode(', ', $allMatches[1]));
                }
            }
            
            if (empty($ultimoNudoStr)) {
                Log::error("❌ No se pudo obtener el último NUDO. Resultado completo de tsql:");
                Log::error($result);
                throw new \Exception("No se pudo obtener el último número correlativo de NVV");
            }
            
            $ultimoNudo = (int)$ultimoNudoStr;
            $siguienteNudo = $ultimoNudo + 1;
            $nudoFormateado = str_pad($siguienteNudo, 10, '0', STR_PAD_LEFT);
            
            Log::info("✅ Último NUDO de NVV: {$ultimoNudoStr}");
            Log::info("✅ Siguiente NUDO asignado: {$nudoFormateado}");
            
            // Obtener información del vendedor
            $codigoVendedor = substr($cotizacion->user->codigo_vendedor ?? '001', 0, 3);
            
            // Obtener datos adicionales del cliente
            $listaPrecios = $this->obtenerListaPreciosCliente($cotizacion->cliente_codigo);
            $diasPago = $this->obtenerDiasPagoCliente($cotizacion->cliente_codigo);
            $nuevecr = $this->obtenerNuevecrCliente($cotizacion->cliente_codigo);
            
            // Calcular HORAGRAB
            $fechaActual = now();
            $diasDesde1900 = $fechaActual->diffInDays('1900-01-01') + 2;
            $horaDecimal = ($fechaActual->hour * 3600 + $fechaActual->minute * 60 + $fechaActual->second) / 86400;
            $horagrab = $diasDesde1900 + $horaDecimal;
            
            // Obtener sucursal del cliente (COPIADO DEL MÉTODO QUE FUNCIONA)
            $querySucursal = "SELECT LTRIM(RTRIM(SUEN)) as SUCURSAL FROM MAEEN WHERE KOEN = '{$cotizacion->cliente_codigo}'";
            $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
            file_put_contents($tempFile, $querySucursal . "\ngo\nquit");
            $command = "tsql -H " . env('SQLSRV_EXTERNAL_HOST') . " -p " . env('SQLSRV_EXTERNAL_PORT') . " -U " . env('SQLSRV_EXTERNAL_USERNAME') . " -P " . env('SQLSRV_EXTERNAL_PASSWORD') . " -D " . env('SQLSRV_EXTERNAL_DATABASE') . " < {$tempFile} 2>&1";
            $result = shell_exec($command);
            unlink($tempFile);
            
            $sucursalCliente = '';
            if ($result && !str_contains($result, 'error')) {
                $lines = explode("\n", $result);
                $foundHeader = false;
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === 'SUCURSAL') {
                        $foundHeader = true;
                        continue;
                    }
                    if ($foundHeader && !empty($line) && !str_contains($line, 'row') && !str_contains($line, '---') && !str_contains($line, '>')) {
                        $sucursalCliente = $line;
                        break;
                    }
                }
            }
            
            // CAPRCO = suma de cantidades de productos
            $sumaCantidades = $cotizacion->productos->sum('cantidad');
            
            // Calcular SUMAS de MAEDDO para MAEEDO
            $sumaVANELI = 0;
            $sumaVAIVLI = 0;
            $sumaVABRLI = 0;
            
            foreach ($cotizacion->productos as $producto) {
                $precioNeto = $producto->precio_unitario;
                $cantidad = $producto->cantidad;
                $subtotalBruto = $precioNeto * $cantidad;
                $valorDescuento = $producto->descuento_valor ?? 0;
                $subtotalConDescuento = $subtotalBruto - $valorDescuento;
                $ivaConDescuento = $subtotalConDescuento * 0.19;
                $totalConIVA = $subtotalConDescuento + $ivaConDescuento;
                
                $sumaVANELI += $subtotalConDescuento;
                $sumaVAIVLI += $ivaConDescuento;
                $sumaVABRLI += $totalConIVA;
            }
            
            $VANEDO = round($sumaVANELI, 0);
            $VAIVDO = $sumaVAIVLI;
            $VABRDO = round($sumaVABRLI, 0);
            
            // ==================== PREVISUALIZACIÓN TABLA MAEEDO ====================
            $preview = "\n\n";
            $preview .= "╔══════════════════════════════════════════════════════════════════════════╗\n";
            $preview .= "║                    📊 PREVISUALIZACIÓN TABLA MAEEDO                      ║\n";
            $preview .= "╠══════════════════════════════════════════════════════════════════════════╣\n";
            $preview .= sprintf("║ %-30s = %-39s ║\n", "IDMAEEDO", $siguienteId);
            $preview .= sprintf("║ %-30s = %-39s ║\n", "EMPRESA", "01");
            $preview .= sprintf("║ %-30s = %-39s ║\n", "TIDO", "NVV");
            $preview .= sprintf("║ %-30s = %-39s ║\n", "NUDO", $nudoFormateado);
            $preview .= sprintf("║ %-30s = %-39s ║\n", "ENDO", $cotizacion->cliente_codigo);
            $preview .= sprintf("║ %-30s = %-39s ║\n", "SUENDO", $sucursalCliente);
            $preview .= sprintf("║ %-30s = %-39s ║\n", "ENDOFI", "");
            $preview .= sprintf("║ %-30s = %-39s ║\n", "SUDO", "LIB");
            $preview .= sprintf("║ %-30s = %-39s ║\n", "TIGEDO", "I");
            $preview .= sprintf("║ %-30s = %-39s ║\n", "LUVTDO", "");
            $preview .= sprintf("║ %-30s = %-39s ║\n", "MEARDO", "N");
            $preview .= sprintf("║ %-30s = %-39s ║\n", "ESPGDO", "S");
            $preview .= sprintf("║ %-30s = %-39s ║\n", "FEEMDO", "GETDATE()");
            $preview .= sprintf("║ %-30s = %-39s ║\n", "FE01VEDO", "GETDATE() + {$diasPago} días");
            $preview .= sprintf("║ %-30s = %-39s ║\n", "FEULVEDO", "GETDATE() + {$diasPago} días");
            $preview .= sprintf("║ %-30s = %-39s ║\n", "FEER", "GETDATE() (igual que FEEMDO)");
            $preview .= sprintf("║ %-30s = %-39s ║\n", "CAPRCO (suma cantidades)", $sumaCantidades);
            $preview .= sprintf("║ %-30s = %-39s ║\n", "CAPRAD", "0");
            $preview .= sprintf("║ %-30s = %-39s ║\n", "CAPREX", "0");
            $preview .= sprintf("║ %-30s = %-39s ║\n", "CAPRNC", "0");
            $preview .= sprintf("║ %-30s = %-39s ║\n", "MODO", "$");
            $preview .= sprintf("║ %-30s = %-39s ║\n", "TIMODO", "N");
            $preview .= sprintf("║ %-30s = %-39s ║\n", "TAMODO", "1");
            $preview .= sprintf("║ %-30s = %-39s ║\n", "VAIVDO (suma VAIVLI)", $VAIVDO);
            $preview .= sprintf("║ %-30s = %-39s ║\n", "VANEDO (suma VANELI)", $VANEDO);
            $preview .= sprintf("║ %-30s = %-39s ║\n", "VABRDO (suma VABRLI)", $VABRDO);
            $preview .= sprintf("║ %-30s = %-39s ║\n", "VAABDO", "0");
            $preview .= sprintf("║ %-30s = %-39s ║\n", "ESDO", "");
            $preview .= sprintf("║ %-30s = %-39s ║\n", "KOFUDO (vendedor)", $codigoVendedor);
            $preview .= sprintf("║ %-30s = %-39s ║\n", "KOTU", "1");
            $preview .= sprintf("║ %-30s = %-39s ║\n", "LAHORA", "GETDATE()");
            $preview .= sprintf("║ %-30s = %-39s ║\n", "DESPACHO", "1");
            $preview .= sprintf("║ %-30s = %-39s ║\n", "HORAGRAB", number_format($horagrab, 6));
            $preview .= sprintf("║ %-30s = %-39s ║\n", "CUOGASDIF", "0");
            $preview .= sprintf("║ %-30s = %-39s ║\n", "BODESTI", "");
            $preview .= sprintf("║ %-30s = %-39s ║\n", "PROYECTO", "");
            $preview .= sprintf("║ %-30s = %-39s ║\n", "FLIQUIFCV", "GETDATE()");
            $preview .= sprintf("║ %-30s = %-39s ║\n", "LISACTIVA", $listaPrecios);
            $preview .= sprintf("║ %-30s = %-39s ║\n", "NUVEDO", $nuevecr);
            $preview .= "╚══════════════════════════════════════════════════════════════════════════╝\n\n";
            
            Log::info($preview);
            
            // ==================== PREVISUALIZACIÓN TABLA MAEDDO ====================
            foreach ($cotizacion->productos as $index => $producto) {
                $lineaId = $index + 1;
                
                $productoDB = \App\Models\Producto::where('KOPR', $producto->codigo_producto)->first();
                
                $udtrpr = 1;
                $rludpr = 1;
                $ud01pr = 'UN';
                $ud02pr = 'UN';
                
                if ($productoDB) {
                    $rludpr = $productoDB->RLUD ?? 1;
                    $ud01pr = trim($productoDB->UD01PR ?? 'UN');
                    // UD02PR: usar el valor de la BD si existe y no está vacío, sino 'UN' por defecto
                    $ud02prAux = trim($productoDB->UD02PR ?? '');
                    $ud02pr = !empty($ud02prAux) ? $ud02prAux : 'UN';
                    $udtrpr = ($rludpr > 1) ? 2 : 1;
                }
                
                $codigoProducto = substr($producto->codigo_producto, 0, 13);
                // Limpiar nombre del producto antes de truncarlo
                $nombreLimpio = $this->limpiarNombreProducto($producto->nombre_producto);
                $nombreProducto = substr($nombreLimpio, 0, 50);
                $ud01prTruncado = substr($ud01pr, 0, 2);
                $ud02prTruncado = substr($ud02pr, 0, 2);
                
                $precioNeto = $producto->precio_unitario;
                $cantidad = $producto->cantidad;
                $subtotalBruto = $precioNeto * $cantidad;
                $porcentajeDescuento = $producto->descuento_porcentaje ?? 0;
                $valorDescuento = $producto->descuento_valor ?? 0;
                $nudtli = ($porcentajeDescuento > 0 || $valorDescuento > 0) ? 1 : 0;
                $vadtneli = $valorDescuento;
                $subtotalConDescuento = $subtotalBruto - $valorDescuento;
                $ivaConDescuento = $subtotalConDescuento * 0.19;
                $total = $subtotalConDescuento + $ivaConDescuento;
                $caprco2 = $rludpr > 0 ? round($cantidad / $rludpr, 2) : 0;
                $nulidoFormateado = str_pad($lineaId, 5, '0', STR_PAD_LEFT);
                
                // Obtener PPPRPM (COPIADO DEL MÉTODO QUE FUNCIONA)
                $ppprpm = 0;
                try {
                    $queryPrecioMin = "SELECT ISNULL(PM, 0) as PM FROM MAEPREM WHERE KOPR = '{$codigoProducto}'";
                    $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
                    file_put_contents($tempFile, $queryPrecioMin . "\ngo\nquit");
                    $command = "tsql -H " . env('SQLSRV_EXTERNAL_HOST') . " -p " . env('SQLSRV_EXTERNAL_PORT') . " -U " . env('SQLSRV_EXTERNAL_USERNAME') . " -P " . env('SQLSRV_EXTERNAL_PASSWORD') . " -D " . env('SQLSRV_EXTERNAL_DATABASE') . " < {$tempFile} 2>&1";
                    $result = shell_exec($command);
                    unlink($tempFile);
                    
                    if ($result && !str_contains($result, 'error')) {
                        $lines = explode("\n", $result);
                        foreach ($lines as $line) {
                            $line = trim($line);
                            if (is_numeric($line)) {
                                $ppprpm = (float)$line;
                                break;
                            }
                        }
                    }
                } catch (\Exception $e) {
                    // Ignorar error
                }
                
                $descuentoUnitario = $cantidad > 0 ? $valorDescuento / $cantidad : 0;
                $ppprnere1 = $precioNeto - $descuentoUnitario;
                $ppprnere2 = $ppprnere1;
                
                $previewDetalle = "\n";
                $previewDetalle .= "╔══════════════════════════════════════════════════════════════════════════╗\n";
                $previewDetalle .= sprintf("║              📦 PREVISUALIZACIÓN TABLA MAEDDO - LÍNEA %02d                ║\n", $lineaId);
                $previewDetalle .= "╠══════════════════════════════════════════════════════════════════════════╣\n";
                $previewDetalle .= sprintf("║ %-30s = %-39s ║\n", "IDMAEEDO", $siguienteId);
                $previewDetalle .= sprintf("║ %-30s = %-39s ║\n", "EMPRESA", "01");
                $previewDetalle .= sprintf("║ %-30s = %-39s ║\n", "TIDO", "NVV");
                $previewDetalle .= sprintf("║ %-30s = %-39s ║\n", "NUDO", $nudoFormateado);
                $previewDetalle .= sprintf("║ %-30s = %-39s ║\n", "ENDO", $cotizacion->cliente_codigo);
                $previewDetalle .= sprintf("║ %-30s = %-39s ║\n", "SUENDO", $sucursalCliente);
                $previewDetalle .= sprintf("║ %-30s = %-39s ║\n", "LILG", "SI");
                $previewDetalle .= sprintf("║ %-30s = %-39s ║\n", "NULIDO", $nulidoFormateado);
                $previewDetalle .= sprintf("║ %-30s = %-39s ║\n", "SULIDO", "LIB");
                $previewDetalle .= sprintf("║ %-30s = %-39s ║\n", "BOSULIDO", "LIB");
                $previewDetalle .= sprintf("║ %-30s = %-39s ║\n", "LUVTLIDO", "");
                $previewDetalle .= sprintf("║ %-30s = %-39s ║\n", "KOFULIDO", $codigoVendedor);
                $previewDetalle .= sprintf("║ %-30s = %-39s ║\n", "TIPR", "FPN");
                $previewDetalle .= sprintf("║ %-30s = %-39s ║\n", "UDTRPR", $udtrpr);
                $previewDetalle .= sprintf("║ %-30s = %-39s ║\n", "RLUDPR", $rludpr);
                $previewDetalle .= sprintf("║ %-30s = %-39s ║\n", "UD01PR", $ud01prTruncado);
                $previewDetalle .= sprintf("║ %-30s = %-39s ║\n", "UD02PR", $ud02prTruncado);
                $previewDetalle .= sprintf("║ %-30s = %-39s ║\n", "KOPRCT", $codigoProducto);
                $previewDetalle .= sprintf("║ %-30s = %-39s ║\n", "NOKOPR", substr($nombreProducto, 0, 39));
                $previewDetalle .= sprintf("║ %-30s = %-39s ║\n", "CAPRCO1", $cantidad);
                $previewDetalle .= sprintf("║ %-30s = %-39s ║\n", "CAPRCO2", $caprco2);
                $previewDetalle .= sprintf("║ %-30s = %-39s ║\n", "KOLTPR", $listaPrecios);
                $previewDetalle .= sprintf("║ %-30s = %-39s ║\n", "MOPPPR", "$");
                $previewDetalle .= sprintf("║ %-30s = %-39s ║\n", "TIMOPPPR", "N");
                $previewDetalle .= sprintf("║ %-30s = %-39s ║\n", "TAMOPPPR", "1");
                $previewDetalle .= sprintf("║ %-30s = %-39s ║\n", "PPPRNE", number_format($precioNeto, 2));
                $previewDetalle .= sprintf("║ %-30s = %-39s ║\n", "PPPRNELT", number_format($precioNeto, 2));
                $previewDetalle .= sprintf("║ %-30s = %-39s ║\n", "PPPRBR", number_format($precioNeto * 1.19, 2));
                $previewDetalle .= sprintf("║ %-30s = %-39s ║\n", "PPPRBRLT", number_format($precioNeto * 1.19, 2));
                $previewDetalle .= sprintf("║ %-30s = %-39s ║\n", "PODTGLLI (% descuento)", number_format($porcentajeDescuento, 2));
                $previewDetalle .= sprintf("║ %-30s = %-39s ║\n", "VADTNELI (valor descuento)", number_format($vadtneli, 2));
                $previewDetalle .= sprintf("║ %-30s = %-39s ║\n", "VANELI (subtotal)", number_format($subtotalConDescuento, 2));
                $previewDetalle .= sprintf("║ %-30s = %-39s ║\n", "POIVLI", "19");
                $previewDetalle .= sprintf("║ %-30s = %-39s ║\n", "VAIVLI (IVA)", number_format($ivaConDescuento, 2));
                $previewDetalle .= sprintf("║ %-30s = %-39s ║\n", "VABRLI (total)", number_format($total, 2));
                $previewDetalle .= sprintf("║ %-30s = %-39s ║\n", "TIGELI", "I");
                $previewDetalle .= sprintf("║ %-30s = %-39s ║\n", "FEEMLI", "GETDATE() con hora 00:00:00.000");
                $previewDetalle .= sprintf("║ %-30s = %-39s ║\n", "FEERLI", "GETDATE() con hora 00:00:00.000 (igual que FEEMLI)");
                $previewDetalle .= sprintf("║ %-30s = %-39s ║\n", "NUDTLI", $nudtli);
                $previewDetalle .= sprintf("║ %-30s = %-39s ║\n", "ARCHIRST", "");
                $previewDetalle .= sprintf("║ %-30s = %-39s ║\n", "IDRST", "0");
                $previewDetalle .= sprintf("║ %-30s = %-39s ║\n", "PPPRPM", number_format($ppprpm, 2));
                $previewDetalle .= sprintf("║ %-30s = %-39s ║\n", "PPPRNERE1", number_format($ppprnere1, 2));
                $previewDetalle .= sprintf("║ %-30s = %-39s ║\n", "PPPRNERE2", number_format($ppprnere2, 2));
                $previewDetalle .= sprintf("║ %-30s = %-39s ║\n", "TASADORIG", "1");
                $previewDetalle .= sprintf("║ %-30s = %-39s ║\n", "CUOGASDIF", "0");
                $previewDetalle .= sprintf("║ %-30s = %-39s ║\n", "PROYECTO", "0");
                $previewDetalle .= sprintf("║ %-30s = %-39s ║\n", "POTENCIA", "0");
                $previewDetalle .= sprintf("║ %-30s = %-39s ║\n", "HUMEDAD", "0");
                $previewDetalle .= sprintf("║ %-30s = %-39s ║\n", "IDTABITPRE", "0");
                $previewDetalle .= sprintf("║ %-30s = %-39s ║\n", "FEERLIMODI", "GETDATE() con hora 00:00:00.000 (igual que FEEMLI)");
                $previewDetalle .= "╚══════════════════════════════════════════════════════════════════════════╝\n";
                
                Log::info($previewDetalle);
            }
            
            // ==================== PREVISUALIZACIÓN UPDATE STOCK ====================
            $previewStock = "\n\n";
            $previewStock .= "╔══════════════════════════════════════════════════════════════════════════╗\n";
            $previewStock .= "║                   📊 PREVISUALIZACIÓN UPDATE STOCK                       ║\n";
            $previewStock .= "╠══════════════════════════════════════════════════════════════════════════╣\n";
            
            foreach ($cotizacion->productos as $producto) {
                $previewStock .= sprintf("║ Producto: %-62s ║\n", substr($producto->codigo_producto, 0, 62));
                $previewStock .= sprintf("║   MySQL productos.stock_comprometido  += %-29s ║\n", $producto->cantidad);
                $previewStock .= sprintf("║   SQL MAEST.STOCKSALIDA               += %-29s ║\n", $producto->cantidad);
                $previewStock .= "║                                                                          ║\n";
            }
            
            $previewStock .= "╚══════════════════════════════════════════════════════════════════════════╝\n\n";
            
            Log::info($previewStock);
            
            // ==================== PREVISUALIZACIÓN TABLA MAEEDOOB ====================
            $observacionVendedor = $cotizacion->observacion_vendedor ?? '';
            $numeroOrdenCompra = $cotizacion->numero_orden_compra ?? '';
            $condicionPago = $this->obtenerCondicionPagoCliente($cotizacion->cliente_codigo);
            
            $observacionTruncada = substr($observacionVendedor, 0, 250);
            $ordenCompraTruncada = substr($numeroOrdenCompra, 0, 40);
            
            // Obtener datos de picking para previsualización
            $separadorPor = $cotizacion->guia_picking_separado_por ?? '';
            $revisadoPor = $cotizacion->guia_picking_revisado_por ?? '';
            $numeroBultos = $cotizacion->guia_picking_numero_bultos ?? '';
            
            $previewMAEEDOOB = "\n\n";
            $previewMAEEDOOB .= "╔══════════════════════════════════════════════════════════════════════════╗\n";
            $previewMAEEDOOB .= "║                  📊 PREVISUALIZACIÓN TABLA MAEEDOOB                      ║\n";
            $previewMAEEDOOB .= "╠══════════════════════════════════════════════════════════════════════════╣\n";
            $previewMAEEDOOB .= sprintf("║ %-30s = %-39s ║\n", "IDMAEEDO", $siguienteId);
            $previewMAEEDOOB .= sprintf("║ %-30s = %-39s ║\n", "OBDO (observación vendedor)", substr($observacionTruncada, 0, 39));
            $previewMAEEDOOB .= sprintf("║ %-30s = %-39s ║\n", "CPDO (condición pago)", $condicionPago);
            $previewMAEEDOOB .= sprintf("║ %-30s = %-39s ║\n", "OCDO (orden compra)", substr($ordenCompraTruncada, 0, 39));
            $previewMAEEDOOB .= sprintf("║ %-30s = %-39s ║\n", "TEXTO1 (separador)", substr($separadorPor, 0, 39));
            $previewMAEEDOOB .= sprintf("║ %-30s = %-39s ║\n", "TEXTO2 (revisor)", substr($revisadoPor, 0, 39));
            $previewMAEEDOOB .= sprintf("║ %-30s = %-39s ║\n", "TEXTO3 (n° bultos)", substr($numeroBultos, 0, 39));
            $previewMAEEDOOB .= "╚══════════════════════════════════════════════════════════════════════════╝\n\n";
            
            Log::info($previewMAEEDOOB);
            
            // ==================== PREVISUALIZACIÓN TABLA MAEDTLI ====================
            $productosConDescuento = 0;
            foreach ($cotizacion->productos as $producto) {
                $porcentajeDescuento = $producto->descuento_porcentaje ?? 0;
                if ($porcentajeDescuento > 0) {
                    $productosConDescuento++;
                }
            }
            
            if ($productosConDescuento > 0) {
                $previewMAEDTLI = "\n\n";
                $previewMAEDTLI .= "╔══════════════════════════════════════════════════════════════════════════╗\n";
                $previewMAEDTLI .= "║                  📊 PREVISUALIZACIÓN TABLA MAEDTLI                       ║\n";
                $previewMAEDTLI .= "╠══════════════════════════════════════════════════════════════════════════╣\n";
                $previewMAEDTLI .= sprintf("║ %-30s = %-39s ║\n", "Productos con descuento", $productosConDescuento);
                $previewMAEDTLI .= sprintf("║ %-30s = %-39s ║\n", "KODT (fijo)", "D_SIN_TIPO");
                $previewMAEDTLI .= "║                                                                          ║\n";
                
                foreach ($cotizacion->productos as $index => $producto) {
                    $porcentajeDescuento = $producto->descuento_porcentaje ?? 0;
                    if ($porcentajeDescuento > 0) {
                        $lineaId = $index + 1;
                        $nulidoFormateado = str_pad($lineaId, 5, '0', STR_PAD_LEFT);
                        $valorDescuento = $producto->descuento_valor ?? 0;
                        
                        $previewMAEDTLI .= sprintf("║ Línea %-25s = %-39s ║\n", $lineaId, substr($producto->codigo_producto, 0, 39));
                        $previewMAEDTLI .= sprintf("║   IDMAEEDO = %-26s = %-39s ║\n", "", $siguienteId);
                        $previewMAEDTLI .= sprintf("║   NULIDO = %-27s = %-39s ║\n", "", $nulidoFormateado);
                        $previewMAEDTLI .= sprintf("║   KODT = %-28s = %-39s ║\n", "", "D_SIN_TIPO");
                        $previewMAEDTLI .= sprintf("║   PODT = %-28s = %-39s ║\n", "", number_format($porcentajeDescuento, 2));
                        $previewMAEDTLI .= sprintf("║   VADT = %-28s = %-39s ║\n", "", number_format($valorDescuento, 2));
                        $previewMAEDTLI .= "║                                                                          ║\n";
                    }
                }
                
                $previewMAEDTLI .= "╚══════════════════════════════════════════════════════════════════════════╝\n\n";
                
                Log::info($previewMAEDTLI);
            } else {
                $previewMAEDTLI = "\n\n";
                $previewMAEDTLI .= "╔══════════════════════════════════════════════════════════════════════════╗\n";
                $previewMAEDTLI .= "║                  📊 PREVISUALIZACIÓN TABLA MAEDTLI                       ║\n";
                $previewMAEDTLI .= "╠══════════════════════════════════════════════════════════════════════════╣\n";
                $previewMAEDTLI .= "║ ⏭️  NO HAY PRODUCTOS CON DESCUENTO - NO SE INSERTA MAEDTLI                ║\n";
                $previewMAEDTLI .= "╚══════════════════════════════════════════════════════════════════════════╝\n\n";
                
                Log::info($previewMAEDTLI);
            }
            
            return [
                'success' => true,
                'message' => 'Previsualización generada. Revisa los logs para ver los detalles.',
                'nudo' => $nudoFormateado,
                'id_maeedo' => $siguienteId
            ];
            
        } catch (\Exception $e) {
            Log::error('Error en previsualizarInsertSQL: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Insertar cotización en SQL Server
     */
    private function insertarEnSQLServer($cotizacion, $datosPicking = [])
    {
        try {
            // Obtener siguiente correlativo para IDMAEEDO
            $queryCorrelativo = "SELECT TOP 1 ISNULL(MAX(IDMAEEDO), 0) + 1 AS siguiente_id FROM MAEEDO WHERE EMPRESA = '01'";
            
            $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
            file_put_contents($tempFile, $queryCorrelativo . "\ngo\nquit");
            
            $command = "tsql -H " . env('SQLSRV_EXTERNAL_HOST') . " -p " . env('SQLSRV_EXTERNAL_PORT') . " -U " . env('SQLSRV_EXTERNAL_USERNAME') . " -P " . env('SQLSRV_EXTERNAL_PASSWORD') . " -D " . env('SQLSRV_EXTERNAL_DATABASE') . " < {$tempFile} 2>&1";
            $result = shell_exec($command);
            
            unlink($tempFile);
            
            // Parsear el resultado para obtener el siguiente ID
            $siguienteId = 1; // Valor por defecto
            if ($result && !str_contains($result, 'error')) {
                // Buscar el patrón específico: número grande después de "siguiente_id"
                if (preg_match('/siguiente_id\s*\n\s*(\d+)/', $result, $matches)) {
                    $siguienteId = (int)$matches[1];
                } else {
                    // Fallback: buscar el número más grande en las líneas
                $lines = explode("\n", $result);
                    $maxNumber = 0;
                foreach ($lines as $line) {
                    $line = trim($line);
                        if (is_numeric($line) && (int)$line > $maxNumber && (int)$line > 1000) {
                            $maxNumber = (int)$line;
                        }
                    }
                    if ($maxNumber > 0) {
                        $siguienteId = $maxNumber;
                    }
                }
            }
            
            Log::info('Siguiente ID calculado para MAEEDO: ' . $siguienteId);
            Log::info("✅ IDMAEEDO asignado: {$siguienteId}");
            
            // Obtener el máximo NUDO de NVV y sumarle 1 (consulta simple y directa)
            // IMPORTANTE: Filtrar por TIDO = 'NVV' porque cada tipo de documento tiene su propia numeración
            // Usar MAX directamente para obtener el mayor valor numérico en una sola consulta
            $queryNudo = "SELECT MAX(CAST(NUDO AS INT)) as max_nudo FROM MAEEDO WHERE TIDO = 'NVV' AND ISNUMERIC(NUDO) = 1";
            
            $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
            file_put_contents($tempFile, $queryNudo . "\ngo\nquit");
            
            $command = "tsql -H " . env('SQLSRV_EXTERNAL_HOST') . " -p " . env('SQLSRV_EXTERNAL_PORT') . " -U " . env('SQLSRV_EXTERNAL_USERNAME') . " -P " . env('SQLSRV_EXTERNAL_PASSWORD') . " -D " . env('SQLSRV_EXTERNAL_DATABASE') . " < {$tempFile} 2>&1";
            $result = shell_exec($command);
            unlink($tempFile);
            
            Log::info("Resultado query máximo NUDO: " . substr($result, 0, 200));
            
            // Parsear el resultado - buscar el número después de "max_nudo"
            $maxNudo = 0;
            if (preg_match('/max_nudo\s*\n\s*(\d+)/', $result, $matches)) {
                $maxNudo = (int)$matches[1];
            } elseif (preg_match('/(\d{6,})/', $result, $matches)) {
                // Fallback: buscar cualquier número grande (6+ dígitos)
                $maxNudo = (int)$matches[1];
            }
            
            if ($maxNudo <= 0) {
                Log::warning("No se pudo obtener el máximo NUDO, usando 1 como valor por defecto. Resultado: " . substr($result, 0, 300));
                $maxNudo = 0; // Si no hay registros, empezar desde 1
            }
            
            // Sumar 1 al máximo NUDO para obtener el siguiente disponible
            $siguienteNudo = $maxNudo + 1;
            $nudoFormateado = str_pad($siguienteNudo, 10, '0', STR_PAD_LEFT);
            
            Log::info("Máximo NUDO de NVV encontrado: {$maxNudo}");
            Log::info("✅ NUDO asignado: {$nudoFormateado}");
            
            // Obtener información del vendedor
            $codigoVendedor = $cotizacion->user->codigo_vendedor ?? '001';
            $nombreVendedor = $cotizacion->user->name ?? 'Vendedor Sistema';
            
            // OPTIMIZACIÓN: Combinar todas las consultas de datos del cliente en una sola
            $queryCliente = "SELECT 
                LTRIM(RTRIM(SUEN)) as SUCURSAL,
                ISNULL(DIPRVE, 0) as DIPRVE,
                ISNULL(CPEN, '') as CPEN,
                ISNULL(NUVECR, 0) as NUVECR
            FROM MAEEN WHERE KOEN = '{$cotizacion->cliente_codigo}'";
            
            $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
            file_put_contents($tempFile, $queryCliente . "\ngo\nquit");
            
            $command = "tsql -H " . env('SQLSRV_EXTERNAL_HOST') . " -p " . env('SQLSRV_EXTERNAL_PORT') . " -U " . env('SQLSRV_EXTERNAL_USERNAME') . " -P " . env('SQLSRV_EXTERNAL_PASSWORD') . " -D " . env('SQLSRV_EXTERNAL_DATABASE') . " < {$tempFile} 2>&1";
            $result = shell_exec($command);
            unlink($tempFile);
            
            // Parsear todos los datos del cliente de una vez
            $sucursalCliente = '';
            $diasPago = 0;
            $condicionPago = '';
            $nuevecr = 0;
            
            if ($result && !str_contains($result, 'error')) {
                $lines = explode("\n", $result);
                $foundHeaders = false;
                $headerIndexes = [];
                
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line) || str_contains($line, 'row') || str_contains($line, '---') || str_contains($line, 'Setting')) {
                        continue;
                    }
                    
                    // Buscar línea de headers
                    if (str_contains($line, 'SUCURSAL') || str_contains($line, 'DIPRVE') || str_contains($line, 'CPEN') || str_contains($line, 'NUVECR')) {
                        $foundHeaders = true;
                        // Extraer índices de cada columna
                        $parts = preg_split('/\s+/', $line);
                        foreach ($parts as $idx => $part) {
                            $part = trim($part);
                            if ($part === 'SUCURSAL') $headerIndexes['SUCURSAL'] = $idx;
                            if ($part === 'DIPRVE') $headerIndexes['DIPRVE'] = $idx;
                            if ($part === 'CPEN') $headerIndexes['CPEN'] = $idx;
                            if ($part === 'NUVECR') $headerIndexes['NUVECR'] = $idx;
                        }
                        continue;
                    }
                    
                    // Después de headers, siguiente línea con datos
                    if ($foundHeaders && !empty($headerIndexes)) {
                        $values = preg_split('/\s+/', $line);
                        if (count($values) > max($headerIndexes)) {
                            if (isset($headerIndexes['SUCURSAL'])) {
                                $sucursalCliente = trim($values[$headerIndexes['SUCURSAL']] ?? '');
                            }
                            if (isset($headerIndexes['DIPRVE'])) {
                                $diasPago = (int)($values[$headerIndexes['DIPRVE']] ?? 0);
                            }
                            if (isset($headerIndexes['CPEN'])) {
                                $condicionPago = trim($values[$headerIndexes['CPEN']] ?? '');
                            }
                            if (isset($headerIndexes['NUVECR'])) {
                                $nuevecr = (float)($values[$headerIndexes['NUVECR']] ?? 0);
                            }
                        }
                        break;
                    }
                }
            }
            
            // Obtener lista de precios desde MySQL (rápido, no necesita optimización)
            $listaPrecios = $this->obtenerListaPreciosCliente($cotizacion->cliente_codigo);
            
            // Si la sucursal está vacía o no se encontró, dejar vacío (no usar '001' como fallback)
            Log::info("Sucursal del cliente '{$cotizacion->cliente_codigo}': '{$sucursalCliente}' " . (empty($sucursalCliente) ? "(vacía - correcto)" : ""));
            Log::info("Días de pago del cliente (DIPRVE): {$diasPago}");
            
            // Calcular fecha de vencimiento: fecha de emisión + días de pago del cliente
            // Si días de pago es 0, usar la fecha de creación (sin agregar días)
            $fechaEmision = now();
            if ($diasPago > 0) {
                $fechaVencimiento = $fechaEmision->copy()->addDays($diasPago)->format('Y-m-d');
            } else {
                // Si es 0, usar la fecha de creación (misma fecha de emisión)
                $fechaVencimiento = $fechaEmision->format('Y-m-d');
            }
            Log::info("Fecha de vencimiento calculada: {$fechaVencimiento} (días de pago: {$diasPago})");
            
            // Calcular HORAGRAB (función de Excel: convertir fecha/hora a número serial)
            $diasDesde1900 = $fechaEmision->diffInDays('1900-01-01') + 2; // +2 por bug de Excel (año 1900 bisiesto)
            $horaDecimal = ($fechaEmision->hour * 3600 + $fechaEmision->minute * 60 + $fechaEmision->second) / 86400;
            $horagrab = $diasDesde1900 + $horaDecimal;
            
            // CAPRCO = suma de cantidades de productos
            $sumaCantidades = $cotizacion->productos->sum('cantidad');
            
            // Calcular SUMAS de MAEDDO para MAEEDO (igual que en previsualización)
            $sumaVANELI = 0;
            $sumaVAIVLI = 0;
            $sumaVABRLI = 0;
            
            foreach ($cotizacion->productos as $producto) {
                $precioNeto = $producto->precio_unitario;
                $cantidad = $producto->cantidad;
                $subtotalBruto = $precioNeto * $cantidad;
                $valorDescuento = $producto->descuento_valor ?? 0;
                $subtotalConDescuento = $subtotalBruto - $valorDescuento;
                $ivaConDescuento = $subtotalConDescuento * 0.19;
                $totalConIVA = $subtotalConDescuento + $ivaConDescuento;
                
                $sumaVANELI += $subtotalConDescuento;
                $sumaVAIVLI += $ivaConDescuento;
                $sumaVABRLI += $totalConIVA;
            }
            
            $VANEDO = round($sumaVANELI, 0);
            $VAIVDO = $sumaVAIVLI;
            $VABRDO = round($sumaVABRLI, 0);
            
            Log::info("=== DATOS PARA INSERT NVV ===");
            Log::info("Cotización ID: {$cotizacion->id}");
            Log::info("Cliente: {$cotizacion->cliente_codigo} - {$cotizacion->cliente_nombre}");
            Log::info("Sucursal Cliente (SUENDO): '{$sucursalCliente}'");
            Log::info("Vendedor: {$codigoVendedor} - {$nombreVendedor}");
            Log::info("CAPRCO (suma cantidades): {$sumaCantidades}");
            Log::info("VANEDO (suma VANELI): {$VANEDO}");
            Log::info("VAIVDO (suma VAIVLI): {$VAIVDO}");
            Log::info("VABRDO (suma VABRLI): {$VABRDO}");
            Log::info("IDMAEEDO: {$siguienteId}");
            Log::info("NUDO: {$nudoFormateado}");
            Log::info("Fecha Vencimiento: {$fechaVencimiento}");
            
            // Insertar encabezado en MAEEDO con campos requeridos por el sistema interno
            $insertMAEEDO = "
                SET IDENTITY_INSERT MAEEDO ON
                
                INSERT INTO MAEEDO (
                    IDMAEEDO, EMPRESA, TIDO, NUDO, ENDO, SUENDO, ENDOFI, SUDO,
                    TIGEDO, LUVTDO, MEARDO, ESPGDO,
                    FEEMDO, FE01VEDO, FEULVEDO, FEER,
                    CAPRCO, CAPRAD, CAPREX, CAPRNC,
                    MODO, TIMODO, TAMODO,
                    VAIVDO, VANEDO, VABRDO, VAABDO,
                    ESDO, KOFUDO, KOTU, LAHORA, DESPACHO, HORAGRAB,
                    CUOGASDIF, BODESTI, PROYECTO, FLIQUIFCV, LISACTIVA
                ) VALUES (
                    {$siguienteId}, '01', 'NVV', '{$nudoFormateado}', '{$cotizacion->cliente_codigo}', 
                    '{$sucursalCliente}', '', 'LIB',
                    'I', '', 'N', 'S',
                    CONVERT(DATETIME, CONVERT(DATE, GETDATE())), CONVERT(DATETIME, '{$fechaVencimiento}'), CONVERT(DATETIME, '{$fechaVencimiento}'), CONVERT(DATETIME, CONVERT(DATE, GETDATE())),
                    {$sumaCantidades}, 0, 0, 0,
                    '$', 'N', 1,
                    {$VAIVDO}, {$VANEDO}, {$VABRDO}, 0,
                    '', '{$codigoVendedor}', 1, CONVERT(DATETIME, CONVERT(DATE, GETDATE())), 1, {$horagrab},
                    0, '', '', CONVERT(DATETIME, CONVERT(DATE, GETDATE())), 'TABPP01P'
                )
                
                SET IDENTITY_INSERT MAEEDO OFF
            ";
            
            Log::info("=== SQL INSERT MAEEDO ===");
            Log::info("IDMAEEDO: {$siguienteId}");
            Log::info("NUDO: {$nudoFormateado}");
            Log::info("ENDO: {$cotizacion->cliente_codigo}");
            Log::info("SUENDO: {$sucursalCliente}");
            Log::info("VANEDO: {$VANEDO}");
            Log::info("VAIVDO: {$VAIVDO}");
            Log::info("VABRDO: {$VABRDO}");
            Log::info("CAPRCO: {$sumaCantidades}");
            Log::info("SQL completo (primeros 500 caracteres): " . substr($insertMAEEDO, 0, 500));
            
            $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
            file_put_contents($tempFile, $insertMAEEDO . "\ngo\nquit");
            
            $command = "tsql -H " . env('SQLSRV_EXTERNAL_HOST') . " -p " . env('SQLSRV_EXTERNAL_PORT') . " -U " . env('SQLSRV_EXTERNAL_USERNAME') . " -P " . env('SQLSRV_EXTERNAL_PASSWORD') . " -D " . env('SQLSRV_EXTERNAL_DATABASE') . " < {$tempFile} 2>&1";
            $result = shell_exec($command);
            
            unlink($tempFile);
            
            // Log completo del resultado para debugging
            Log::info("=== RESULTADO INSERT MAEEDO ===");
            Log::info("Resultado completo: " . substr($result, 0, 1000));
            
            // Verificar si hay error de duplicado en NUDO
            $nudoDuplicado = false;
            if (str_contains($result, 'duplicate') || str_contains($result, 'Violation') || str_contains($result, 'UNIQUE')) {
                // Verificar si es duplicado de NUDO específicamente
                $queryVerificarDuplicado = "SELECT COUNT(*) as total FROM MAEEDO WHERE TIDO = 'NVV' AND NUDO = '{$nudoFormateado}'";
                
                $tempFileVerificar = tempnam(sys_get_temp_dir(), 'sql_');
                file_put_contents($tempFileVerificar, $queryVerificarDuplicado . "\ngo\nquit");
                
                $commandVerificar = "tsql -H " . env('SQLSRV_EXTERNAL_HOST') . " -p " . env('SQLSRV_EXTERNAL_PORT') . " -U " . env('SQLSRV_EXTERNAL_USERNAME') . " -P " . env('SQLSRV_EXTERNAL_PASSWORD') . " -D " . env('SQLSRV_EXTERNAL_DATABASE') . " < {$tempFileVerificar} 2>&1";
                $resultVerificar = shell_exec($commandVerificar);
                unlink($tempFileVerificar);
                
                $totalDuplicados = 0;
                if ($resultVerificar) {
                    if (preg_match('/total\s*\n\s*(\d+)/', $resultVerificar, $matches)) {
                        $totalDuplicados = (int)$matches[1];
                    } elseif (preg_match('/\b(\d+)\b/', $resultVerificar, $matches)) {
                        $totalDuplicados = (int)$matches[1];
                    }
                }
                
                if ($totalDuplicados > 0) {
                    $nudoDuplicado = true;
                    Log::warning("⚠️ NUDO duplicado detectado: {$nudoFormateado} ({$totalDuplicados} registros). Corrigiendo...");
                    
                    // Calcular nuevo NUDO sumando 1
                    $nuevoNudo = (int)$nudoFormateado + 1;
                    $nuevoNudoFormateado = str_pad($nuevoNudo, 10, '0', STR_PAD_LEFT);
                    
                    // Actualizar el NUDO en MAEEDO para este IDMAEEDO
                    $queryUpdateNudo = "UPDATE MAEEDO SET NUDO = '{$nuevoNudoFormateado}' WHERE IDMAEEDO = {$siguienteId} AND TIDO = 'NVV'";
                    
                    $tempFileUpdate = tempnam(sys_get_temp_dir(), 'sql_');
                    file_put_contents($tempFileUpdate, $queryUpdateNudo . "\ngo\nquit");
                    
                    $commandUpdate = "tsql -H " . env('SQLSRV_EXTERNAL_HOST') . " -p " . env('SQLSRV_EXTERNAL_PORT') . " -U " . env('SQLSRV_EXTERNAL_USERNAME') . " -P " . env('SQLSRV_EXTERNAL_PASSWORD') . " -D " . env('SQLSRV_EXTERNAL_DATABASE') . " < {$tempFileUpdate} 2>&1";
                    $resultUpdate = shell_exec($commandUpdate);
                    unlink($tempFileUpdate);
                    
                    if (str_contains($resultUpdate, 'Msg') && !str_contains($resultUpdate, 'rows affected')) {
                        Log::error("❌ Error actualizando NUDO: " . $resultUpdate);
                        throw new \Exception('Error corrigiendo NUDO duplicado: ' . substr($resultUpdate, 0, 500));
                    }
                    
                    // Actualizar también en MAEDDO si existe
                    $queryUpdateMAEDDO = "UPDATE MAEDDO SET NUDO = '{$nuevoNudoFormateado}' WHERE IDMAEEDO = {$siguienteId} AND TIDO = 'NVV'";
                    
                    $tempFileUpdateMAEDDO = tempnam(sys_get_temp_dir(), 'sql_');
                    file_put_contents($tempFileUpdateMAEDDO, $queryUpdateMAEDDO . "\ngo\nquit");
                    
                    $commandUpdateMAEDDO = "tsql -H " . env('SQLSRV_EXTERNAL_HOST') . " -p " . env('SQLSRV_EXTERNAL_PORT') . " -U " . env('SQLSRV_EXTERNAL_USERNAME') . " -P " . env('SQLSRV_EXTERNAL_PASSWORD') . " -D " . env('SQLSRV_EXTERNAL_DATABASE') . " < {$tempFileUpdateMAEDDO} 2>&1";
                    $resultUpdateMAEDDO = shell_exec($commandUpdateMAEDDO);
                    unlink($tempFileUpdateMAEDDO);
                    
                    // Actualizar variable para usar el nuevo NUDO
                    $nudoAnterior = $nudoFormateado;
                    $nudoFormateado = $nuevoNudoFormateado;
                    
                    // Actualizar también en MySQL
                    $cotizacion->numero_nvv = $nuevoNudoFormateado;
                    $cotizacion->save();
                    
                    Log::info("✅ NUDO corregido de {$nudoAnterior} a {$nuevoNudoFormateado} (actualizado en SQL Server y MySQL)");
                }
            }
            
            if (!$nudoDuplicado && (str_contains($result, 'Msg') || str_contains($result, 'Error'))) {
                Log::error("❌ ERROR INSERTANDO MAEEDO:");
                Log::error("IDMAEEDO intentado: {$siguienteId}");
                Log::error("NUDO: {$nudoFormateado}");
                Log::error("Resultado completo: " . $result);
                throw new \Exception('Error insertando encabezado: ' . substr($result, 0, 500));
            }
            
            Log::info('Encabezado MAEEDO insertado correctamente' . ($nudoDuplicado ? ' (NUDO corregido)' : ''));
            
            // OPTIMIZACIÓN: Obtener todos los precios mínimos en una sola consulta
            $codigosProductos = [];
            foreach ($cotizacion->productos as $producto) {
                $codigosProductos[] = "'" . substr($producto->codigo_producto, 0, 13) . "'";
            }
            $codigosProductosStr = implode(',', $codigosProductos);
            $preciosMinimos = [];
            
            if (!empty($codigosProductosStr)) {
                $queryPreciosMin = "SELECT KOPR, ISNULL(PM, 0) as PM FROM MAEPREM WHERE KOPR IN ({$codigosProductosStr})";
                $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
                file_put_contents($tempFile, $queryPreciosMin . "\ngo\nquit");
                $command = "tsql -H " . env('SQLSRV_EXTERNAL_HOST') . " -p " . env('SQLSRV_EXTERNAL_PORT') . " -U " . env('SQLSRV_EXTERNAL_USERNAME') . " -P " . env('SQLSRV_EXTERNAL_PASSWORD') . " -D " . env('SQLSRV_EXTERNAL_DATABASE') . " < {$tempFile} 2>&1";
                $result = shell_exec($command);
                unlink($tempFile);
                
                if ($result && !str_contains($result, 'error')) {
                    $lines = explode("\n", $result);
                    $foundHeaders = false;
                    foreach ($lines as $line) {
                        $line = trim($line);
                        if (empty($line) || str_contains($line, 'row') || str_contains($line, '---') || str_contains($line, '>') || str_contains($line, 'Setting')) {
                            continue;
                        }
                        if (str_contains($line, 'KOPR') || str_contains($line, 'PM')) {
                            $foundHeaders = true;
                            continue;
                        }
                        if ($foundHeaders) {
                            $parts = preg_split('/\s+/', $line);
                            if (count($parts) >= 2) {
                                $preciosMinimos[trim($parts[0])] = (float)trim($parts[1]);
                            }
                        }
                    }
                }
            }
            
            // Preparar datos de productos para INSERT masivo
            $insertsMAEDDO = [];
            
            // Insertar detalles en MAEDDO
            foreach ($cotizacion->productos as $index => $producto) {
                $lineaId = $index + 1;
                
                $productoDB = \App\Models\Producto::where('KOPR', $producto->codigo_producto)->first();
                
                $udtrpr = 1;
                $rludpr = 1;
                $ud01pr = 'UN';
                $ud02pr = 'UN';
                
                if ($productoDB) {
                    $rludpr = $productoDB->RLUD ?? 1;
                    $ud01pr = trim($productoDB->UD01PR ?? 'UN');
                    // UD02PR: usar el valor de la BD si existe y no está vacío, sino 'UN' por defecto
                    $ud02prAux = trim($productoDB->UD02PR ?? '');
                    $ud02pr = !empty($ud02prAux) ? $ud02prAux : 'UN';
                    $udtrpr = ($rludpr > 1) ? 2 : 1;
                }
                
                $codigoProducto = substr($producto->codigo_producto, 0, 13);
                // Limpiar nombre del producto antes de truncarlo
                $nombreLimpio = $this->limpiarNombreProducto($producto->nombre_producto);
                $nombreProducto = substr($nombreLimpio, 0, 50);
                $ud01prTruncado = substr($ud01pr, 0, 2);
                $ud02prTruncado = substr($ud02pr, 0, 2);
                
                $precioNeto = $producto->precio_unitario;
                $cantidad = $producto->cantidad;
                $subtotalBruto = $precioNeto * $cantidad;
                $porcentajeDescuento = $producto->descuento_porcentaje ?? 0;
                $valorDescuento = $producto->descuento_valor ?? 0;
                $nudtli = ($porcentajeDescuento > 0 || $valorDescuento > 0) ? 1 : 0;
                $vadtneli = $valorDescuento;
                $subtotalConDescuento = $subtotalBruto - $valorDescuento;
                $ivaConDescuento = $subtotalConDescuento * 0.19;
                $total = $subtotalConDescuento + $ivaConDescuento;
                $caprco2 = $rludpr > 0 ? round($cantidad / $rludpr, 2) : 0;
                $nulidoFormateado = str_pad($lineaId, 5, '0', STR_PAD_LEFT);
                
                // Obtener precio mínimo desde el array pre-cargado
                $ppprpm = $preciosMinimos[$codigoProducto] ?? 0;
                
                $descuentoUnitario = $cantidad > 0 ? $valorDescuento / $cantidad : 0;
                $ppprnere1 = $precioNeto - $descuentoUnitario;
                $ppprnere2 = $ppprnere1;
                
                // Escapar comillas simples en nombre del producto
                $nombreProductoEscapado = str_replace("'", "''", $nombreProducto);
                
                // Preparar VALUES para INSERT masivo
                $insertsMAEDDO[] = "(
                    {$siguienteId}, '01', 'NVV', '{$nudoFormateado}',
                    '{$cotizacion->cliente_codigo}', '{$sucursalCliente}',
                    'SI', '{$nulidoFormateado}', 'LIB', 'LIB', '', '{$codigoVendedor}', 'FPN',
                    {$udtrpr}, {$rludpr}, '{$ud01prTruncado}', '{$ud02prTruncado}',
                    '{$codigoProducto}', '{$nombreProductoEscapado}',
                    {$cantidad}, {$caprco2},
                    '{$listaPrecios}', '$', 'N', 1,
                    {$precioNeto}, {$precioNeto}, " . ($precioNeto * 1.19) . ", " . ($precioNeto * 1.19) . ",
                    {$porcentajeDescuento}, {$vadtneli}, {$subtotalConDescuento}, 19, {$ivaConDescuento}, {$total},
                    'I', CONVERT(DATETIME, CONVERT(DATE, GETDATE())), CONVERT(DATETIME, CONVERT(DATE, GETDATE())), {$nudtli}, '', 0,
                    {$ppprpm}, {$ppprnere1}, {$ppprnere2}, 1, 0, 0,
                    0, 0, 0, CONVERT(DATETIME, CONVERT(DATE, GETDATE()))
                )";
            }
            
            // OPTIMIZACIÓN: INSERT masivo de todos los detalles en una sola consulta
            if (!empty($insertsMAEDDO)) {
                $insertMAEDDOMasivo = "
                    INSERT INTO MAEDDO (
                        IDMAEEDO, EMPRESA, TIDO, NUDO, ENDO, SUENDO,
                        LILG, NULIDO, SULIDO, BOSULIDO, LUVTLIDO, KOFULIDO, TIPR,
                        UDTRPR, RLUDPR, UD01PR, UD02PR,
                        KOPRCT, NOKOPR, CAPRCO1, CAPRCO2,
                        KOLTPR, MOPPPR, TIMOPPPR, TAMOPPPR,
                        PPPRNE, PPPRNELT, PPPRBR, PPPRBRLT,
                        PODTGLLI, VADTNELI, VANELI, POIVLI, VAIVLI, VABRLI,
                        TIGELI, FEEMLI, FEERLI, NUDTLI, ARCHIRST, IDRST,
                        PPPRPM, PPPRNERE1, PPPRNERE2, TASADORIG, CUOGASDIF, PROYECTO,
                        POTENCIA, HUMEDAD, IDTABITPRE, FEERLIMODI
                    ) VALUES " . implode(',', $insertsMAEDDO);
                
                Log::info("SQL INSERT MAEDDO masivo (" . count($insertsMAEDDO) . " líneas)");
                
                $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
                file_put_contents($tempFile, $insertMAEDDOMasivo . "\ngo\nquit");
                
                $command = "tsql -H " . env('SQLSRV_EXTERNAL_HOST') . " -p " . env('SQLSRV_EXTERNAL_PORT') . " -U " . env('SQLSRV_EXTERNAL_USERNAME') . " -P " . env('SQLSRV_EXTERNAL_PASSWORD') . " -D " . env('SQLSRV_EXTERNAL_DATABASE') . " < {$tempFile} 2>&1";
                $result = shell_exec($command);
                
                unlink($tempFile);
                
                if (str_contains($result, 'Msg') || str_contains($result, 'Error') || str_contains($result, 'Violation')) {
                    Log::error("Error en INSERT masivo MAEDDO: " . substr($result, 0, 500));
                    Log::warning("Intentando INSERTs individuales como fallback...");
                    
                    // Fallback: INSERTs individuales si el masivo falla
                    foreach ($insertsMAEDDO as $idx => $values) {
                        $insertIndividual = "
                            INSERT INTO MAEDDO (
                                IDMAEEDO, EMPRESA, TIDO, NUDO, ENDO, SUENDO,
                                LILG, NULIDO, SULIDO, BOSULIDO, LUVTLIDO, KOFULIDO, TIPR,
                                UDTRPR, RLUDPR, UD01PR, UD02PR,
                                KOPRCT, NOKOPR, CAPRCO1, CAPRCO2,
                                KOLTPR, MOPPPR, TIMOPPPR, TAMOPPPR,
                                PPPRNE, PPPRNELT, PPPRBR, PPPRBRLT,
                                PODTGLLI, VADTNELI, VANELI, POIVLI, VAIVLI, VABRLI,
                                TIGELI, FEEMLI, FEERLI, NUDTLI, ARCHIRST, IDRST,
                                PPPRPM, PPPRNERE1, PPPRNERE2, TASADORIG, CUOGASDIF, PROYECTO,
                                POTENCIA, HUMEDAD, IDTABITPRE, FEERLIMODI
                            ) VALUES {$values}";
                        
                        $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
                        file_put_contents($tempFile, $insertIndividual . "\ngo\nquit");
                        $command = "tsql -H " . env('SQLSRV_EXTERNAL_HOST') . " -p " . env('SQLSRV_EXTERNAL_PORT') . " -U " . env('SQLSRV_EXTERNAL_USERNAME') . " -P " . env('SQLSRV_EXTERNAL_PASSWORD') . " -D " . env('SQLSRV_EXTERNAL_DATABASE') . " < {$tempFile} 2>&1";
                        $resultIndividual = shell_exec($command);
                        unlink($tempFile);
                        
                        if (str_contains($resultIndividual, 'Msg') || str_contains($resultIndividual, 'Error')) {
                            throw new \Exception('Error insertando detalle línea ' . ($idx + 1) . ': ' . substr($resultIndividual, 0, 500));
                        }
                    }
                    Log::info('INSERTs individuales completados correctamente (fallback)');
                }
            }
            
            Log::info('Detalles MAEDDO insertados correctamente');
            
            // OPTIMIZACIÓN: Combinar todos los UPDATEs en consultas masivas usando CASE WHEN
            $codigosProductosUpdate = [];
            $cantidadesUpdate = [];
            foreach ($cotizacion->productos as $producto) {
                $codigo = trim(substr($producto->codigo_producto, 0, 13));
                $codigoEscapado = str_replace("'", "''", $codigo); // Escapar comillas simples
                $codigosProductosUpdate[] = "'{$codigoEscapado}'";
                $cantidadesUpdate[$codigoEscapado] = $producto->cantidad;
            }
            $codigosProductosUpdateStr = implode(',', $codigosProductosUpdate);
            
            if (!empty($codigosProductosUpdateStr)) {
                // Construir CASE WHEN para cada producto
                $caseStocksalida = [];
                $caseStocknv1 = [];
                $caseStocknv2 = [];
                
                foreach ($cantidadesUpdate as $codigo => $cantidad) {
                    $caseStocksalida[] = "WHEN KOPR = '{$codigo}' THEN ISNULL(STOCKSALIDA, 0) + {$cantidad}";
                    $caseStocknv1[] = "WHEN KOPR = '{$codigo}' THEN ISNULL(STOCNV1, 0) + {$cantidad}";
                    $caseStocknv2[] = "WHEN KOPR = '{$codigo}' THEN ISNULL(STOCNV2, 0) + {$cantidad}";
                }
                
                // Función helper mejorada para detectar errores reales (ignora mensajes informativos)
                $detectarErrorReal = function($result) {
                    // Mensajes informativos normales de tsql que NO son errores
                    $mensajesInformativos = [
                        'Setting HIGUERA as default database in login packet',
                        'locale is',
                        'locale charset is',
                        'using default charset',
                        '1>',
                        '2>',
                        '3>',
                    ];
                    
                    // Limpiar el resultado removiendo mensajes informativos
                    $resultLimpio = $result;
                    foreach ($mensajesInformativos as $msgInfo) {
                        $resultLimpio = str_replace($msgInfo, '', $resultLimpio);
                    }
                    $resultLimpio = preg_replace('/\n\s*\n/', "\n", $resultLimpio);
                    $resultLimpio = trim($resultLimpio);
                    
                    // Verificar errores REALES de SQL Server (Msg con Level >= 11 es error)
                    if (preg_match('/Msg (\d+), Level (\d+), State \d+/', $result, $matches)) {
                        $level = (int)$matches[2];
                        if ($level >= 11) {
                            return true; // Es un error real
                        }
                    } elseif (str_contains($result, 'Cannot insert') || 
                             str_contains($result, 'violation') || 
                             str_contains($result, 'constraint') ||
                             str_contains($result, 'Permission denied') ||
                             str_contains($result, 'Invalid object name')) {
                        return true; // Es un error real
                    }
                    
                    return false; // No es un error real
                };
                
                // UPDATE masivo MAEST (STOCKSALIDA)
                $updateMAESTMasivo = "
                    UPDATE MAEST 
                    SET STOCKSALIDA = CASE " . implode(' ', $caseStocksalida) . " ELSE STOCKSALIDA END
                    WHERE KOPR IN ({$codigosProductosUpdateStr}) AND EMPRESA = '01'
                ";
                
                $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
                file_put_contents($tempFile, $updateMAESTMasivo . "\ngo\nquit");
                $command = "tsql -H " . env('SQLSRV_EXTERNAL_HOST') . " -p " . env('SQLSRV_EXTERNAL_PORT') . " -U " . env('SQLSRV_EXTERNAL_USERNAME') . " -P " . env('SQLSRV_EXTERNAL_PASSWORD') . " -D " . env('SQLSRV_EXTERNAL_DATABASE') . " < {$tempFile} 2>&1";
                $resultMAEST = shell_exec($command);
                unlink($tempFile);
                
                // Verificar si el UPDATE de MAEST tuvo errores reales
                $maestActualizado = !$detectarErrorReal($resultMAEST);
                if (!$maestActualizado) {
                    Log::error("Error en UPDATE masivo MAEST STOCKSALIDA: " . substr($resultMAEST, 0, 500));
                    Log::error("Query ejecutada: " . substr($updateMAESTMasivo, 0, 500));
                } else {
                    Log::info("✅ UPDATE MAEST STOCKSALIDA ejecutado correctamente");
                }
                
                // Si el UPDATE masivo de MAEST falló, hacer UPDATEs individuales como fallback
                if (!$maestActualizado) {
                    Log::warning('UPDATE masivo MAEST STOCKSALIDA falló, ejecutando UPDATEs individuales como fallback');
                    foreach ($cotizacion->productos as $producto) {
                        $codigo = trim(substr($producto->codigo_producto, 0, 13));
                        $codigoEscapado = str_replace("'", "''", $codigo);
                        $cantidad = $producto->cantidad;
                        
                        $updateIndividual = "
                            UPDATE MAEST 
                            SET STOCKSALIDA = ISNULL(STOCKSALIDA, 0) + {$cantidad}
                            WHERE KOPR = '{$codigoEscapado}' AND EMPRESA = '01'
                        ";
                        
                        $tempFile = tempnam(sys_get_temp_dir(), 'sql_individual_');
                        file_put_contents($tempFile, $updateIndividual . "\ngo\nquit");
                        $command = "tsql -H " . env('SQLSRV_EXTERNAL_HOST') . " -p " . env('SQLSRV_EXTERNAL_PORT') . " -U " . env('SQLSRV_EXTERNAL_USERNAME') . " -P " . env('SQLSRV_EXTERNAL_PASSWORD') . " -D " . env('SQLSRV_EXTERNAL_DATABASE') . " < {$tempFile} 2>&1";
                        $result = shell_exec($command);
                        unlink($tempFile);
                        
                        if (!$detectarErrorReal($result)) {
                            Log::info("✅ MAEST STOCKSALIDA actualizado individualmente para producto: {$codigo}");
                        } else {
                            Log::error("❌ Error actualizando MAEST STOCKSALIDA individual para producto {$codigo}: " . substr($result, 0, 200));
                        }
                    }
                }
                
                // UPDATE MAEPR (STOCNV1, STOCNV2) - Usar UPDATEs individuales (más confiable)
                Log::info("🔄 Actualizando MAEPR STOCNV1/STOCNV2 para " . count($cotizacion->productos) . " productos");
                foreach ($cotizacion->productos as $producto) {
                    $codigo = trim(substr($producto->codigo_producto, 0, 13));
                    $codigoEscapado = str_replace("'", "''", $codigo);
                    $cantidad = $producto->cantidad;
                    
                    // Obtener valores ANTES del UPDATE
                    $queryAntes = "SELECT KOPR, STOCNV1, STOCNV2 FROM MAEPR WHERE KOPR = '{$codigoEscapado}'";
                    $tempFileAntes = tempnam(sys_get_temp_dir(), 'sql_antes_');
                    file_put_contents($tempFileAntes, $queryAntes . "\ngo\nquit");
                    $commandAntes = "tsql -H " . env('SQLSRV_EXTERNAL_HOST') . " -p " . env('SQLSRV_EXTERNAL_PORT') . " -U " . env('SQLSRV_EXTERNAL_USERNAME') . " -P " . env('SQLSRV_EXTERNAL_PASSWORD') . " -D " . env('SQLSRV_EXTERNAL_DATABASE') . " < {$tempFileAntes} 2>&1";
                    $resultAntes = shell_exec($commandAntes);
                    unlink($tempFileAntes);
                    
                    $stocnv1Antes = null;
                    $stocnv2Antes = null;
                    if (preg_match('/' . preg_quote($codigo, '/') . '\s+(\d+)\s+(\d+)/', $resultAntes, $matches)) {
                        $stocnv1Antes = (int)$matches[1];
                        $stocnv2Antes = (int)$matches[2];
                        Log::info("📊 ANTES UPDATE para {$codigo}: STOCNV1={$stocnv1Antes}, STOCNV2={$stocnv2Antes}");
                    }
                    
                    // Ejecutar UPDATE
                    $updateIndividual = "
                        UPDATE MAEPR 
                        SET STOCNV1 = ISNULL(STOCNV1, 0) + {$cantidad},
                            STOCNV2 = ISNULL(STOCNV2, 0) + {$cantidad}
                        WHERE KOPR = '{$codigoEscapado}'
                    ";
                    
                    Log::info("🔍 Ejecutando UPDATE MAEPR para producto {$codigo}");
                    
                    $tempFile = tempnam(sys_get_temp_dir(), 'sql_maepr_');
                    file_put_contents($tempFile, $updateIndividual . "\ngo\nquit");
                    $command = "tsql -H " . env('SQLSRV_EXTERNAL_HOST') . " -p " . env('SQLSRV_EXTERNAL_PORT') . " -U " . env('SQLSRV_EXTERNAL_USERNAME') . " -P " . env('SQLSRV_EXTERNAL_PASSWORD') . " -D " . env('SQLSRV_EXTERNAL_DATABASE') . " < {$tempFile} 2>&1";
                    $result = shell_exec($command);
                    unlink($tempFile);
                    
                    // Verificar que realmente se actualizó
                    $queryDespues = "SELECT KOPR, STOCNV1, STOCNV2 FROM MAEPR WHERE KOPR = '{$codigoEscapado}'";
                    $tempFileDespues = tempnam(sys_get_temp_dir(), 'sql_despues_');
                    file_put_contents($tempFileDespues, $queryDespues . "\ngo\nquit");
                    $commandDespues = "tsql -H " . env('SQLSRV_EXTERNAL_HOST') . " -p " . env('SQLSRV_EXTERNAL_PORT') . " -U " . env('SQLSRV_EXTERNAL_USERNAME') . " -P " . env('SQLSRV_EXTERNAL_PASSWORD') . " -D " . env('SQLSRV_EXTERNAL_DATABASE') . " < {$tempFileDespues} 2>&1";
                    $resultDespues = shell_exec($commandDespues);
                    unlink($tempFileDespues);
                    
                    $stocnv1Despues = null;
                    $stocnv2Despues = null;
                    if (preg_match('/' . preg_quote($codigo, '/') . '\s+(\d+)\s+(\d+)/', $resultDespues, $matches)) {
                        $stocnv1Despues = (int)$matches[1];
                        $stocnv2Despues = (int)$matches[2];
                        Log::info("📊 DESPUÉS UPDATE para {$codigo}: STOCNV1={$stocnv1Despues}, STOCNV2={$stocnv2Despues}");
                    }
                    
                    // Verificar si hubo error o si no se actualizó
                    if ($detectarErrorReal($result)) {
                        Log::error("❌ ERROR actualizando MAEPR para producto {$codigo}: " . substr($result, 0, 300));
                    } elseif ($stocnv1Antes !== null && $stocnv1Despues !== null) {
                        $diferencia1 = $stocnv1Despues - $stocnv1Antes;
                        $diferencia2 = $stocnv2Despues - $stocnv2Antes;
                        
                        if ($diferencia1 == $cantidad && $diferencia2 == $cantidad) {
                            Log::info("✅ MAEPR actualizado CORRECTAMENTE para producto {$codigo}: STOCNV1 {$stocnv1Antes}→{$stocnv1Despues} (+{$cantidad}), STOCNV2 {$stocnv2Antes}→{$stocnv2Despues} (+{$cantidad})");
                        } else {
                            Log::error("❌ ERROR: MAEPR NO se actualizó correctamente para producto {$codigo}. Diferencia esperada: {$cantidad}, obtenida: STOCNV1={$diferencia1}, STOCNV2={$diferencia2}");
                            Log::error("   Resultado UPDATE: " . substr($result, 0, 200));
                        }
                    } else {
                        Log::warning("⚠️ No se pudieron verificar los valores para producto {$codigo}. Resultado antes: " . substr($resultAntes, 0, 100) . " | Resultado después: " . substr($resultDespues, 0, 100));
                    }
                }
                Log::info("✅ UPDATE MAEPR STOCNV1/STOCNV2 completado para todos los productos");
                
                // UPDATE masivo MAEST (STOCNV1, STOCNV2) - IMPORTANTE: En MAEST los campos son STOCNV1/STOCNV2 (sin K)
                // IMPORTANTE: MAEST requiere EMPRESA, KOSU y KOBO (no KOPRST)
                $updateMAESTStockMasivo = "
                    UPDATE MAEST 
                    SET STOCNV1 = CASE " . implode(' ', $caseStocknv1) . " ELSE STOCNV1 END,
                        STOCNV2 = CASE " . implode(' ', $caseStocknv2) . " ELSE STOCNV2 END
                    WHERE KOPR IN ({$codigosProductosUpdateStr}) AND EMPRESA = '01' AND KOSU = 'LIB' AND KOBO = 'LIB'
                ";
                
                $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
                file_put_contents($tempFile, $updateMAESTStockMasivo . "\ngo\nquit");
                $command = "tsql -H " . env('SQLSRV_EXTERNAL_HOST') . " -p " . env('SQLSRV_EXTERNAL_PORT') . " -U " . env('SQLSRV_EXTERNAL_USERNAME') . " -P " . env('SQLSRV_EXTERNAL_PASSWORD') . " -D " . env('SQLSRV_EXTERNAL_DATABASE') . " < {$tempFile} 2>&1";
                $resultMAESTStock = shell_exec($command);
                unlink($tempFile);
                
                $maestStockActualizado = !$detectarErrorReal($resultMAESTStock);
                if (!$maestStockActualizado) {
                    Log::error("Error en UPDATE masivo MAEST STOCKNV1/STOCKNV2: " . substr($resultMAESTStock, 0, 500));
                } else {
                    Log::info("✅ UPDATE MAEST STOCKNV1/STOCKNV2 ejecutado correctamente");
                }
                
                // UPDATE masivo MAEPREM (STOCNV1, STOCNV2)
                $updateMAEPREMMasivo = "
                    UPDATE MAEPREM 
                    SET STOCNV1 = CASE " . implode(' ', $caseStocknv1) . " ELSE STOCNV1 END,
                        STOCNV2 = CASE " . implode(' ', $caseStocknv2) . " ELSE STOCNV2 END
                    WHERE KOPR IN ({$codigosProductosUpdateStr}) AND EMPRESA = '01'
                ";
                
                $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
                file_put_contents($tempFile, $updateMAEPREMMasivo . "\ngo\nquit");
                $command = "tsql -H " . env('SQLSRV_EXTERNAL_HOST') . " -p " . env('SQLSRV_EXTERNAL_PORT') . " -U " . env('SQLSRV_EXTERNAL_USERNAME') . " -P " . env('SQLSRV_EXTERNAL_PASSWORD') . " -D " . env('SQLSRV_EXTERNAL_DATABASE') . " < {$tempFile} 2>&1";
                $resultMAEPREM = shell_exec($command);
                unlink($tempFile);
                
                $maepremActualizado = !$detectarErrorReal($resultMAEPREM);
                if (!$maepremActualizado) {
                    Log::error("Error en UPDATE masivo MAEPREM STOCNV1/STOCNV2: " . substr($resultMAEPREM, 0, 500));
                } else {
                    Log::info("✅ UPDATE MAEPREM STOCNV1/STOCNV2 ejecutado correctamente");
                }
                
                Log::info('Stock comprometido y STOCNV actualizados correctamente (consultas masivas)');
            }
            
            Log::info('Productos MAEPR actualizados correctamente');
            
            // NOTA: No actualizamos stock comprometido en MySQL aquí
            // MySQL es solo una tabla de paso/caché. Los datos principales están en SQL Server.
            // El stock comprometido en MySQL se actualiza automáticamente cuando se ejecuta
            // la sincronización de productos desde SQL Server (StockService::sincronizarStockDesdeSQLServer)
            // que obtiene STOCNV1 de SQL Server y lo sincroniza con MySQL.
            Log::info('Stock comprometido actualizado en SQL Server (MAEPR, MAEST, MAEPREM). MySQL se sincronizará automáticamente en la próxima sincronización de productos.');
            
            // INSERT MAEEDOOB - Observaciones, orden de compra y datos de picking
            $observacionVendedor = $cotizacion->observacion_vendedor ?? '';
            $numeroOrdenCompra = $cotizacion->numero_orden_compra ?? '';
            
            // Truncar observación a 250 caracteres
            $observacionTruncada = substr($observacionVendedor, 0, 250);
            
            // Truncar orden de compra a 40 caracteres
            $ordenCompraTruncada = substr($numeroOrdenCompra, 0, 40);
            
            // La condición de pago ya se obtuvo arriba en la consulta combinada
            
            // Obtener datos de picking (prioridad: parámetro $datosPicking > cotización > vacío)
            $separadorPor = $datosPicking['separado_por'] ?? $cotizacion->guia_picking_separado_por ?? '';
            $revisadoPor = $datosPicking['revisado_por'] ?? $cotizacion->guia_picking_revisado_por ?? '';
            $numeroBultos = $datosPicking['numero_bultos'] ?? $cotizacion->guia_picking_numero_bultos ?? '';
            
            // Truncar campos de picking si es necesario (ajustar según estructura de TEXTO1, TEXTO2, TEXTO3 en MAEEDOOB)
            $separadorPorTruncado = substr($separadorPor, 0, 100); // Ajustar según longitud del campo TEXTO1
            $revisadoPorTruncado = substr($revisadoPor, 0, 100); // Ajustar según longitud del campo TEXTO2
            $numeroBultosTruncado = substr($numeroBultos, 0, 50); // Ajustar según longitud del campo TEXTO3
            
            // Escapar comillas simples para SQL y asegurar que los valores no estén vacíos (usar espacio si están vacíos)
            $observacionEscapada = str_replace("'", "''", $observacionTruncada ?: ' ');
            $condicionPagoEscapada = str_replace("'", "''", $condicionPago ?: ' ');
            $ordenCompraEscapada = str_replace("'", "''", $ordenCompraTruncada ?: ' ');
            $separadorPorEscapado = str_replace("'", "''", $separadorPorTruncado ?: ' ');
            $revisadoPorEscapado = str_replace("'", "''", $revisadoPorTruncado ?: ' ');
            $numeroBultosEscapado = str_replace("'", "''", $numeroBultosTruncado ?: ' ');
            
            // Log de valores que se van a insertar para debugging
            Log::info("📋 Valores MAEEDOOB a insertar - IDMAEEDO: {$siguienteId}", [
                'OBDO' => substr($observacionEscapada, 0, 50),
                'CPDO' => $condicionPagoEscapada,
                'OCDO' => substr($ordenCompraEscapada, 0, 40),
                'TEXTO1' => substr($separadorPorEscapado, 0, 50),
                'TEXTO2' => substr($revisadoPorEscapado, 0, 50),
                'TEXTO3' => substr($numeroBultosEscapado, 0, 50),
            ]);
            
            // INSERT MAEEDOOB - La tabla NO tiene IDMAEDOOB ni EMPRESA según estructura real
            $insertMAEEDOOB = "
                INSERT INTO MAEEDOOB (
                    IDMAEEDO, OBDO, CPDO, OCDO, TEXTO1, TEXTO2, TEXTO3
                ) VALUES (
                    {$siguienteId}, '{$observacionEscapada}', '{$condicionPagoEscapada}', '{$ordenCompraEscapada}', 
                    '{$separadorPorEscapado}', '{$revisadoPorEscapado}', '{$numeroBultosEscapado}'
                )
            ";
            
            Log::info("SQL INSERT MAEEDOOB:");
            Log::info($insertMAEEDOOB);
            
            $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
            file_put_contents($tempFile, $insertMAEEDOOB . "\ngo\nquit");
            
            $command = "tsql -H " . env('SQLSRV_EXTERNAL_HOST') . " -p " . env('SQLSRV_EXTERNAL_PORT') . " -U " . env('SQLSRV_EXTERNAL_USERNAME') . " -P " . env('SQLSRV_EXTERNAL_PASSWORD') . " -D " . env('SQLSRV_EXTERNAL_DATABASE') . " < {$tempFile} 2>&1";
            $result = shell_exec($command);
            
            unlink($tempFile);
            
            // Mejorar detección de errores - ignorar mensajes informativos normales de tsql
            $tieneError = false;
            $mensajeError = '';
            
            // Mensajes informativos normales de tsql que NO son errores
            $mensajesInformativos = [
                'Setting HIGUERA as default database in login packet',
                'locale is',
                'locale charset is',
                'using default charset',
                '1>',
                '2>',
                '3>',
            ];
            
            // Limpiar el resultado removiendo mensajes informativos para análisis
            $resultLimpio = $result;
            foreach ($mensajesInformativos as $msgInfo) {
                $resultLimpio = str_replace($msgInfo, '', $resultLimpio);
            }
            $resultLimpio = preg_replace('/\n\s*\n/', "\n", $resultLimpio); // Limpiar líneas vacías múltiples
            $resultLimpio = trim($resultLimpio);
            
            // Verificar errores REALES de SQL Server (Msg con Level > 10 generalmente es error)
            if (preg_match('/Msg (\d+), Level (\d+), State \d+/', $result, $matches)) {
                $msgNumber = (int)$matches[1];
                $level = (int)$matches[2];
                // Niveles 11-25 son errores, niveles 0-10 son informativos
                if ($level >= 11) {
                    $tieneError = true;
                    // Extraer el mensaje de error específico
                    if (preg_match('/Msg \d+, Level \d+, State \d+[:\s]+(.*?)(?:\n|$)/s', $result, $errorMatches)) {
                        $mensajeError = trim($errorMatches[1] ?? '');
                    } else {
                        $mensajeError = "Error SQL Server (Msg {$msgNumber}, Level {$level})";
                    }
                }
            } elseif (str_contains($result, 'Cannot insert') || 
                     str_contains($result, 'violation') || 
                     str_contains($result, 'constraint') ||
                     str_contains($result, 'Permission denied') ||
                     str_contains($result, 'Invalid object name')) {
                $tieneError = true;
                $mensajeError = 'Error de SQL Server detectado';
            }
            
            // Si no hay error, considerar éxito (tsql normalmente no devuelve nada en caso de éxito)
            if (!$tieneError) {
                // Si el resultado limpio está vacío o solo tiene números (podría ser "(1 row affected)"), es éxito
                if (empty($resultLimpio) || 
                    preg_match('/^\s*(\d+\s+)?(row\s+affected|rows?\s+affected)?\s*$/i', $resultLimpio) ||
                    preg_match('/^\s*\(?\d+\s+row.*affected.*\)?\s*$/i', $resultLimpio)) {
                    Log::info("✅ MAEEDOOB insertado correctamente - IDMAEEDO: {$siguienteId}");
                } else {
                    // Caso ambiguo - loguear para revisar pero no marcar como error
                    Log::info("✅ MAEEDOOB insertado (resultado: " . substr($resultLimpio, 0, 100) . ") - IDMAEEDO: {$siguienteId}");
                    Log::debug("Resultado completo MAEEDOOB: " . substr($result, 0, 500));
                }
            } else {
                Log::error('❌ Error insertando MAEEDOOB: ' . $mensajeError);
                Log::error('Resultado completo: ' . substr($result, 0, 1000));
                Log::error('SQL ejecutado: ' . $insertMAEEDOOB);
                // No lanzar excepción para no detener el proceso completo, pero loguear el error claramente
            }
            
            // INSERT MAEDTLI - Solo para productos CON descuento
            $productosConDescuento = 0;
            foreach ($cotizacion->productos as $index => $producto) {
                $porcentajeDescuento = $producto->descuento_porcentaje ?? 0;
                if ($porcentajeDescuento > 0) {
                    $productosConDescuento++;
                    $lineaId = $index + 1;
                    $nulidoFormateado = str_pad($lineaId, 5, '0', STR_PAD_LEFT);
                    $valorDescuento = $producto->descuento_valor ?? 0;
                    
                    $insertMAEDTLI = "
                        INSERT INTO MAEDTLI (
                            IDMAEEDO, NULIDO, KODT, PODT, VADT
                ) VALUES (
                            {$siguienteId}, '{$nulidoFormateado}', 'D_SIN_TIPO', {$porcentajeDescuento}, {$valorDescuento}
                )
            ";
                    
                    Log::info("SQL INSERT MAEDTLI línea {$lineaId} (producto con descuento):");
                    Log::info($insertMAEDTLI);
            
            $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
                    file_put_contents($tempFile, $insertMAEDTLI . "\ngo\nquit");
            
            $command = "tsql -H " . env('SQLSRV_EXTERNAL_HOST') . " -p " . env('SQLSRV_EXTERNAL_PORT') . " -U " . env('SQLSRV_EXTERNAL_USERNAME') . " -P " . env('SQLSRV_EXTERNAL_PASSWORD') . " -D " . env('SQLSRV_EXTERNAL_DATABASE') . " < {$tempFile} 2>&1";
            $result = shell_exec($command);
            
            unlink($tempFile);
            
                    if (str_contains($result, 'Msg') || str_contains($result, 'Error')) {
                        Log::warning('Error insertando MAEDTLI línea ' . $lineaId . ': ' . $result);
            } else {
                        Log::info("✅ MAEDTLI insertado correctamente para producto con descuento {$lineaId}");
                    }
                }
            }
            
            if ($productosConDescuento > 0) {
                Log::info("✅ {$productosConDescuento} productos con descuento insertados en MAEDTLI");
            } else {
                Log::info("⏭️ No hay productos con descuento - NO se inserta en MAEDTLI");
            }
            
            // Verificar que la NVV realmente se insertó en SQL Server
            $queryVerificacion = "SELECT COUNT(*) as total FROM MAEEDO WHERE IDMAEEDO = {$siguienteId} AND EMPRESA = '01' AND TIDO = 'NVV'";
                
                $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
            file_put_contents($tempFile, $queryVerificacion . "\ngo\nquit");
            
            $command = "tsql -H " . env('SQLSRV_EXTERNAL_HOST') . " -p " . env('SQLSRV_EXTERNAL_PORT') . " -U " . env('SQLSRV_EXTERNAL_USERNAME') . " -P " . env('SQLSRV_EXTERNAL_PASSWORD') . " -D " . env('SQLSRV_EXTERNAL_DATABASE') . " < {$tempFile} 2>&1";
            $resultVerificacion = shell_exec($command);
            
            unlink($tempFile);
            
            // Verificar si se encontró el registro
            $insertado = false;
            if ($resultVerificacion) {
                $lines = explode("\n", $resultVerificacion);
                foreach ($lines as $line) {
                    if (trim($line) === '1') {
                        $insertado = true;
                        break;
                    }
                }
            }
            
            if (!$insertado) {
                Log::error("NVV {$siguienteId} no se encontró en SQL Server después del insert");
                throw new \Exception("No se pudo verificar que la NVV fue insertada correctamente en SQL Server");
            }
            
            Log::info("NVV {$siguienteId} verificada exitosamente en SQL Server");
            
            // Guardar el número correlativo (NUDO) en la cotización
            $cotizacion->numero_nvv = $nudoFormateado;
            $cotizacion->save();
            
            Log::info("✅ Número NVV guardado en cotización: {$nudoFormateado}");
            
            return [
                'success' => true,
                'nota_venta_id' => $siguienteId,
                'numero_correlativo' => $nudoFormateado,
                'message' => "NVV #{$nudoFormateado} (ID: {$siguienteId}) insertada y verificada correctamente en SQL Server"
            ];
            
        } catch (\Exception $e) {
            Log::error('Error en insertarEnSQLServer: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * FUNCIÓN DE PRUEBA: Insertar con NUDO fijo para debugging
     */
    private function insertarEnSQLServerTest($cotizacion)
    {
        try {
            Log::info("🧪 === INICIO FUNCIÓN DE PRUEBA ===");
            
            // NUDO FIJO PARA PRUEBAS
            $nudoFormateado = '9999999991';
            Log::info("🧪 NUDO FIJO: {$nudoFormateado}");
            
            // Obtener siguiente correlativo para IDMAEEDO
            $queryCorrelativo = "SELECT TOP 1 ISNULL(MAX(IDMAEEDO), 0) + 1 AS siguiente_id FROM MAEEDO WHERE EMPRESA = '01'";
            
            $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
            file_put_contents($tempFile, $queryCorrelativo . "\ngo\nquit");
                
                $command = "tsql -H " . env('SQLSRV_EXTERNAL_HOST') . " -p " . env('SQLSRV_EXTERNAL_PORT') . " -U " . env('SQLSRV_EXTERNAL_USERNAME') . " -P " . env('SQLSRV_EXTERNAL_PASSWORD') . " -D " . env('SQLSRV_EXTERNAL_DATABASE') . " < {$tempFile} 2>&1";
                $result = shell_exec($command);
            unlink($tempFile);
            
            // Parsear el resultado
            $siguienteId = 1;
            if ($result && !str_contains($result, 'error')) {
                if (preg_match('/siguiente_id\s*\n\s*(\d+)/', $result, $matches)) {
                    $siguienteId = (int)$matches[1];
                } else {
                    $lines = explode("\n", $result);
                    $maxNumber = 0;
                    foreach ($lines as $line) {
                        $line = trim($line);
                        if (is_numeric($line) && (int)$line > $maxNumber && (int)$line > 1000) {
                            $maxNumber = (int)$line;
                        }
                    }
                    if ($maxNumber > 0) {
                        $siguienteId = $maxNumber;
                    }
                }
            }
            
            Log::info("🧪 Siguiente ID para MAEEDO: {$siguienteId}");
            
            // Obtener información del vendedor
            $codigoVendedor = $cotizacion->user->codigo_vendedor ?? '001';
            $nombreVendedor = $cotizacion->user->name ?? 'Vendedor Sistema';
            
            // Obtener sucursal del cliente
            $querySucursal = "SELECT LTRIM(RTRIM(SUEN)) as SUCURSAL FROM MAEEN WHERE KOEN = '{$cotizacion->cliente_codigo}'";
            $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
            file_put_contents($tempFile, $querySucursal . "\ngo\nquit");
            
            $command = "tsql -H " . env('SQLSRV_EXTERNAL_HOST') . " -p " . env('SQLSRV_EXTERNAL_PORT') . " -U " . env('SQLSRV_EXTERNAL_USERNAME') . " -P " . env('SQLSRV_EXTERNAL_PASSWORD') . " -D " . env('SQLSRV_EXTERNAL_DATABASE') . " < {$tempFile} 2>&1";
            $result = shell_exec($command);
                unlink($tempFile);
                
            $sucursalCliente = '';
            if ($result && !str_contains($result, 'error')) {
                $lines = explode("\n", $result);
                $foundHeader = false;
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === 'SUCURSAL') {
                        $foundHeader = true;
                        continue;
                    }
                    if ($foundHeader && !empty($line) && !str_contains($line, 'row') && !str_contains($line, '---') && !str_contains($line, '>')) {
                        $sucursalCliente = $line;
                        break;
                    }
                }
            }
            
            // Si sucursal está vacía, usar valor por defecto
            if (empty($sucursalCliente)) {
                $sucursalCliente = '1';
                Log::info("🧪 Sucursal vacía, usando valor por defecto: '1'");
            }
            
            Log::info("🧪 Sucursal Cliente: '{$sucursalCliente}'");
            Log::info("🧪 Vendedor: {$codigoVendedor}");
            
            // Obtener días de pago del cliente (DIPRVE)
            $diasPago = $this->obtenerDiasPagoCliente($cotizacion->cliente_codigo);
            Log::info("🧪 Días de pago del cliente (DIPRVE): {$diasPago}");
            
            // Calcular fecha de vencimiento: fecha de emisión + días de pago del cliente
            // Si días de pago es 0, usar la fecha de creación (sin agregar días)
            $fechaEmision = now();
            if ($diasPago > 0) {
                $fechaVencimiento = $fechaEmision->copy()->addDays($diasPago)->format('Y-m-d');
            } else {
                // Si es 0, usar la fecha de creación (misma fecha de emisión)
                $fechaVencimiento = $fechaEmision->format('Y-m-d');
            }
            Log::info("🧪 Fecha de vencimiento calculada: {$fechaVencimiento} (días de pago: {$diasPago})");
            
            // Calcular HORAGRAB (función de Excel: convertir fecha/hora a número serial)
            $diasDesde1900 = $fechaEmision->diffInDays('1900-01-01') + 2; // +2 por bug de Excel (año 1900 bisiesto)
            $horaDecimal = ($fechaEmision->hour * 3600 + $fechaEmision->minute * 60 + $fechaEmision->second) / 86400;
            $horagrab = $diasDesde1900 + $horaDecimal;
            
            // INSERT SIMPLIFICADO DE MAEEDO
            $insertMAEEDO = "
                SET IDENTITY_INSERT MAEEDO ON
                
                INSERT INTO MAEEDO (
                    IDMAEEDO, EMPRESA, TIDO, NUDO, ENDO, SUENDO, ENDOFI, SUDO,
                    TIGEDO, LUVTDO, MEARDO, ESPGDO,
                    FEEMDO, FE01VEDO, FEULVEDO, FEER,
                    CAPRCO, CAPRAD, CAPREX, CAPRNC,
                    MODO, TIMODO, TAMODO,
                    VAIVDO, VANEDO, VABRDO, VAABDO,
                    ESDO, KOFUDO, KOTU, LAHORA, DESPACHO, HORAGRAB,
                    CUOGASDIF, BODESTI, PROYECTO, FLIQUIFCV, LISACTIVA
                ) VALUES (
                    {$siguienteId}, '01', 'NVV', '{$nudoFormateado}', '{$cotizacion->cliente_codigo}', 
                    '{$sucursalCliente}', '', 'LIB',
                    'I', '', 'N', 'S',
                    CONVERT(DATETIME, CONVERT(DATE, GETDATE())), CONVERT(DATETIME, '{$fechaVencimiento}'), CONVERT(DATETIME, '{$fechaVencimiento}'), CONVERT(DATETIME, CONVERT(DATE, GETDATE())),
                    {$cotizacion->subtotal_neto}, 0, 0, 0,
                    '$', 'N', 1,
                    {$cotizacion->iva}, {$cotizacion->subtotal_neto}, {$cotizacion->total}, 0,
                    '', '{$codigoVendedor}', 1, CONVERT(DATETIME, CONVERT(DATE, GETDATE())), 1, {$horagrab},
                    0, '', '', CONVERT(DATETIME, CONVERT(DATE, GETDATE())), 'TABPP01P'
                )
                
                SET IDENTITY_INSERT MAEEDO OFF
            ";
            
            Log::info("🧪 Ejecutando INSERT MAEEDO...");
            Log::info("🧪 SQL COMPLETO:");
            Log::info($insertMAEEDO);
                
                $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
            file_put_contents($tempFile, $insertMAEEDO . "\ngo\nquit");
                
                $command = "tsql -H " . env('SQLSRV_EXTERNAL_HOST') . " -p " . env('SQLSRV_EXTERNAL_PORT') . " -U " . env('SQLSRV_EXTERNAL_USERNAME') . " -P " . env('SQLSRV_EXTERNAL_PASSWORD') . " -D " . env('SQLSRV_EXTERNAL_DATABASE') . " < {$tempFile} 2>&1";
                $result = shell_exec($command);
            unlink($tempFile);
            
            if (str_contains($result, 'Msg') || str_contains($result, 'Error')) {
                Log::error("🧪 ERROR en MAEEDO: " . $result);
                throw new \Exception('Error insertando encabezado: ' . $result);
            }
            
            Log::info("🧪 ✅ MAEEDO insertado correctamente");
            
            // INSERT SIMPLIFICADO DE MAEDDO (solo primer producto para prueba)
            $producto = $cotizacion->productos->first();
            if ($producto) {
                $productoDB = \App\Models\Producto::where('KOPR', $producto->codigo_producto)->first();
                
                $udtrpr = 1;
                $rludpr = 1;
                $ud01pr = 'UN';
                $ud02pr = 'UN';
                
                if ($productoDB) {
                    $rludpr = $productoDB->RLUD ?? 1;
                    $ud01pr = trim($productoDB->UD01PR ?? 'UN');
                    // UD02PR: usar el valor de la BD si existe y no está vacío, sino 'UN' por defecto
                    $ud02prAux = trim($productoDB->UD02PR ?? '');
                    $ud02pr = !empty($ud02prAux) ? $ud02prAux : 'UN';
                    $udtrpr = ($rludpr > 1) ? 2 : 1;
                }
                
                $subtotal = $producto->cantidad * $producto->precio_unitario;
                $nombreProductoNVV = substr(str_replace("'", "''", \App\Helpers\ProductoHelper::limpiarNombreParaNVV($producto->nombre_producto ?? '')), 0, 50);

                $insertMAEDDO = "
                    INSERT INTO MAEDDO (
                        IDMAEEDO, EMPRESA, TIDO, NUDO, ENDO, SUENDO,
                        LILG, NULIDO, SULIDO, BOSULIDO, LUVTLIDO, KOFULIDO, TIPR,
                        UDTRPR, RLUDPR, UD01PR, UD02PR,
                        KOPRCT, NOKOPR, CAPRCO1, CAPRCO2,
                        KOLTPR, MOPPPR, TIMOPPPR, TAMOPPPR,
                        PPPRNE, PPPRNELT, PPPRBR, PPPRBRLT,
                        PODTGLLI, VADTNELI, VANELI, POIVLI, VAIVLI, VABRLI,
                        TIGELI, FEEMLI, FEERLI, NUDTLI, ARCHIRST, IDRST,
                        PPPRPM, PPPRNERE1, PPPRNERE2, TASADORIG, CUOGASDIF, PROYECTO,
                        POTENCIA, HUMEDAD, IDTABITPRE, FEERLIMODI
                    ) VALUES (
                        {$siguienteId}, '01', 'NVV', '{$nudoFormateado}',
                        '{$cotizacion->cliente_codigo}', '{$sucursalCliente}',
                        'SI', '{$lineaId}', 'LIB', 'LIB', '', '{$codigoVendedor}', 'FPN',
                        {$udtrpr}, {$rludpr}, '{$ud01pr}', '{$ud02pr}',
                        '{$producto->codigo_producto}', '{$nombreProductoNVV}',
                        {$producto->cantidad}, {$producto->cantidad},
                        'TABPP01P', '$', 'N', 1,
                        {$producto->precio_unitario}, {$producto->precio_unitario}, {$precioBruto}, {$precioBruto},
                        {$porcentajeDescuento}, {$valorDescuento}, {$subtotalConDescuento}, 19, {$ivaConDescuento}, {$totalConIVA},
                        'I', CONVERT(DATETIME, CONVERT(DATE, GETDATE())), CONVERT(DATETIME, CONVERT(DATE, GETDATE())), 1, '', 0,
                        {$precioMinimo}, {$precioNetoReal}, {$precioNetoReal}, 1, 0, 0,
                        0, 0, 0, CONVERT(DATETIME, CONVERT(DATE, GETDATE()))
                    )
                ";
                
                Log::info("🧪 Ejecutando INSERT MAEDDO...");
                
                $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
                file_put_contents($tempFile, $insertMAEDDO . "\ngo\nquit");
                
                $command = "tsql -H " . env('SQLSRV_EXTERNAL_HOST') . " -p " . env('SQLSRV_EXTERNAL_PORT') . " -U " . env('SQLSRV_EXTERNAL_USERNAME') . " -P " . env('SQLSRV_EXTERNAL_PASSWORD') . " -D " . env('SQLSRV_EXTERNAL_DATABASE') . " < {$tempFile} 2>&1";
                $result = shell_exec($command);
                unlink($tempFile);
                
                if (str_contains($result, 'Msg') || str_contains($result, 'Error')) {
                    Log::error("🧪 ERROR en MAEDDO: " . $result);
                    throw new \Exception('Error insertando detalle: ' . $result);
                }
                
                Log::info("🧪 ✅ MAEDDO insertado correctamente");
            }
            
            // Guardar el NUDO en MySQL
            $cotizacion->numero_nvv = $nudoFormateado;
            $cotizacion->save();
            
            Log::info("🧪 === FIN FUNCIÓN DE PRUEBA - ÉXITO ===");
            
            return [
                'success' => true,
                'nota_venta_id' => $siguienteId,
                'numero_correlativo' => $nudoFormateado,
                'message' => "🧪 NVV DE PRUEBA #{$nudoFormateado} insertada correctamente"
            ];
            
        } catch (\Exception $e) {
            Log::error('🧪 ERROR en insertarEnSQLServerTest: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtener lista de precios del cliente desde MySQL
     */
    private function obtenerListaPreciosCliente($codigoCliente)
    {
        try {
            $cliente = \App\Models\Cliente::where('codigo_cliente', $codigoCliente)->first();
            if ($cliente && $cliente->lista_precios) {
                return substr($cliente->lista_precios, 0, 8);
            }
            return 'TABPP01P';
        } catch (\Exception $e) {
            Log::warning("Error obteniendo lista de precios: " . $e->getMessage());
            return 'TABPP01P';
        }
    }

    /**
     * Obtener días de pago del cliente desde SQL Server
     */
    private function obtenerDiasPagoCliente($codigoCliente)
    {
        try {
            $query = "SELECT ISNULL(DIPRVE, 0) as DIPRVE FROM MAEEN WHERE KOEN = '{$codigoCliente}'";
            $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
            file_put_contents($tempFile, $query . "\ngo\nquit");
            
            $command = "tsql -H " . env('SQLSRV_EXTERNAL_HOST') . " -p " . env('SQLSRV_EXTERNAL_PORT') . " -U " . env('SQLSRV_EXTERNAL_USERNAME') . " -P " . env('SQLSRV_EXTERNAL_PASSWORD') . " -D " . env('SQLSRV_EXTERNAL_DATABASE') . " < {$tempFile} 2>&1";
            $result = shell_exec($command);
            unlink($tempFile);
            
            if ($result && !str_contains($result, 'error')) {
                $lines = explode("\n", $result);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (is_numeric($line)) {
                        return (int)$line;
                    }
                }
            }
            return 0;
        } catch (\Exception $e) {
            Log::warning("Error obteniendo días de pago: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Obtener condición de pago del cliente desde SQL Server
     */
    private function obtenerCondicionPagoCliente($codigoCliente)
    {
        try {
            $query = "SELECT ISNULL(CPEN, '') as CPEN FROM MAEEN WHERE KOEN = '{$codigoCliente}'";
            $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
            file_put_contents($tempFile, $query . "\ngo\nquit");
            
            $command = "tsql -H " . env('SQLSRV_EXTERNAL_HOST') . " -p " . env('SQLSRV_EXTERNAL_PORT') . " -U " . env('SQLSRV_EXTERNAL_USERNAME') . " -P " . env('SQLSRV_EXTERNAL_PASSWORD') . " -D " . env('SQLSRV_EXTERNAL_DATABASE') . " < {$tempFile} 2>&1";
            $result = shell_exec($command);
            unlink($tempFile);
            
            if ($result && !str_contains($result, 'error')) {
                $lines = explode("\n", $result);
                $foundHeader = false;
                foreach ($lines as $line) {
                    $line = trim($line);
                    
                    // Saltar líneas de configuración
                    if (str_contains($line, 'Setting') || str_contains($line, 'locale')) {
                        continue;
                    }
                    
                    // Buscar el header CPEN (puede estar en línea con números como '1> 2> CPEN')
                    if (str_contains($line, 'CPEN')) {
                        $foundHeader = true;
                        continue;
                    }
                    
                    // Después del header, la siguiente línea con datos es el valor
                    if ($foundHeader && !empty($line) && !str_contains($line, 'row') && !str_contains($line, '---') && !str_contains($line, 'affected')) {
                        Log::info("Condición de pago encontrada para cliente {$codigoCliente}: '{$line}'");
                        return $line;
                    }
                }
            }
            
            Log::warning("No se encontró condición de pago para cliente {$codigoCliente}");
            return '';
        } catch (\Exception $e) {
            Log::warning("Error obteniendo condición de pago: " . $e->getMessage());
            return '';
        }
    }

    /**
     * Obtener NUVECR del cliente desde SQL Server
     */
    private function obtenerNuevecrCliente($codigoCliente)
    {
        try {
            $query = "SELECT ISNULL(NUVECR, 0) as NUVECR FROM MAEEN WHERE KOEN = '{$codigoCliente}'";
            $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
            file_put_contents($tempFile, $query . "\ngo\nquit");
            
            $command = "tsql -H " . env('SQLSRV_EXTERNAL_HOST') . " -p " . env('SQLSRV_EXTERNAL_PORT') . " -U " . env('SQLSRV_EXTERNAL_USERNAME') . " -P " . env('SQLSRV_EXTERNAL_PASSWORD') . " -D " . env('SQLSRV_EXTERNAL_DATABASE') . " < {$tempFile} 2>&1";
            $result = shell_exec($command);
            unlink($tempFile);
            
            if ($result && !str_contains($result, 'error')) {
                $lines = explode("\n", $result);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (is_numeric($line)) {
                        return (float)$line;
                    }
                }
            }
            return 0;
        } catch (\Exception $e) {
            Log::warning("Error obteniendo NUVECR: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Rechazar nota de venta
     */
    public function rechazar(Request $request, $id)
    {
        $request->validate([
            'motivo' => 'required|string|max:500'
        ]);

        $cotizacion = Cotizacion::findOrFail($id);
        $user = Auth::user();
        $rol = $this->determinarRolUsuario($user);

        if (!$rol) {
            return response()->json(['error' => 'No tienes permisos para rechazar notas de venta'], 403);
        }

        try {
            DB::beginTransaction();

            // Guardar estado anterior para el historial ANTES de actualizar
            $estadoAnterior = $cotizacion->estado_aprobacion;

            // Rechazar la cotización
            $cotizacion->rechazar($user->id, $request->motivo, $rol);
            
            // Recargar el modelo para obtener el estado actualizado
            $cotizacion->refresh();

            // Liberar stock comprometido si existe
            $stockComprometidoService = new \App\Services\StockComprometidoService();
            $stockComprometidoService->liberarStock($cotizacion->id, "Rechazada por {$rol}: {$request->motivo}");

            // Registrar en el historial (pasar estado anterior antes de la actualización)
            \App\Services\HistorialCotizacionService::registrarRechazo(
                $cotizacion,
                $request->motivo,
                $estadoAnterior, // Estado anterior antes del rechazo
                $rol,
                "Rechazada por {$rol}: {$request->motivo}"
            );

            DB::commit();
            
            Log::info("Nota de venta {$cotizacion->id} rechazada por {$rol} {$user->id} - Motivo: {$request->motivo}");
            
            return response()->json([
                'success' => true,
                'message' => 'Nota de venta rechazada exitosamente',
                'estado_aprobacion' => $cotizacion->estado_aprobacion,
                'redirect' => route('aprobaciones.index')
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error rechazando nota de venta {$id}: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'error' => 'Error al rechazar la nota de venta: ' . $e->getMessage()
            ], 500);
        }
    }


    /**
     * Separar productos problemáticos en una nueva nota de venta
     */
    public function separarProductosStock(Request $request, $id)
    {
        $request->validate([
            'productos_problematicos' => 'required|array',
            'productos_problematicos.*' => 'integer|exists:cotizacion_productos,id'
        ]);

        $cotizacion = Cotizacion::findOrFail($id);
        $user = Auth::user();

        if (!$user->hasRole('Compras') && !$this->tieneRolPicking($user)) {
            return response()->json(['error' => 'No tienes permisos para separar productos'], 403);
        }

        try {
            $notaSeparada = $cotizacion->separarPorProblemasStock($request->productos_problematicos);
            
            Log::info("Productos separados de nota de venta {$cotizacion->id} a nueva nota {$notaSeparada->id}");
            
            return response()->json([
                'success' => true,
                'message' => 'Productos separados en nueva nota de venta',
                'nota_separada_id' => $notaSeparada->id,
                'nota_original_id' => $cotizacion->id
            ]);
        } catch (\Exception $e) {
            Log::error("Error separando productos: " . $e->getMessage());
            return response()->json(['error' => 'Error al separar los productos'], 500);
        }
    }

    /**
     * Validar stock real de los productos
     */
    private function validarStockReal($cotizacion)
    {
        $productosSinStock = [];
        $stockValido = true;

        foreach ($cotizacion->productos as $producto) {
            // Aquí se implementaría la validación real contra SQL Server
            // Por ahora usamos el stock disponible en la base local
            if ($producto->cantidad > $producto->stock_disponible) {
                $stockValido = false;
                $productosSinStock[] = [
                    'codigo' => $producto->codigo_producto,
                    'nombre' => $producto->nombre_producto,
                    'cantidad_solicitada' => $producto->cantidad,
                    'stock_disponible' => $producto->stock_disponible,
                    'diferencia' => $producto->cantidad - $producto->stock_disponible
                ];
            }
        }

        return [
            'valido' => $stockValido,
            'detalle' => $productosSinStock
        ];
    }

    /**
     * Determinar el rol del usuario para las aprobaciones
     */
    private function determinarRolUsuario($user)
    {
        if ($user->hasRole('Supervisor')) return 'supervisor';
        if ($user->hasRole('Compras')) return 'compras';
        if ($user->hasRole('Picking') || $user->hasRole('Picking Operativo')) return 'picking';
        
        return null;
    }

    /**
     * Verificar si el usuario tiene rol Picking o Picking Operativo
     */
    private function tieneRolPicking($user)
    {
        return $user->hasRole('Picking') || $user->hasRole('Picking Operativo');
    }

    /**
     * Verificar si el usuario puede aprobar (solo Picking, no Picking Operativo)
     */
    private function puedeAprobarPicking($user)
    {
        return $user->hasRole('Picking');
    }

    /**
     * Limpiar nombre del producto removiendo información adicional como "Múltiplo: X" o "adicional"
     */
    private function limpiarNombreProducto($nombreProducto)
    {
        if (empty($nombreProducto)) {
            return $nombreProducto;
        }
        return \App\Helpers\ProductoHelper::limpiarNombreParaNVV($nombreProducto);
    }

    /**
     * Vista detallada de una nota de venta para aprobación
     */
    public function show($id)
    {
        $cotizacion = Cotizacion::with(['user', 'productos', 'aprobadoPorSupervisor', 'aprobadoPorCompras', 'aprobadoPorPicking'])
            ->findOrFail($id);
        
        $user = Auth::user();
        $puedeAprobar = false;
        $tipoAprobacion = '';

        if ($user->hasRole('Supervisor') && $cotizacion->puedeAprobarSupervisor()) {
            $puedeAprobar = true;
            $tipoAprobacion = 'supervisor';
        } elseif ($user->hasRole('Compras') && $cotizacion->puedeAprobarCompras()) {
            $puedeAprobar = true;
            $tipoAprobacion = 'compras';
        } elseif ($this->puedeAprobarPicking($user) && ($cotizacion->puedeAprobarPicking() || $cotizacion->estado_aprobacion === 'pendiente_picking')) {
            // Solo Picking puede aprobar, Picking Operativo NO puede aprobar
            $puedeAprobar = true;
            $tipoAprobacion = 'picking';
        }

        // Obtener historial completo
        $historial = \App\Models\CotizacionHistorial::obtenerHistorialCompleto($id);
        
        // Obtener resumen de tiempos
        $resumenTiempos = \App\Services\HistorialCotizacionService::obtenerResumenTiempos($cotizacion);

        return view('aprobaciones.show', compact('cotizacion', 'puedeAprobar', 'tipoAprobacion', 'historial', 'resumenTiempos'));
    }

    /**
     * Mostrar historial completo de una cotización
     */
    public function historial($id)
    {
        $user = Auth::user();
        
        $cotizacion = Cotizacion::with(['user', 'productos'])->findOrFail($id);
        
        // Verificar permisos
        $puedeAcceder = false;
        
        // Super Admin, Supervisor, Compras, Picking y Picking Operativo siempre pueden acceder
        if ($user->hasRole('Super Admin') || $user->hasRole('Supervisor') || 
            $user->hasRole('Compras') || $this->tieneRolPicking($user)) {
            $puedeAcceder = true;
        }
        // Vendedores solo pueden ver sus propias cotizaciones
        elseif ($user->hasRole('Vendedor') && $cotizacion->user_id == $user->id) {
            $puedeAcceder = true;
        }
        
        if (!$puedeAcceder) {
            return redirect()->route('dashboard')->with('error', 'Acceso no autorizado');
        }
        
        // Obtener historial completo
        $historial = \App\Models\CotizacionHistorial::obtenerHistorialCompleto($id);
        
        // Obtener resumen de tiempos (crear uno básico si el servicio no existe)
        try {
            $resumenTiempos = \App\Services\HistorialCotizacionService::obtenerResumenTiempos($cotizacion);
        } catch (\Exception $e) {
            // Crear resumen básico si el servicio no existe
            $resumenTiempos = $this->crearResumenTiemposBasico($cotizacion);
        }

        return view('aprobaciones.historial', compact('cotizacion', 'historial', 'resumenTiempos'))
            ->with('pageSlug', 'aprobaciones');
    }

    /**
     * Separar producto con problemas de stock en una NVV duplicada
     */
    public function separarPorStock(Request $request, $id)
    {
        $user = Auth::user();
        
        // Verificar permisos - solo Cobranza puede separar por stock
        if (!$user->hasRole('Cobranza') && !$user->hasRole('Super Admin')) {
            return redirect()->route('aprobaciones.show', $id)
                ->with('error', 'No tienes permisos para realizar esta acción');
        }

        $request->validate([
            'producto_id' => 'required|integer|exists:cotizacion_productos,id',
            'motivo' => 'required|string|max:500'
        ]);

        try {
            $cotizacion = Cotizacion::with(['productos', 'user'])->findOrFail($id);
            
            // Buscar el producto específico
            $producto = $cotizacion->productos()->findOrFail($request->producto_id);
            
            // Verificar que el producto tenga problemas de stock
            if ($producto->stock_disponible >= $producto->cantidad) {
                return redirect()->route('aprobaciones.show', $id)
                    ->with('error', 'Este producto no tiene problemas de stock');
            }

            // Crear la nueva NVV duplicada con solo el producto problemático
            $nuevaCotizacion = $this->crearNvvDuplicada($cotizacion, $producto, $request->motivo);
            
            // Eliminar el producto de la NVV original
            $producto->delete();
            
            // Actualizar totales de la NVV original
            $this->actualizarTotalesCotizacion($cotizacion);
            
            // Registrar en el historial
            $this->registrarSeparacionStock($cotizacion, $nuevaCotizacion, $producto, $request->motivo, $user);
            
            // Enviar notificación al vendedor
            $this->enviarNotificacionSeparacion($cotizacion, $nuevaCotizacion, $producto, $user);
            
            Log::info("Producto separado por stock", [
                'cotizacion_original' => $cotizacion->id,
                'cotizacion_nueva' => $nuevaCotizacion->id,
                'producto' => $producto->producto_codigo,
                'usuario' => $user->name
            ]);

            return redirect()->route('aprobaciones.show', $id)
                ->with('success', "Producto '{$producto->producto_nombre}' separado exitosamente. Nueva NVV #{$nuevaCotizacion->id} creada para el producto con problemas de stock.");

        } catch (\Exception $e) {
            Log::error("Error al separar producto por stock: " . $e->getMessage(), [
                'cotizacion_id' => $id,
                'producto_id' => $request->producto_id,
                'usuario' => $user->name
            ]);

            return redirect()->route('aprobaciones.show', $id)
                ->with('error', 'Error al separar el producto: ' . $e->getMessage());
        }
    }

    /**
     * Crear una nueva NVV duplicada con solo el producto problemático
     */
    private function crearNvvDuplicada($cotizacionOriginal, $producto, $motivo)
    {
        // Determinar estado de aprobación según si la NVV original ya fue aprobada
        $estadoAprobacionOriginal = $cotizacionOriginal->estado_aprobacion;
        $heredarAprobaciones = in_array($estadoAprobacionOriginal, [
            'aprobada_supervisor',
            'aprobada_compras',
            'aprobada_picking',
            'pendiente_picking'
        ]);
        
        // Crear nueva cotización
        $nuevaCotizacion = $cotizacionOriginal->replicate();
        $nuevaCotizacion->estado = 'pendiente_stock';
        
        // Si la NVV original ya fue aprobada, heredar su estado
        if ($heredarAprobaciones) {
            $nuevaCotizacion->estado_aprobacion = $estadoAprobacionOriginal;
            $nuevaCotizacion->tiene_problemas_stock = true;
            // Las NVVs separadas NUNCA deben tener problemas de crédito, incluso si heredan aprobaciones
            // (heredan aprobaciones pero no problemas de crédito porque se separan solo por stock)
            $nuevaCotizacion->tiene_problemas_credito = false;
            // NO resetear aprobaciones, mantener las de la original (el replicate ya las copió)
        } else {
            // Si está pendiente, validar crédito del cliente para establecer tiene_problemas_credito
            // Calcular total del producto separado para validar crédito
            $precioBase = $producto->precio_unitario * $producto->cantidad;
            $descuentoPorcentaje = $producto->descuento_porcentaje ?? 0;
            $descuentoValor = $precioBase * ($descuentoPorcentaje / 100);
            $subtotalConDescuento = $precioBase - $descuentoValor;
            $ivaValor = $subtotalConDescuento * 0.19;
            $totalProducto = $subtotalConDescuento + $ivaValor;
            
            // Validar cliente para determinar si tiene problemas de crédito
            $validacionCliente = ClienteValidacionService::validarClienteParaNotaVenta(
                $cotizacionOriginal->cliente_codigo,
                $totalProducto
            );
            $tieneProblemasCredito = $validacionCliente['requiere_autorizacion'] ?? false;
            
            // Si está pendiente, iniciar como pendiente
            $nuevaCotizacion->estado_aprobacion = 'pendiente';
            $nuevaCotizacion->tiene_problemas_stock = true;
            $nuevaCotizacion->tiene_problemas_credito = $tieneProblemasCredito;
            
            // Resetear aprobaciones ya que está pendiente
            $nuevaCotizacion->aprobado_por_supervisor = null;
            $nuevaCotizacion->fecha_aprobacion_supervisor = null;
            $nuevaCotizacion->comentarios_supervisor = null;
            $nuevaCotizacion->aprobado_por_compras = null;
            $nuevaCotizacion->fecha_aprobacion_compras = null;
            $nuevaCotizacion->comentarios_compras = null;
            $nuevaCotizacion->aprobado_por_picking = null;
            $nuevaCotizacion->fecha_aprobacion_picking = null;
            $nuevaCotizacion->comentarios_picking = null;
        }
        
        $nuevaCotizacion->fecha_creacion = now();
        $nuevaCotizacion->fecha_modificacion = now();
        $nuevaCotizacion->comentarios = "NVV separada por problemas de stock del producto: {$producto->producto_nombre}. Motivo: {$motivo}";
        $nuevaCotizacion->nota_original_id = $cotizacionOriginal->id; // Referencia a la NVV original/padre
        $nuevaCotizacion->save();

        // Duplicar el producto problemático
        $nuevoProducto = $producto->replicate();
        $nuevoProducto->cotizacion_id = $nuevaCotizacion->id;
        $nuevoProducto->save();

        // Calcular totales de la nueva cotización
        $this->actualizarTotalesCotizacion($nuevaCotizacion);

        return $nuevaCotizacion;
    }

    /**
     * Actualizar totales de una cotización
     */
    private function actualizarTotalesCotizacion($cotizacion)
    {
        $productos = $cotizacion->fresh()->productos; // Recargar relación para asegurar datos actualizados
        
        // Calcular subtotal sin descuentos (precio base * cantidad)
        $subtotal = $productos->sum(function($producto) {
            return $producto->precio_unitario * $producto->cantidad;
        });
        
        // Calcular descuento total (suma de descuento_valor de cada producto)
        $descuentoGlobal = $productos->sum(function($producto) {
            return floatval($producto->descuento_valor ?? 0);
        });
        
        // Calcular subtotal neto (suma de subtotal_con_descuento de cada producto)
        $subtotalNeto = $productos->sum(function($producto) {
            // Si existe subtotal_con_descuento usarlo, sino calcularlo
            if (isset($producto->subtotal_con_descuento) && $producto->subtotal_con_descuento > 0) {
                return floatval($producto->subtotal_con_descuento);
            }
            // Calcular: precio * cantidad - descuento
            $subtotalBruto = $producto->precio_unitario * $producto->cantidad;
            $descuentoValor = floatval($producto->descuento_valor ?? 0);
            return $subtotalBruto - $descuentoValor;
        });
        
        // Calcular IVA total (suma de iva_valor de cada producto)
        $iva = $productos->sum(function($producto) {
            // Si existe iva_valor usarlo, sino calcularlo
            if (isset($producto->iva_valor) && $producto->iva_valor > 0) {
                return floatval($producto->iva_valor);
            }
            // Calcular IVA sobre subtotal con descuento
            $subtotalBruto = $producto->precio_unitario * $producto->cantidad;
            $descuentoValor = floatval($producto->descuento_valor ?? 0);
            $subtotalConDescuento = $subtotalBruto - $descuentoValor;
            return $subtotalConDescuento * 0.19;
        });
        
        // Calcular total final (suma de total_producto de cada producto)
        $total = $productos->sum(function($producto) {
            // Si existe total_producto usarlo, sino calcularlo
            if (isset($producto->total_producto) && $producto->total_producto > 0) {
                return floatval($producto->total_producto);
            }
            // Calcular: subtotal con descuento + IVA
            $subtotalBruto = $producto->precio_unitario * $producto->cantidad;
            $descuentoValor = floatval($producto->descuento_valor ?? 0);
            $subtotalConDescuento = $subtotalBruto - $descuentoValor;
            $ivaProducto = $subtotalConDescuento * 0.19;
            return $subtotalConDescuento + $ivaProducto;
        });

        $cotizacion->update([
            'subtotal' => $subtotal,
            'descuento_global' => $descuentoGlobal,
            'subtotal_neto' => $subtotalNeto,
            'iva' => $iva,
            'total' => $total
        ]);
    }

    /**
     * Registrar la separación en el historial
     */
    private function registrarSeparacionStock($cotizacionOriginal, $cotizacionNueva, $producto, $motivo, $user)
    {
        // Historial para la cotización original
        \App\Models\CotizacionHistorial::create([
            'cotizacion_id' => $cotizacionOriginal->id,
            'usuario_id' => $user->id,
            'estado_anterior' => $cotizacionOriginal->estado_aprobacion,
            'estado_nuevo' => $cotizacionOriginal->estado_aprobacion,
            'fecha_cambio' => now(),
            'comentarios' => "Producto '{$producto->producto_nombre}' separado por problemas de stock. Nueva NVV #{$cotizacionNueva->id} creada.",
            'detalles_cambio' => json_encode([
                'accion' => 'separar_por_stock',
                'producto_codigo' => $producto->producto_codigo,
                'producto_nombre' => $producto->producto_nombre,
                'nueva_cotizacion_id' => $cotizacionNueva->id,
                'motivo' => $motivo
            ])
        ]);

        // Historial para la nueva cotización
        \App\Models\CotizacionHistorial::create([
            'cotizacion_id' => $cotizacionNueva->id,
            'usuario_id' => $user->id,
            'estado_anterior' => null,
            'estado_nuevo' => 'pendiente',
            'fecha_cambio' => now(),
            'comentarios' => "NVV creada por separación de stock del producto '{$producto->producto_nombre}'. NVV original: #{$cotizacionOriginal->id}",
            'detalles_cambio' => json_encode([
                'accion' => 'creada_por_separacion_stock',
                'cotizacion_original_id' => $cotizacionOriginal->id,
                'producto_codigo' => $producto->producto_codigo,
                'producto_nombre' => $producto->producto_nombre,
                'motivo' => $motivo
            ])
        ]);
    }

    /**
     * Enviar notificación al vendedor sobre la separación
     */
    private function enviarNotificacionSeparacion($cotizacionOriginal, $cotizacionNueva, $producto, $user)
    {
        try {
            // Crear notificación en la base de datos
            \App\Models\Notificacion::create([
                'usuario_id' => $cotizacionOriginal->user_id,
                'tipo' => 'separacion_stock',
                'titulo' => 'Producto Separado por Problemas de Stock',
                'mensaje' => "Se ha separado el producto '{$producto->producto_nombre}' de la NVV #{$cotizacionOriginal->id} por problemas de stock. Se ha creado una nueva NVV #{$cotizacionNueva->id} específicamente para este producto.",
                'datos_adicionales' => json_encode([
                    'cotizacion_original_id' => $cotizacionOriginal->id,
                    'cotizacion_nueva_id' => $cotizacionNueva->id,
                    'producto_codigo' => $producto->producto_codigo,
                    'producto_nombre' => $producto->producto_nombre,
                    'usuario_separacion' => $user->name
                ]),
                'leida' => false,
                'fecha_creacion' => now()
            ]);

            // Aquí podrías agregar envío de email si es necesario
            // Mail::to($cotizacionOriginal->user->email)->send(new SeparacionStockMail($cotizacionOriginal, $cotizacionNueva, $producto));

        } catch (\Exception $e) {
            Log::error("Error al enviar notificación de separación: " . $e->getMessage());
        }
    }

    /**
     * Crear resumen básico de tiempos para el historial
     */
    private function crearResumenTiemposBasico($cotizacion)
    {
        $fechaCreacion = \Carbon\Carbon::parse($cotizacion->fecha_creacion);
        $fechaActual = now();
        $tiempoTotal = $fechaCreacion->diffInHours($fechaActual);

        return [
            [
                'etapa' => 'Creación',
                'tiempo' => $fechaCreacion->format('d/m/Y H:i'),
                'descripcion' => 'Fecha de creación de la NVV',
                'color' => 'primary',
                'icono' => 'add_circle'
            ],
            [
                'etapa' => 'Tiempo Total',
                'tiempo' => $tiempoTotal . ' hrs',
                'descripcion' => 'Tiempo transcurrido desde la creación',
                'color' => 'info',
                'icono' => 'schedule'
            ],
            [
                'etapa' => 'Estado Actual',
                'tiempo' => ucfirst($cotizacion->estado_aprobacion ?? 'pendiente'),
                'descripcion' => 'Estado actual de la aprobación',
                'color' => $this->getColorEstado($cotizacion->estado_aprobacion ?? 'pendiente'),
                'icono' => $this->getIconoEstado($cotizacion->estado_aprobacion ?? 'pendiente')
            ],
            [
                'etapa' => 'Última Modificación',
                'tiempo' => $cotizacion->fecha_modificacion ? \Carbon\Carbon::parse($cotizacion->fecha_modificacion)->format('d/m/Y H:i') : 'N/A',
                'descripcion' => 'Última actualización de la NVV',
                'color' => 'warning',
                'icono' => 'update'
            ]
        ];
    }

    /**
     * Obtener color según el estado
     */
    private function getColorEstado($estado)
    {
        switch($estado) {
            case 'aprobada': return 'success';
            case 'rechazada': return 'danger';
            case 'pendiente': return 'warning';
            default: return 'secondary';
        }
    }

    /**
     * Obtener icono según el estado
     */
    private function getIconoEstado($estado)
    {
        switch($estado) {
            case 'aprobada': return 'check_circle';
            case 'rechazada': return 'cancel';
            case 'pendiente': return 'schedule';
            default: return 'help';
        }
    }

    /**
     * Separar productos múltiples con problemas de stock en una nueva NVV
     */
    public function separarProductos(Request $request, $id)
    {
        $request->validate([
            'productos_ids' => 'required|array|min:1',
            'productos_ids.*' => 'integer|exists:cotizacion_productos,id',
            'motivo' => 'required|string|max:500'
        ]);

        $cotizacion = Cotizacion::with(['productos', 'user'])->findOrFail($id);
        $user = Auth::user();

        // Verificar permisos - Compras, Picking y Picking Operativo pueden separar productos
        if (!$user->hasRole('Compras') && !$this->tieneRolPicking($user)) {
            return response()->json(['error' => 'No tienes permisos para realizar esta acción'], 403);
        }

        try {
            // Obtener los productos seleccionados
            $productosSeleccionados = $cotizacion->productos()->whereIn('id', $request->productos_ids)->get();
            
            if ($productosSeleccionados->isEmpty()) {
                return response()->json(['error' => 'No se encontraron productos válidos'], 400);
            }

            // Para los perfiles Compras, Picking y Picking Operativo, permitir separar cualquier producto
            // (pueden modificar cantidades después de la separación)
            if (!$user->hasRole('Compras') && !$this->tieneRolPicking($user)) {
                // Solo para otros roles, verificar problemas de stock
                $productosSinProblemas = $productosSeleccionados->filter(function($producto) {
                    return $producto->stock_disponible >= $producto->cantidad;
                });

                if ($productosSinProblemas->isNotEmpty()) {
                    $productos = $productosSinProblemas->pluck('nombre_producto')->implode(', ');
                    return response()->json(['error' => "Los siguientes productos no tienen problemas de stock: {$productos}"], 400);
                }
            }

            // Crear la nueva NVV duplicada con los productos seleccionados (usando cantidad_separar)
            $nuevaCotizacion = $this->crearNvvDuplicadaMultiple($cotizacion, $productosSeleccionados, $request->motivo, $user);
            
            // Manejar productos en la NVV original según cantidad_separar
            foreach ($productosSeleccionados as $producto) {
                $cantidadSeparar = $producto->cantidad_separar ?? $producto->cantidad;
                
                if ($cantidadSeparar >= $producto->cantidad) {
                    // Si se separa toda la cantidad, eliminar el producto de la NVV original
                    $producto->delete();
                } else {
                    // Si se separa parte, reducir la cantidad en la NVV original
                    $nuevaCantidad = $producto->cantidad - $cantidadSeparar;
                    $producto->update([
                        'cantidad' => $nuevaCantidad,
                        'cantidad_separar' => 0, // Resetear cantidad a separar
                        'subtotal' => $producto->precio_unitario * $nuevaCantidad
                    ]);
                    // Recalcular valores del producto
                    $subtotalBruto = $producto->precio_unitario * $nuevaCantidad;
                    $descuentoPorcentaje = $producto->descuento_porcentaje ?? 0;
                    $descuentoValor = $producto->descuento_valor ?? 0;
                    if ($descuentoPorcentaje > 0) {
                        $descuentoValor = $subtotalBruto * ($descuentoPorcentaje / 100);
                    } else {
                        // Proporcional al porcentaje de cantidad restante
                        $porcentajeCantidad = $nuevaCantidad / ($nuevaCantidad + $cantidadSeparar);
                        $descuentoValor = $descuentoValor * $porcentajeCantidad;
                    }
                    $subtotalConDescuento = $subtotalBruto - $descuentoValor;
                    $ivaValor = $subtotalConDescuento * 0.19;
                    $producto->update([
                        'descuento_valor' => $descuentoValor,
                        'subtotal_con_descuento' => $subtotalConDescuento,
                        'iva_valor' => $ivaValor,
                        'total_producto' => $subtotalConDescuento + $ivaValor
                    ]);
                }
            }
            
            // Actualizar totales de la NVV original (que mantiene los productos no seleccionados o con cantidad reducida)
            $this->actualizarTotalesCotizacion($cotizacion);
            
            // Registrar en el historial
            $this->registrarSeparacionProductos($cotizacion, $nuevaCotizacion, $productosSeleccionados, $request->motivo, $user);
            
            // Enviar notificación al vendedor
            $this->enviarNotificacionSeparacionMultiple($cotizacion, $nuevaCotizacion, $productosSeleccionados, $user);
            
            Log::info("Productos múltiples separados por stock", [
                'cotizacion_original' => $cotizacion->id,
                'cotizacion_nueva' => $nuevaCotizacion->id,
                'productos_count' => $productosSeleccionados->count(),
                'usuario' => $user->name
            ]);

            return response()->json([
                'success' => true,
                'message' => "Se han separado {$productosSeleccionados->count()} productos exitosamente. Nueva NVV #{$nuevaCotizacion->id} creada con los productos seleccionados.",
                'nota_separada_id' => $nuevaCotizacion->id,
                'nota_original_id' => $cotizacion->id,
                'productos_separados' => $productosSeleccionados->pluck('nombre_producto')->toArray()
            ]);

        } catch (\Exception $e) {
            Log::error("Error al separar productos múltiples: " . $e->getMessage(), [
                'cotizacion_id' => $id,
                'productos_ids' => $request->productos_ids,
                'usuario' => $user->name
            ]);

            return response()->json(['error' => 'Error al separar los productos: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Modificar cantidades de productos (solo para perfil Compras)
     */
    public function modificarCantidadesProductos(Request $request, $id)
    {
        $request->validate([
            'producto_id' => 'required|integer|exists:cotizacion_productos,id',
            'nueva_cantidad' => 'required|integer|min:1',
            'motivo' => 'nullable|string|max:500'
        ]);

        $cotizacion = Cotizacion::with(['productos', 'user'])->findOrFail($id);
        $user = Auth::user();

        // Verificar permisos - solo Compras puede modificar cantidades
        if (!$user->hasRole('Compras')) {
            return response()->json(['error' => 'No tienes permisos para realizar esta acción'], 403);
        }

        try {
            $producto = $cotizacion->productos()->findOrFail($request->producto_id);
            $cantidadAnterior = $producto->cantidad;
            $nuevaCantidad = $request->nueva_cantidad;

            // Actualizar la cantidad del producto
            $producto->update([
                'cantidad' => $nuevaCantidad,
                'subtotal' => $producto->precio_unitario * $nuevaCantidad
            ]);

            // Actualizar totales de la cotización
            $this->actualizarTotalesCotizacion($cotizacion);

            // Registrar en el historial
            CotizacionHistorial::registrarModificacionProductos(
                $cotizacion->id,
                [], // productos agregados
                [], // productos eliminados
                [[
                    'codigo' => $producto->codigo_producto,
                    'nombre' => $producto->nombre_producto,
                    'cantidad_anterior' => $cantidadAnterior,
                    'cantidad_nueva' => $nuevaCantidad
                ]], // productos modificados
                $request->motivo ?: "Cantidad modificada de {$cantidadAnterior} a {$nuevaCantidad} por perfil Compras"
            );

            Log::info("Cantidad de producto modificada por Compras", [
                'cotizacion_id' => $cotizacion->id,
                'producto_id' => $producto->id,
                'cantidad_anterior' => $cantidadAnterior,
                'cantidad_nueva' => $nuevaCantidad,
                'usuario' => $user->name
            ]);

            return response()->json([
                'success' => true,
                'message' => "Cantidad del producto '{$producto->nombre_producto}' modificada de {$cantidadAnterior} a {$nuevaCantidad}",
                'producto' => [
                    'id' => $producto->id,
                    'nombre' => $producto->nombre_producto,
                    'cantidad_anterior' => $cantidadAnterior,
                    'cantidad_nueva' => $nuevaCantidad,
                    'subtotal' => $producto->subtotal
                ],
                'total_cotizacion' => $cotizacion->total
            ]);

        } catch (\Exception $e) {
            Log::error("Error al modificar cantidad de producto: " . $e->getMessage(), [
                'cotizacion_id' => $id,
                'producto_id' => $request->producto_id,
                'usuario' => $user->name
            ]);

            return response()->json(['error' => 'Error al modificar la cantidad: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Crear una nueva NVV duplicada con múltiples productos problemáticos
     */
    private function crearNvvDuplicadaMultiple($cotizacionOriginal, $productos, $motivo, $user)
    {
        // Determinar estado según el rol del usuario
        $estadoSeparado = 'separado_por_compras'; // Por defecto para Compras
        if ($this->tieneRolPicking($user)) {
            $estadoSeparado = 'separado_por_picking';
        } elseif ($user->hasRole('Compras')) {
            $estadoSeparado = 'separado_por_compras';
        }
        
        // Determinar estado de aprobación según si la NVV original ya fue aprobada
        // Si la NVV original ya fue aprobada, heredar ese estado
        // Si está pendiente, evaluar según problemas de crédito/stock
        
        $estadoAprobacionOriginal = $cotizacionOriginal->estado_aprobacion;
        $heredarAprobaciones = in_array($estadoAprobacionOriginal, [
            'aprobada_supervisor',
            'aprobada_compras',
            'aprobada_picking',
            'pendiente_picking'
        ]);
        
        // Las NVVs separadas siempre tienen problemas de stock (necesitan compras)
        // PERO NO deben heredar problemas de crédito de la NVV original
        $tieneProblemasCredito = false; // Las NVVs separadas NO tienen problemas de crédito
        
        // Si la NVV original ya fue aprobada, heredar su estado y aprobaciones
        if ($heredarAprobaciones) {
            $estadoAprobacion = $estadoAprobacionOriginal;
            
            // Heredar aprobaciones de la NVV original (el replicate ya copia estos campos)
            $nuevaCotizacion = $cotizacionOriginal->replicate();
            $nuevaCotizacion->estado = $estadoSeparado;
            $nuevaCotizacion->estado_aprobacion = $estadoAprobacion;
            $nuevaCotizacion->tiene_problemas_stock = true;
            // Las NVVs separadas NUNCA deben tener problemas de crédito, incluso si heredan aprobaciones
            $nuevaCotizacion->tiene_problemas_credito = false;
            
            // Mantener las aprobaciones de la original (el replicate ya las copió automáticamente)
            // aprobado_por_supervisor, fecha_aprobacion_supervisor, etc. se mantienen del replicate
        } else {
            // Si la NVV original está pendiente, verificar crédito del cliente para la nueva NVV
            // Calcular total de la nueva NVV para validar crédito
            $totalNuevaNvv = $productos->sum(function($producto) {
                $cantidadSeparar = $producto->cantidad_separar ?? $producto->cantidad;
                $precioBase = $producto->precio_unitario * $cantidadSeparar;
                $descuentoPorcentaje = $producto->descuento_porcentaje ?? 0;
                $descuentoValor = $precioBase * ($descuentoPorcentaje / 100);
                $subtotalConDescuento = $precioBase - $descuentoValor;
                $ivaValor = $subtotalConDescuento * 0.19;
                return $subtotalConDescuento + $ivaValor;
            });
            
            // Validar cliente para determinar si tiene problemas de crédito
            $validacionCliente = \App\Services\ClienteValidacionService::validarClienteParaNotaVenta(
                $cotizacionOriginal->cliente_codigo,
                $totalNuevaNvv
            );
            $tieneProblemasCredito = $validacionCliente['requiere_autorizacion'] ?? false;
            
            // Si la NVV original está pendiente, la nueva NVV también inicia pendiente
            // pero se establece tiene_problemas_credito según la validación del cliente
            $estadoAprobacion = 'pendiente';
            
            $nuevaCotizacion = $cotizacionOriginal->replicate();
            $nuevaCotizacion->estado = $estadoSeparado;
            $nuevaCotizacion->estado_aprobacion = $estadoAprobacion;
            $nuevaCotizacion->tiene_problemas_stock = true;
            $nuevaCotizacion->tiene_problemas_credito = $tieneProblemasCredito;
            
            // Resetear aprobaciones ya que está pendiente
            $nuevaCotizacion->aprobado_por_supervisor = null;
            $nuevaCotizacion->fecha_aprobacion_supervisor = null;
            $nuevaCotizacion->comentarios_supervisor = null;
            $nuevaCotizacion->aprobado_por_compras = null;
            $nuevaCotizacion->fecha_aprobacion_compras = null;
            $nuevaCotizacion->comentarios_compras = null;
            $nuevaCotizacion->aprobado_por_picking = null;
            $nuevaCotizacion->fecha_aprobacion_picking = null;
            $nuevaCotizacion->comentarios_picking = null;
        }
        
        $nuevaCotizacion->created_at = now();
        $nuevaCotizacion->updated_at = now();
        $nuevaCotizacion->observaciones = "NVV creada con productos separados. Motivo: {$motivo}";
        $nuevaCotizacion->nota_original_id = $cotizacionOriginal->id; // Referencia a la NVV original/padre
        
        $nuevaCotizacion->save();

        // Duplicar los productos problemáticos usando cantidad_separar de cada uno
        foreach ($productos as $producto) {
            $cantidadSeparar = $producto->cantidad_separar ?? $producto->cantidad;
            
            $nuevoProducto = $producto->replicate();
            $nuevoProducto->cotizacion_id = $nuevaCotizacion->id;
            $nuevoProducto->cantidad = $cantidadSeparar;
            $nuevoProducto->cantidad_separar = 0; // Resetear cantidad a separar
            
            // Recalcular subtotal y valores del producto con la cantidad separada
            $subtotalBruto = $producto->precio_unitario * $cantidadSeparar;
            $descuentoPorcentaje = $producto->descuento_porcentaje ?? 0;
            $descuentoValor = $producto->descuento_valor ?? 0;
            
            // Si hay descuento porcentual, calcularlo proporcionalmente
            if ($descuentoPorcentaje > 0 && $descuentoValor == 0) {
                $descuentoValor = $subtotalBruto * ($descuentoPorcentaje / 100);
            } elseif ($descuentoValor > 0) {
                // Proporcional al porcentaje de cantidad separada
                $porcentajeCantidad = $cantidadSeparar / $producto->cantidad;
                $descuentoValor = $descuentoValor * $porcentajeCantidad;
            }
            
            $subtotalConDescuento = $subtotalBruto - $descuentoValor;
            $ivaValor = $subtotalConDescuento * 0.19;
            $totalProducto = $subtotalConDescuento + $ivaValor;
            
            $nuevoProducto->subtotal = $subtotalBruto;
            $nuevoProducto->descuento_valor = $descuentoValor;
            $nuevoProducto->subtotal_con_descuento = $subtotalConDescuento;
            $nuevoProducto->iva_valor = $ivaValor;
            $nuevoProducto->total_producto = $totalProducto;
            
            $nuevoProducto->save();
        }

        // Calcular totales de la nueva cotización
        $this->actualizarTotalesCotizacion($nuevaCotizacion);

        return $nuevaCotizacion;
    }

    /**
     * Registrar la separación múltiple en el historial
     */
    private function registrarSeparacionProductos($cotizacionOriginal, $cotizacionNueva, $productos, $motivo, $user)
    {
        $productosNombres = $productos->pluck('nombre_producto')->implode(', ');

        // Historial para la cotización original (mantiene productos no seleccionados)
        \App\Models\CotizacionHistorial::create([
            'cotizacion_id' => $cotizacionOriginal->id,
            'usuario_id' => $user->id,
            'estado_anterior' => $cotizacionOriginal->estado_aprobacion,
            'estado_nuevo' => $cotizacionOriginal->estado_aprobacion,
            'fecha_accion' => now(),
            'comentarios' => "Se separaron {$productos->count()} productos con problemas de stock. Nueva NVV #{$cotizacionNueva->id} creada con productos separados.",
            'detalles_adicionales' => [
                'accion' => 'separar_productos_multiples',
                'productos_count' => $productos->count(),
                'productos_nombres' => $productosNombres,
                'nueva_cotizacion_id' => $cotizacionNueva->id,
                'motivo' => $motivo,
                'descripcion' => 'Productos separados de esta NVV para crear nueva NVV'
            ]
        ]);

        // Historial para la nueva cotización (contiene productos separados)
        \App\Models\CotizacionHistorial::create([
            'cotizacion_id' => $cotizacionNueva->id,
            'usuario_id' => $user->id,
            'estado_anterior' => null,
            'estado_nuevo' => $cotizacionNueva->estado_aprobacion ?? 'pendiente',
            'fecha_accion' => now(),
            'comentarios' => "NVV creada con {$productos->count()} productos separados por problemas de stock. NVV original: #{$cotizacionOriginal->id}. Estado: {$cotizacionNueva->estado}",
            'detalles_adicionales' => [
                'accion' => 'creada_por_separacion_productos',
                'cotizacion_original_id' => $cotizacionOriginal->id,
                'productos_count' => $productos->count(),
                'productos_nombres' => $productosNombres,
                'motivo' => $motivo,
                'estado_separado' => $cotizacionNueva->estado,
                'estado_aprobacion' => $cotizacionNueva->estado_aprobacion,
                'descripcion' => 'Nueva NVV creada con productos separados de NVV original'
            ]
        ]);
    }

    /**
     * Enviar notificación al vendedor sobre la separación múltiple
     */
    private function enviarNotificacionSeparacionMultiple($cotizacionOriginal, $cotizacionNueva, $productos, $user)
    {
        try {
            $productosNombres = $productos->pluck('nombre_producto')->implode(', ');

            // Crear notificación en la base de datos
            \App\Models\Notificacion::create([
                'usuario_id' => $cotizacionOriginal->user_id,
                'tipo' => 'separacion_productos_stock',
                'titulo' => 'Productos Separados por Problemas de Stock',
                'mensaje' => "Se han separado {$productos->count()} productos de la NVV #{$cotizacionOriginal->id} por problemas de stock. Se ha creado una nueva NVV #{$cotizacionNueva->id} específicamente para estos productos.",
                'datos_adicionales' => json_encode([
                    'cotizacion_original_id' => $cotizacionOriginal->id,
                    'cotizacion_nueva_id' => $cotizacionNueva->id,
                    'productos_count' => $productos->count(),
                    'productos_nombres' => $productosNombres,
                    'usuario_separacion' => $user->name
                ]),
                'leida' => false,
                'fecha_creacion' => now()
            ]);

        } catch (\Exception $e) {
            Log::error("Error al enviar notificación de separación múltiple: " . $e->getMessage());
        }
    }

    /**
     * Imprimir guía de despacho
     */
    public function imprimir($id)
    {
        $cotizacion = Cotizacion::with(['productos', 'cliente'])->findOrFail($id);
        $observacionesExtra = request('observaciones', '');
        
        return view('aprobaciones.imprimir', compact('cotizacion', 'observacionesExtra'));
    }

    /**
     * Descargar guía de picking en PDF
     */
    public function descargarGuiaPicking($id)
    {
        try {
            $cotizacion = Cotizacion::with(['productos', 'cliente', 'user'])->findOrFail($id);
            
            // Verificar que sea una nota de venta
            if ($cotizacion->tipo_documento !== 'nota_venta') {
                return redirect()->back()->with('error', 'Solo se puede descargar la guía de picking para notas de venta');
            }
            
            // Generar PDF usando DomPDF
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('aprobaciones.imprimir', compact('cotizacion'));
            
            // Configurar el PDF
            $pdf->setPaper('A4', 'portrait');
            $pdf->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'defaultFont' => 'Arial'
            ]);
            
            // Generar nombre del archivo
            $filename = 'Guia_Picking_NVV_' . $cotizacion->id . '_' . now()->format('Y-m-d') . '.pdf';
            
            // Descargar el PDF
            return $pdf->download($filename);
            
        } catch (\Exception $e) {
            \Log::error('Error generando guía de picking: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error al generar la guía de picking: ' . $e->getMessage());
        }
    }

    /**
     * Guardar cantidad a separar
     */
    public function guardarSeparar($id)
    {
        try {
            $request = request();
            $productoId = $request->producto_id;
            $cantidadSeparar = $request->cantidad_separar;
            $user = Auth::user();

            // Verificar permisos - solo Compras, Picking y Picking Operativo pueden separar
            if (!$user->hasRole('Compras') && !$this->tieneRolPicking($user)) {
                return response()->json(['error' => 'No tienes permisos para realizar esta acción'], 403);
            }

            $cotizacion = Cotizacion::findOrFail($id);
            $producto = $cotizacion->productos()->findOrFail($productoId);

            // Validar múltiplos de venta
            $multiplo = intval($producto->multiplo ?? (\DB::table('productos')->where('KOPR', $producto->codigo_producto)->value('multiplo_venta') ?? 1));
            if ($multiplo > 1 && ($cantidadSeparar % $multiplo) !== 0) {
                return response()->json(['error' => "La cantidad a separar debe ser múltiplo de {$multiplo}"], 400);
            }

            // Validar que la cantidad a separar no exceda la cantidad disponible
            if ($cantidadSeparar > $producto->cantidad) {
                return response()->json(['error' => 'La cantidad a separar no puede exceder la cantidad del producto'], 400);
            }

            // Guardar la cantidad a separar en el producto
            $producto->update([
                'cantidad_separar' => $cantidadSeparar
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cantidad a separar guardada correctamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al guardar cantidad a separar: ' . $e->getMessage());
            return response()->json(['error' => 'Error interno del servidor'], 500);
        }
    }

    /**
     * Separar producto individual con lógica de cantidades
     */
    public function separarProductoIndividual(Request $request, $id)
    {
        $request->validate([
            'producto_id' => 'required|integer|exists:cotizacion_productos,id',
            'motivo' => 'required|string|max:500'
        ]);

        $cotizacion = Cotizacion::with(['productos', 'user'])->findOrFail($id);
        $user = Auth::user();

        // Verificar permisos - Compras, Picking y Picking Operativo pueden separar
        if (!$user->hasRole('Compras') && !$this->tieneRolPicking($user)) {
            return response()->json(['error' => 'No tienes permisos para realizar esta acción'], 403);
        }

        try {
            $producto = $cotizacion->productos()->findOrFail($request->producto_id);
            $cantidadSeparar = $producto->cantidad_separar ?? 0;

            if ($cantidadSeparar <= 0) {
                return response()->json(['error' => 'Debe especificar una cantidad a separar mayor a 0'], 400);
            }

            // Validar múltiplos también aquí
            $multiplo = intval($producto->multiplo ?? (\DB::table('productos')->where('KOPR', $producto->codigo_producto)->value('multiplo_venta') ?? 1));
            if ($multiplo > 1 && ($cantidadSeparar % $multiplo) !== 0) {
                return response()->json(['error' => "La cantidad a separar debe ser múltiplo de {$multiplo}"], 400);
            }

            if ($cantidadSeparar > $producto->cantidad) {
                return response()->json(['error' => 'La cantidad a separar no puede exceder la cantidad del producto'], 400);
            }

            // Crear nueva NVV con el producto separado
            $nuevaCotizacion = $this->crearNvvConProductoSeparado($cotizacion, $producto, $cantidadSeparar, $request->motivo, $user);

            // Lógica de separación:
            if ($cantidadSeparar == $producto->cantidad) {
                // Si separar = cantidad total, eliminar el producto de la NVV original
                $producto->delete();
            } else {
                // Si separar < cantidad, reducir la cantidad del producto original
                $nuevaCantidad = $producto->cantidad - $cantidadSeparar;
                $producto->update([
                    'cantidad' => $nuevaCantidad,
                    'cantidad_separar' => 0, // Resetear cantidad a separar
                    'subtotal' => $producto->precio_unitario * $nuevaCantidad
                ]);
            }

            // Actualizar totales de la NVV original
            $this->actualizarTotalesCotizacion($cotizacion);

            // Registrar en el historial
            $this->registrarSeparacionIndividual($cotizacion, $nuevaCotizacion, $producto, $cantidadSeparar, $request->motivo, $user);

            Log::info("Producto separado individualmente", [
                'cotizacion_original' => $cotizacion->id,
                'cotizacion_nueva' => $nuevaCotizacion->id,
                'producto' => $producto->codigo_producto,
                'cantidad_separada' => $cantidadSeparar,
                'usuario' => $user->name
            ]);

            return response()->json([
                'success' => true,
                'message' => "Producto '{$producto->nombre_producto}' separado exitosamente. Nueva NVV #{$nuevaCotizacion->id} creada con {$cantidadSeparar} unidades.",
                'nota_separada_id' => $nuevaCotizacion->id,
                'nota_original_id' => $cotizacion->id
            ]);

        } catch (\Exception $e) {
            Log::error("Error al separar producto individual: " . $e->getMessage(), [
                'cotizacion_id' => $id,
                'producto_id' => $request->producto_id,
                'usuario' => $user->name
            ]);

            return response()->json(['error' => 'Error al separar el producto: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Crear nueva NVV con producto separado
     */
    private function crearNvvConProductoSeparado($cotizacionOriginal, $producto, $cantidadSeparar, $motivo, $user)
    {
        // Determinar estado según el rol del usuario
        $estadoSeparado = 'separado_por_compras'; // Por defecto para Compras
        if ($this->tieneRolPicking($user)) {
            $estadoSeparado = 'separado_por_picking';
        } elseif ($user->hasRole('Compras')) {
            $estadoSeparado = 'separado_por_compras';
        }
        
        // Las NVVs separadas SIEMPRE deben quedar pendientes de compras
        // porque ellos son los que generan las compras de productos
        $estadoAprobacion = 'pendiente';
        
        // Crear nueva cotización
        $nuevaCotizacion = $cotizacionOriginal->replicate();
        $nuevaCotizacion->estado = $estadoSeparado; // Usar el nuevo estado separado
        $nuevaCotizacion->estado_aprobacion = $estadoAprobacion;
        // Las NVVs separadas siempre tienen problemas de stock (necesitan compras)
        $nuevaCotizacion->tiene_problemas_stock = true;
        $nuevaCotizacion->created_at = now();
        $nuevaCotizacion->updated_at = now();
        $nuevaCotizacion->observaciones = "NVV creada con producto separado: {$producto->nombre_producto} (Cantidad: {$cantidadSeparar}). Motivo: {$motivo}";
        $nuevaCotizacion->nota_original_id = $cotizacionOriginal->id;
        
        // Las NVVs separadas siempre empiezan desde cero, sin aprobaciones previas
        // ya que deben ser revisadas nuevamente
        $nuevaCotizacion->aprobado_por_supervisor = null;
        $nuevaCotizacion->fecha_aprobacion_supervisor = null;
        $nuevaCotizacion->comentarios_supervisor = null;
        $nuevaCotizacion->aprobado_por_compras = null;
        $nuevaCotizacion->fecha_aprobacion_compras = null;
        $nuevaCotizacion->comentarios_compras = null;
        
        // Las NVVs separadas siempre empiezan desde cero, sin aprobaciones previas
        // ya que deben ser revisadas nuevamente
        
        $nuevaCotizacion->save();

        // Crear el producto separado con la cantidad especificada
        $nuevoProducto = $producto->replicate();
        $nuevoProducto->cotizacion_id = $nuevaCotizacion->id;
        $nuevoProducto->cantidad = $cantidadSeparar;
        $nuevoProducto->cantidad_separar = 0; // Resetear cantidad a separar
        
        // Recalcular subtotal y valores del producto con la nueva cantidad
        $subtotalBruto = $producto->precio_unitario * $cantidadSeparar;
        $descuentoPorcentaje = $producto->descuento_porcentaje ?? 0;
        $descuentoValor = $producto->descuento_valor ?? 0;
        // Si hay descuento porcentual, calcularlo proporcionalmente
        if ($descuentoPorcentaje > 0 && $descuentoValor == 0) {
            $descuentoValor = $subtotalBruto * ($descuentoPorcentaje / 100);
        } elseif ($descuentoValor > 0) {
            // Proporcional al porcentaje de cantidad separada
            $porcentajeCantidad = $cantidadSeparar / $producto->cantidad;
            $descuentoValor = $descuentoValor * $porcentajeCantidad;
        }
        $subtotalConDescuento = $subtotalBruto - $descuentoValor;
        $ivaValor = $subtotalConDescuento * 0.19;
        $totalProducto = $subtotalConDescuento + $ivaValor;
        
        $nuevoProducto->subtotal = $subtotalBruto;
        $nuevoProducto->descuento_valor = $descuentoValor;
        $nuevoProducto->subtotal_con_descuento = $subtotalConDescuento;
        $nuevoProducto->iva_valor = $ivaValor;
        $nuevoProducto->total_producto = $totalProducto;
        $nuevoProducto->save();

        // Calcular totales de la nueva cotización
        $this->actualizarTotalesCotizacion($nuevaCotizacion);

        return $nuevaCotizacion;
    }

    /**
     * Registrar la separación individual en el historial
     */
    private function registrarSeparacionIndividual($cotizacionOriginal, $cotizacionNueva, $producto, $cantidadSeparada, $motivo, $user)
    {
        // Historial para la cotización original
        \App\Models\CotizacionHistorial::create([
            'cotizacion_id' => $cotizacionOriginal->id,
            'usuario_id' => $user->id,
            'estado_anterior' => $cotizacionOriginal->estado_aprobacion,
            'estado_nuevo' => $cotizacionOriginal->estado_aprobacion,
            'fecha_accion' => now(),
            'comentarios' => "Producto '{$producto->nombre_producto}' separado. Cantidad separada: {$cantidadSeparada}. Nueva NVV #{$cotizacionNueva->id} creada.",
            'detalles_adicionales' => [
                'accion' => 'separar_producto_individual',
                'producto_codigo' => $producto->codigo_producto,
                'producto_nombre' => $producto->nombre_producto,
                'cantidad_original' => $producto->cantidad,
                'cantidad_separada' => $cantidadSeparada,
                'nueva_cotizacion_id' => $cotizacionNueva->id,
                'motivo' => $motivo,
                'descripcion' => 'Producto separado de esta NVV para crear nueva NVV'
            ]
        ]);

        // Historial para la nueva cotización
        \App\Models\CotizacionHistorial::create([
            'cotizacion_id' => $cotizacionNueva->id,
            'usuario_id' => $user->id,
            'estado_anterior' => null,
            'estado_nuevo' => $cotizacionNueva->estado_aprobacion ?? 'pendiente',
            'fecha_accion' => now(),
            'comentarios' => "NVV creada por separación de producto '{$producto->nombre_producto}' de la NVV #{$cotizacionOriginal->id}. Cantidad: {$cantidadSeparada}. Estado: {$cotizacionNueva->estado}",
            'detalles_adicionales' => [
                'accion' => 'crear_por_separacion',
                'cotizacion_origen_id' => $cotizacionOriginal->id,
                'producto_codigo' => $producto->codigo_producto,
                'producto_nombre' => $producto->nombre_producto,
                'cantidad_separada' => $cantidadSeparada,
                'motivo' => $motivo,
                'estado_separado' => $cotizacionNueva->estado,
                'estado_aprobacion' => $cotizacionNueva->estado_aprobacion,
                'descripcion' => 'NVV creada por separación de producto'
            ]
        ]);
    }
    
    /**
     * Sincronizar stock de los productos de una NVV específica desde SQL Server
     */
    public function sincronizarStock(Request $request, $id)
    {
        try {
            // Obtener la cotización/NVV
            $cotizacion = Cotizacion::findOrFail($id);
            
            // Obtener los productos de esta NVV
            $productos = $cotizacion->productos;
            
            if ($productos->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay productos en esta nota de venta'
                ], 400);
            }
            
            Log::info("🔄 Sincronizando stock para NVV #{$id} con " . $productos->count() . " productos");
            
            $stockConsultaService = new StockConsultaService();
            $productosSincronizados = 0;
            $productosConError = 0;
            
            // Consultar y actualizar cada producto individualmente (usando el mismo método que funciona bien)
            foreach ($productos as $productoCotizacion) {
                $codigo = $productoCotizacion->codigo_producto;
                
                try {
                    // Usar el mismo método que usa obtenerStockProducto (tsql directo con un solo producto)
                    $host = env('SQLSRV_EXTERNAL_HOST');
                    $port = env('SQLSRV_EXTERNAL_PORT', '1433');
                    $database = env('SQLSRV_EXTERNAL_DATABASE');
                    $username = env('SQLSRV_EXTERNAL_USERNAME');
                    $password = env('SQLSRV_EXTERNAL_PASSWORD');
                    
                    $codigoEscapado = "'" . addslashes(trim($codigo)) . "'";
                    $query = "
                        SELECT 
                            CAST(SUM(ISNULL(STFI1, 0)) AS FLOAT) AS STOCK_FISICO,
                            CAST(SUM(ISNULL(STOCNV1, 0)) AS FLOAT) AS STOCK_COMPROMETIDO
                        FROM MAEST
                        WHERE KOPR = {$codigoEscapado}
                        AND KOBO = 'LIB'
                    ";
                    
                    $tempFile = tempnam(sys_get_temp_dir(), 'sql_stock_');
                    file_put_contents($tempFile, $query . "\ngo\nquit");
                    
                    $command = "tsql -H {$host} -p {$port} -U {$username} -P {$password} -D {$database} < {$tempFile} 2>&1";
                    $output = shell_exec($command);
                    unlink($tempFile);
                    
                    // Parsear resultado (mismo método que obtenerStockProducto)
                    $stockFisico = 0;
                    $stockComprometido = 0;
                    
                    $lines = explode("\n", $output);
                    $headerFound = false;
                    
                    foreach ($lines as $line) {
                        $line = trim($line);
                        
                        if (empty($line) || strpos($line, 'locale') !== false || 
                            strpos($line, 'Setting') !== false || strpos($line, 'rows affected') !== false ||
                            strpos($line, 'Msg ') !== false || strpos($line, 'Warning:') !== false ||
                            preg_match('/^\d+>$/', $line) || preg_match('/^\d+>\s+\d+>\s+\d+>/', $line)) {
                            continue;
                        }
                        
                        if (stripos($line, 'STOCK_FISICO') !== false || stripos($line, 'STOCK_COMPROMETIDO') !== false) {
                            $headerFound = true;
                            continue;
                        }
                        
                        if (preg_match('/^\s*([0-9.]+)\s+([0-9.]+)\s*$/', $line, $matches)) {
                            $stockFisico = (float)$matches[1];
                            $stockComprometido = (float)$matches[2];
                            break;
                        }
                        
                        if ($headerFound) {
                            $parts = preg_split('/\s+/', $line);
                            if (count($parts) >= 2 && is_numeric($parts[0]) && is_numeric($parts[1])) {
                                $stockFisico = (float)$parts[0];
                                $stockComprometido = (float)$parts[1];
                                break;
                            }
                        }
                    }
                    
                    // Actualizar MySQL con los valores obtenidos
                    if ($stockFisico >= 0 && $stockComprometido >= 0) {
                        $stockConsultaService->actualizarStockSiEsDiferente($codigo, $stockFisico, $stockComprometido);
                        $productosSincronizados++;
                        Log::info("✅ Stock sincronizado para {$codigo}: Físico={$stockFisico}, Comprometido={$stockComprometido}");
                    } else {
                        $productosConError++;
                        Log::warning("⚠️ No se pudo parsear stock para {$codigo}");
                    }
                } catch (\Exception $e) {
                    $productosConError++;
                    Log::error("❌ Error sincronizando stock para {$codigo}: " . $e->getMessage());
                }
            }
            
            $mensaje = "Stock sincronizado exitosamente. {$productosSincronizados} productos actualizados.";
            if ($productosConError > 0) {
                $mensaje .= " {$productosConError} productos con errores.";
            }
            
            Log::info("✅ Sincronización completada para NVV #{$id}: {$productosSincronizados} productos actualizados, {$productosConError} con errores");
            
            // Si es una petición AJAX, devolver JSON
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $mensaje,
                    'productos_sincronizados' => $productosSincronizados,
                    'productos_con_error' => $productosConError,
                    'total_productos' => $productos->count()
                ]);
            }
            
            // Si no es AJAX, redirigir de vuelta
            return redirect()->back()->with('success', $mensaje);
            
        } catch (\Exception $e) {
            Log::error('Error sincronizando stock para NVV #' . $id . ': ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            $mensajeError = 'Error al sincronizar stock: ' . $e->getMessage();
            
            // Si es una petición AJAX, devolver JSON
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $mensajeError
                ], 500);
            }
            
            return redirect()->back()->with('error', $mensajeError);
        }
    }

    /**
     * Modificar descuentos de productos (Supervisor)
     */
    public function modificarDescuentosProductos(Request $request, $id)
    {
        try {
            $cotizacion = Cotizacion::findOrFail($id);
            
            // Verificar permisos - solo supervisor puede modificar descuentos
            if (!auth()->user()->hasRole('Supervisor')) {
                return response()->json(['error' => 'No tienes permisos para modificar descuentos'], 403);
            }
            
            // Verificar que puede aprobar supervisor
            if (!$cotizacion->puedeAprobarSupervisor()) {
                return response()->json(['error' => 'Esta nota de venta no requiere aprobación del supervisor'], 400);
            }
            
            $descuentos = $request->input('descuentos', []);
            
            if (empty($descuentos)) {
                return response()->json(['error' => 'No se proporcionaron descuentos para modificar'], 400);
            }
            
            DB::beginTransaction();
            
            foreach ($descuentos as $descuento) {
                $producto = $cotizacion->productos()->find($descuento['producto_id']);
                
                if ($producto) {
                    $porcentaje = floatval($descuento['descuento_porcentaje']);
                    
                    // Calcular valores
                    $subtotal = $producto->cantidad * $producto->precio_unitario;
                    $descuentoValor = ($subtotal * $porcentaje) / 100;
                    $subtotalConDescuento = $subtotal - $descuentoValor;
                    $iva = $subtotalConDescuento * 0.19;
                    $total = $subtotalConDescuento + $iva;
                    
                    // Actualizar producto
                    $producto->update([
                        'descuento_porcentaje' => $porcentaje,
                        'descuento_valor' => $descuentoValor,
                        'subtotal_con_descuento' => $subtotalConDescuento,
                        'iva_valor' => $iva,
                        'total_producto' => $total
                    ]);
                }
            }
            
            // Recalcular totales de la cotización
            $productos = $cotizacion->productos;
            $subtotal = $productos->sum('subtotal');
            $descuentoGlobal = $productos->sum('descuento_valor');
            $subtotalNeto = $productos->sum('subtotal_con_descuento');
            $iva = $productos->sum('iva_valor');
            $total = $productos->sum('total_producto');
            
            $cotizacion->update([
                'subtotal' => $subtotal,
                'descuento_global' => $descuentoGlobal,
                'subtotal_neto' => $subtotalNeto,
                'iva' => $iva,
                'total' => $total
            ]);
            
            // Registrar en historial
            \App\Models\CotizacionHistorial::crearRegistro(
                $cotizacion->id,
                $cotizacion->estado_aprobacion,
                'aprobacion',
                $cotizacion->estado_aprobacion,
                'Descuentos modificados por supervisor',
                ['modificado_por' => auth()->id()]
            );
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Descuentos actualizados correctamente',
                'totales' => [
                    'subtotal' => (float) $subtotal,
                    'descuento' => (float) $descuentoGlobal,
                    'subtotal_neto' => (float) $subtotalNeto,
                    'iva' => (float) $iva,
                    'total' => (float) $total,
                ]
            ]);
            
        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Error modificando descuentos: ' . $e->getMessage());
            return response()->json(['error' => 'Error al modificar descuentos: ' . $e->getMessage()], 500);
        }
    }

}
