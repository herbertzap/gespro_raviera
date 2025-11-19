<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AutoLogout
{
    /**
     * Rutas que deben ser excluidas de la verificación de expiración
     */
    protected $except = [
        'login',
        'logout',
    ];

    /**
     * Maneja la expiración manual de la sesión para evitar errores 419.
     */
    public function handle(Request $request, Closure $next)
    {
        // Excluir rutas de autenticación para evitar bucles
        if ($this->inExceptArray($request)) {
            return $next($request);
        }

        // Solo verificar si la sesión está iniciada y el usuario está autenticado
        if ($request->hasSession()) {
            try {
                // Verificar si el usuario está autenticado
                if (Auth::check()) {
                    $lastActivity = (int) $request->session()->get('last_activity', 0);
                    $lifetimeInSeconds = (int) config('session.lifetime', 120) * 60;
                    $now = time();

                    if ($lastActivity > 0 && ($now - $lastActivity) >= $lifetimeInSeconds) {
                        // Marcar que estamos haciendo logout para evitar bucles
                        $request->session()->put('auto_logout_in_progress', true);
                        
                        Auth::logout();
                        $request->session()->forget('last_activity');
                        $request->session()->invalidate();
                        $request->session()->regenerateToken();

                        return redirect()->to('/login')
                            ->with('error', 'Tu sesión expiró por inactividad. Por favor, vuelve a iniciar sesión.')
                            ->with('auto_logout', true);
                    }

                    // Actualizar la última actividad
                    $request->session()->put('last_activity', $now);
                }
            } catch (\Exception $e) {
                // Si hay un error con la sesión, loguear y continuar
                \Log::warning('Error en AutoLogout middleware: ' . $e->getMessage(), [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        return $next($request);
    }

    /**
     * Determina si la ruta actual debe ser excluida de la verificación.
     */
    protected function inExceptArray($request)
    {
        foreach ($this->except as $except) {
            try {
                if ($request->routeIs($except) || $request->is($except) || $request->is($except . '*')) {
                    return true;
                }
            } catch (\Exception $e) {
                // Continuar si hay un error al verificar la ruta
                continue;
            }
        }

        return false;
    }
}

