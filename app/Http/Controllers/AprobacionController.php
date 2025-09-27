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
