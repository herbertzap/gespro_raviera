<?php

require_once 'vendor/autoload.php';

// Cargar Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\StockComprometidoService;
use App\Models\Producto;

echo "=== ACTUALIZANDO STOCK DE PRODUCTOS EN COTIZACIÓN ===\n\n";

// Simular productos agregados a una cotización
$productosCotizacion = [
    '2240340008000' => 'BROCHE MALLA RASCHEL RECTANGULAR UN',
    'PALOESC000000' => 'PALOS PARA ESCOBILLONES UNIDAD'
];

$stockService = new StockComprometidoService();

foreach ($productosCotizacion as $codigo => $nombre) {
    echo "🔍 ACTUALIZANDO STOCK: $codigo - $nombre\n";
    echo str_repeat("=", 60) . "\n";
    
    // 1. Obtener stock disponible real
    $stockDisponibleReal = $stockService->obtenerStockDisponibleReal($codigo);
    
    // 2. Obtener datos del producto desde MySQL
    $producto = Producto::where('KOPR', $codigo)->first();
    
    if ($producto) {
        echo "📊 DATOS ACTUALES EN MYSQL:\n";
        echo "  Stock Físico: " . ($producto->stock_fisico ?? 0) . "\n";
        echo "  Stock Comprometido: " . ($producto->stock_comprometido ?? 0) . "\n";
        echo "  Stock Disponible: " . ($producto->stock_disponible ?? 0) . "\n";
        
        echo "\n🧮 STOCK DISPONIBLE REAL CALCULADO:\n";
        echo "  Stock Disponible Real: $stockDisponibleReal\n";
        
        // 3. Determinar estado del stock
        if ($stockDisponibleReal > 0) {
            $estadoStock = 'Disponible';
            $claseStock = 'text-success';
            $iconoStock = 'check_circle';
        } else {
            $estadoStock = 'Sin stock';
            $claseStock = 'text-warning';
            $iconoStock = 'warning';
        }
        
        echo "\n✅ ESTADO DEL STOCK:\n";
        echo "  Estado: $estadoStock\n";
        echo "  Clase CSS: $claseStock\n";
        echo "  Icono: $iconoStock\n";
        
        // 4. Generar HTML para mostrar en la cotización
        $htmlStock = "
            <td class=\"$claseStock\">
                <i class=\"material-icons\">$iconoStock</i>
                $stockDisponibleReal UN
                " . ($producto->stock_comprometido > 0 ? "<br><small class=\"text-muted\">Comprometido: {$producto->stock_comprometido}</small>" : "") . "
                " . ($stockDisponibleReal <= 0 ? "<br><small class=\"text-warning\"><i class=\"material-icons\">info</i> Sin stock - Nota pendiente</small>" : "") . "
            </td>";
        
        echo "\n📝 HTML GENERADO PARA LA COTIZACIÓN:\n";
        echo $htmlStock . "\n";
        
    } else {
        echo "❌ PRODUCTO NO ENCONTRADO EN MYSQL\n";
    }
    
    echo "\n" . str_repeat("=", 60) . "\n\n";
}
