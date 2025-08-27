<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\ClienteValidacionService;

class ValidacionController extends Controller
{
    /**
     * Validar cliente para nota de venta
     */
    public function validarCliente(Request $request)
    {
        try {
            $request->validate([
                'codigo_cliente' => 'required|string',
                'monto_nota_venta' => 'required|numeric|min:0'
            ]);

            $codigoCliente = $request->codigo_cliente;
            $montoNotaVenta = $request->monto_nota_venta;

            $resultado = ClienteValidacionService::validarClienteParaNotaVenta($codigoCliente, $montoNotaVenta);

            return response()->json($resultado);

        } catch (\Exception $e) {
            return response()->json([
                'valido' => false,
                'motivo' => 'Error en validación: ' . $e->getMessage(),
                'requiere_autorizacion' => true,
                'estado' => 'error'
            ], 500);
        }
    }

    /**
     * Validar stock de productos
     */
    public function validarStock(Request $request)
    {
        try {
            $request->validate([
                'productos' => 'required|array',
                'productos.*.codigo' => 'required|string',
                'productos.*.nombre' => 'required|string',
                'productos.*.cantidad' => 'required|numeric|min:0',
                'productos.*.stock_disponible' => 'required|numeric|min:0'
            ]);

            $productos = $request->productos;

            $resultado = ClienteValidacionService::validarStockProductos($productos);

            return response()->json($resultado);

        } catch (\Exception $e) {
            return response()->json([
                'valido' => false,
                'motivo' => 'Error en validación de stock: ' . $e->getMessage(),
                'requiere_autorizacion' => true,
                'estado' => 'error'
            ], 500);
        }
    }
}
