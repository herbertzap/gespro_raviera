<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$cotizacion = \App\Models\Cotizacion::find(45);
if ($cotizacion) {
    $cotizacion->estado_aprobacion = 'pendiente_picking';
    $cotizacion->save();
    echo "✅ NVV #45 actualizada a pendiente_picking - lista para nueva prueba\n";
    echo "🔧 Cliente: " . $cotizacion->cliente_nombre . "\n";
    echo "💰 Total: $" . number_format($cotizacion->total, 0) . "\n";
    echo "📋 Estado: " . $cotizacion->estado_aprobacion . "\n";
} else {
    echo "❌ NVV #45 no encontrada\n";
}
