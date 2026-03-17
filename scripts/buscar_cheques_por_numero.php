<?php
/**
 * Buscar cheques en cartera por número y mostrar el cliente asociado.
 * Uso:
 *   php scripts/buscar_cheques_por_numero.php LB00013631 LB00013632 ...
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
    echo "❌ Configura .env con SQLSRV_EXTERNAL_* para usar este script\n";
    exit(1);
}

$argv = $argv ?? [];
array_shift($argv); // quitar nombre de script

if (empty($argv)) {
    echo "Uso: php scripts/buscar_cheques_por_numero.php NUDP1 [NUDP2 ...]\n";
    exit(1);
}

// Sanitizar y preparar lista de números de cheque
$numeros = [];
foreach ($argv as $num) {
    $num = trim($num);
    if ($num === '') continue;
    $numeros[] = "'" . str_replace("'", "''", $num) . "'";
}

if (empty($numeros)) {
    echo "No se proporcionaron números de cheque válidos.\n";
    exit(1);
}

$inList = implode(',', $numeros);

// Query: buscar cheques en MAEDPCE (cartera: TIDP = CHV, ESPGDP = 'P') y traer cliente
$query = "
SELECT 
    MAEDPCE.NUDP AS NUMERO_CHEQUE,
    MAEDPCE.ENDP AS CODIGO_CLIENTE,
    CLIENTES.NOKOEN AS NOMBRE_CLIENTE,
    MAEDPCE.FEVEDP AS FECHA_VENCIMIENTO,
    MAEDPCE.VADP AS VALOR
FROM MAEDPCE
LEFT JOIN CLIENTES ON CLIENTES.KOEN = MAEDPCE.ENDP
WHERE MAEDPCE.TIDP = 'CHV'
  AND MAEDPCE.ESPGDP = 'P'
  AND MAEDPCE.EMPRESA = '01'
  AND MAEDPCE.NUDP IN ($inList)
ORDER BY MAEDPCE.NUDP, MAEDPCE.ENDP
";

$tempFile = tempnam(sys_get_temp_dir(), 'sql_cheques_');
file_put_contents($tempFile, $query . "\ngo\nquit");
$command = "tsql -H {$host} -p {$port} -U {$username} -P {$password} -D " . ($database ?: 'master') . " < {$tempFile} 2>&1";
$output = shell_exec($command);
@unlink($tempFile);

echo "═══════════════════════════════════════════════════════════\n";
echo "  Resultado búsqueda de cheques\n";
echo "═══════════════════════════════════════════════════════════\n\n";
echo $output . "\n";

