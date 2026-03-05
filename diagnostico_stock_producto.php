<?php
/**
 * Diagnóstico de stock para un producto.
 * Uso: php diagnostico_stock_producto.php 1453105030000
 */
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$codigo = $argv[1] ?? '1453105030000';

use Illuminate\Support\Facades\DB;

echo "=== Diagnóstico stock producto: {$codigo} ===\n\n";

// 1. MySQL - tabla productos
$p = DB::table('productos')->where('KOPR', $codigo)->first();
if (!$p) {
    echo "❌ Producto NO encontrado en tabla 'productos' (MySQL).\n";
    exit(1);
}
echo "1. MySQL (productos):\n";
echo "   stock_fisico: " . ($p->stock_fisico ?? 'NULL') . "\n";
echo "   stock_comprometido: " . ($p->stock_comprometido ?? 'NULL') . "\n";
echo "   stock_disponible: " . ($p->stock_disponible ?? 'NULL') . "\n";
echo "   activo: " . ($p->activo ? '1' : '0') . "\n";
echo "   NOKOPR: " . substr($p->NOKOPR ?? 'N/A', 0, 60) . "\n\n";

// 2. Stock comprometido local (cotizaciones)
$comprometido = \App\Models\StockComprometido::calcularStockComprometido($codigo, '01');
echo "2. Stock comprometido local (cotizaciones/NVV): {$comprometido}\n\n";

// 3. Stock disponible real (según servicio)
$service = new \App\Services\StockComprometidoService();
$disponibleReal = $service->obtenerStockDisponibleReal($codigo, '01');
echo "3. Stock disponible real (obtenerStockDisponibleReal): {$disponibleReal}\n\n";

// 4. Consultar SQL Server - bodega 01 y LIB
$host = env('SQLSRV_EXTERNAL_HOST');
$port = env('SQLSRV_EXTERNAL_PORT', '1433');
$database = env('SQLSRV_EXTERNAL_DATABASE');
$username = env('SQLSRV_EXTERNAL_USERNAME');
$password = env('SQLSRV_EXTERNAL_PASSWORD');

foreach (['01', 'LIB'] as $kobo) {
    $query = "
        SELECT 
            CAST(MAEST.KOPR AS VARCHAR(50)) AS KOPR,
            CAST(MAEST.KOBO AS VARCHAR(10)) AS KOBO,
            CAST(ISNULL(MAEST.STFI1, 0) AS FLOAT) AS STFI1,
            CAST(ISNULL(MAEST.STOCNV1, 0) AS FLOAT) AS STOCNV1
        FROM MAEST
        WHERE MAEST.KOPR = '{$codigo}' AND MAEST.KOBO = '{$kobo}'
    ";
    $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
    file_put_contents($tempFile, $query . "\ngo\nquit");
    $command = "tsql -H {$host} -p {$port} -U {$username} -P {$password} -D {$database} < {$tempFile} 2>&1";
    $output = shell_exec($command);
    unlink($tempFile);
    echo "4. SQL Server MAEST (KOBO={$kobo}):\n";
    echo $output;
    echo "\n";
}

echo "=== Fin diagnóstico ===\n";
