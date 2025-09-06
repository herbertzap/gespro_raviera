<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

class HandleErrorsMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $response = $next($request);
            
            // Si la respuesta es un error 500, redirigir al login
            if ($response->getStatusCode() >= 500) {
                return $this->redirectToLogin($request, 'Error interno del servidor. Sesión cerrada por seguridad.');
            }
            
            return $response;
            
        } catch (\Exception $e) {
            // Capturar cualquier excepción y redirigir al login
            \Log::error('Error capturado por HandleErrorsMiddleware: ' . $e->getMessage(), [
                'url' => $request->url(),
                'method' => $request->method(),
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->redirectToLogin($request, 'Error inesperado. Sesión cerrada por seguridad.');
        }
    }
    
    /**
     * Redirigir al login cerrando la sesión
     */
    private function redirectToLogin(Request $request, string $message = 'Sesión cerrada por seguridad.')
    {
        // Cerrar sesión si el usuario está autenticado
        if (Auth::check()) {
            Auth::logout();
        }
        
        // Limpiar todas las sesiones
        Session::flush();
        
        // Redirigir al login con mensaje
        return redirect()->route('login')
            ->with('error', $message)
            ->with('auto_logout', true);
    }
}
