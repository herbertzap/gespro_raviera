<?php

require_once 'vendor/autoload.php';

// Cargar Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\StockComprometidoService;
use App\Models\Producto;
use App\Models\StockComprometido;
use Illuminate\Support\Facades\DB;

echo "=== DEBUG DE STOCK DE PRODUCTOS ===\n\n";

$productos = [
    '2240340008000' => 'Producto con 20 stock físico, 1300 comprometido',
    'PALOESC000000' => 'Producto con 41700 stock físico, 0 comprometido'
];

foreach ($productos as $codigo => $descripcion) {
    echo "🔍 ANALIZANDO: $codigo - $descripcion\n";
    echo str_repeat("=", 60) . "\n";
    
    // 1. Verificar producto en tabla local
    $producto = Producto::where('KOPR', $codigo)->first();
    
    if (!$producto) {
        echo "❌ Producto no encontrado en tabla local\n\n";
        continue;
    }
    
    echo "📊 DATOS EN TABLA LOCAL:\n";
    echo "  Stock Físico: " . ($producto->stock_fisico ?? 'N/A') . "\n";
    echo "  Stock Comprometido: " . ($producto->stock_comprometido ?? 'N/A') . "\n";
    echo "  Stock Disponible: " . ($producto->stock_disponible ?? 'N/A') . "\n";
    echo "  Activo: " . ($producto->activo ? 'Sí' : 'No') . "\n";
    
    // 2. Verificar stock comprometido en MySQL
    $stockComprometidoMySQL = StockComprometido::calcularStockComprometido($codigo);
    echo "\n📦 STOCK COMPROMETIDO MYSQL:\n";
    echo "  Total Comprometido: $stockComprometidoMySQL\n";
    
    // 3. Verificar registros de stock comprometido
    $registrosComprometidos = StockComprometido::where('producto_codigo', $codigo)
        ->where('estado', 'activo')
        ->get();
    
    echo "\n📋 REGISTROS DE STOCK COMPROMETIDO:\n";
    if ($registrosComprometidos->count() > 0) {
        foreach ($registrosComprometidos as $registro) {
            echo "  - ID: {$registro->id}, Cantidad: {$registro->cantidad_comprometida}, Estado: {$registro->estado}\n";
            echo "    Cotización: {$registro->cotizacion_id}, Cliente: {$registro->cliente_nombre}\n";
        }
    } else {
        echo "  No hay registros de stock comprometido\n";
    }
    
    // 4. Calcular stock disponible usando el servicio
    $stockService = new StockComprometidoService();
    $stockDisponibleReal = $stockService->obtenerStockDisponibleReal($codigo);
    
    echo "\n🧮 CÁLCULO DE STOCK DISPONIBLE:\n";
    echo "  Stock Físico: " . ($producto->stock_fisico ?? 0) . "\n";
    echo "  Stock Comprometido SQL: " . ($producto->stock_comprometido ?? 0) . "\n";
    echo "  Stock Comprometido MySQL: $stockComprometidoMySQL\n";
    echo "  Stock Disponible Real: $stockDisponibleReal\n";
    
    // 5. Verificar si el producto está activo
    if (!$producto->activo) {
        echo "\n⚠️  PRODUCTO INACTIVO - Esto puede causar que aparezca como 'sin stock'\n";
    }
    
    // 6. Verificar stock en SQL Server
    echo "\n🔍 VERIFICANDO STOCK EN SQL SERVER:\n";
    
    $host = env('SQLSRV_EXTERNAL_HOST');
    $port = env('SQLSRV_EXTERNAL_PORT', '1433');
    $database = env('SQLSRV_EXTERNAL_DATABASE');
    $username = env('SQLSRV_EXTERNAL_USERNAME');
    $password = env('SQLSRV_EXTERNAL_PASSWORD');
    
    $query = "
        SELECT 
            KOPR,
            STOCKSALIDA,
            STOCKNV1,
            STOCKNV2,
            STOCKFISICO
        FROM MAEST 
        WHERE KOPR = '{$codigo}' AND EMPRESA = '01'
    ";
    
    $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
    file_put_contents($tempFile, $query . "\ngo\nquit");
    
    $command = "timeout 10 tsql -H {$host} -p {$port} -U {$username} -P {$password} -D {$database} < {$tempFile} 2>&1";
    $output = shell_exec($command);
    unlink($tempFile);
    
    echo "Consulta SQL Server:\n";
    echo $output . "\n";
    
    echo "\n" . str_repeat("=", 60) . "\n\n";
}
