<?php

namespace App\Services;

use App\Models\Cotizacion;
use App\Models\StockComprometido;
use Illuminate\Support\Facades\Log;

class NvvSqlInsertRollbackService
{
    /**
     * Revierte en ERP y MySQL lo insertado/actualizado durante un insert NVV fallido.
     */
    public function ejecutarRollback(
        NvvSqlInsertContext $ctx,
        Cotizacion $cotizacion,
        ?string $numeroNvvMysqlOriginal
    ): array {
        $pasos = [];

        if (! $ctx->tieneDatosEnErp() && empty($ctx->stockComprometidoIds)) {
            $this->revertirMysql($cotizacion, $numeroNvvMysqlOriginal, $ctx->stockComprometidoIds);

            return ['ejecutado' => true, 'pasos' => ['mysql_sin_cambios_erp']];
        }

        Log::warning("Iniciando rollback NVV SQL — cotización {$cotizacion->id}, IDMAEEDO {$ctx->idMaeedo}");

        if ($ctx->confiestUpdated && $ctx->confiestNvvAnterior !== null) {
            $pasos['confiest'] = $this->revertirConfiest($ctx->confiestNvvAnterior);
        }

        if ($ctx->stockUpdated && $ctx->productosCantidades !== []) {
            $pasos['stock'] = $this->revertirStock($ctx->productosCantidades);
        }

        if ($ctx->idMaeedo > 0) {
            if ($ctx->maedtliInserted) {
                $pasos['maedtli'] = $this->eliminarPorIdMaeedo('MAEDTLI', $ctx->idMaeedo);
            }
            if ($ctx->maeedoobInserted) {
                $pasos['maeedoob'] = $this->eliminarPorIdMaeedo('MAEEDOOB', $ctx->idMaeedo);
            }
            if ($ctx->maeddoInserted) {
                $pasos['maeddo'] = $this->eliminarPorIdMaeedo('MAEDDO', $ctx->idMaeedo);
            }
            if ($ctx->maeedoInserted) {
                $pasos['maeedo'] = $this->eliminarMaeedo($ctx->idMaeedo);
            }
        }

        $pasos['mysql'] = $this->revertirMysql($cotizacion, $numeroNvvMysqlOriginal, $ctx->stockComprometidoIds);

        Log::warning("Rollback NVV SQL finalizado — cotización {$cotizacion->id}", $pasos);

        return ['ejecutado' => true, 'pasos' => $pasos];
    }

    public function leerConfiestNvvActual(): ?string
    {
        $query = "SELECT NVV FROM CONFIEST WHERE MODALIDAD = CHAR(5)+CHAR(32)+CHAR(5)+CHAR(32)+CHAR(5)";
        $result = $this->ejecutarTsql($query);
        if ($result === null) {
            return null;
        }

        if (preg_match('/\b(\d{6,})\b/', $result, $m)) {
            return str_pad($m[1], 10, '0', STR_PAD_LEFT);
        }

        return null;
    }

    private function revertirConfiest(string $nvvAnterior): bool
    {
        $nvv = str_replace("'", "''", $nvvAnterior);
        $sql = "UPDATE CONFIEST SET NVV = '{$nvv}' WHERE MODALIDAD = CHAR(5)+CHAR(32)+CHAR(5)+CHAR(32)+CHAR(5)";
        $result = $this->ejecutarTsql($sql);

        return ! $this->tsqlIndicaError($result);
    }

    /**
     * @param  array<string, float>  $productosCantidades
     */
    private function revertirStock(array $productosCantidades): bool
    {
        $ok = true;

        foreach ($productosCantidades as $codigo => $cantidad) {
            $codigoEsc = str_replace("'", "''", trim(substr((string) $codigo, 0, 13)));
            $cantidad = (float) $cantidad;
            if ($cantidad <= 0) {
                continue;
            }

            $updates = [
                "UPDATE MAEPR SET STOCNV1 = ISNULL(STOCNV1, 0) - {$cantidad}, STOCNV2 = ISNULL(STOCNV2, 0) - {$cantidad} WHERE KOPR = '{$codigoEsc}'",
                "UPDATE MAEST SET STOCKSALIDA = ISNULL(STOCKSALIDA, 0) - {$cantidad} WHERE KOPR = '{$codigoEsc}' AND EMPRESA = '01'",
                "UPDATE MAEST SET STOCNV1 = ISNULL(STOCNV1, 0) - {$cantidad}, STOCNV2 = ISNULL(STOCNV2, 0) - {$cantidad} WHERE KOPR = '{$codigoEsc}' AND EMPRESA = '01' AND KOSU = 'LIB' AND KOBO = 'LIB'",
                "UPDATE MAEPREM SET STOCNV1 = ISNULL(STOCNV1, 0) - {$cantidad}, STOCNV2 = ISNULL(STOCNV2, 0) - {$cantidad} WHERE KOPR = '{$codigoEsc}' AND EMPRESA = '01'",
            ];

            foreach ($updates as $sql) {
                $result = $this->ejecutarTsql($sql);
                if ($this->tsqlIndicaError($result)) {
                    Log::error("Rollback stock falló ({$codigoEsc}): ".substr($result ?? '', 0, 300));
                    $ok = false;
                }
            }
        }

        return $ok;
    }

    private function eliminarPorIdMaeedo(string $tabla, int $idMaeedo): bool
    {
        $sql = "DELETE FROM {$tabla} WHERE IDMAEEDO = {$idMaeedo}";
        $result = $this->ejecutarTsql($sql);

        return ! $this->tsqlIndicaError($result);
    }

    private function eliminarMaeedo(int $idMaeedo): bool
    {
        $sql = "DELETE FROM MAEEDO WHERE IDMAEEDO = {$idMaeedo} AND EMPRESA = '01' AND TIDO = 'NVV'";
        $result = $this->ejecutarTsql($sql);

        return ! $this->tsqlIndicaError($result);
    }

    /**
     * @param  int[]  $stockComprometidoIds
     */
    private function revertirMysql(Cotizacion $cotizacion, ?string $numeroNvvOriginal, array $stockComprometidoIds): bool
    {
        try {
            if ($numeroNvvOriginal !== $cotizacion->numero_nvv) {
                $cotizacion->numero_nvv = $numeroNvvOriginal;
                $cotizacion->save();
            }

            if ($stockComprometidoIds !== []) {
                StockComprometido::whereIn('id', $stockComprometidoIds)->each(function (StockComprometido $stock) {
                    $stock->revertirProcesado();
                });
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('Rollback MySQL NVV falló: '.$e->getMessage());

            return false;
        }
    }

    private function ejecutarTsql(string $query): ?string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'sql_rb_');
        file_put_contents($tempFile, $query."\ngo\nquit");
        $command = 'tsql -H '.env('SQLSRV_EXTERNAL_HOST')
            .' -p '.env('SQLSRV_EXTERNAL_PORT')
            .' -U '.env('SQLSRV_EXTERNAL_USERNAME')
            .' -P '.env('SQLSRV_EXTERNAL_PASSWORD')
            .' -D '.env('SQLSRV_EXTERNAL_DATABASE')
            ." < {$tempFile} 2>&1";
        $result = shell_exec($command);
        unlink($tempFile);

        return $result;
    }

    private function tsqlIndicaError(?string $result): bool
    {
        if ($result === null || $result === '') {
            return false;
        }
        if (preg_match('/Msg (\d+), Level (\d+), State \d+/', $result, $matches)) {
            return (int) ($matches[2] ?? 0) >= 11;
        }

        return str_contains($result, 'Cannot insert')
            || str_contains($result, 'violation')
            || str_contains($result, 'constraint')
            || str_contains($result, 'Permission denied')
            || str_contains($result, 'Invalid object name');
    }
}
