<?php

declare(strict_types=1);

/**
 * Uso: php scripts/check_stock_maest.php 41A257120P000
 */
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$sku = isset($argv[1]) ? trim($argv[1]) : '41A257120P000';

echo "SKU consultado: {$sku}\n\n";

try {
    $rows = Illuminate\Support\Facades\DB::connection('sqlsrv_external')
        ->table('MAEST')
        ->selectRaw('RTRIM(KOPR) as koprr, RTRIM(KOBO) as kobo, CAST(SUM(STFI1) AS FLOAT) as stfi1, CAST(SUM(STOCNV1) AS FLOAT) as stocnv')
        ->whereRaw('RTRIM(KOPR) = ?', [$sku])
        ->groupByRaw('RTRIM(KOPR), RTRIM(KOBO)')
        ->get();

    echo 'MAEST por bodega (filas): '.$rows->count()."\n";
    foreach ($rows as $r) {
        $sf = (float) $r->stfi1;
        $sc = abs((float) $r->stocnv);
        $disp = max(0.0, $sf - $sc);
        echo sprintf("  KOBO=[%s] STFI1=%s STOCNV1=%s disponible_bruto=%s\n",
            $r->kobo ?? '',
            number_format($sf, 3, '.', ''),
            number_format($sc, 3, '.', ''),
            number_format($disp, 3, '.', '')
        );
    }

    $agg = Illuminate\Support\Facades\DB::connection('sqlsrv_external')
        ->table('MAEST')
        ->selectRaw('CAST(SUM(ISNULL(STFI1,0)) AS FLOAT) sf, CAST(SUM(ISNULL(STOCNV1,0)) AS FLOAT) sc')
        ->whereRaw('RTRIM(KOPR) = ?', [$sku])
        ->first();

    $sfT = (float) ($agg->sf ?? 0);
    $scT = abs((float) ($agg->sc ?? 0));
    echo "\nAgregado todas las bodegas: STFI1_total={$sfT} STOCNV1_total={$scT} disponible_bruto=".max(0.0, $sfT - $scT)."\n";

    $pr = Illuminate\Support\Facades\DB::connection('sqlsrv_external')
        ->table('MAEPR')
        ->selectRaw('RTRIM(KOPR) as kopr, RTRIM(ISNULL(NOKOPR,\'\')) as nombre, RTRIM(ISNULL(ATPR,\'\')) as atpr')
        ->whereRaw('RTRIM(KOPR) = ?', [$sku])
        ->first();

    if ($pr) {
        echo "\nMAEPR: código=[{$pr->kopr}] ATPR=[{$pr->atpr}]\n";
        echo 'Nombre: '.substr((string) $pr->nombre, 0, 120)."\n";
    } else {
        echo "\nMAEPR: sin fila para este KOPR.\n";
    }
} catch (Throwable $e) {
    echo 'Error PDO sqlsrv: '.$e->getMessage()."\n";
    exit(1);
}

echo "\n--- Stock comprometido local (MySQL app) ---\n";
$loc = Illuminate\Support\Facades\DB::table('stock_comprometidos')
    ->whereRaw('TRIM(producto_codigo) = ?', [$sku])
    ->where('estado', 'activo')
    ->sum('cantidad_comprometida');
echo 'Suma activa: '.(float) $loc."\n";

$p = Illuminate\Support\Facades\DB::table('productos')->whereRaw('TRIM(KOPR) = ?', [$sku])->first();
if ($p) {
    echo "\n--- Tabla MySQL productos (sync) ---\n";
    echo 'stock_fisico='.(float) ($p->stock_fisico ?? 0).' stock_comprometido='.(float) ($p->stock_comprometido ?? 0)." stock_disponible_col=".(float) ($p->stock_disponible ?? 0)."\n";
} else {
    echo "\nTabla MySQL productos: sin fila TRIM(KOPR)=SKU.\n";
}
