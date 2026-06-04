<?php

namespace App\Console\Commands;

use App\Models\Producto;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ActualizarProductosOcultosErp extends Command
{
    protected $signature = 'productos:actualizar-ocultos-erp';

    protected $description = 'Marca activo=0 y atpr=OCU en MySQL para productos ocultos en MAEPR (ERP)';

    public function handle(): int
    {
        $this->info('Consultando productos ocultos (ATPR=OCU) en SQL Server...');

        $codigos = $this->obtenerCodigosOcultosDesdeSql();
        if (empty($codigos)) {
            $this->warn('No se obtuvieron códigos OCU desde SQL Server.');

            return 1;
        }

        $this->info('Códigos ocultos en ERP: '.count($codigos));

        $actualizados = 0;
        foreach ($codigos as $codigo) {
            $n = Producto::whereRaw('TRIM(KOPR) = ?', [$codigo])->update([
                'atpr' => 'OCU',
                'activo' => false,
            ]);
            $actualizados += $n;
        }

        $this->info("Filas MySQL actualizadas (activo=0, atpr=OCU): {$actualizados}");
        Log::info("productos:actualizar-ocultos-erp — {$actualizados} productos marcados ocultos");

        return 0;
    }

    /**
     * @return array<int, string>
     */
    private function obtenerCodigosOcultosDesdeSql(): array
    {
        try {
            $rows = DB::connection('sqlsrv_external')
                ->table('MAEPR')
                ->whereRaw("RTRIM(ATPR) = 'OCU'")
                ->selectRaw('RTRIM(KOPR) AS kopr')
                ->pluck('kopr')
                ->map(fn ($k) => trim((string) $k))
                ->filter()
                ->unique()
                ->values()
                ->all();

            if (! empty($rows)) {
                return $rows;
            }
        } catch (\Throwable $e) {
            $this->warn('sqlsrv no disponible, usando tsql: '.$e->getMessage());
        }

        $host = env('SQLSRV_EXTERNAL_HOST');
        $port = env('SQLSRV_EXTERNAL_PORT', '1433');
        $database = env('SQLSRV_EXTERNAL_DATABASE');
        $username = env('SQLSRV_EXTERNAL_USERNAME');
        $password = env('SQLSRV_EXTERNAL_PASSWORD');

        $query = "SELECT RTRIM(KOPR) AS KOPR FROM MAEPR WHERE RTRIM(ATPR) = 'OCU'";
        $tempFile = tempnam(sys_get_temp_dir(), 'sql_ocu_');
        file_put_contents($tempFile, $query."\ngo\nquit");
        $output = shell_exec("tsql -H {$host} -p {$port} -U {$username} -P {$password} -D {$database} < {$tempFile} 2>&1");
        unlink($tempFile);

        $codigos = [];
        foreach (explode("\n", $output ?? '') as $line) {
            $line = trim($line);
            if ($line === '' || stripos($line, 'KOPR') !== false
                || stripos($line, 'Setting') !== false
                || stripos($line, 'locale') !== false
                || stripos($line, 'rows affected') !== false
                || preg_match('/^\d+>$/', $line)) {
                continue;
            }
            if (preg_match('/^([A-Z0-9]+)$/', $line, $m)) {
                $codigos[] = $m[1];
            }
        }

        return array_values(array_unique($codigos));
    }
}
