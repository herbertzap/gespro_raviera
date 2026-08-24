<?php

namespace App\Console\Commands;

use App\Models\StockComprometido;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Cierra (estado=procesado) el stock comprometido local de NVV que ya tienen
 * numero_nvv asignado. Evita que sigan restando del disponible en la app
 * cuando el compromiso real ya debe estar en SQL Server (STOCNV1).
 */
class CerrarStockComprometidoNvvAsignada extends Command
{
    protected $signature = 'stock:cerrar-comprometidos-nvv
                            {--dry-run : Solo listar, no actualizar}
                            {--limit=0 : Máximo de filas a procesar (0 = todas)}';

    protected $description = 'Marca como procesado el stock_comprometidos activo de NVV que ya tienen numero_nvv (evita descuadre de stock en la app)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit = (int) $this->option('limit');

        $this->info($dryRun
            ? 'DRY-RUN: buscando stock comprometido activo con NVV asignada...'
            : 'Cerrando stock comprometido activo con NVV asignada...');

        $base = DB::table('stock_comprometidos as s')
            ->join('cotizaciones as c', 'c.id', '=', 's.cotizacion_id')
            ->where('s.estado', 'activo')
            ->where('c.tipo_documento', 'nota_venta')
            ->whereNotNull('c.numero_nvv')
            ->where('c.numero_nvv', '!=', '')
            ->orderBy('s.id')
            ->select([
                's.id',
                's.producto_codigo',
                's.cantidad_comprometida',
                's.cotizacion_id',
                's.observaciones',
                'c.numero_nvv',
                'c.estado_aprobacion',
            ]);

        if ($limit > 0) {
            $base->limit($limit);
        }

        $filas = $base->get();
        $total = $filas->count();
        $qty = (float) $filas->sum('cantidad_comprometida');

        $this->info("Encontradas: {$total} filas | cantidad total: {$qty}");
        Log::info('stock:cerrar-comprometidos-nvv inicio', [
            'dry_run' => $dryRun,
            'filas' => $total,
            'cantidad' => $qty,
        ]);

        if ($total === 0) {
            $this->info('Nada que cerrar.');
            return self::SUCCESS;
        }

        if ($dryRun) {
            foreach ($filas->take(15) as $f) {
                $this->line(sprintf(
                    '  #%d SKU=%s qty=%s cot=%s nvv=%s aprob=%s',
                    $f->id,
                    trim((string) $f->producto_codigo),
                    $f->cantidad_comprometida,
                    $f->cotizacion_id,
                    $f->numero_nvv,
                    $f->estado_aprobacion
                ));
            }
            if ($total > 15) {
                $this->line('  ...');
            }
            $this->warn('Dry-run: no se actualizó ninguna fila.');
            return self::SUCCESS;
        }

        $ahora = now()->format('Y-m-d H:i:s');
        $nota = "\n\n✅ Procesado por cron stock:cerrar-comprometidos-nvv ({$ahora}): NVV ya asignada; compromiso debe estar en SQL (STOCNV1).";
        $actualizadas = 0;

        DB::transaction(function () use ($filas, $nota, &$actualizadas) {
            foreach ($filas as $f) {
                $obs = trim((string) ($f->observaciones ?? ''));
                $ok = StockComprometido::where('id', $f->id)
                    ->where('estado', 'activo')
                    ->update([
                        'estado' => 'procesado',
                        'observaciones' => $obs === '' ? trim($nota) : ($obs . $nota),
                        'updated_at' => now(),
                    ]);
                $actualizadas += $ok;
            }
        });

        $this->info("Filas marcadas como procesado: {$actualizadas}");
        Log::info('stock:cerrar-comprometidos-nvv fin', [
            'actualizadas' => $actualizadas,
            'cantidad' => $qty,
        ]);

        return self::SUCCESS;
    }
}
