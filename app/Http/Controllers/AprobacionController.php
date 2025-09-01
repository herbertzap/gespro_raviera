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
            $cotizaciones = Cotizacion::pendientesSupervisor()
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

        return view('aprobaciones.show', compact('cotizacion', 'puedeAprobar', 'tipoAprobacion'));
    }
}
