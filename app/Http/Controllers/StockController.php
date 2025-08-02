<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\StockLocal;
use App\Services\StockService;
use Illuminate\Support\Facades\Log;

class StockController extends Controller
{
    /**
     * Listar productos con stock
     */
    public function index(Request $request)
    {
        $query = StockLocal::query();
        
        // Filtros
        if ($request->filled('buscar')) {
            $buscar = $request->get('buscar');
            $query->where(function($q) use ($buscar) {
                $q->where('codigo_producto', 'LIKE', "%{$buscar}%")
                  ->orWhere('nombre_producto', 'LIKE', "%{$buscar}%");
            });
        }
        
        if ($request->filled('bodega')) {
            $query->where('codigo_bodega', $request->get('bodega'));
        }
        
        if ($request->filled('stock')) {
            switch ($request->get('stock')) {
                case 'con_stock':
                    $query->where('stock_disponible', '>', 0);
                    break;
                case 'sin_stock':
                    $query->where('stock_disponible', '<=', 0);
                    break;
                case 'bajo_stock':
                    $query->where('stock_disponible', '<=', 10)->where('stock_disponible', '>', 0);
                    break;
            }
        }
        
        // Ordenamiento
        $orden = $request->get('orden', 'nombre_producto');
        $direccion = $request->get('direccion', 'asc');
        $query->orderBy($orden, $direccion);
        
        $productos = $query->paginate(20);
        
        return view('stock.index', compact('productos'));
    }
    
    /**
     * Editar stock de un producto
     */
    public function edit($id)
    {
        $producto = StockLocal::findOrFail($id);
        return view('stock.edit', compact('producto'));
    }
    
    /**
     * Actualizar stock
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'stock_fisico' => 'required|numeric|min:0',
            'precio_venta' => 'required|numeric|min:0',
            'activo' => 'boolean'
        ]);
        
        try {
            $producto = StockLocal::findOrFail($id);
            
            $producto->update([
                'stock_fisico' => $request->stock_fisico,
                'stock_disponible' => $request->stock_fisico - $producto->stock_comprometido,
                'precio_venta' => $request->precio_venta,
                'activo' => $request->has('activo'),
                'ultima_actualizacion' => now()
            ]);
            
            Log::info("Stock actualizado: {$producto->codigo_producto} - Stock: {$request->stock_fisico}");
            
            return redirect()->route('stock.index')
                           ->with('success', 'Stock actualizado correctamente');
            
        } catch (\Exception $e) {
            Log::error('Error actualizando stock: ' . $e->getMessage());
            return back()->with('error', 'Error al actualizar stock');
        }
    }
    
    /**
     * Sincronizar stock desde SQL Server
     */
    public function sincronizar()
    {
        try {
            $stockService = new StockService();
            $productosSincronizados = $stockService->sincronizarStockDesdeSQLServer();
            
            return redirect()->route('stock.index')
                           ->with('success', "Stock sincronizado: {$productosSincronizados} productos actualizados");
            
        } catch (\Exception $e) {
            Log::error('Error sincronizando stock: ' . $e->getMessage());
            return back()->with('error', 'Error al sincronizar stock');
        }
    }
    
    /**
     * Ver productos sin stock (para notificaciones)
     */
    public function productosSinStock()
    {
        $productos = StockLocal::where('stock_disponible', '<=', 0)
                              ->where('activo', true)
                              ->orderBy('nombre_producto')
                              ->get();
        
        return view('stock.sin_stock', compact('productos'));
    }
    
    /**
     * Ver productos con bajo stock
     */
    public function productosBajoStock()
    {
        $productos = StockLocal::where('stock_disponible', '<=', 10)
                              ->where('stock_disponible', '>', 0)
                              ->where('activo', true)
                              ->orderBy('stock_disponible')
                              ->get();
        
        return view('stock.bajo_stock', compact('productos'));
    }
}
