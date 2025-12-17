<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Cotizacion;
use Spatie\Permission\Models\Role;

class LimpiarSistemaEntrega extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'limpiar:sistema-entrega';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Limpiar sistema para entrega: eliminar usuarios excepto Super Admin y AVS, eliminar NVV y cotizaciones pero mantener historiales, productos y clientes';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('🚀 Iniciando limpieza del sistema para entrega...');
        $this->newLine();

        // Confirmar antes de proceder
        if (!$this->confirm('¿Estás seguro de que quieres limpiar el sistema? Esta acción NO se puede deshacer.')) {
            $this->warn('Operación cancelada.');
            return Command::SUCCESS;
        }

        DB::beginTransaction();

        try {
            // 1. Eliminar usuarios excepto Super Admin y AVS
            $this->info('1️⃣ Eliminando usuarios (excepto Super Admin y AVS)...');
            $superAdminRole = Role::where('name', 'Super Admin')->first();
            $vendedorRole = Role::where('name', 'Vendedor')->first();
            
            $usersToKeep = User::where(function($query) use ($superAdminRole, $vendedorRole) {
                $query->whereHas('roles', function($q) use ($superAdminRole) {
                    $q->where('roles.id', $superAdminRole->id);
                })
                ->orWhere('codigo_vendedor', 'AVS');
            })->pluck('id')->toArray();

            $usersDeleted = User::whereNotIn('id', $usersToKeep)->delete();
            $this->info("   ✓ Eliminados {$usersDeleted} usuarios");
            
            // 2. Eliminar cotizaciones y NVV (pero mantener historiales)
            $this->info('2️⃣ Eliminando cotizaciones y notas de venta...');
            $cotizacionesDeleted = Cotizacion::whereIn('tipo_documento', ['cotizacion', 'nota_venta'])->delete();
            $this->info("   ✓ Eliminadas {$cotizacionesDeleted} cotizaciones/NVV");
            
            // 3. Eliminar productos_cotizacion (detalles de cotizaciones)
            $this->info('3️⃣ Eliminando productos de cotizaciones...');
            $productosCotizacionDeleted = 0;
            
            // Intentar ambas posibles tablas
            if (DB::getSchemaBuilder()->hasTable('productos_cotizacion')) {
                $productosCotizacionDeleted += DB::table('productos_cotizacion')->delete();
            }
            if (DB::getSchemaBuilder()->hasTable('cotizacion_productos')) {
                $productosCotizacionDeleted += DB::table('cotizacion_productos')->delete();
            }
            if (DB::getSchemaBuilder()->hasTable('cotizacion_producto')) {
                $productosCotizacionDeleted += DB::table('cotizacion_producto')->delete();
            }
            
            $this->info("   ✓ Eliminados {$productosCotizacionDeleted} productos de cotizaciones");
            
            // 4. Eliminar otras tablas relacionadas (si existen)
            $tablesToClean = [
                'stock_comprometido',
                'aprobaciones',
                'nota_ventas', // Tabla alternativa si existe
                'notificaciones',
            ];
            
            // NO eliminar historiales
            // NO eliminar: cotizaciones_historial, nota_venta_historial, historiales
            
            foreach ($tablesToClean as $table) {
                if (DB::getSchemaBuilder()->hasTable($table)) {
                    $deleted = DB::table($table)->delete();
                    $this->info("   ✓ Limpiada tabla {$table}: {$deleted} registros");
                }
            }
            
            // 5. Resetear contadores AUTO_INCREMENT
            $this->info('4️⃣ Reseteando contadores de tablas...');
            $tablesToReset = [
                'users',
                'cotizaciones',
                'productos_cotizacion',
                'cotizacion_productos',
                'cotizacion_producto',
                'stock_comprometido',
            ];
            
            foreach ($tablesToReset as $table) {
                if (DB::getSchemaBuilder()->hasTable($table)) {
                    try {
                        DB::statement("ALTER TABLE {$table} AUTO_INCREMENT = 1");
                        $this->info("   ✓ Contador reseteado para tabla {$table}");
                    } catch (\Exception $e) {
                        $this->warn("   ⚠️ No se pudo resetear contador de {$table}: " . $e->getMessage());
                    }
                }
            }
            
            // 6. Verificar que Super Admin y AVS existen
            $this->info('5️⃣ Verificando usuarios esenciales...');
            $superAdmin = User::role('Super Admin')->first();
            $avsUser = User::where('codigo_vendedor', 'AVS')->first();
            
            if (!$superAdmin) {
                $this->warn('   ⚠️ No se encontró usuario Super Admin');
            } else {
                $this->info("   ✓ Super Admin encontrado: {$superAdmin->email}");
            }
            
            if (!$avsUser) {
                $this->warn('   ⚠️ No se encontró usuario AVS');
            } else {
                $this->info("   ✓ Usuario AVS encontrado: {$avsUser->email}");
            }
            
            // Intentar commit, pero si no hay transacción activa (por ALTER TABLE que hace auto-commit), continuar
            try {
                DB::commit();
            } catch (\Exception $commitError) {
                // Si falla el commit, probablemente ya se hizo auto-commit por ALTER TABLE
                // No es un error crítico, las operaciones ya se completaron
                $this->warn('   ⚠️ Nota: Algunas operaciones ya se completaron automáticamente');
            }
            
            $this->newLine();
            $this->info('✅ Limpieza completada exitosamente!');
            $this->info('📋 Resumen:');
            $this->info("   - Usuarios eliminados: {$usersDeleted}");
            $this->info("   - Cotizaciones/NVV eliminadas: {$cotizacionesDeleted}");
            $this->info("   - Productos de cotizaciones eliminados: {$productosCotizacionDeleted}");
            $this->newLine();
            $this->info('💾 Se mantuvieron:');
            $this->info('   - Usuarios: Super Admin y AVS');
            $this->info('   - Productos');
            $this->info('   - Clientes');
            $this->info('   - Historiales');
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('❌ Error durante la limpieza: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());
            return Command::FAILURE;
        }
    }
}
