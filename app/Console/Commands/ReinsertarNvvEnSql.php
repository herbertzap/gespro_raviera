<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\AprobacionController;

/**
 * Reintenta el insert en SQL Server para una NVV ya aprobada por picking.
 * Útil cuando el sistema marcó la NVV como insertada pero no aparece en SQL.
 */
class ReinsertarNvvEnSql extends Command
{
    protected $signature = 'nvv:reinsertar {id : ID de la cotización (ej: 208)}';
    protected $description = 'Reenvía la NVV a SQL Server (mismos datos, nuevo número NVV). Solo para NVV ya aprobadas por picking.';

    public function handle()
    {
        $id = $this->argument('id');
        $this->info("Reintentando insert en SQL Server para cotización #{$id}...");

        try {
            $controller = app()->make(AprobacionController::class);
            $resultado = $controller->reinsertarNvvEnSqlServerById($id);
            $numeroNVV = $resultado['numero_correlativo'] ?? $resultado['nota_venta_id'];
            $this->info("✅ NVV reenviada correctamente.");
            $this->line("   N° NVV: {$numeroNVV}");
            $this->line("   ID interno: {$resultado['nota_venta_id']}");
            return 0;
        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            return 1;
        }
    }
}
