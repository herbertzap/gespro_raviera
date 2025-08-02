<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Ruta para obtener detalles completos del cliente
Route::get('/cliente/detalle/{codigoCliente}', function ($codigoCliente) {
    try {
        $cobranzaService = app(\App\Services\CobranzaService::class);
        
        // Obtener información del cliente
        $cliente = $cobranzaService->getClienteInfo($codigoCliente);
        
        // Obtener facturas pendientes del cliente
        $facturas = $cobranzaService->getFacturasPendientesCliente($codigoCliente);
        
        // Obtener notas de venta del cliente
        $notasVenta = $cobranzaService->getNotasVentaCliente($codigoCliente);
        
        return response()->json([
            'success' => true,
            'cliente' => $cliente,
            'facturas' => $facturas,
            'notasVenta' => $notasVenta
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error al obtener datos del cliente: ' . $e->getMessage()
        ], 500);
    }
})->middleware('auth');
