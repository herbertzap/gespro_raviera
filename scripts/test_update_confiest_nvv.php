<?php
/**
 * Script de prueba: mismo UPDATE CONFIEST que la app (NVV + 1).
 * Uso: php scripts/test_update_confiest_nvv.php
 *       php scripts/test_update_confiest_nvv.php --ejecutar   (ejecuta el UPDATE)
 *
 * Sin --ejecutar: solo muestra el valor actual y el UPDATE que se haría.
 * Con --ejecutar: ejecuta el UPDATE y muestra antes/después.
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

$argv = $argv ?? [];
$ejecutar = in_array('--ejecutar', $argv);
$nvvManual = null;
foreach ($argv as $i => $arg) {
    if ($arg === '--ejecutar' && isset($argv[$i + 1]) && is_numeric($argv[$i + 1])) {
        $nvvManual = (int) $argv[$i + 1];
        break;
    }
    if (is_numeric($arg) && (int) $arg > 0) {
        $nvvManual = (int) $arg;
        break;
    }
}

function runTsql($sql, $host, $port, $username, $password, $database) {
    $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
    file_put_contents($tempFile, $sql . "\ngo\nquit");
    $command = "tsql -H {$host} -p {$port} -U {$username} -P {$password} -D " . ($database ?: 'master') . " < {$tempFile} 2>&1";
    $out = shell_exec($command);
    @unlink($tempFile);
    return $out;
}

// SELECT actual (mismo WHERE que la app)
$selectSql = "SELECT EMPRESA,MODALIDAD,NVV FROM CONFIEST WHERE MODALIDAD = CHAR(5)+CHAR(32)+CHAR(5)+CHAR(32)+CHAR(5)";
echo "\n📋 SELECT (valor actual):\n";
echo $selectSql . "\n\n";

$outSelect = runTsql($selectSql, $host, $port, $username, $password, $database);
echo "📤 Resultado:\n";
echo $outSelect . "\n";

// Extraer número NVV: recorrer líneas tras el header (EMPRESA/MODALIDAD/NVV), quitar prefijos "1> " "2> ", buscar número
$nvvActual = null;
$lines = explode("\n", $outSelect ?? '');
$headerFound = false;
foreach ($lines as $line) {
    $line = trim($line);
    if (empty($line) || stripos($line, 'locale') !== false || stripos($line, 'Setting') !== false || stripos($line, 'charset') !== false) {
        continue;
    }
    if (stripos($line, 'EMPRESA') !== false && stripos($line, 'NVV') !== false) {
        $headerFound = true;
        continue;
    }
    if ($headerFound) {
        $line = preg_replace('/^\d+>\s*/', '', $line);
        if (preg_match('/\b(\d{4,10})\b/', $line, $m)) {
            $nvvActual = (int) $m[1];
            break;
        }
    }
}
if ($nvvActual === null && preg_match('/\b(\d{4,10})\b/', $outSelect, $m)) {
    $nvvActual = (int) $m[1];
}
if ($nvvActual === null && $nvvManual !== null) {
    $nvvActual = $nvvManual;
    echo "📌 Usando NVV manual: {$nvvActual}\n\n";
}
if ($nvvActual === null) {
    echo "⚠️ No se encontró NVV en el resultado. Posibles causas:\n";
    echo "   - El WHERE MODALIDAD = CHAR(5)+CHAR(32)+CHAR(5)+CHAR(32)+CHAR(5) no coincide (revisa CONFIEST).\n";
    echo "   - Ejecuta: php scripts/ver_confiest.php y revisa si hay filas.\n";
    echo "   - Puedes probar con un valor manual: php scripts/test_update_confiest_nvv.php --ejecutar 39304\n";
    exit(1);
}

$nvvSiguiente = $nvvActual + 1;
$nvvSiguienteFormateado = str_pad((string) $nvvSiguiente, 10, '0', STR_PAD_LEFT);

// UPDATE (igual que en NotaVentaController)
$updateSql = "UPDATE CONFIEST SET NVV = '{$nvvSiguienteFormateado}' WHERE MODALIDAD = CHAR(5)+CHAR(32)+CHAR(5)+CHAR(32)+CHAR(5)";
echo "═══════════════════════════════════════════════════════════\n";
echo "  UPDATE (mismo que la app)\n";
echo "═══════════════════════════════════════════════════════════\n\n";
echo $updateSql . "\n\n";
echo "  NVV pasaría de {$nvvActual} a {$nvvSiguiente}\n\n";

if ($ejecutar) {
    echo "🔧 Ejecutando UPDATE...\n\n";
    $outUpdate = runTsql($updateSql, $host, $port, $username, $password, $database);
    echo "📤 Salida tsql:\n";
    echo $outUpdate . "\n";
    if ($outUpdate && (stripos($outUpdate, 'Msg ') !== false || stripos($outUpdate, 'error') !== false)) {
        echo "❌ Posible error. Revisa la salida.\n";
    } else {
        echo "✅ UPDATE enviado. Verificando con SELECT...\n\n";
        $outDespues = runTsql($selectSql, $host, $port, $username, $password, $database);
        echo "📤 CONFIEST después del UPDATE:\n";
        echo $outDespues . "\n";
    }
} else {
    echo "💡 Para ejecutar el UPDATE: php scripts/test_update_confiest_nvv.php --ejecutar\n";
}

echo "\n";
