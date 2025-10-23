<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\ErrorController;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ErrorHandlerMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        try {
            return $next($request);
        } catch (\Exception $exception) {
            return $this->handleException($exception, $request);
        }
    }

    /**
     * Manejar excepciones
     */
    private function handleException(\Exception $exception, Request $request)
    {
        $errorController = new ErrorController();
        $errorData = $errorController->handleError($exception, [
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'headers' => $request->headers->all()
        ]);

        // Si es una excepción HTTP, usar el código de estado
        if ($exception instanceof HttpException) {
            $code = $exception->getStatusCode();
        } else {
            $code = 500;
        }

        // Redirigir a página de error personalizada
        return redirect()->route('error.show', [
            'code' => $code,
            'message' => $exception->getMessage()
        ]);
    }
}

