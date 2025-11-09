<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cotizacion;
use App\Models\Cliente;
use App\Services\CobranzaService;
use App\Services\StockService;
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
        };

        if ($user->hasRole('Supervisor')) {
            // Supervisor ve todas las notas pendientes de aprobación
            // Incluye: pendiente (nuevas), pendiente_picking (aprobadas por supervisor pero pendientes de picking)
            $query = Cotizacion::whereIn('estado_aprobacion', ['pendiente', 'pendiente_picking'])
                ->where('tipo_documento', 'nota_venta'); // Solo notas de venta
            
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
            $query = Cotizacion::where('tipo_documento', 'nota_venta')
                ->where('tiene_problemas_stock', true)
                ->where(function($q) {
                    $q->where('estado_aprobacion', 'aprobada_supervisor')
                      ->orWhere('estado_aprobacion', 'pendiente');
                });
            
            $aplicarFiltros($query);
            
            $cotizaciones = $query->with(['user', 'productos', 'cliente'])
                ->latest()
                ->paginate(15)
                ->appends($request->query());
            $tipoAprobacion = 'compras';
        } elseif ($user->hasRole('Picking')) {
            // Picking ve tanto notas con problemas de stock como sin problemas
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

        return view('aprobaciones.index', compact('cotizaciones', 'tipoAprobacion', 'filtros', 'regiones', 'comunas'));
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
            'productos_pendientes.*' => 'integer'
        ]);

        $cotizacion = Cotizacion::findOrFail($id);
        $user = Auth::user();

        if (!$user->hasRole('Picking')) {
            return redirect()->route('aprobaciones.show', $id)
                ->with('error', 'No tienes permisos para esta acción');
        }

        if (!$cotizacion->puedeAprobarPicking() && $cotizacion->estado_aprobacion !== 'pendiente_picking') {
            return redirect()->route('aprobaciones.show', $id)
                ->with('error', 'La nota de venta no puede ser procesada');
        }

        try {
            // Actualizar observación y estado general de la NVV a pendiente de entrega
            $cotizacion->guardarPendienteEntrega($user->id, $request->observaciones_picking);

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
            'validar_stock_real' => 'nullable|boolean'
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

        if (!$user->hasRole('Picking')) {
            Log::error("❌ ERROR: Usuario no tiene rol Picking");
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
            
            $resultado = $this->insertarEnSQLServer($cotizacion);
            
            $endTime = microtime(true);
            $duration = $endTime - $startTime;
            Log::info("⏱️ PASO CRÍTICO: Insert completado en " . round($duration, 2) . " segundos");
            
            // Solo si el insert fue exitoso, aprobamos en MySQL
            if ($resultado['success']) {
                Log::info("✅ Insert en SQL Server exitoso, aprobando en MySQL...");
            $cotizacion->aprobarPorPicking($user->id, $request->comentarios);
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
            $preview .= sprintf("║ %-30s = %-39s ║\n", "LUVTDO", "LIB");
            $preview .= sprintf("║ %-30s = %-39s ║\n", "MEARDO", "N");
            $preview .= sprintf("║ %-30s = %-39s ║\n", "ESPGDO", "S");
            $preview .= sprintf("║ %-30s = %-39s ║\n", "FEEMDO", "GETDATE()");
            $preview .= sprintf("║ %-30s = %-39s ║\n", "FE01VEDO", "GETDATE() + {$diasPago} días");
            $preview .= sprintf("║ %-30s = %-39s ║\n", "FEULVEDO", "GETDATE() + {$diasPago} días");
            $preview .= sprintf("║ %-30s = %-39s ║\n", "FEER", $cotizacion->fecha_despacho->format('Y-m-d H:i:s'));
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
                    $ud02pr = trim($productoDB->UD02PR ?? 'UN');
                    $udtrpr = ($rludpr > 1) ? 2 : 1;
                }
                
                $codigoProducto = substr($producto->codigo_producto, 0, 13);
                $nombreProducto = substr($producto->nombre_producto, 0, 50);
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
                $previewDetalle .= sprintf("║ %-30s = %-39s ║\n", "FEEMLI", "GETDATE()");
                $previewDetalle .= sprintf("║ %-30s = %-39s ║\n", "FEERLI", $cotizacion->fecha_despacho->format('Y-m-d'));
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
                $previewDetalle .= sprintf("║ %-30s = %-39s ║\n", "FEERLIMODI", $cotizacion->fecha_despacho->format('Y-m-d'));
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
            
            $previewMAEEDOOB = "\n\n";
            $previewMAEEDOOB .= "╔══════════════════════════════════════════════════════════════════════════╗\n";
            $previewMAEEDOOB .= "║                  📊 PREVISUALIZACIÓN TABLA MAEEDOOB                      ║\n";
            $previewMAEEDOOB .= "╠══════════════════════════════════════════════════════════════════════════╣\n";
            $previewMAEEDOOB .= sprintf("║ %-30s = %-39s ║\n", "IDMAEEDO", $siguienteId);
            $previewMAEEDOOB .= sprintf("║ %-30s = %-39s ║\n", "OBDO (observación vendedor)", substr($observacionTruncada, 0, 39));
            $previewMAEEDOOB .= sprintf("║ %-30s = %-39s ║\n", "CPDO (condición pago)", $condicionPago);
            $previewMAEEDOOB .= sprintf("║ %-30s = %-39s ║\n", "OCDO (orden compra)", substr($ordenCompraTruncada, 0, 39));
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
    private function insertarEnSQLServer($cotizacion)
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
            
            Log::info('Siguiente ID para MAEEDO: ' . $siguienteId);
            
            // Obtener el último NUDO de NVV y sumarle 1 (consulta simple y directa)
            // IMPORTANTE: Filtrar por TIDO = 'NVV' porque cada tipo de documento tiene su propia numeración
            $queryNudo = "SELECT TOP 1 NUDO FROM MAEEDO WHERE TIDO = 'NVV' AND ISNUMERIC(NUDO) = 1 ORDER BY NUDO DESC";
            
            $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
            file_put_contents($tempFile, $queryNudo . "\ngo\nquit");
            
            $command = "tsql -H " . env('SQLSRV_EXTERNAL_HOST') . " -p " . env('SQLSRV_EXTERNAL_PORT') . " -U " . env('SQLSRV_EXTERNAL_USERNAME') . " -P " . env('SQLSRV_EXTERNAL_PASSWORD') . " -D " . env('SQLSRV_EXTERNAL_DATABASE') . " < {$tempFile} 2>&1";
            $result = shell_exec($command);
            unlink($tempFile);
            
            Log::info("Resultado query último NUDO: " . $result);
            
            // Parsear el resultado - buscar el NUDO en el formato 0000037566
            $ultimoNudoStr = '';
            if (preg_match('/(\d{10})/', $result, $matches)) {
                $ultimoNudoStr = $matches[1];
            }
            
            if (empty($ultimoNudoStr)) {
                Log::error("No se pudo obtener el último NUDO. Resultado de tsql: " . $result);
                throw new \Exception("No se pudo obtener el último número correlativo de NVV");
            }
            
            // Convertir a entero, sumar 1, y formatear de vuelta
            $ultimoNudo = (int)$ultimoNudoStr;
            $siguienteNudo = $ultimoNudo + 1;
            $nudoFormateado = str_pad($siguienteNudo, 10, '0', STR_PAD_LEFT);
            
            Log::info("Último NUDO de NVV: {$ultimoNudoStr}");
            Log::info("Siguiente NUDO asignado: {$nudoFormateado}");
            
            // Calcular fecha de vencimiento (30 días desde hoy)
            $fechaVencimiento = date('Y-m-d', strtotime('+30 days'));
            
            // Obtener información del vendedor
            $codigoVendedor = $cotizacion->user->codigo_vendedor ?? '001';
            $nombreVendedor = $cotizacion->user->name ?? 'Vendedor Sistema';
            
            // Obtener datos adicionales del cliente
            $listaPrecios = $this->obtenerListaPreciosCliente($cotizacion->cliente_codigo);
            $diasPago = $this->obtenerDiasPagoCliente($cotizacion->cliente_codigo);
            $nuevecr = $this->obtenerNuevecrCliente($cotizacion->cliente_codigo);
            $condicionPago = $this->obtenerCondicionPagoCliente($cotizacion->cliente_codigo);
            
            // Obtener sucursal del cliente desde SQL Server
            $querySucursal = "SELECT LTRIM(RTRIM(SUEN)) as SUCURSAL FROM MAEEN WHERE KOEN = '{$cotizacion->cliente_codigo}'";
            $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
            file_put_contents($tempFile, $querySucursal . "\ngo\nquit");
            
            $command = "tsql -H " . env('SQLSRV_EXTERNAL_HOST') . " -p " . env('SQLSRV_EXTERNAL_PORT') . " -U " . env('SQLSRV_EXTERNAL_USERNAME') . " -P " . env('SQLSRV_EXTERNAL_PASSWORD') . " -D " . env('SQLSRV_EXTERNAL_DATABASE') . " < {$tempFile} 2>&1";
            $result = shell_exec($command);
            unlink($tempFile);
            
            // Parsear sucursal del cliente
            $sucursalCliente = '';
            if ($result && !str_contains($result, 'error')) {
                $lines = explode("\n", $result);
                $foundHeader = false;
                foreach ($lines as $line) {
                    $line = trim($line);
                    // Primero encontrar el header "SUCURSAL"
                    if ($line === 'SUCURSAL') {
                        $foundHeader = true;
                        continue;
                    }
                    // Después del header, la siguiente línea con contenido es el valor
                    if ($foundHeader && !empty($line) && !str_contains($line, 'row') && !str_contains($line, '---') && !str_contains($line, '>')) {
                        $sucursalCliente = $line;
                        break;
                    }
                }
            }
            
            // Si la sucursal está vacía o no se encontró, dejar vacío (no usar '001' como fallback)
            Log::info("Sucursal del cliente '{$cotizacion->cliente_codigo}': '{$sucursalCliente}' " . (empty($sucursalCliente) ? "(vacía - correcto)" : ""));
            
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
                    '{$sucursalCliente}', '{$cotizacion->cliente_codigo}', 'LIB',
                    'I', 'LIB', 'N', 'S',
                    GETDATE(), GETDATE(), GETDATE(), '{$cotizacion->fecha->format('Y-m-d H:i:s')}',
                    {$sumaCantidades}, 0, 0, 0,
                    '$', 'N', 1,
                    {$VAIVDO}, {$VANEDO}, {$VABRDO}, 0,
                    '', '{$codigoVendedor}', 1, GETDATE(), 1, 0,
                    0, '', '', GETDATE(), 'TABPP01P'
                )
                
                SET IDENTITY_INSERT MAEEDO OFF
            ";
            
            Log::info("=== SQL INSERT MAEEDO ===");
            Log::info($insertMAEEDO);
            
            $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
            file_put_contents($tempFile, $insertMAEEDO . "\ngo\nquit");
            
            $command = "tsql -H " . env('SQLSRV_EXTERNAL_HOST') . " -p " . env('SQLSRV_EXTERNAL_PORT') . " -U " . env('SQLSRV_EXTERNAL_USERNAME') . " -P " . env('SQLSRV_EXTERNAL_PASSWORD') . " -D " . env('SQLSRV_EXTERNAL_DATABASE') . " < {$tempFile} 2>&1";
            $result = shell_exec($command);
            
            unlink($tempFile);
            
            if (str_contains($result, 'Msg') || str_contains($result, 'Error')) {
                throw new \Exception('Error insertando encabezado: ' . $result);
            }
            
            Log::info('Encabezado MAEEDO insertado correctamente');
            
            // Insertar detalles en MAEDDO (COPIADO EXACTO DE PREVISUALIZACIÓN)
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
                    $ud02pr = trim($productoDB->UD02PR ?? 'UN');
                    $udtrpr = ($rludpr > 1) ? 2 : 1;
                }
                
                $codigoProducto = substr($producto->codigo_producto, 0, 13);
                $nombreProducto = substr($producto->nombre_producto, 0, 50);
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
                
                // Obtener PPPRPM (precio mínimo)
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
                        'SI', '{$nulidoFormateado}', 'LIB', 'LIB', '', '{$codigoVendedor}', 'FPN',
                        {$udtrpr}, {$rludpr}, '{$ud01prTruncado}', '{$ud02prTruncado}',
                        '{$codigoProducto}', '{$nombreProducto}',
                        {$cantidad}, {$caprco2},
                        '{$listaPrecios}', '$', 'N', 1,
                        {$precioNeto}, {$precioNeto}, " . ($precioNeto * 1.19) . ", " . ($precioNeto * 1.19) . ",
                        {$porcentajeDescuento}, {$vadtneli}, {$subtotalConDescuento}, 19, {$ivaConDescuento}, {$total},
                        'I', GETDATE(), '{$cotizacion->fecha->format('Y-m-d')}', {$nudtli}, '', 0,
                        {$ppprpm}, {$ppprnere1}, {$ppprnere2}, 1, 0, 0,
                        0, 0, 0, '{$cotizacion->fecha->format('Y-m-d')}'
                    )
                ";
                
                Log::info("SQL INSERT MAEDDO línea {$lineaId}:");
                Log::info($insertMAEDDO);
                
                $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
                file_put_contents($tempFile, $insertMAEDDO . "\ngo\nquit");
                
                $command = "tsql -H " . env('SQLSRV_EXTERNAL_HOST') . " -p " . env('SQLSRV_EXTERNAL_PORT') . " -U " . env('SQLSRV_EXTERNAL_USERNAME') . " -P " . env('SQLSRV_EXTERNAL_PASSWORD') . " -D " . env('SQLSRV_EXTERNAL_DATABASE') . " < {$tempFile} 2>&1";
                $result = shell_exec($command);
                
                unlink($tempFile);
                
                if (str_contains($result, 'Msg') || str_contains($result, 'Error')) {
                    throw new \Exception('Error insertando detalle línea ' . $lineaId . ': ' . $result);
                }
            }
            
            Log::info('Detalles MAEDDO insertados correctamente');
            
            // Actualizar stock comprometido en MAEST
            foreach ($cotizacion->productos as $producto) {
                $updateMAEST = "
                    UPDATE MAEST 
                    SET STOCKSALIDA = ISNULL(STOCKSALIDA, 0) + {$producto->cantidad}
                    WHERE KOPR = '{$producto->codigo_producto}' AND EMPRESA = '01'
                ";
                
                $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
                file_put_contents($tempFile, $updateMAEST . "\ngo\nquit");
                
                $command = "tsql -H " . env('SQLSRV_EXTERNAL_HOST') . " -p " . env('SQLSRV_EXTERNAL_PORT') . " -U " . env('SQLSRV_EXTERNAL_USERNAME') . " -P " . env('SQLSRV_EXTERNAL_PASSWORD') . " -D " . env('SQLSRV_EXTERNAL_DATABASE') . " < {$tempFile} 2>&1";
                $result = shell_exec($command);
                
                unlink($tempFile);
                
                if (str_contains($result, 'Msg') || str_contains($result, 'Error')) {
                    Log::warning('Error actualizando stock comprometido para producto ' . $producto->codigo_producto . ': ' . $result);
                }
            }
            
            Log::info('Stock comprometido MAEST actualizado correctamente');
            
            // Actualizar productos en MAEPR (STOCNV1 y STOCNV2)
            foreach ($cotizacion->productos as $producto) {
                $updateMAEPR = "
                    UPDATE MAEPR 
                    SET STOCNV1 = ISNULL(STOCNV1, 0) + {$producto->cantidad},
                        STOCNV2 = ISNULL(STOCNV2, 0) + {$producto->cantidad},
                        ULTIMACOMPRA = GETDATE()
                    WHERE KOPR = '{$producto->codigo_producto}'
                ";
                
                $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
                file_put_contents($tempFile, $updateMAEPR . "\ngo\nquit");
                
                $command = "tsql -H " . env('SQLSRV_EXTERNAL_HOST') . " -p " . env('SQLSRV_EXTERNAL_PORT') . " -U " . env('SQLSRV_EXTERNAL_USERNAME') . " -P " . env('SQLSRV_EXTERNAL_PASSWORD') . " -D " . env('SQLSRV_EXTERNAL_DATABASE') . " < {$tempFile} 2>&1";
                $result = shell_exec($command);
                
                unlink($tempFile);
                
                if (str_contains($result, 'Msg') || str_contains($result, 'Error')) {
                    Log::warning('Error actualizando MAEPR para producto ' . $producto->codigo_producto . ': ' . $result);
                }
            }
            
            Log::info('STOCNV1 y STOCNV2 en MAEPR actualizados correctamente');
            
            // Actualizar stock en MAEST (STOCKNV1 y STOCKNV2)
            foreach ($cotizacion->productos as $producto) {
                $updateMAESTStock = "
                    UPDATE MAEST 
                    SET STOCKNV1 = ISNULL(STOCKNV1, 0) + {$producto->cantidad},
                        STOCKNV2 = ISNULL(STOCKNV2, 0) + {$producto->cantidad}
                    WHERE KOPR = '{$producto->codigo_producto}' AND KOPRST = 'LIB'
                ";
                
                $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
                file_put_contents($tempFile, $updateMAESTStock . "\ngo\nquit");
                
                $command = "tsql -H " . env('SQLSRV_EXTERNAL_HOST') . " -p " . env('SQLSRV_EXTERNAL_PORT') . " -U " . env('SQLSRV_EXTERNAL_USERNAME') . " -P " . env('SQLSRV_EXTERNAL_PASSWORD') . " -D " . env('SQLSRV_EXTERNAL_DATABASE') . " < {$tempFile} 2>&1";
                $result = shell_exec($command);
                
                unlink($tempFile);
                
                if (str_contains($result, 'Msg') || str_contains($result, 'Error')) {
                    Log::warning('Error actualizando STOCKNV en MAEST para producto ' . $producto->codigo_producto . ': ' . $result);
                }
            }
            
            Log::info('STOCKNV1 y STOCKNV2 en MAEST actualizados correctamente');
            
            // Actualizar stock en MAEPREM (STOCNV1 y STOCNV2)
            foreach ($cotizacion->productos as $producto) {
                $updateMAEPREM = "
                    UPDATE MAEPREM 
                    SET STOCNV1 = ISNULL(STOCNV1, 0) + {$producto->cantidad},
                        STOCNV2 = ISNULL(STOCNV2, 0) + {$producto->cantidad}
                    WHERE KOPR = '{$producto->codigo_producto}' AND EMPRESA = '01'
                ";
                
                $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
                file_put_contents($tempFile, $updateMAEPREM . "\ngo\nquit");
                
                $command = "tsql -H " . env('SQLSRV_EXTERNAL_HOST') . " -p " . env('SQLSRV_EXTERNAL_PORT') . " -U " . env('SQLSRV_EXTERNAL_USERNAME') . " -P " . env('SQLSRV_EXTERNAL_PASSWORD') . " -D " . env('SQLSRV_EXTERNAL_DATABASE') . " < {$tempFile} 2>&1";
                $result = shell_exec($command);
                
                unlink($tempFile);
                
                if (str_contains($result, 'Msg') || str_contains($result, 'Error')) {
                    Log::warning('Error actualizando STOCNV en MAEPREM para producto ' . $producto->codigo_producto . ': ' . $result);
                }
            }
            
            Log::info('STOCNV1 y STOCNV2 en MAEPREM actualizados correctamente');
            
            Log::info('Productos MAEPR actualizados correctamente');
            
            // Actualizar stock comprometido en MySQL (productos tabla local)
            foreach ($cotizacion->productos as $producto) {
                $productoLocal = \App\Models\Producto::where('KOPR', $producto->codigo_producto)->first();
                
                if ($productoLocal) {
                    // Incrementar stock comprometido virtual
                    $productoLocal->stock_comprometido = ($productoLocal->stock_comprometido ?? 0) + $producto->cantidad;
                    // Recalcular stock disponible
                    $productoLocal->stock_disponible = ($productoLocal->stock_fisico ?? 0) - $productoLocal->stock_comprometido;
                    $productoLocal->save();
                    
                    Log::info("Stock MySQL actualizado para {$producto->codigo_producto}: Comprometido +{$producto->cantidad}, Disponible: {$productoLocal->stock_disponible}");
                }
            }
            
            Log::info('Stock comprometido MySQL actualizado correctamente');
            
            // INSERT MAEEDOOB - Observaciones y orden de compra
            $observacionVendedor = $cotizacion->observacion_vendedor ?? '';
            $numeroOrdenCompra = $cotizacion->numero_orden_compra ?? '';
            
            // Truncar observación a 250 caracteres
            $observacionTruncada = substr($observacionVendedor, 0, 250);
            
            // Truncar orden de compra a 40 caracteres
            $ordenCompraTruncada = substr($numeroOrdenCompra, 0, 40);
            
            // Obtener condición de pago del cliente
            $condicionPago = $this->obtenerCondicionPagoCliente($cotizacion->cliente_codigo);
            
            $insertMAEEDOOB = "
                INSERT INTO MAEEDOOB (
                    IDMAEEDO, OBDO, CPDO, OCDO
                ) VALUES (
                    {$siguienteId}, '{$observacionTruncada}', '{$condicionPago}', '{$ordenCompraTruncada}'
                )
            ";
            
            Log::info("SQL INSERT MAEEDOOB:");
            Log::info($insertMAEEDOOB);
            
            $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
            file_put_contents($tempFile, $insertMAEEDOOB . "\ngo\nquit");
            
            $command = "tsql -H " . env('SQLSRV_EXTERNAL_HOST') . " -p " . env('SQLSRV_EXTERNAL_PORT') . " -U " . env('SQLSRV_EXTERNAL_USERNAME') . " -P " . env('SQLSRV_EXTERNAL_PASSWORD') . " -D " . env('SQLSRV_EXTERNAL_DATABASE') . " < {$tempFile} 2>&1";
            $result = shell_exec($command);
            
            unlink($tempFile);
            
            if (str_contains($result, 'Msg') || str_contains($result, 'Error')) {
                Log::warning('Error insertando MAEEDOOB: ' . $result);
            } else {
                Log::info("✅ MAEEDOOB insertado correctamente");
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
            
            // Fecha de vencimiento
            $fechaVencimiento = date('Y-m-d', strtotime('+30 days'));
            
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
                    '{$sucursalCliente}', '{$cotizacion->cliente_codigo}', 'LIB',
                    'I', 'LIB', 'N', 'S',
                    GETDATE(), GETDATE(), GETDATE(), '{$cotizacion->fecha->format('Y-m-d H:i:s')}',
                    {$cotizacion->subtotal_neto}, 0, 0, 0,
                    '$', 'N', 1,
                    {$cotizacion->iva}, {$cotizacion->subtotal_neto}, {$cotizacion->total}, 0,
                    '', '{$codigoVendedor}', 1, GETDATE(), 1, 0,
                    0, '', '', GETDATE(), 'TABPP01P'
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
                    $ud02pr = trim($productoDB->UD02PR ?? 'CJ');
                    $udtrpr = ($rludpr > 1) ? 2 : 1;
                }
                
                $subtotal = $producto->cantidad * $producto->precio_unitario;
                
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
                        '{$producto->codigo_producto}', '{$producto->nombre_producto}',
                        {$producto->cantidad}, {$producto->cantidad},
                        'TABPP01P', '$', 'N', 1,
                        {$producto->precio_unitario}, {$producto->precio_unitario}, {$precioBruto}, {$precioBruto},
                        {$porcentajeDescuento}, {$valorDescuento}, {$subtotalConDescuento}, 19, {$ivaConDescuento}, {$totalConIVA},
                        'I', GETDATE(), '{$cotizacion->fecha->format('Y-m-d H:i:s')}', 1, '', 0,
                        {$precioMinimo}, {$precioNetoReal}, {$precioNetoReal}, 1, 0, 0,
                        0, 0, 0, '{$cotizacion->fecha->format('Y-m-d')}'
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
            $cotizacion->rechazar($user->id, $request->motivo, $rol);
            
            Log::info("Nota de venta {$cotizacion->id} rechazada por {$rol} {$user->id}");
            
            return response()->json([
                'success' => true,
                'message' => 'Nota de venta rechazada',
                'estado_aprobacion' => $cotizacion->estado_aprobacion
            ]);
        } catch (\Exception $e) {
            Log::error("Error rechazando nota de venta: " . $e->getMessage());
            return response()->json(['error' => 'Error al rechazar la nota de venta'], 500);
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

        if (!$user->hasRole('Compras') && !$user->hasRole('Picking')) {
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
        if ($user->hasRole('Picking')) return 'picking';
        
        return null;
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
        } elseif ($user->hasRole('Picking') && ($cotizacion->puedeAprobarPicking() || $cotizacion->estado_aprobacion === 'pendiente_picking')) {
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
        
        // Super Admin, Supervisor, Compras, Picking siempre pueden acceder
        if ($user->hasRole('Super Admin') || $user->hasRole('Supervisor') || 
            $user->hasRole('Compras') || $user->hasRole('Picking')) {
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
        // Crear nueva cotización
        $nuevaCotizacion = $cotizacionOriginal->replicate();
        $nuevaCotizacion->estado = 'pendiente_stock';
        $nuevaCotizacion->estado_aprobacion = 'pendiente';
        $nuevaCotizacion->fecha_creacion = now();
        $nuevaCotizacion->fecha_modificacion = now();
        $nuevaCotizacion->comentarios = "NVV separada por problemas de stock del producto: {$producto->producto_nombre}. Motivo: {$motivo}";
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
        $productos = $cotizacion->productos;
        $subtotal = $productos->sum(function($producto) {
            return $producto->precio_unitario * $producto->cantidad;
        });
        
        $descuento = $subtotal * ($cotizacion->descuento_porcentaje / 100);
        $total = $subtotal - $descuento;

        $cotizacion->update([
            'subtotal' => $subtotal,
            'descuento_monto' => $descuento,
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

        // Verificar permisos - solo Compras puede separar productos
        if (!$user->hasRole('Compras')) {
            return response()->json(['error' => 'No tienes permisos para realizar esta acción'], 403);
        }

        try {
            // Obtener los productos seleccionados
            $productosSeleccionados = $cotizacion->productos()->whereIn('id', $request->productos_ids)->get();
            
            if ($productosSeleccionados->isEmpty()) {
                return response()->json(['error' => 'No se encontraron productos válidos'], 400);
            }

            // Para el perfil Compras, permitir separar cualquier producto
            // (puede modificar cantidades después de la separación)
            if (!$user->hasRole('Compras')) {
                // Solo para otros roles, verificar problemas de stock
                $productosSinProblemas = $productosSeleccionados->filter(function($producto) {
                    return $producto->stock_disponible >= $producto->cantidad;
                });

                if ($productosSinProblemas->isNotEmpty()) {
                    $productos = $productosSinProblemas->pluck('nombre_producto')->implode(', ');
                    return response()->json(['error' => "Los siguientes productos no tienen problemas de stock: {$productos}"], 400);
                }
            }

            // Crear la nueva NVV duplicada con los productos seleccionados
            $nuevaCotizacion = $this->crearNvvDuplicadaMultiple($cotizacion, $productosSeleccionados, $request->motivo);
            
            // Eliminar los productos seleccionados de la NVV original
            $cotizacion->productos()->whereIn('id', $request->productos_ids)->delete();
            
            // Actualizar totales de la NVV original (que mantiene los productos no seleccionados)
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
    private function crearNvvDuplicadaMultiple($cotizacionOriginal, $productos, $motivo)
    {
        // Crear nueva cotización con los productos seleccionados
        $nuevaCotizacion = $cotizacionOriginal->replicate();
        $nuevaCotizacion->estado = 'pendiente_stock';
        $nuevaCotizacion->estado_aprobacion = 'pendiente';
        $nuevaCotizacion->created_at = now();
        $nuevaCotizacion->updated_at = now();
        $nuevaCotizacion->observaciones = "NVV creada con productos separados por problemas de stock. Motivo: {$motivo}";
        $nuevaCotizacion->nota_original_id = $cotizacionOriginal->id; // Referencia a la NVV original
        $nuevaCotizacion->save();

        // Duplicar los productos problemáticos
        foreach ($productos as $producto) {
            $nuevoProducto = $producto->replicate();
            $nuevoProducto->cotizacion_id = $nuevaCotizacion->id;
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
            'estado_nuevo' => 'pendiente',
            'fecha_accion' => now(),
            'comentarios' => "NVV creada con {$productos->count()} productos separados por problemas de stock. NVV original: #{$cotizacionOriginal->id}",
            'detalles_adicionales' => [
                'accion' => 'creada_por_separacion_productos',
                'cotizacion_original_id' => $cotizacionOriginal->id,
                'productos_count' => $productos->count(),
                'productos_nombres' => $productosNombres,
                'motivo' => $motivo,
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
     * Guardar cantidad a separar
     */
    public function guardarSeparar($id)
    {
        try {
            $request = request();
            $productoId = $request->producto_id;
            $cantidadSeparar = $request->cantidad_separar;
            $user = Auth::user();

            // Verificar permisos - solo Compras y Picking pueden separar
            if (!$user->hasRole('Compras') && !$user->hasRole('Picking')) {
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

        // Verificar permisos - solo Compras y Picking pueden separar
        if (!$user->hasRole('Compras') && !$user->hasRole('Picking')) {
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
            $nuevaCotizacion = $this->crearNvvConProductoSeparado($cotizacion, $producto, $cantidadSeparar, $request->motivo);

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
    private function crearNvvConProductoSeparado($cotizacionOriginal, $producto, $cantidadSeparar, $motivo)
    {
        // Crear nueva cotización
        $nuevaCotizacion = $cotizacionOriginal->replicate();
        $nuevaCotizacion->estado = 'pendiente_stock';
        $nuevaCotizacion->estado_aprobacion = 'pendiente';
        $nuevaCotizacion->created_at = now();
        $nuevaCotizacion->updated_at = now();
        $nuevaCotizacion->observaciones = "NVV creada con producto separado: {$producto->nombre_producto} (Cantidad: {$cantidadSeparar}). Motivo: {$motivo}";
        $nuevaCotizacion->nota_original_id = $cotizacionOriginal->id;
        $nuevaCotizacion->save();

        // Crear el producto separado con la cantidad especificada
        $nuevoProducto = $producto->replicate();
        $nuevoProducto->cotizacion_id = $nuevaCotizacion->id;
        $nuevoProducto->cantidad = $cantidadSeparar;
        $nuevoProducto->cantidad_separar = 0; // Resetear cantidad a separar
        $nuevoProducto->subtotal = $producto->precio_unitario * $cantidadSeparar;
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
            'estado_nuevo' => 'pendiente',
            'fecha_accion' => now(),
            'comentarios' => "NVV creada por separación de producto '{$producto->nombre_producto}' de la NVV #{$cotizacionOriginal->id}. Cantidad: {$cantidadSeparada}.",
            'detalles_adicionales' => [
                'accion' => 'crear_por_separacion',
                'cotizacion_origen_id' => $cotizacionOriginal->id,
                'producto_codigo' => $producto->codigo_producto,
                'producto_nombre' => $producto->nombre_producto,
                'cantidad_separada' => $cantidadSeparada,
                'motivo' => $motivo,
                'descripcion' => 'NVV creada por separación de producto'
            ]
        ]);
    }
    
    /**
     * Sincronizar stock desde SQL Server
     */
    public function sincronizarStock(Request $request, $id = null)
    {
        try {
            $stockService = new StockService();
            $productosSincronizados = $stockService->sincronizarStockDesdeSQLServer();
            
            $mensaje = "Stock sincronizado exitosamente. {$productosSincronizados} productos actualizados.";
            
            // Si es una petición AJAX, devolver JSON
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $mensaje,
                    'productos_sincronizados' => $productosSincronizados
                ]);
            }
            
            if ($id) {
                // Si viene desde una aprobación específica, redirigir de vuelta
                return redirect()->back()->with('success', $mensaje);
            }
            
            // Si no viene desde una vista específica, redirigir al index de aprobaciones
            return redirect()->route('aprobaciones.index')->with('success', $mensaje);
            
        } catch (\Exception $e) {
            Log::error('Error sincronizando stock: ' . $e->getMessage());
            $mensajeError = 'Error al sincronizar stock: ' . $e->getMessage();
            
            // Si es una petición AJAX, devolver JSON
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $mensajeError
                ], 500);
            }
            
            if ($id) {
                return redirect()->back()->with('error', $mensajeError);
            }
            
            return redirect()->route('aprobaciones.index')->with('error', $mensajeError);
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
