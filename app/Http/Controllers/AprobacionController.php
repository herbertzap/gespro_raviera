<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cotizacion;
use App\Models\Cliente;
use App\Services\CobranzaService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

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
    public function index()
    {
        $user = Auth::user();
        $cotizaciones = collect();

        if ($user->hasRole('Supervisor')) {
            // Supervisor ve todas las notas pendientes de aprobación (crédito o stock)
            $cotizaciones = Cotizacion::whereIn('estado_aprobacion', ['pendiente', 'pendiente_picking'])
                ->where(function($query) {
                    $query->where('tiene_problemas_credito', true)
                          ->orWhere('tiene_problemas_stock', true);
                })
                ->with(['user', 'productos'])
                ->latest()
                ->paginate(15);
            $tipoAprobacion = 'supervisor';
        } elseif ($user->hasRole('Compras')) {
            $cotizaciones = Cotizacion::pendientesCompras()
                ->with(['user', 'productos'])
                ->latest()
                ->paginate(15);
            $tipoAprobacion = 'compras';
        } elseif ($user->hasRole('Picking')) {
            // Picking ve tanto notas con problemas de stock como sin problemas
            $cotizacionesConProblemas = Cotizacion::pendientesPicking()
                ->with(['user', 'productos'])
                ->latest()
                ->get();
            
            $cotizacionesSinProblemas = Cotizacion::pendientesPickingSinProblemas()
                ->with(['user', 'productos'])
                ->latest()
                ->get();
            
            $cotizaciones = $cotizacionesConProblemas->merge($cotizacionesSinProblemas);
            $tipoAprobacion = 'picking';
        } else {
            return redirect()->route('dashboard')->with('error', 'No tienes permisos para aprobar notas de venta');
        }

        return view('aprobaciones.index', compact('cotizaciones', 'tipoAprobacion'));
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
            
            // Aprobar en MySQL primero
            $cotizacion->aprobarPorPicking($user->id, $request->comentarios);
            
            Log::info("Cotización aprobada en MySQL, iniciando insert en SQL Server");
            
            // Insertar en SQL Server de forma asíncrona
            Log::info("📝 PASO CRÍTICO: Iniciando insert en SQL Server...");
            Log::info("🧪 USANDO FUNCIÓN DE PRUEBA CON NUDO FIJO");
            $startTime = microtime(true);
            
            try {
                $resultado = $this->insertarEnSQLServerTest($cotizacion);
                $endTime = microtime(true);
                $duration = $endTime - $startTime;
                Log::info("⏱️ PASO CRÍTICO: Insert completado en " . round($duration, 2) . " segundos");
            } catch (Exception $e) {
                Log::error("❌ ERROR en insert SQL Server: " . $e->getMessage());
                // Continuar sin fallar - la NVV ya está aprobada en MySQL
                $resultado = ['success' => false, 'message' => 'Error en SQL Server: ' . $e->getMessage()];
            }
            
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
            $queryNudo = "SELECT TOP 1 NUDO FROM MAEEDO WHERE TIDO = 'NVV' AND ISNUMERIC(NUDO) = 1 ORDER BY IDMAEEDO DESC";
            
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
            
            Log::info("=== DATOS PARA INSERT NVV ===");
            Log::info("Cotización ID: {$cotizacion->id}");
            Log::info("Cliente: {$cotizacion->cliente_codigo} - {$cotizacion->cliente_nombre}");
            Log::info("Sucursal Cliente (SUENDO): '{$sucursalCliente}'");
            Log::info("Vendedor: {$codigoVendedor} - {$nombreVendedor}");
            Log::info("Total: {$cotizacion->total}");
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
                    GETDATE(), GETDATE(), GETDATE(), '{$cotizacion->fecha_despacho->format('Y-m-d H:i:s')}',
                    {$cotizacion->subtotal_neto}, 0, 0, 0,
                    '$', 'N', 1,
                    {$cotizacion->iva}, {$cotizacion->subtotal_neto}, {$cotizacion->total}, 0,
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
            
            // Insertar detalles en MAEDDO
            foreach ($cotizacion->productos as $index => $producto) {
                $lineaId = $index + 1;
                
                // Obtener datos del producto desde la tabla productos en MySQL
                $productoDB = \App\Models\Producto::where('KOPR', $producto->codigo_producto)->first();
                
                // Valores por defecto si no existe el producto
                $udtrpr = 1; // Por defecto venta por unidad
                $rludpr = 1; // Por defecto 1 unidad por caja
                $ud01pr = 'UN'; // Primera unidad
                $ud02pr = 'CJ'; // Segunda unidad
                
                if ($productoDB) {
                    // Si el producto existe, usar sus datos
                    $rludpr = $productoDB->RLUD ?? 1;
                    $ud01pr = trim($productoDB->UD01PR ?? 'UN');
                    $ud02pr = trim($productoDB->UD02PR ?? 'CJ');
                    // Si RLUD > 1, probablemente se vende por caja
                    $udtrpr = ($rludpr > 1) ? 2 : 1;
                }
                
                // Calcular valores con descuento e IVA
                $subtotalBruto = $producto->cantidad * $producto->precio_unitario;
                $porcentajeDescuento = $producto->descuento_porcentaje ?? 0;
                $valorDescuento = $producto->descuento_valor ?? 0;
                $subtotalConDescuento = $producto->subtotal_con_descuento ?? $subtotalBruto;
                
                // IVA (19%)
                $porcentajeIVA = 19;
                $valorIVA = $producto->iva_valor ?? ($subtotalConDescuento * 0.19);
                
                // Precios con IVA
                $precioConIVA = $producto->precio_unitario * 1.19;
                $precioConIVARedondeado = round($precioConIVA, 2);
                
                Log::info("=== PRODUCTO #{$lineaId} ===");
                Log::info("Código: {$producto->codigo_producto}");
                Log::info("Nombre: {$producto->nombre_producto}");
                Log::info("Cantidad: {$producto->cantidad}");
                Log::info("Precio Unitario: {$producto->precio_unitario}");
                Log::info("UDTRPR (1=UN, 2=CJ): {$udtrpr}");
                Log::info("RLUDPR (unidades por caja): {$rludpr}");
                Log::info("UD01PR: {$ud01pr}");
                Log::info("UD02PR: {$ud02pr}");
                Log::info("Descuento %: {$porcentajeDescuento}");
                Log::info("Descuento $: {$valorDescuento}");
                Log::info("Subtotal con descuento: {$subtotalConDescuento}");
                Log::info("IVA %: {$porcentajeIVA}");
                Log::info("IVA $: {$valorIVA}");
                Log::info("Precio con IVA: {$precioConIVARedondeado}");
                
                // INSERT simplificado con solo los campos esenciales
                $insertMAEDDO = "
                    INSERT INTO MAEDDO (
                        IDMAEEDO, EMPRESA, TIDO, NUDO, ENDO, SUENDO,
                        LILG, NULIDO, KOPRCT, NOKOPR, 
                        CAPRCO1, PPPRNE, VANELI, VABRLI,
                        KOFULIDO, UDTRPR, RLUDPR, UD01PR, UD02PR,
                        FEEMLI, FEERLI
                    ) VALUES (
                        {$siguienteId}, '01', 'NVV', '{$nudoFormateado}',
                        '{$cotizacion->cliente_codigo}', '{$sucursalCliente}',
                        'SI', '{$lineaId}', '{$producto->codigo_producto}', '{$producto->nombre_producto}',
                        {$producto->cantidad}, {$producto->precio_unitario}, {$subtotalBruto}, {$subtotalBruto},
                        '{$codigoVendedor}', {$udtrpr}, {$rludpr}, '{$ud01pr}', '{$ud02pr}',
                        GETDATE(), '{$cotizacion->fecha_despacho->format('Y-m-d H:i:s')}'
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
                    GETDATE(), GETDATE(), GETDATE(), '{$cotizacion->fecha_despacho->format('Y-m-d H:i:s')}',
                    {$cotizacion->subtotal_neto}, 0, 0, 0,
                    '$', 'N', 1,
                    {$cotizacion->iva}, {$cotizacion->subtotal_neto}, {$cotizacion->total}, 0,
                    '', '{$codigoVendedor}', 1, GETDATE(), 1, 0,
                    0, '', '', GETDATE(), 'TABPP01P'
                )
                
                SET IDENTITY_INSERT MAEEDO OFF
            ";
            
            Log::info("🧪 Ejecutando INSERT MAEEDO...");
            
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
                $ud02pr = 'CJ';
                
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
                        LILG, NULIDO, KOPRCT, NOKOPR, 
                        CAPRCO1, PPPRNE, VANELI, VABRLI,
                        KOFULIDO, UDTRPR, RLUDPR, UD01PR, UD02PR,
                        FEEMLI, FEERLI
                    ) VALUES (
                        {$siguienteId}, '01', 'NVV', '{$nudoFormateado}',
                        '{$cotizacion->cliente_codigo}', '{$sucursalCliente}',
                        'SI', '1', '{$producto->codigo_producto}', '{$producto->nombre_producto}',
                        {$producto->cantidad}, {$producto->precio_unitario}, {$subtotal}, {$subtotal},
                        '{$codigoVendedor}', {$udtrpr}, {$rludpr}, '{$ud01pr}', '{$ud02pr}',
                        GETDATE(), '{$cotizacion->fecha_despacho->format('Y-m-d H:i:s')}'
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
        
        // Verificar permisos
        if (!$user->hasRole('Supervisor') && !$user->hasRole('Compras') && !$user->hasRole('Picking') && !$user->hasRole('Super Admin')) {
            return redirect()->route('dashboard')->with('error', 'Acceso no autorizado');
        }

        $cotizacion = Cotizacion::with(['user', 'productos'])->findOrFail($id);
        
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

}
