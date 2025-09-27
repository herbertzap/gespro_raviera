<?php

// Test script para verificar que la vista funciona
require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\View;

// Simular datos como los que vienen del controlador
$productosCotizacion = [
    [
        'codigo' => 'PROD001',
        'nombre' => 'Producto de Prueba 1',
        'cantidad' => 10,
        'precio' => 15000,
        'subtotal' => 150000
    ],
    [
        'codigo' => 'PROD002', 
        'nombre' => 'Producto de Prueba 2',
        'cantidad' => 5,
        'precio' => 25000,
        'subtotal' => 125000
    ]
];

$cliente = (object) [
    'codigo' => 'CLI001',
    'nombre' => 'Cliente de Prueba'
];

try {
    // Renderizar la vista directamente
    $html = View::make('cotizaciones.partials.detalle', compact('productosCotizacion', 'cliente'))->render();
    echo "✅ Vista renderizada exitosamente!\n";
    echo "Longitud del HTML: " . strlen($html) . " caracteres\n";
    echo "Primeras 200 caracteres:\n";
    echo substr($html, 0, 200) . "...\n";
} catch (Exception $e) {
    echo "❌ Error al renderizar vista: " . $e->getMessage() . "\n";
    echo "Archivo: " . $e->getFile() . "\n";
    echo "Línea: " . $e->getLine() . "\n";
}
