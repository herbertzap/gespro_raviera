<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;
use Illuminate\Session\TokenMismatchException;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        //
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     *
     * @throws \Illuminate\Session\TokenMismatchException
     */
    public function handle($request, \Closure $next)
    {
        try {
            return parent::handle($request, $next);
        } catch (TokenMismatchException $e) {
            // Si es el login, redirigir de vuelta al login con mensaje
            if ($request->is('login') || $request->routeIs('login')) {
                return redirect()->route('login')
                    ->with('error', 'La sesión expiró. Por favor, intenta iniciar sesión nuevamente.')
                    ->withInput($request->except('password'));
            }
            
            // Para otras rutas, lanzar la excepción normalmente
            throw $e;
        }
    }
}
