<?php

require_once 'vendor/autoload.php';

// Cargar Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== VERIFICANDO OTRAS BODEGAS Y EMPRESAS ===\n\n";

$productos = ['2240340008000', 'PALOESC000000'];

$host = env('SQLSRV_EXTERNAL_HOST');
$port = env('SQLSRV_EXTERNAL_PORT', '1433');
$database = env('SQLSRV_EXTERNAL_DATABASE');
$username = env('SQLSRV_EXTERNAL_USERNAME');
$password = env('SQLSRV_EXTERNAL_PASSWORD');

foreach ($productos as $codigo) {
    echo "🔍 VERIFICANDO: $codigo\n";
    echo str_repeat("=", 60) . "\n";
    
    // 1. Verificar en todas las bodegas
    $queryBodegas = "
        SELECT 
            EMPRESA,
            KOBO,
            KOSU,
            STFI1,
            STFI2,
            STOCNV1,
            STOCNV2
        FROM MAEST 
        WHERE KOPR = '{$codigo}'
        ORDER BY EMPRESA, KOBO
    ";
    
    $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
    file_put_contents($tempFile, $queryBodegas . "\ngo\nquit");
    
    $command = "timeout 10 tsql -H {$host} -p {$port} -U {$username} -P {$password} -D {$database} < {$tempFile} 2>&1";
    $output = shell_exec($command);
    unlink($tempFile);
    
    echo "📊 DATOS EN TODAS LAS BODEGAS:\n";
    echo $output . "\n";
    
    // 2. Verificar en tabla de productos
    $queryProducto = "
        SELECT 
            KOPR,
            NOKOPR,
            ATPR,
            TIPR
        FROM MAEPR 
        WHERE KOPR = '{$codigo}'
    ";
    
    $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
    file_put_contents($tempFile, $queryProducto . "\ngo\nquit");
    
    $command = "timeout 10 tsql -H {$host} -p {$port} -U {$username} -P {$password} -D {$database} < {$tempFile} 2>&1";
    $output = shell_exec($command);
    unlink($tempFile);
    
    echo "📋 DATOS DEL PRODUCTO:\n";
    echo $output . "\n";
    
    echo "\n" . str_repeat("=", 60) . "\n\n";
}
