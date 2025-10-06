<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Producto;
use PhpOffice\PhpSpreadsheet\IOFactory;

class SincronizarMultiplosDesdeExcel extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'multiplos:sincronizar 
                            {archivo=docs/multiplos26-09.xlsx : Ruta del archivo Excel}
                            {--dry-run : Simular sin guardar cambios}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincronizar múltiplos de venta desde archivo Excel';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $archivoPath = base_path($this->argument('archivo'));
        $dryRun = $this->option('dry-run');

        if (!file_exists($archivoPath)) {
            $this->error("El archivo no existe: {$archivoPath}");
            return 1;
        }

        $this->info("📊 Iniciando sincronización de múltiplos...");
        $this->info("📁 Archivo: {$archivoPath}");
        
        if ($dryRun) {
            $this->warn("🔍 MODO DRY-RUN: No se guardarán cambios");
        }

        try {
            // Cargar archivo Excel
            $this->info("📖 Leyendo archivo Excel...");
            $spreadsheet = IOFactory::load($archivoPath);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            $totalFilas = count($rows);
            $this->info("✓ Total de filas leídas: {$totalFilas}");

            // Estadísticas
            $actualizados = 0;
            $noEncontrados = [];
            $errores = [];
            $sinCambios = 0;

            // Crear barra de progreso
            $bar = $this->output->createProgressBar($totalFilas - 1); // -1 por el encabezado
            $bar->start();

            // Procesar desde la fila 2 (saltamos encabezado)
            for ($i = 1; $i < $totalFilas; $i++) {
                $row = $rows[$i];
                
                // Verificar que la fila tenga datos
                if (empty($row[0])) {
                    continue;
                }

                $sku = trim($row[0]);
                $multiploVenta = !empty($row[2]) ? (int)$row[2] : 1;

                // Validar múltiplo
                if ($multiploVenta < 1) {
                    $multiploVenta = 1;
                }

                // Buscar producto por KOPR
                $producto = Producto::where('KOPR', $sku)->first();

                if ($producto) {
                    // Solo actualizar si cambió
                    if ($producto->multiplo_venta != $multiploVenta) {
                        if (!$dryRun) {
                            $producto->multiplo_venta = $multiploVenta;
                            $producto->save();
                        }
                        $actualizados++;
                    } else {
                        $sinCambios++;
                    }
                } else {
                    $noEncontrados[] = $sku;
                }

                $bar->advance();
            }

            $bar->finish();
            $this->newLine(2);

            // Mostrar resumen
            $this->info("✅ Sincronización completada!");
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info("📊 RESUMEN:");
            $this->info("   • Productos actualizados: {$actualizados}");
            $this->info("   • Productos sin cambios: {$sinCambios}");
            $this->info("   • Productos no encontrados: " . count($noEncontrados));
            
            if (count($noEncontrados) > 0) {
                $this->warn("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
                $this->warn("⚠️  PRODUCTOS NO ENCONTRADOS (primeros 20):");
                foreach (array_slice($noEncontrados, 0, 20) as $sku) {
                    $this->warn("   • {$sku}");
                }
                if (count($noEncontrados) > 20) {
                    $this->warn("   ... y " . (count($noEncontrados) - 20) . " más");
                }
            }

            if ($dryRun) {
                $this->newLine();
                $this->warn("🔍 Modo DRY-RUN: Ningún cambio fue guardado");
                $this->info("💡 Ejecuta sin --dry-run para aplicar los cambios");
            }

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Error al procesar el archivo: " . $e->getMessage());
            $this->error($e->getTraceAsString());
            return 1;
        }
    }
}
