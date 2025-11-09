<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cotizacion;
use App\Models\Cliente;
use App\Models\Producto;
use App\Models\CotizacionProducto;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class CotizacionSimpleController extends Controller
{
    public function __construct()
    {
        // Cotizaciones son más accesibles - solo requieren autenticación
        $this->middleware('auth');
    }

    public function nueva(Request $request)
    {
        \Log::info('🔍 Método nueva() llamado - COTIZACIÓN SIMPLE');
        
        $clienteCodigo = $request->get('cliente');
        $clienteNombre = $request->get('nombre');
        
        if (!$clienteCodigo) {
            \Log::warning('❌ Intento de crear cotización sin cliente asociado');
            return redirect()->route('cotizaciones.index')
                ->with('error', 'Debe seleccionar un cliente antes de crear una cotización.');
        }
        
        // Obtener datos del cliente
        $cliente = Cliente::where('codigo_cliente', $clienteCodigo)->first();
        
                if (!$cliente) {
            \Log::warning('❌ Cliente no encontrado: ' . $clienteCodigo);
            return redirect()->route('cotizaciones.index')
                ->with('error', 'Cliente no encontrado.');
        }
        
        // Para cotizaciones simples, no necesitamos validaciones complejas
        $alertas = [];
        $puedeGenerarNotaVenta = true; // Las cotizaciones siempre pueden convertirse a NVV
        
        \Log::info('✅ Cliente encontrado: ' . $cliente->nombre_cliente);
        
        return view('cotizaciones.nueva', compact('cliente', 'alertas', 'puedeGenerarNotaVenta'))
            ->with('pageSlug', 'nueva-cotizacion');
    }
    
        public function guardar(Request $request)
    {
        \Log::info('🚀 INICIANDO PROCESO DE GUARDAR COTIZACIÓN SIMPLE');
        \Log::info('📋 Datos recibidos:', [
            'cliente_codigo' => $request->cliente_codigo,
            'cliente_nombre' => $request->cliente_nombre,
            'productos_count' => count($request->productos ?? []),
            'user_id' => auth()->id()
        ]);
        
        $request->validate([
            'cliente_codigo' => 'required',
            'cliente_nombre' => 'required',
            'productos' => 'required|array|min:1',
            'productos.*.codigo' => 'required',
            'productos.*.cantidad' => 'required|numeric|min:0.01',
            'productos.*.precio' => 'required|numeric|min:0'
        ]);
        
        \Log::info('✅ Validación de datos completada');
        
        try {
            \Log::info('🔄 INICIANDO TRANSACCIÓN DE BASE DE DATOS');
            DB::beginTransaction();
            
            // 1. Obtener información del cliente
            $cliente = Cliente::where('codigo_cliente', $request->cliente_codigo)->first();
            
            // 2. Calcular totales por producto y generales
            \Log::info('💰 CALCULANDO TOTALES');
            $subtotalSinDescuentos = 0;
            $descuentoTotal = 0;
            $ivaTotal = 0;
            
            foreach ($request->productos as $index => $producto) {
                // Calcular valores por producto
                $precioBase = $producto['cantidad'] * $producto['precio'];
                $descuentoPorcentaje = $producto['descuento'] ?? 0;
                $descuentoValor = $precioBase * ($descuentoPorcentaje / 100);
                $subtotalConDescuento = $precioBase - $descuentoValor;
                $ivaProducto = $subtotalConDescuento * 0.19;
                $totalProducto = $subtotalConDescuento + $ivaProducto;
                
                // Acumular totales
                $subtotalSinDescuentos += $precioBase;
                $descuentoTotal += $descuentoValor;
                $ivaTotal += $ivaProducto;
                
                \Log::info("📦 Producto {$index}: {$producto['codigo']} - Cantidad: {$producto['cantidad']} - Precio: {$producto['precio']} - Descuento: {$descuentoPorcentaje}% - IVA: {$ivaProducto}");
            }
            
            // Calcular subtotal neto (después de descuentos)
            $subtotalNeto = $subtotalSinDescuentos - $descuentoTotal;
            
            // Total final con IVA
            $total = $subtotalNeto + $ivaTotal;
            \Log::info("💰 Subtotal sin descuentos: {$subtotalSinDescuentos}, Descuento total: {$descuentoTotal}, Subtotal neto: {$subtotalNeto}, IVA total: {$ivaTotal}, Total: {$total}");
            
            // 3. Crear cotización en base de datos local
            \Log::info('📝 CREANDO COTIZACIÓN EN TABLA cotizaciones');
            
            $cotizacionData = [
                'tipo_documento' => 'cotizacion',
                'user_id' => auth()->id(),
                'cliente_codigo' => $request->cliente_codigo,
                'cliente_nombre' => $request->cliente_nombre,
                'cliente_direccion' => $cliente->direccion ?? null,
                'cliente_telefono' => $cliente->telefono ?? null,
                'cliente_lista_precios' => $cliente->lista_precios_codigo ?? null,
                'fecha' => now(),
                'subtotal' => $subtotalSinDescuentos,
                'descuento_global' => $descuentoTotal,
                'subtotal_neto' => $subtotalNeto,
                'iva' => $ivaTotal,
                'total' => $total,
                'observaciones' => $request->observaciones ?? '',
                'fecha_despacho' => now()->startOfDay(),
                // Campos específicos para cotizaciones (simplificados)
                'numero_orden_compra' => null,
                'observacion_vendedor' => null,
                'solicitar_descuento_extra' => false,
                'estado' => 'borrador',
                // Las cotizaciones NO requieren aprobación
                'requiere_aprobacion' => false,
                'estado_aprobacion' => 'pendiente' // Valor por defecto, pero no se usa para cotizaciones
            ];
            
            \Log::info('📝 Datos de cotización a crear:', $cotizacionData);
            
            $cotizacion = Cotizacion::create($cotizacionData);
            \Log::info("✅ Cotización creada exitosamente - ID: {$cotizacion->id}");
            
            // 4. Crear detalles de cotización
            \Log::info('📦 CREANDO DETALLES DE COTIZACIÓN');
            
            foreach ($request->productos as $producto) {
                $precioBase = $producto['cantidad'] * $producto['precio'];
                $descuentoPorcentaje = $producto['descuento'] ?? 0;
                $descuentoValor = $precioBase * ($descuentoPorcentaje / 100);
                $subtotalConDescuento = $precioBase - $descuentoValor;
                $ivaProducto = $subtotalConDescuento * 0.19;
                $totalProducto = $subtotalConDescuento + $ivaProducto;
                
                CotizacionProducto::create([
                    'cotizacion_id' => $cotizacion->id,
                    'codigo_producto' => $producto['codigo'],
                    'nombre_producto' => $producto['nombre'],
                    'cantidad' => $producto['cantidad'],
                    'precio_unitario' => $producto['precio'],
                    'subtotal' => $precioBase,
                    'descuento_porcentaje' => $descuentoPorcentaje,
                    'descuento_valor' => $descuentoValor,
                    'subtotal_con_descuento' => $subtotalConDescuento,
                    'iva_valor' => $ivaProducto,
                    'total_producto' => $totalProducto
                ]);
            }
            
            \Log::info('💾 CONFIRMANDO TRANSACCIÓN');
            DB::commit();
            \Log::info('✅ TRANSACCIÓN CONFIRMADA EXITOSAMENTE');
            
            $response = [
                'success' => true,
                'message' => 'Cotización guardada exitosamente',
                'cotizacion_id' => $cotizacion->id,
                'estado' => 'borrador',
                'requiere_aprobacion' => false,
                'total' => $total
            ];
            
            \Log::info('🎉 RESPUESTA FINAL:', $response);
            return response()->json($response);
            
        } catch (\Exception $e) {
            \Log::error('❌ ERROR EN PROCESO DE GUARDAR COTIZACIÓN');
            \Log::error('❌ Mensaje de error: ' . $e->getMessage());
            \Log::error('❌ Archivo: ' . $e->getFile());
            \Log::error('❌ Línea: ' . $e->getLine());
            \Log::error('❌ Stack trace: ' . $e->getTraceAsString());
            
            \Log::info('🔄 REVERTIENDO TRANSACCIÓN');
            DB::rollback();
            \Log::info('✅ TRANSACCIÓN REVERTIDA');
            
            $errorResponse = [
                'success' => false,
                'message' => 'Error al guardar la cotización: ' . $e->getMessage()
            ];
            
            \Log::error('❌ RESPUESTA DE ERROR:', $errorResponse);
            return response()->json($errorResponse, 500);
        }
    }
    
    public function editar($id)
    {
        try {
            $cotizacion = Cotizacion::with('productos')->findOrFail($id);
            
            // Verificar que sea una cotización, no una NVV
            if ($cotizacion->tipo_documento !== 'cotizacion') {
                abort(404, 'No se encontró la cotización solicitada');
            }
            
            // Obtener datos del cliente
            $clienteDB = Cliente::where('codigo_cliente', $cotizacion->cliente_codigo)->first();
            
            // Preparar objeto cliente compatible con la vista
            $cliente = (object) [
                'codigo' => $cotizacion->cliente_codigo ?? '',
                'nombre' => $cotizacion->cliente_nombre ?? 'Cliente no asignado',
                'direccion' => $cotizacion->cliente_direccion ?? ($clienteDB->direccion ?? ''),
                'telefono' => $cotizacion->cliente_telefono ?? ($clienteDB->telefono ?? ''),
                'email' => $clienteDB->email ?? '',
                'region' => $clienteDB->region ?? '',
                'comuna' => $clienteDB->comuna ?? '',
                'lista_precios_codigo' => $cotizacion->cliente_lista_precios ?? ($clienteDB->lista_precios_codigo ?? '01P'),
                'lista_precios_nombre' => 'Lista Precios ' . ($cotizacion->cliente_lista_precios ?? ($clienteDB->lista_precios_codigo ?? '01P')),
                'bloqueado' => $clienteDB->bloqueado ?? false,
                'puede_generar_nota_venta' => true
            ];
            
            // Preparar productos de la cotización para el frontend
            $productosCotizacion = [];
            
            foreach ($cotizacion->productos as $producto) {
                // Obtener información adicional del producto desde la tabla productos
                $productoDB = DB::table('productos')
                    ->where('KOPR', $producto->codigo_producto)
                    ->first();
                
                // Determinar lista de precios para obtener descuento máximo
                $listaPrecios = $cotizacion->cliente_lista_precios ?? '01P';
                $descuentoMaximo = 0;
                $multiplo = 1;
                $unidad = 'UN';
                
                if ($productoDB) {
                    // Obtener múltiplo de venta
                    $multiplo = $productoDB->MUVECODI ?? 1;
                    if ($multiplo <= 0) $multiplo = 1;
                    
                    // Obtener unidad de medida
                    $unidad = $productoDB->KOPRUDEN ?? 'UN';
                    
                    // Obtener descuento máximo según lista de precios
                    if ($listaPrecios === '01P' || $listaPrecios === '01') {
                        $descuentoMaximo = $productoDB->descuento_maximo_01p ?? 0;
                    } elseif ($listaPrecios === '02P' || $listaPrecios === '02') {
                        $descuentoMaximo = $productoDB->descuento_maximo_02p ?? 0;
                    } elseif ($listaPrecios === '03P' || $listaPrecios === '03') {
                        $descuentoMaximo = $productoDB->descuento_maximo_03p ?? 0;
                    } elseif ($listaPrecios === '04P' || $listaPrecios === '04') {
                        $descuentoMaximo = $productoDB->descuento_maximo_04p ?? 0;
                    } elseif ($listaPrecios === '05P' || $listaPrecios === '05') {
                        $descuentoMaximo = $productoDB->descuento_maximo_05p ?? 0;
                    }
                }
                
                $productosCotizacion[] = [
                    'codigo' => $producto->codigo_producto,
                    'nombre' => $producto->nombre_producto,
                    'cantidad' => $producto->cantidad,
                    'precio' => floatval($producto->precio_unitario),
                    'subtotal' => floatval($producto->subtotal),
                    'descuento' => floatval($producto->descuento_porcentaje ?? 0),
                    'descuentoMaximo' => floatval($descuentoMaximo),
                    'multiplo' => intval($multiplo),
                    'stock_disponible' => $producto->stock_disponible ?? 0,
                    'stock_suficiente' => $producto->stock_suficiente ?? true,
                    'unidad' => $unidad,
                    'stock' => $producto->stock_disponible ?? 0 // Alias para compatibilidad con frontend
                ];
            }
            
            \Log::info('Editando cotización ID: ' . $id);
            \Log::info('Productos encontrados: ' . count($productosCotizacion));
            
            $alertas = [];
            $puedeGenerarNotaVenta = true;
            
            return view('cotizaciones.editar', compact('cotizacion', 'cliente', 'productosCotizacion', 'alertas', 'puedeGenerarNotaVenta'))
                ->with('pageSlug', 'editar-cotizacion');
                
        } catch (\Exception $e) {
            \Log::error('Error editando cotización: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            abort(500, 'Error editando cotización: ' . $e->getMessage());
        }
    }

    public function actualizar(Request $request, $id)
    {
        \Log::info('🚀 INICIANDO PROCESO DE ACTUALIZAR COTIZACIÓN SIMPLE');
        
        $cotizacion = Cotizacion::findOrFail($id);
        
        // Verificar que sea una cotización, no una NVV
        if ($cotizacion->tipo_documento !== 'cotizacion') {
                return response()->json([
                    'success' => false,
                'message' => 'No se puede actualizar una nota de venta desde el controlador de cotizaciones'
                ], 400);
            }
            
        $request->validate([
            'cliente_codigo' => 'required',
            'cliente_nombre' => 'required',
            'productos' => 'required|array|min:1',
            'productos.*.codigo' => 'required',
            'productos.*.cantidad' => 'required|numeric|min:0.01',
            'productos.*.precio' => 'required|numeric|min:0'
        ]);
        
        try {
            DB::beginTransaction();
            
            // 1. Obtener información del cliente
            $cliente = Cliente::where('codigo_cliente', $request->cliente_codigo)->first();
            
            // 2. Calcular totales
            $subtotalSinDescuentos = 0;
            $descuentoTotal = 0;
            $ivaTotal = 0;
            
            foreach ($request->productos as $producto) {
                $precioBase = $producto['cantidad'] * $producto['precio'];
                $descuentoPorcentaje = $producto['descuento'] ?? 0;
                $descuentoValor = $precioBase * ($descuentoPorcentaje / 100);
                $subtotalConDescuento = $precioBase - $descuentoValor;
                $ivaProducto = $subtotalConDescuento * 0.19;
                
                $subtotalSinDescuentos += $precioBase;
                $descuentoTotal += $descuentoValor;
                $ivaTotal += $ivaProducto;
            }
            
            $subtotalNeto = $subtotalSinDescuentos - $descuentoTotal;
            $total = $subtotalNeto + $ivaTotal;
            
            // 3. Actualizar cotización
            $cotizacion->update([
                'cliente_codigo' => $request->cliente_codigo,
                'cliente_nombre' => $request->cliente_nombre,
                'cliente_direccion' => $cliente->direccion ?? null,
                'cliente_telefono' => $cliente->telefono ?? null,
                'cliente_lista_precios' => $cliente->lista_precios_codigo ?? null,
                'subtotal' => $subtotalSinDescuentos,
                'descuento_global' => $descuentoTotal,
                'subtotal_neto' => $subtotalNeto,
                'iva' => $ivaTotal,
                'total' => $total,
                'observaciones' => $request->observaciones ?? ''
            ]);
            
            // 4. Eliminar productos existentes y crear nuevos
            $cotizacion->productos()->delete();
            
            foreach ($request->productos as $producto) {
                $precioBase = $producto['cantidad'] * $producto['precio'];
                $descuentoPorcentaje = $producto['descuento'] ?? 0;
                $descuentoValor = $precioBase * ($descuentoPorcentaje / 100);
                $subtotalConDescuento = $precioBase - $descuentoValor;
                $ivaProducto = $subtotalConDescuento * 0.19;
                $totalProducto = $subtotalConDescuento + $ivaProducto;
                
                CotizacionProducto::create([
                    'cotizacion_id' => $cotizacion->id,
                    'codigo_producto' => $producto['codigo'],
                    'nombre_producto' => $producto['nombre'],
                    'cantidad' => $producto['cantidad'],
                    'precio_unitario' => $producto['precio'],
                    'subtotal' => $precioBase,
                    'descuento_porcentaje' => $descuentoPorcentaje,
                    'descuento_valor' => $descuentoValor,
                    'subtotal_con_descuento' => $subtotalConDescuento,
                    'iva_valor' => $ivaProducto,
                    'total_producto' => $totalProducto
                ]);
            }
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Cotización actualizada exitosamente',
                'cotizacion_id' => $cotizacion->id,
                'total' => $total
            ]);
            
        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Error actualizando cotización: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la cotización: ' . $e->getMessage()
            ], 500);
        }
    }

    public function ver($id)
    {
        $cotizacion = Cotizacion::with('productos')->findOrFail($id);
        
        // Verificar que sea una cotización, no una NVV
        if ($cotizacion->tipo_documento !== 'cotizacion') {
            abort(404, 'No se encontró la cotización solicitada');
        }
        
        // Obtener información del cliente
        $cliente = Cliente::where('codigo_cliente', $cotizacion->cliente_codigo)->first();
        
        return view('cotizaciones.ver', compact('cotizacion', 'cliente'))
            ->with('pageSlug', 'ver-cotizacion');
    }

    public function eliminar($id)
    {
        try {
            $cotizacion = Cotizacion::findOrFail($id);
            
            // Verificar que sea una cotización, no una NVV
            if ($cotizacion->tipo_documento !== 'cotizacion') {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar una nota de venta desde el controlador de cotizaciones'
                ], 400);
            }
            
            $cotizacion->productos()->delete();
            $cotizacion->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Cotización eliminada exitosamente'
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error eliminando cotización: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la cotización: ' . $e->getMessage()
            ], 500);
        }
    }

    public function generarPDF($id)
    {
        try {
            $cotizacion = Cotizacion::with('productos')->findOrFail($id);
            
            // Verificar que sea una cotización, no una NVV
            if ($cotizacion->tipo_documento !== 'cotizacion') {
                abort(404, 'No se encontró la cotización solicitada');
            }
            
            // Cargar datos del cliente para la vista PDF
            $cliente = Cliente::where('codigo_cliente', $cotizacion->cliente_codigo)->first();

            // Generar PDF usando DomPDF
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('cotizaciones.pdf', compact('cotizacion', 'cliente'));
            
            // Configurar el PDF
            $pdf->setPaper('A4', 'portrait');
            $pdf->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'defaultFont' => 'Arial'
            ]);
            
            // Generar nombre del archivo
            $filename = 'Cotizacion_' . $cotizacion->id . '_' . now()->format('Y-m-d') . '.pdf';
            
            // Descargar el PDF
            return $pdf->download($filename);
            
        } catch (\Exception $e) {
            \Log::error('Error generando PDF de cotización: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error al generar el PDF: ' . $e->getMessage());
        }
    }

    // Métodos auxiliares para búsqueda de productos y precios
    public function buscarProductos(Request $request)
    {
        // Implementar búsqueda de productos (simplificada)
        return response()->json([
            'success' => true,
            'data' => [],
            'message' => 'Búsqueda de productos no implementada en controlador simple'
        ]);
    }

    public function obtenerPrecios(Request $request)
    {
        // Implementar obtención de precios (simplificada)
            return response()->json([
                'success' => true,
            'data' => [],
            'message' => 'Obtención de precios no implementada en controlador simple'
        ]);
    }
} 