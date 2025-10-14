<?php
// Script para limpiar OPcache
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "✅ OPcache limpiado exitosamente\n";
} else {
    echo "❌ OPcache no está habilitado\n";
}

if (function_exists('opcache_get_status')) {
    $status = opcache_get_status();
    echo "Estado: " . ($status['opcache_enabled'] ? 'Habilitado' : 'Deshabilitado') . "\n";
    echo "Archivos en caché: " . $status['opcache_statistics']['num_cached_scripts'] . "\n";
}

