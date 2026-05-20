<?php

namespace App\Console\Commands;

use App\Models\Cotizacion;
use App\Models\CotizacionProducto;
use App\Models\StockComprometido;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DuplicarCotizacionNvv extends Command
{
    protected $signature = 'nvv:duplicar-cotizacion
                            {cotizacion_id : ID de la cotización en MySQL (ej. 707)}
                            {--sin-stock : No duplicar filas de stock_comprometidos}
                            {--dry-run : Mostrar resumen sin escribir en la base}
                            {--force : Permitir ejecución fuera de entorno local (solo pruebas)}';

    protected $description = 'Duplica una nota de venta (cotización) con todos sus productos y stock comprometido, con nuevo ID y sin número NVV SQL, para pruebas de Picking / MAEDTLI.';

    public function handle(): int
    {
        if (! app()->environment('local', 'testing') && ! $this->option('force')) {
            $this->error('Comando de prueba bloqueado en producción. Use --force solo si es intencional.');

            return 1;
        }

        $origenId = (int) $this->argument('cotizacion_id');
        $sinStock = (bool) $this->option('sin-stock');
        $dryRun = (bool) $this->option('dry-run');

        $origen = Cotizacion::with(['productos'])->find($origenId);
        if (! $origen) {
            $this->error("No existe cotización con ID {$origenId}.");

            return 1;
        }

        if ($origen->tipo_documento !== 'nota_venta') {
            $this->warn("La cotización {$origenId} no es tipo_documento=nota_venta (actual: {$origen->tipo_documento}). Se continúa igualmente.");
        }

        $nLineas = $origen->productos->count();
        $conDesc = $origen->productos->filter(function (CotizacionProducto $p) {
            $pct = (float) ($p->descuento_porcentaje ?? 0);
            $val = (float) ($p->descuento_valor ?? 0);

            return $pct > 0 || $val > 0;
        })->count();

        $stockCount = StockComprometido::where('cotizacion_id', $origenId)->count();

        $this->info("Origen: cotización #{$origenId} — {$nLineas} líneas de producto, {$conDesc} con descuento (esperados inserts MAEDTLI), {$stockCount} stock_comprometidos.");

        if ($dryRun) {
            $this->warn('Dry-run: no se creó ningún registro.');

            return 0;
        }

        $nuevoId = null;

        DB::transaction(function () use ($origen, $sinStock, &$nuevoId) {
            $nueva = $origen->replicate([
                'id',
                'created_at',
                'updated_at',
            ]);

            $sufijo = now()->format('Y-m-d H:i');
            $obs = trim((string) ($nueva->observaciones ?? ''));
            $marca = "[Duplicado desde cotización #{$origen->id} — {$sufijo}]";
            $nueva->observaciones = $obs === '' ? $marca : $obs."\n".$marca;

            $nueva->nota_original_id = $origen->id;
            $nueva->numero_nvv = null;
            $nueva->facturada = false;
            $nueva->numero_factura = null;
            $nueva->fecha_facturacion = null;
            $nueva->fecha_aprobacion = null;
            $nueva->aprobado_por = null;
            $nueva->fecha_cancelacion = null;
            $nueva->cancelado_por = null;
            $nueva->motivo_rechazo = null;

            $nueva->aprobado_por_supervisor = null;
            $nueva->fecha_aprobacion_supervisor = null;
            $nueva->comentarios_supervisor = null;
            $nueva->aprobado_por_compras = null;
            $nueva->fecha_aprobacion_compras = null;
            $nueva->comentarios_compras = null;
            $nueva->aprobado_por_picking = null;
            $nueva->fecha_aprobacion_picking = null;
            $nueva->comentarios_picking = null;
            $nueva->observaciones_picking = null;
            $nueva->guia_picking_bodega = null;
            $nueva->guia_picking_separado_por = null;
            $nueva->guia_picking_revisado_por = null;
            $nueva->guia_picking_numero_bultos = null;
            $nueva->guia_picking_firma = null;

            $nueva->estado = $origen->estado;
            $nueva->estado_aprobacion = $origen->estado_aprobacion;

            $nueva->save();
            $nuevoId = $nueva->id;

            foreach ($origen->productos as $prod) {
                $nuevoProd = $prod->replicate(['id', 'created_at', 'updated_at']);
                $nuevoProd->cotizacion_id = $nuevoId;
                $nuevoProd->save();
            }

            if (! $sinStock) {
                $stocks = StockComprometido::where('cotizacion_id', $origen->id)->get();
                foreach ($stocks as $st) {
                    $ns = $st->replicate(['id', 'created_at', 'updated_at']);
                    $ns->cotizacion_id = $nuevoId;
                    $ns->estado = 'activo';
                    $ns->fecha_liberacion = null;
                    $ns->save();
                }
            }
        });

        $this->info("Listo. Nueva cotización MySQL ID: {$nuevoId}");
        $this->line('Tras aprobar Picking, comparar en SQL Server: líneas MAEDTLI ≈ líneas con descuento % o valor en cotizacion_productos (ver AprobacionController insert MAEDTLI).');

        return 0;
    }
}
