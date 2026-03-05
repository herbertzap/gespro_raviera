<?php
/**
 * Lista las columnas reales de la tabla MAEEDOOB en SQL Server.
 * Uso: php scripts/ver_columnas_maeedoob.php
 * Requiere: .env con SQLSRV_EXTERNAL_*
 */

require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$host     = env('SQLSRV_EXTERNAL_HOST');
$port     = env('SQLSRV_EXTERNAL_PORT', '1433');
$database = env('SQLSRV_EXTERNAL_DATABASE');
$username = env('SQLSRV_EXTERNAL_USERNAME');
$password = env('SQLSRV_EXTERNAL_PASSWORD');

if (!$host || !$username || !$password) {
    echo "❌ Configura .env con SQLSRV_EXTERNAL_*\n";
    exit(1);
}

$query = "
SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_NAME = 'MAEEDOOB'
ORDER BY ORDINAL_POSITION
";

$tempFile = tempnam(sys_get_temp_dir(), 'sql_');
file_put_contents($tempFile, $query . "\ngo\nquit");
$command = "tsql -H {$host} -p {$port} -U {$username} -P {$password} -D " . ($database ?: 'master') . " < {$tempFile} 2>&1";
$output = shell_exec($command);
@unlink($tempFile);

echo "\n═══════════════════════════════════════════════════════════\n";
echo "  Columnas de la tabla MAEEDOOB (SQL Server)\n";
echo "═══════════════════════════════════════════════════════════\n\n";
echo $output;
echo "\n";
echo "Usa estos nombres exactos en los INSERT para evitar errores.\n\n";
