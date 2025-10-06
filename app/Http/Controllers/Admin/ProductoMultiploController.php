<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Producto;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ProductoMultiploController extends Controller
{
    /**
     * Constructor - Solo el perfil Compras puede acceder
     */
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (!auth()->user()->hasRole('Compras') && !auth()->user()->hasRole('Super Admin')) {
                abort(403, 'No tienes permisos para acceder a esta sección.');
            }
            return $next($request);
        });
    }

    /**
     * Mostrar vista de gestión de múltiplos
     */
    public function index()
    {
        $pageSlug = 'multiplos-productos';
        
        // Obtener productos con múltiplos diferentes de 1
        $productosConMultiplo = Producto::where('multiplo_venta', '>', 1)
            ->orderBy('KOPR')
            ->get();
        
        return view('admin.productos.multiplos', compact('pageSlug', 'productosConMultiplo'));
    }

    /**
     * Cargar múltiplos desde archivo Excel
     */
    public function cargarExcel(Request $request)
    {
        $request->validate([
            'archivo_excel' => 'required|file|mimes:xlsx,xls|max:10240' // Max 10MB
        ]);

        try {
            $archivo = $request->file('archivo_excel');
            $spreadsheet = IOFactory::load($archivo->getPathname());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            $actualizados = 0;
            $noEncontrados = [];
            $errores = [];

            // Empezar desde la fila 2 (saltamos encabezado)
            for ($i = 1; $i < count($rows); $i++) {
                $row = $rows[$i];
                
                // Verificar que la fila tenga datos
                if (empty($row[0])) {
                    continue;
                }

                $sku = trim($row[0]); // Columna SKU
                $multiploVenta = !empty($row[2]) ? (int)$row[2] : 1; // Columna MINIMO DE VENTA

                // Buscar producto por KOPR
                $producto = Producto::where('KOPR', $sku)->first();

                if ($producto) {
                    $producto->multiplo_venta = $multiploVenta > 0 ? $multiploVenta : 1;
                    $producto->save();
                    $actualizados++;
                } else {
                    $noEncontrados[] = $sku;
                }
            }

            $mensaje = "Se actualizaron {$actualizados} productos correctamente.";
            
            if (count($noEncontrados) > 0) {
                $mensaje .= " No se encontraron " . count($noEncontrados) . " productos: " . implode(', ', array_slice($noEncontrados, 0, 10));
                if (count($noEncontrados) > 10) {
                    $mensaje .= " y " . (count($noEncontrados) - 10) . " más...";
                }
            }

            return redirect()->route('admin.productos.multiplos')
                ->with('success', $mensaje);

        } catch (\Exception $e) {
            return redirect()->route('admin.productos.multiplos')
                ->with('error', 'Error al procesar el archivo: ' . $e->getMessage());
        }
    }

    /**
     * Actualizar múltiplo individual
     */
    public function actualizar(Request $request, $id)
    {
        $request->validate([
            'multiplo_venta' => 'required|integer|min:1|max:1000'
        ]);

        try {
            $producto = Producto::findOrFail($id);
            $producto->multiplo_venta = $request->multiplo_venta;
            $producto->save();

            return response()->json([
                'success' => true,
                'message' => 'Múltiplo actualizado correctamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar múltiplo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Restablecer múltiplo a 1
     */
    public function restablecer($id)
    {
        try {
            $producto = Producto::findOrFail($id);
            $producto->multiplo_venta = 1;
            $producto->save();

            return response()->json([
                'success' => true,
                'message' => 'Múltiplo restablecido a 1'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al restablecer múltiplo: ' . $e->getMessage()
            ], 500);
        }
    }
}
