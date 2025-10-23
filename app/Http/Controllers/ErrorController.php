<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class ErrorController extends Controller
{
    /**
     * Página de error personalizada
     */
    public function show($code = 500, $message = null)
    {
        $user = Auth::user();
        $errorData = [
            'code' => $code,
            'message' => $message ?: $this->getDefaultMessage($code),
            'user_id' => $user ? $user->id : null,
            'user_email' => $user ? $user->email : null,
            'timestamp' => now()->toDateTimeString(),
            'url' => request()->fullUrl(),
            'user_agent' => request()->userAgent(),
            'ip' => request()->ip()
        ];

        // Log del error
        Log::error('Error personalizado capturado', $errorData);

        return view('errors.custom', compact('errorData'));
    }

    /**
     * Manejar errores de la aplicación
     */
    public function handleError(\Exception $exception, $context = [])
    {
        $user = Auth::user();
        
        $errorData = [
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'user_id' => $user ? $user->id : null,
            'user_email' => $user ? $user->email : null,
            'user_role' => $user ? $user->roles->pluck('name')->implode(', ') : null,
            'timestamp' => now()->toDateTimeString(),
            'url' => request()->fullUrl(),
            'user_agent' => request()->userAgent(),
            'ip' => request()->ip(),
            'context' => $context
        ];

        // Log detallado del error
        Log::error('Error de aplicación capturado', $errorData);

        // Si es un error crítico, enviar notificación
        if ($this->isCriticalError($exception)) {
            $this->sendCriticalErrorNotification($errorData);
        }

        return $errorData;
    }

    /**
     * Obtener mensaje por defecto según el código
     */
    private function getDefaultMessage($code)
    {
        $messages = [
            400 => 'Solicitud incorrecta',
            401 => 'No autorizado',
            403 => 'Acceso prohibido',
            404 => 'Página no encontrada',
            500 => 'Error interno del servidor',
            503 => 'Servicio no disponible'
        ];

        return $messages[$code] ?? 'Error desconocido';
    }

    /**
     * Verificar si es un error crítico
     */
    private function isCriticalError(\Exception $exception)
    {
        $criticalErrors = [
            'PDOException',
            'DatabaseException',
            'ConnectionException',
            'FatalErrorException'
        ];

        return in_array(get_class($exception), $criticalErrors) || 
               str_contains($exception->getMessage(), 'database') ||
               str_contains($exception->getMessage(), 'connection');
    }

    /**
     * Enviar notificación de error crítico
     */
    private function sendCriticalErrorNotification($errorData)
    {
        // Aquí puedes implementar notificaciones por email, Slack, etc.
        Log::critical('ERROR CRÍTICO DETECTADO', $errorData);
    }
}

