<?php
/**
 * Ver filas de CONFIEST (EMPRESA, MODALIDAD, NVV) para revisar el WHERE del UPDATE.
 * Uso: php scripts/ver_confiest.php
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

// MODALIDAD en BD = CHAR(5)+CHAR(32)+CHAR(5)+CHAR(32)+CHAR(5) (hex 05 20 05 20 05)
$query = "
SELECT EMPRESA,MODALIDAD,NVV FROM CONFIEST WHERE MODALIDAD = CHAR(5)+CHAR(32)+CHAR(5)+CHAR(32)+CHAR(5)
";

$tempFile = tempnam(sys_get_temp_dir(), 'sql_');
file_put_contents($tempFile, $query . "\ngo\nquit");
$command = "tsql -H {$host} -p {$port} -U {$username} -P {$password} -D " . ($database ?: 'master') . " < {$tempFile} 2>&1";
$output = shell_exec($command);
@unlink($tempFile);

echo "\n═══════════════════════════════════════════════════════════\n";
echo "  CONFIEST (fila con MODALIDAD = CHAR(5)+CHAR(32)+CHAR(5)+CHAR(32)+CHAR(5))\n";
echo "  El UPDATE usa: WHERE MODALIDAD = CHAR(5)+CHAR(32)+CHAR(5)+CHAR(32)+CHAR(5)\n";
echo "═══════════════════════════════════════════════════════════\n\n";
echo $output;
echo "\n";

// Extraer NVV y mostrar si es el esperado
$nvv = null;
if (preg_match('/\b(\d{4,10})\b/', $output ?? '', $m)) {
    $nvv = (int) $m[1];
}
if ($nvv !== null) {
    echo "───────────────────────────────────────────────────────────\n";
    echo "  NVV actual en CONFIEST = " . $nvv . "\n";
    echo "  ¿Es 39305? " . ($nvv === 39305 ? "✅ SÍ" : "❌ No (valor: {$nvv})") . "\n";
    echo "───────────────────────────────────────────────────────────\n";
} else {
    echo "  (No se pudo leer NVV del resultado)\n";
}
echo "\n";
