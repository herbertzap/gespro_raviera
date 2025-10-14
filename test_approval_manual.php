<?php

require_once 'vendor/autoload.php';

use App\Models\Cotizacion;
use App\Http\Controllers\AprobacionController;
use App\Services\CobranzaService;
use Illuminate\Support\Facades\Auth;

// Inicializar Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== PRUEBA MANUAL DE APROBACIÓN NVV 42 ===\n";

try {
    // Simular usuario autenticado
    $user = \App\Models\User::find(1);
    Auth::login($user);
    
    echo "Usuario autenticado: " . $user->email . "\n";
    
    // Obtener la cotización
    $cotizacion = Cotizacion::find(42);
    if (!$cotizacion) {
        echo "NVV 42 no encontrada\n";
        exit;
    }
    
    echo "NVV encontrada: " . $cotizacion->cliente_nombre . "\n";
    echo "Estado actual: " . $cotizacion->estado_aprobacion . "\n";
    
    // Crear controlador
    $cobranzaService = app(CobranzaService::class);
    $controller = new AprobacionController($cobranzaService);
    
    // Simular request
    $request = new \Illuminate\Http\Request();
    $request->merge([
        'comentarios' => 'Prueba manual',
        'validar_stock_real' => true
    ]);
    
    echo "Iniciando aprobación...\n";
    $startTime = microtime(true);
    
    // Llamar al método directamente
    $response = $controller->aprobarPicking($request, 42);
    
    $endTime = microtime(true);
    $duration = $endTime - $startTime;
    
    echo "Proceso completado en " . round($duration, 2) . " segundos\n";
    echo "Respuesta: " . $response->getContent() . "\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Archivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
