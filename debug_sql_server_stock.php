<?php

require_once 'vendor/autoload.php';

// Cargar Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== VERIFICANDO ESTRUCTURA DE TABLA MAEST ===\n\n";

$host = env('SQLSRV_EXTERNAL_HOST');
$port = env('SQLSRV_EXTERNAL_PORT', '1433');
$database = env('SQLSRV_EXTERNAL_DATABASE');
$username = env('SQLSRV_EXTERNAL_USERNAME');
$password = env('SQLSRV_EXTERNAL_PASSWORD');

// 1. Verificar estructura de tabla MAEST
$queryEstructura = "
    SELECT TOP 1 *
    FROM MAEST 
    WHERE EMPRESA = '01'
";

$tempFile = tempnam(sys_get_temp_dir(), 'sql_');
file_put_contents($tempFile, $queryEstructura . "\ngo\nquit");

$command = "timeout 10 tsql -H {$host} -p {$port} -U {$username} -P {$password} -D {$database} < {$tempFile} 2>&1";
$output = shell_exec($command);
unlink($tempFile);

echo "🔍 ESTRUCTURA DE TABLA MAEST:\n";
echo $output . "\n";

// 2. Buscar los productos específicos
$productos = ['2240340008000', 'PALOESC000000'];

foreach ($productos as $codigo) {
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "🔍 BUSCANDO PRODUCTO: $codigo\n";
    echo str_repeat("=", 60) . "\n";
    
    $queryProducto = "
        SELECT TOP 1 *
        FROM MAEST 
        WHERE KOPR = '{$codigo}' AND EMPRESA = '01'
    ";
    
    $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
    file_put_contents($tempFile, $queryProducto . "\ngo\nquit");
    
    $command = "timeout 10 tsql -H {$host} -p {$port} -U {$username} -P {$password} -D {$database} < {$tempFile} 2>&1";
    $output = shell_exec($command);
    unlink($tempFile);
    
    echo "Resultado:\n";
    echo $output . "\n";
}
