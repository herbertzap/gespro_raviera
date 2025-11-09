<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Cliente;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SincronizarClientesMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        // Solo aplicar a usuarios autenticados con rol de vendedor
        if (!auth()->check() || !auth()->user()->hasRole('Vendedor')) {
            return $next($request);
        }

        // Solo sincronizar en rutas específicas donde se necesita información de clientes
        $rutasCliente = [
            '/dashboard',
            '/cobranza',
            '/clientes',
            '/cotizaciones',
            '/cotizacion/nueva',
            '/nvv-pendientes',
            '/facturas-pendientes',
            '/home'
        ];
        
        $rutaActual = $request->path();
        $necesitaSincronizacion = false;
        
        foreach ($rutasCliente as $ruta) {
            if (str_starts_with($rutaActual, trim($ruta, '/'))) {
                $necesitaSincronizacion = true;
                break;
            }
        }
        
        // Si no es una ruta que necesita sincronización, continuar
        if (!$necesitaSincronizacion) {
            return $next($request);
        }

        $user = auth()->user();
        $codigoVendedor = $user->codigo_vendedor ?? 'GOP';
        
        // Crear clave única para el usuario y fecha
        $cacheKey = "sincronizacion_clientes_{$user->id}_" . date('Y-m-d');
        
        // Verificar si ya se sincronizó hoy
        if (!Cache::has($cacheKey)) {
            try {
                Log::info("🔄 Sincronización automática de clientes para vendedor: {$codigoVendedor} en ruta: {$rutaActual}");
                
                // Ejecutar sincronización en segundo plano para no bloquear la respuesta
                $this->sincronizarEnSegundoPlano($codigoVendedor, $cacheKey);
                
                // Marcar como sincronizado (con TTL de 24 horas)
                Cache::put($cacheKey, true, now()->addHours(24));
                
                Log::info("✅ Sincronización automática iniciada para vendedor: {$codigoVendedor}");
                
            } catch (\Exception $e) {
                Log::error("❌ Error en sincronización automática: " . $e->getMessage());
                // No bloquear la respuesta si falla la sincronización
            }
        }

        return $next($request);
    }

    /**
     * Sincronizar clientes en segundo plano
     */
    private function sincronizarEnSegundoPlano($codigoVendedor, $cacheKey)
    {
        // Usar dispatch para ejecutar en segundo plano
        dispatch(function () use ($codigoVendedor, $cacheKey) {
            try {
                // Sincronizar clientes
                $resultado = \App\Console\Commands\SincronizarClientesSimple::sincronizarVendedorDirecto($codigoVendedor);
                
                if ($resultado['success']) {
                    Log::info("✅ Sincronización automática de clientes completada: {$resultado['nuevos']} nuevos, {$resultado['actualizados']} actualizados");
                    
                    // Sincronizar productos después de sincronizar clientes
                    try {
                        $stockService = new \App\Services\StockService();
                        $productosSincronizados = $stockService->sincronizarStockDesdeSQLServer();
                        Log::info("✅ Sincronización automática de productos completada: {$productosSincronizados} productos actualizados");
                    } catch (\Exception $e) {
                        Log::error("❌ Error sincronizando productos: " . $e->getMessage());
                        // No remover cache de clientes si falla productos
                    }
                } else {
                    Log::error("❌ Error en sincronización automática de clientes: " . $resultado['message']);
                    // Remover cache para permitir reintento
                    Cache::forget($cacheKey);
                }
                
            } catch (\Exception $e) {
                Log::error("❌ Error en sincronización automática: " . $e->getMessage());
                // Remover cache para permitir reintento
                Cache::forget($cacheKey);
            }
        })->afterResponse();
    }
} 