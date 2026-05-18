<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePuedeAccederInformes
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->puedeAccederInformes()) {
            abort(403, 'No tiene permiso para acceder a informes de NVV y facturas.');
        }

        return $next($request);
    }
}
