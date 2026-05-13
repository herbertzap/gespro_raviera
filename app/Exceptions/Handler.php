<?php

namespace App\Exceptions;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Support\Str;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        $this->renderable(function (Throwable $e, $request) {
            if ($this->isSqlServerTimeout($e) && !$request->expectsJson()) {
                return redirect()
                    ->route('login')
                    ->with('error', 'Error de conexion a datos de cliente, por favor intente mas tarde.');
            }
        });
    }

    /**
     * Detecta timeout o problemas de conexion hacia SQL Server externo.
     */
    private function isSqlServerTimeout(Throwable $exception): bool
    {
        if (!$exception instanceof QueryException && !$exception instanceof \PDOException) {
            return false;
        }

        $message = Str::lower($exception->getMessage());

        $patterns = [
            'hyt00',
            'login timeout expired',
            'could not open a connection to sql server',
            'sqlsrv',
            'odbc driver 17 for sql server',
            'tcp provider',
            'connection timed out',
        ];

        foreach ($patterns as $pattern) {
            if (Str::contains($message, $pattern)) {
                return true;
            }
        }

        return false;
    }
}
