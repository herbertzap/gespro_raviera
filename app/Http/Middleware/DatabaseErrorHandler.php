<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Database\QueryException;
use PDOException;

class DatabaseErrorHandler
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        try {
            return $next($request);
        } catch (QueryException $e) {
            // Error de base de datos
            \Log::error('Error de base de datos: ' . $e->getMessage(), [
                'url' => $request->url(),
                'user_id' => Auth::id(),
                'sql' => $e->getSql(),
                'bindings' => $e->getBindings()
            ]);
            
            return $this->redirectToLogin($request, 'Error de conexión a la base de datos. Sesión cerrada por seguridad.');
        } catch (PDOException $e) {
            // Error de PDO
            \Log::error('Error PDO: ' . $e->getMessage(), [
                'url' => $request->url(),
                'user_id' => Auth::id()
            ]);
            
            return $this->redirectToLogin($request, 'Error de conexión a la base de datos. Sesión cerrada por seguridad.');
        }
    }
    
    /**
     * Redirigir al login cerrando la sesión
     */
    private function redirectToLogin(Request $request, string $message)
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
