<?php

require_once 'vendor/autoload.php';

// Cargar Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Producto;
use Illuminate\Support\Facades\DB;

echo "=== SINCRONIZACIÓN DE STOCK ESPECÍFICO ===\n\n";

$productos = ['2240340008000', 'PALOESC000000'];

$host = env('SQLSRV_EXTERNAL_HOST');
$port = env('SQLSRV_EXTERNAL_PORT', '1433');
$database = env('SQLSRV_EXTERNAL_DATABASE');
$username = env('SQLSRV_EXTERNAL_USERNAME');
$password = env('SQLSRV_EXTERNAL_PASSWORD');

foreach ($productos as $codigo) {
    echo "🔍 SINCRONIZANDO: $codigo\n";
    echo str_repeat("=", 50) . "\n";
    
    // Consulta específica para el producto
    $query = "
        SELECT 
            MAEPR.KOPR AS CODIGO_PRODUCTO,
            MAEPR.NOKOPR AS NOMBRE_PRODUCTO,
            ISNULL(MAEST.STFI1, 0) AS STOCK_FISICO,
            ISNULL(MAEST.STOCNV1, 0) AS STOCK_COMPROMETIDO,
            ISNULL(MAEST.STFI1 - MAEST.STOCNV1, 0) AS STOCK_DISPONIBLE,
            ISNULL(MAEPR.UD01PR, 'UN') AS UNIDAD_MEDIDA
        FROM MAEPR
        LEFT JOIN MAEST ON MAEPR.KOPR = MAEST.KOPR AND MAEST.KOBO = '01' AND MAEST.EMPRESA = '01'
        WHERE MAEPR.KOPR = '{$codigo}'
    ";
    
    $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
    file_put_contents($tempFile, $query . "\ngo\nquit");
    
    $command = "timeout 10 tsql -H {$host} -p {$port} -U {$username} -P {$password} -D {$database} < {$tempFile} 2>&1";
    $output = shell_exec($command);
    unlink($tempFile);
    
    echo "Consulta SQL Server:\n";
    echo $output . "\n";
    
    // Parsear resultado
    $lines = explode("\n", $output);
    $productoData = null;
    
    foreach ($lines as $line) {
        $line = trim($line);
        
        // Saltar líneas de configuración
        if (empty($line) || strpos($line, 'locale') !== false || 
            strpos($line, 'Setting') !== false || strpos($line, '1>') !== false ||
            strpos($line, '2>') !== false || strpos($line, 'rows affected') !== false ||
            strpos($line, 'Msg ') !== false || strpos($line, 'Warning:') !== false ||
            strpos($line, 'CODIGO_PRODUCTO') !== false) {
            continue;
        }
        
        // Parsear línea de datos
        if (preg_match('/^([A-Z0-9]+)\s+"?([^"]+)"?\s+([0-9.]+)\s+([0-9.]+)\s+([0-9.-]+)\s+"?([^"]*)"?/', $line, $matches)) {
            $productoData = [
                'codigo' => trim($matches[1]),
                'nombre' => trim($matches[2]),
                'stock_fisico' => (float)$matches[3],
                'stock_comprometido' => (float)$matches[4],
                'stock_disponible' => (float)$matches[5],
                'unidad' => trim($matches[6])
            ];
            break;
        }
    }
    
    if ($productoData) {
        echo "\n📊 DATOS OBTENIDOS:\n";
        echo "  Código: {$productoData['codigo']}\n";
        echo "  Nombre: {$productoData['nombre']}\n";
        echo "  Stock Físico: {$productoData['stock_fisico']}\n";
        echo "  Stock Comprometido: {$productoData['stock_comprometido']}\n";
        echo "  Stock Disponible: {$productoData['stock_disponible']}\n";
        echo "  Unidad: {$productoData['unidad']}\n";
        
        // Actualizar en MySQL
        $producto = Producto::where('KOPR', $codigo)->first();
        
        if ($producto) {
            $producto->stock_fisico = $productoData['stock_fisico'];
            $producto->stock_comprometido = $productoData['stock_comprometido'];
            $producto->stock_disponible = $productoData['stock_disponible'];
            $producto->save();
            
            echo "\n✅ PRODUCTO ACTUALIZADO EN MYSQL\n";
        } else {
            echo "\n❌ PRODUCTO NO ENCONTRADO EN MYSQL\n";
        }
    } else {
        echo "\n❌ NO SE PUDIERON OBTENER DATOS DEL PRODUCTO\n";
    }
    
    echo "\n" . str_repeat("=", 50) . "\n\n";
}
