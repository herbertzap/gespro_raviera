<?php
/**
 * Debug: listar NVV pendientes en SQL Server para un producto específico.
 *
 * Uso:
 *   php scripts/debug_nvv_por_producto.php CDFA010070
 */

require_once __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$host     = env('SQLSRV_EXTERNAL_HOST');
$port     = env('SQLSRV_EXTERNAL_PORT', '1433');
$database = env('SQLSRV_EXTERNAL_DATABASE');
$username = env('SQLSRV_EXTERNAL_USERNAME');
$password = env('SQLSRV_EXTERNAL_PASSWORD');

if (!$host || !$database || !$username || !$password) {
    echo "❌ Faltan credenciales SQLSRV_EXTERNAL_* en .env\n";
    exit(1);
}

$codigo = $argv[1] ?? null;
if (!$codigo) {
    echo "Uso: php scripts/debug_nvv_por_producto.php KOPR\n";
    exit(1);
}

$codigoLimpiado = substr($codigo, 0, 13);

$query = "
SELECT 
    NUDO,
    (CAPRCO1 - CAPRAD1 - CAPREX1) AS CANTIDAD_PENDIENTE
FROM MAEDDO
WHERE TIDO = 'NVV'
  AND LILG = 'SI'
  AND (CAPRCO1 - CAPRAD1 - CAPREX1) > 0
  AND SUBSTRING(KOPRCT, 1, 13) = '{$codigoLimpiado}'
ORDER BY NUDO
";

$tempFile = tempnam(sys_get_temp_dir(), 'sql_nvv_');
file_put_contents($tempFile, $query . "\ngo\nquit");
$command = "tsql -H {$host} -p {$port} -U {$username} -P {$password} -D {$database} < {$tempFile} 2>&1";
$output  = shell_exec($command);
@unlink($tempFile);

echo "=== NVV PENDIENTES PARA PRODUCTO {$codigoLimpiado} ===\n\n";
echo $output . "\n";

