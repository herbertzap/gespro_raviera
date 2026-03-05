<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Cotizacion;

/**
 * Verifica si una NVV (por ID de cotización o por número NVV) existe en SQL Server.
 * Útil para diagnosticar cuando el sistema dice que se insertó pero no aparece en el ERP.
 */
class VerificarNvvEnSqlServer extends Command
{
    protected $signature = 'nvv:verificar-en-sql 
                            {id : ID de la cotización (ej: 208) o número NVV (ej: 39544)}';
    protected $description = 'Verifica si la NVV existe en SQL Server (MAEEDO) por cotización o por número NVV';

    public function handle()
    {
        $id = $this->argument('id');
        $database = env('SQLSRV_EXTERNAL_DATABASE');

        // Determinar si es ID de cotización o número NVV
        $cotizacion = Cotizacion::find($id);
        if ($cotizacion) {
            $numeroNvv = $cotizacion->numero_nvv;
            $this->info("Cotización #{$id} encontrada en MySQL.");
            $this->info("  numero_nvv (NUDO): " . ($numeroNvv ?? 'null'));
            $this->info("  estado_aprobacion: " . ($cotizacion->estado_aprobacion ?? 'null'));
            if (empty($numeroNvv)) {
                $this->warn("Esta cotización no tiene numero_nvv guardado (no se insertó en SQL o falló el guardado).");
                return 1;
            }
        } else {
            $numeroNvv = is_numeric($id) ? (int)$id : null;
            if ($numeroNvv === null) {
                $this->error("No se encontró cotización con ID '{$id}' y no es un número NVV válido.");
                return 1;
            }
            $this->info("Buscando en SQL Server por número NVV (NUDO): {$numeroNvv}");
        }

        $nudoFormateado = str_pad((string)$numeroNvv, 10, '0', STR_PAD_LEFT);

        // 1) COUNT para resultado definitivo
        $queryCount = "SELECT COUNT(*) AS total FROM MAEEDO WHERE TIDO = 'NVV' AND EMPRESA = '01' AND (RTRIM(LTRIM(CAST(NUDO AS VARCHAR(20)))) = '{$nudoFormateado}' OR RTRIM(LTRIM(CAST(NUDO AS VARCHAR(20)))) = '{$numeroNvv}')";
        $tempFile = tempnam(sys_get_temp_dir(), 'sql_verif_');
        file_put_contents($tempFile, $queryCount . "\ngo\nquit");
        $command = "tsql -H " . env('SQLSRV_EXTERNAL_HOST') .
            " -p " . env('SQLSRV_EXTERNAL_PORT') .
            " -U " . env('SQLSRV_EXTERNAL_USERNAME') .
            " -P " . env('SQLSRV_EXTERNAL_PASSWORD') .
            " -D {$database} < {$tempFile} 2>&1";
        $resultCount = shell_exec($command);
        unlink($tempFile);

        $count = 0;
        if ($resultCount) {
            foreach (explode("\n", $resultCount) as $line) {
                $t = trim($line);
                if ($t !== '' && is_numeric($t) && (int)$t >= 0 && (int)$t <= 999999) {
                    $count = (int)$t;
                    break;
                }
            }
        }

        $this->newLine();
        $this->info("Consulta COUNT en MAEEDO (TIDO=NVV, EMPRESA=01, NUDO={$numeroNvv} o {$nudoFormateado}):");
        $this->line($resultCount);
        $this->newLine();

        if ($resultCount && (str_contains($resultCount, 'error') || str_contains(strtolower($resultCount), 'msg '))) {
            $this->error("Parece que hubo un error en la consulta o conexión. Revisa el resultado arriba.");
            return 1;
        }

        if ($count > 0) {
            $this->info("✅ La NVV N° {$numeroNvv} SÍ existe en SQL Server (MAEEDO). Registros encontrados: {$count}");
            return 0;
        }

        $this->warn("❌ La NVV N° {$numeroNvv} NO aparece en SQL Server (MAEEDO).");
        $this->line("   Posibles causas: no se insertó, se insertó en otra base/empresa, o el NUDO tiene otro formato.");
        $this->line("   Revisa storage/logs/laravel.log buscando 'Verificación SQL Server' y 'Cotización ID: 208' para ver qué devolvió la verificación en el momento del insert.");
        return 1;
    }
}
