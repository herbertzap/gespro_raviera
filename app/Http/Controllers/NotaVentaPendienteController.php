<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\NotaVentaPendiente;
use App\Models\NotaVentaPendienteProducto;
use App\Models\StockComprometido;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NotaVentaPendienteController extends Controller
{
    /**
     * Listar notas de venta pendientes
     */
    public function index(Request $request)
    {
        $query = NotaVentaPendiente::with(['vendedor', 'productos']);
        
        // Filtros
        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }
        
        if ($request->filled('cliente')) {
            $query->where('cliente_nombre', 'LIKE', "%{$request->cliente}%");
        }
        
        if ($request->filled('vendedor')) {
            $query->where('vendedor_nombre', 'LIKE', "%{$request->vendedor}%");
        }
        
        if ($request->filled('problemas_stock')) {
            $query->where('tiene_problemas_stock', $request->problemas_stock);
        }
        
        // Solo supervisores pueden ver todas, vendedores solo las suyas
        if (!auth()->user()->hasRole('Supervisor')) {
            $query->where('vendedor_id', auth()->id());
        }
        
        $notasPendientes = $query->orderBy('created_at', 'desc')->paginate(20);
        
        return view('nota-venta-pendiente.index', compact('notasPendientes'));
    }
    
    /**
     * Mostrar detalles de una nota de venta pendiente
     */
    public function show($id)
    {
        $notaPendiente = NotaVentaPendiente::with(['vendedor', 'productos', 'cotizacion'])->findOrFail($id);
        
        // Verificar permisos
        if (!auth()->user()->hasRole('Supervisor') && $notaPendiente->vendedor_id !== auth()->id()) {
            abort(403, 'No tienes permisos para ver esta nota de venta pendiente');
        }
        
        return view('nota-venta-pendiente.show', compact('notaPendiente'));
    }
    
    /**
     * Aprobar nota de venta pendiente
     */
    public function aprobar(Request $request, $id)
    {
        $request->validate([
            'comentarios' => 'nullable|string|max:1000'
        ]);
        
        $notaPendiente = NotaVentaPendiente::with(['productos'])->findOrFail($id);
        
        // Verificar permisos
        if (!auth()->user()->hasRole('Supervisor')) {
            abort(403, 'Solo los supervisores pueden aprobar notas de venta pendientes');
        }
        
        try {
            DB::beginTransaction();
            
            // Verificar stock actual
            $productosSinStock = [];
            foreach ($notaPendiente->productos as $producto) {
                $stockDisponible = $this->obtenerStockDisponible($producto->codigo_producto);
                if ($stockDisponible < $producto->cantidad) {
                    $productosSinStock[] = [
                        'codigo' => $producto->codigo_producto,
                        'nombre' => $producto->nombre_producto,
                        'stock_disponible' => $stockDisponible,
                        'cantidad_solicitada' => $producto->cantidad
                    ];
                }
            }
            
            if (!empty($productosSinStock)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede aprobar: algunos productos siguen sin stock suficiente',
                    'productos_sin_stock' => $productosSinStock
                ], 400);
            }
            
            // Aprobar la nota de venta pendiente
            $notaPendiente->aprobar(auth()->id(), $request->comentarios);
            
            // Generar número de nota de venta
            $numeroNotaVenta = $this->generarNumeroNotaVenta();
            $notaPendiente->update(['numero_nota_venta' => $numeroNotaVenta]);
            
            // Crear nota de venta en SQL Server
            $this->crearNotaVentaEnSQLServer($notaPendiente);
            
            // Descontar stock físico
            foreach ($notaPendiente->productos as $producto) {
                $this->descontarStockFisico($producto->codigo_producto, $producto->cantidad);
            }
            
            // Liberar stock comprometido
            StockComprometido::where('cotizacion_id', $notaPendiente->cotizacion_id)
                ->update(['fecha_liberacion' => now(), 'motivo_liberacion' => 'Aprobada por supervisor']);
            
            DB::commit();
            
            Log::info("Nota de venta pendiente {$id} aprobada por supervisor " . auth()->user()->name);
            
            return response()->json([
                'success' => true,
                'message' => 'Nota de venta aprobada exitosamente',
                'numero_nota_venta' => $numeroNotaVenta
            ]);
            
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error aprobando nota de venta pendiente: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al aprobar nota de venta: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Rechazar nota de venta pendiente
     */
    public function rechazar(Request $request, $id)
    {
        $request->validate([
            'motivo' => 'required|string|max:1000'
        ]);
        
        $notaPendiente = NotaVentaPendiente::findOrFail($id);
        
        // Verificar permisos
        if (!auth()->user()->hasRole('Supervisor')) {
            abort(403, 'Solo los supervisores pueden rechazar notas de venta pendientes');
        }
        
        try {
            DB::beginTransaction();
            
            // Rechazar la nota de venta pendiente
            $notaPendiente->rechazar(auth()->id(), $request->motivo);
            
            // Liberar stock comprometido
            StockComprometido::where('cotizacion_id', $notaPendiente->cotizacion_id)
                ->update(['fecha_liberacion' => now(), 'motivo_liberacion' => 'Rechazada por supervisor']);
            
            DB::commit();
            
            Log::info("Nota de venta pendiente {$id} rechazada por supervisor " . auth()->user()->name);
            
            return response()->json([
                'success' => true,
                'message' => 'Nota de venta rechazada exitosamente'
            ]);
            
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error rechazando nota de venta pendiente: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al rechazar nota de venta: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Obtener stock disponible de un producto
     */
    private function obtenerStockDisponible($productoCodigo)
    {
        try {
            $host = env('SQLSRV_EXTERNAL_HOST');
            $port = env('SQLSRV_EXTERNAL_PORT', '1433');
            $database = env('SQLSRV_EXTERNAL_DATABASE');
            $username = env('SQLSRV_EXTERNAL_USERNAME');
            $password = env('SQLSRV_EXTERNAL_PASSWORD');
            
            $query = "
                SELECT TOP 1 
                    ISNULL(STFI1, 0) AS stock_fisico,
                    ISNULL(STOCNV1, 0) AS stock_comprometido,
                    (ISNULL(STFI1, 0) - ISNULL(STOCNV1, 0)) AS stock_disponible
                FROM MAEST
                WHERE KOPR = '{$productoCodigo}' 
                AND KOBO = '01'
            ";
            
            $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
            file_put_contents($tempFile, $query . "\ngo\nquit");
            
            $command = "tsql -H {$host} -p {$port} -U {$username} -P {$password} -D {$database} < {$tempFile} 2>&1";
            $output = shell_exec($command);
            
            unlink($tempFile);
            
            // Parsear resultado
            $lines = explode("\n", $output);
            foreach ($lines as $line) {
                $line = trim($line);
                if (preg_match('/^\s*(\d+)\s+(\d+)\s+(\d+)\s*$/', $line, $matches)) {
                    return (float)$matches[3]; // stock_disponible
                }
            }
            
            return 0;
            
        } catch (\Exception $e) {
            Log::error('Error obteniendo stock disponible: ' . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Generar número de nota de venta
     */
    private function generarNumeroNotaVenta()
    {
        $ultimaNota = DB::table('nota_ventas')->orderBy('id', 'desc')->first();
        $numero = $ultimaNota ? $ultimaNota->id + 1 : 1;
        return 'NV' . str_pad($numero, 6, '0', STR_PAD_LEFT);
    }
    
    /**
     * Crear nota de venta en SQL Server
     */
    private function crearNotaVentaEnSQLServer($notaPendiente)
    {
        // Aquí iría la lógica para crear la nota de venta en SQL Server
        // Por ahora solo logueamos
        Log::info("Creando nota de venta en SQL Server: {$notaPendiente->numero_nota_venta}");
    }
    
    /**
     * Descontar stock físico en SQL Server
     */
    private function descontarStockFisico($productoCodigo, $cantidad)
    {
        try {
            $host = env('SQLSRV_EXTERNAL_HOST');
            $port = env('SQLSRV_EXTERNAL_PORT', '1433');
            $database = env('SQLSRV_EXTERNAL_DATABASE');
            $username = env('SQLSRV_EXTERNAL_USERNAME');
            $password = env('SQLSRV_EXTERNAL_PASSWORD');
            
            $query = "
                UPDATE MAEST 
                SET STFI1 = STFI1 - {$cantidad}
                WHERE KOPR = '{$productoCodigo}' 
                AND KOBO = '01'
            ";
            
            $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
            file_put_contents($tempFile, $query . "\ngo\nquit");
            
            $command = "tsql -H {$host} -p {$port} -U {$username} -P {$password} -D {$database} < {$tempFile} 2>&1";
            $output = shell_exec($command);
            
            unlink($tempFile);
            
            Log::info("Stock físico descontado para producto {$productoCodigo}: {$cantidad} unidades");
            
        } catch (\Exception $e) {
            Log::error('Error descontando stock físico: ' . $e->getMessage());
            throw $e;
        }
    }
}
