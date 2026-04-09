<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClienteSucursal extends Model
{
    protected $table = 'cliente_sucursales';

    protected $fillable = [
        'codigo_cliente',
        'suen',
        'direccion',
        'telefono',
        'region',
        'comuna',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'codigo_cliente', 'codigo_cliente');
    }

    /**
     * Sincroniza sucursales desde MAEEN (misma base que clientes).
     * Se invoca tras sincronizar clientes o manualmente.
     */
    public static function sincronizarDesdeMaeen(): array
    {
        try {
            $host = env('SQLSRV_EXTERNAL_HOST');
            $port = env('SQLSRV_EXTERNAL_PORT', '1433');
            $database = env('SQLSRV_EXTERNAL_DATABASE');
            $username = env('SQLSRV_EXTERNAL_USERNAME');
            $password = env('SQLSRV_EXTERNAL_PASSWORD');

            $query = "
                SELECT 
                    CAST(MAEEN.KOEN AS VARCHAR(20)) + '|' +
                    CAST(LTRIM(RTRIM(ISNULL(MAEEN.SUEN,''))) AS VARCHAR(20)) + '|' +
                    CAST(ISNULL(MAEEN.DIEN, '') AS VARCHAR(200)) + '|' +
                    CAST(ISNULL(MAEEN.FOEN, '') AS VARCHAR(30)) + '|' +
                    CAST(ISNULL(TABCI.NOKOCI, '') AS VARCHAR(80)) + '|' +
                    CAST(ISNULL(TABCM.NOKOCM, '') AS VARCHAR(80)) AS DATOS
                FROM dbo.MAEEN 
                LEFT JOIN dbo.TABFU ON MAEEN.KOFUEN = TABFU.KOFU
                LEFT JOIN dbo.TABCI ON MAEEN.PAEN = TABCI.KOPA AND MAEEN.CIEN = TABCI.KOCI
                LEFT JOIN dbo.TABCM ON MAEEN.PAEN = TABCM.KOPA AND MAEEN.CIEN = TABCM.KOCI AND MAEEN.CMEN = TABCM.KOCM
                WHERE MAEEN.KOFUEN IS NOT NULL AND MAEEN.KOFUEN != ''
                ORDER BY MAEEN.KOEN, MAEEN.SUEN
            ";

            $tempFile = tempnam(sys_get_temp_dir(), 'sql_suc_');
            file_put_contents($tempFile, $query . "\ngo\nquit");
            $command = "tsql -H {$host} -p {$port} -U {$username} -P {$password} -D {$database} < {$tempFile} 2>&1";
            $output = shell_exec($command);
            unlink($tempFile);

            if (!$output || str_contains(strtolower($output), 'error')) {
                \Log::error('ClienteSucursal::sincronizarDesdeMaeen tsql: ' . substr((string) $output, 0, 500));

                return ['success' => false, 'message' => 'Error consultando MAEEN', 'total' => 0];
            }

            if (!mb_check_encoding($output, 'UTF-8')) {
                $output = @mb_convert_encoding($output, 'UTF-8', 'ISO-8859-1') ?: $output;
            }

            $filas = [];
            $lines = explode("\n", $output);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_contains($line, 'locale') || str_contains($line, 'Setting')
                    || str_contains($line, 'Msg ') || str_contains($line, 'rows affected')
                    || preg_match('/^\d+>$/', $line)) {
                    continue;
                }
                if (!preg_match('/^(\d{8})\s+\|(.+)$/', $line, $matches)) {
                    continue;
                }
                $payload = $matches[2];
                $parts = explode('|', $payload);
                if (count($parts) < 5) {
                    continue;
                }
                $koen = trim($matches[1]);
                $suen = trim($parts[0] ?? '');
                $direccion = trim($parts[1] ?? '');
                $telefono = trim($parts[2] ?? '');
                $region = trim($parts[3] ?? '');
                $comuna = trim($parts[4] ?? '');

                $filas[] = [
                    'codigo_cliente' => $koen,
                    'suen' => $suen,
                    'direccion' => $direccion !== '' ? $direccion : null,
                    'telefono' => $telefono !== '' ? $telefono : null,
                    'region' => $region !== '' ? $region : null,
                    'comuna' => $comuna !== '' ? $comuna : null,
                ];
            }

            if ($filas === []) {
                return ['success' => true, 'total' => 0, 'message' => 'Sin filas MAEEN'];
            }

            $codigos = array_unique(array_column($filas, 'codigo_cliente'));
            self::whereIn('codigo_cliente', $codigos)->delete();

            foreach ($filas as $f) {
                self::create($f);
            }

            \Log::info('ClienteSucursal sincronizadas: ' . count($filas) . ' filas para ' . count($codigos) . ' clientes');

            return ['success' => true, 'total' => count($filas), 'clientes' => count($codigos)];
        } catch (\Throwable $e) {
            \Log::error('ClienteSucursal::sincronizarDesdeMaeen: ' . $e->getMessage());

            return ['success' => false, 'message' => $e->getMessage(), 'total' => 0];
        }
    }
}
