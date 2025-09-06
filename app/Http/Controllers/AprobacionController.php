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
            return response()->json(['error' => 'No tienes permisos para aprobar como supervisor'], 403);
        }

        if (!$cotizacion->puedeAprobarSupervisor()) {
            return response()->json(['error' => 'La nota de venta no puede ser aprobada por supervisor'], 400);
        }

        try {
            $cotizacion->aprobarPorSupervisor($user->id, $request->comentarios);
            
            // Registrar en el historial
            \App\Services\HistorialCotizacionService::registrarAprobacionSupervisor($cotizacion, $request->comentarios);
            
            Log::info("Nota de venta {$cotizacion->id} aprobada por supervisor {$user->id}");
            
            return response()->json([
                'success' => true,
                'message' => 'Nota de venta aprobada por supervisor',
                'estado_aprobacion' => $cotizacion->estado_aprobacion
            ]);
        } catch (\Exception $e) {
            Log::error("Error aprobando nota de venta por supervisor: " . $e->getMessage());
            return response()->json(['error' => 'Error al aprobar la nota de venta'], 500);
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
            return response()->json(['error' => 'No tienes permisos para aprobar como compras'], 403);
        }

        if (!$cotizacion->puedeAprobarCompras()) {
            return response()->json(['error' => 'La nota de venta no puede ser aprobada por compras'], 400);
        }

        try {
            $cotizacion->aprobarPorCompras($user->id, $request->comentarios);
            
            Log::info("Nota de venta {$cotizacion->id} aprobada por compras {$user->id}");
            
            return response()->json([
                'success' => true,
                'message' => 'Nota de venta aprobada por compras',
                'estado_aprobacion' => $cotizacion->estado_aprobacion
            ]);
        } catch (\Exception $e) {
            Log::error("Error aprobando nota de venta por compras: " . $e->getMessage());
            return response()->json(['error' => 'Error al aprobar la nota de venta'], 500);
        }
    }

    /**
     * Aprobar nota de venta por Picking
     */
    public function aprobarPicking(Request $request, $id)
    {
        $request->validate([
            'comentarios' => 'nullable|string|max:500',
            'validar_stock_real' => 'required|boolean'
        ]);

        $cotizacion = Cotizacion::findOrFail($id);
        $user = Auth::user();

        if (!$user->hasRole('Picking')) {
            return response()->json(['error' => 'No tienes permisos para aprobar como picking'], 403);
        }

        if (!$cotizacion->puedeAprobarPicking() && $cotizacion->estado_aprobacion !== 'pendiente_picking') {
            return response()->json(['error' => 'La nota de venta no puede ser aprobada por picking'], 400);
        }

        try {
            // Si se requiere validar stock real
            if ($request->validar_stock_real) {
                $stockValidado = $this->validarStockReal($cotizacion);
                
                if (!$stockValidado['valido']) {
                    return response()->json([
                        'error' => 'Stock insuficiente en algunos productos',
                        'detalle' => $stockValidado['detalle']
                    ], 400);
                }
            }

            $cotizacion->aprobarPorPicking($user->id, $request->comentarios);
            
            // Aquí se insertaría en SQL Server (esto se implementará después)
            // $this->insertarEnSQLServer($cotizacion);
            
            Log::info("Nota de venta {$cotizacion->id} aprobada por picking {$user->id}");
            
            return response()->json([
                'success' => true,
                'message' => 'Nota de venta aprobada por picking e insertada en sistema',
                'estado_aprobacion' => $cotizacion->estado_aprobacion
            ]);
        } catch (\Exception $e) {
            Log::error("Error aprobando nota de venta por picking: " . $e->getMessage());
            return response()->json(['error' => 'Error al aprobar la nota de venta'], 500);
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
        
        // Obtener resumen de tiempos
        $resumenTiempos = \App\Services\HistorialCotizacionService::obtenerResumenTiempos($cotizacion);

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
}
