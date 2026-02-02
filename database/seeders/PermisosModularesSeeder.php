<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class PermisosModularesSeeder extends Seeder
{
    /**
     * Permisos organizados por módulo con acciones CRUD
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Definir permisos por módulo
        $modulos = [
            // ========== DASHBOARD ==========
            'dashboard' => [
                'ver_dashboard',
                // Cards de resumen
                'ver_dashboard_card_total_usuarios',
                'ver_dashboard_card_total_documentos_pendientes',
                'ver_dashboard_card_total_notas_venta_aprobacion',
                'ver_dashboard_card_total_documentos_vencidos',
                'ver_dashboard_card_cheques_cartera',
                'ver_dashboard_card_cheques_protestados',
                'ver_dashboard_card_nvv_pendientes',
                'ver_dashboard_card_valor_nvv_pendientes',
                'ver_dashboard_card_nvv_por_validar',
                'ver_dashboard_card_nvv_sistema_mes',
                'ver_dashboard_card_facturas_mes',
                'ver_dashboard_card_compras_mes',
                'ver_dashboard_card_productos_bajo_stock',
                'ver_dashboard_card_compras_pendientes',
                // Secciones/tablas
                'ver_dashboard_resumen_usuarios',
                'ver_dashboard_resumen_stock',
                'ver_dashboard_resumen_cobranza',
                'ver_dashboard_resumen_nvv',
                'ver_dashboard_graficos',
                'ver_dashboard_tablas_vendedores',
                'ver_dashboard_tabla_usuarios_rol',
                'ver_dashboard_tabla_mis_clientes',
                'ver_dashboard_tabla_clientes_vencidos',
                'ver_dashboard_tabla_notas_pendientes',
                'ver_dashboard_tabla_notas_sql',
                'ver_dashboard_tabla_facturas_pendientes',
                'ver_dashboard_tabla_nvv_pendientes_detalle',
                // Opción especial de filtrado
                'dashboard_filtrar_por_codigo',
            ],

            // ========== VENTAS ==========
            'ventas' => [
                'ver_ventas',
                'ver_clientes',
                'crear_clientes',
                'editar_clientes',
                'ver_cotizaciones',
                'crear_cotizaciones',
                'editar_cotizaciones',
                'eliminar_cotizaciones',
                'ver_notas_venta',
                'crear_notas_venta',
                'editar_notas_venta',
                'aprobar_notas_venta',
            ],

            // ========== COBRANZA ==========
            'cobranza' => [
                'ver_cobranza',
                'gestionar_cobranza',
                'ver_facturas',
                'ver_cheques',
            ],

            // ========== MANEJO STOCK ==========
            'manejo_stock' => [
                'ver_manejo_stock',
                'crear_manejo_stock',
                'editar_manejo_stock',
                'ver_historial_stock',
                'ver_contabilidad_stock',
                'ver_barrido_stock',
                'ver_reporte_inventario',
            ],

            // ========== MANTENEDOR ==========
            'mantenedor' => [
                'ver_mantenedor',
                'gestionar_bodegas',
                'gestionar_ubicaciones',
            ],

            // ========== PRODUCTOS ==========
            'productos' => [
                'ver_productos',
                'crear_productos',
                'editar_productos',
                'eliminar_productos',
                'cargar_productos',
                'ver_stock_productos',
                'gestionar_multiplos_venta',
            ],

            // ========== INFORMES ==========
            'informes' => [
                'ver_informes',
                'ver_nvv_pendientes',
                'ver_facturas_pendientes',
                'ver_facturas_emitidas',
                'exportar_informes',
            ],

            // ========== APROBACIONES ==========
            'aprobaciones' => [
                'ver_aprobaciones',
                'aprobar_supervisor',
                'aprobar_compras',
                'aprobar_picking',
            ],

            // ========== GESTIÓN DE USUARIOS ==========
            'usuarios' => [
                'ver_usuarios',
                'crear_usuarios',
                'editar_usuarios',
                'eliminar_usuarios',
                'asignar_roles',
            ],

            // ========== GESTIÓN DE ROLES Y PERMISOS ==========
            'roles_permisos' => [
                'ver_roles',
                'crear_roles',
                'editar_roles',
                'eliminar_roles',
                'ver_permisos',
                'asignar_permisos',
            ],

            // ========== CONFIGURACIÓN ==========
            'configuracion' => [
                'ver_configuracion',
                'editar_configuracion',
                'ver_logs',
            ],
        ];

        // Crear todos los permisos
        foreach ($modulos as $modulo => $permisos) {
            foreach ($permisos as $permiso) {
                Permission::firstOrCreate([
                    'name' => $permiso,
                    'guard_name' => 'web'
                ]);
            }
        }

        // Crear rol Administrativo si no existe
        $administrativo = Role::firstOrCreate(['name' => 'Administrativo', 'guard_name' => 'web']);
        
        // Permisos para Administrativo (sin gestión de roles/permisos completa)
        $permisosAdministrativo = [
            // Dashboard
            'ver_dashboard',
            'ver_dashboard_resumen_usuarios',
            'ver_dashboard_resumen_cobranza',
            'ver_dashboard_resumen_nvv',
            
            // Usuarios (limitado)
            'ver_usuarios',
            'crear_usuarios',
            'editar_usuarios',
            // NO puede eliminar usuarios ni asignar roles
            
            // Ventas
            'ver_ventas',
            'ver_clientes',
            'ver_cotizaciones',
            'ver_notas_venta',
            
            // Cobranza
            'ver_cobranza',
            'ver_facturas',
            'ver_cheques',
            
            // Informes
            'ver_informes',
            'ver_nvv_pendientes',
            'ver_facturas_pendientes',
            'ver_facturas_emitidas',
            'exportar_informes',
        ];
        
        // Asegurar que todos los permisos del administrativo existan
        foreach ($permisosAdministrativo as $permiso) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
        }
        
        // Resetear cache antes de sincronizar
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        
        // Obtener los permisos existentes por nombre
        $permisosAdminObj = Permission::whereIn('name', $permisosAdministrativo)->get();
        $administrativo->syncPermissions($permisosAdminObj);

        // Asegurar que Super Admin tenga todos los permisos
        $superAdmin = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $superAdmin->syncPermissions(Permission::all());

        // Actualizar rol Manejo Stock
        $manejoStock = Role::firstOrCreate(['name' => 'Manejo Stock', 'guard_name' => 'web']);
        $permisosManejoStock = [
            'ver_dashboard',
            'ver_manejo_stock',
            'crear_manejo_stock',
            'editar_manejo_stock',
            'ver_historial_stock',
            'ver_contabilidad_stock',
            'ver_barrido_stock',
            'ver_reporte_inventario',
        ];
        
        // Asegurar que todos los permisos de manejo stock existan
        foreach ($permisosManejoStock as $permiso) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
        }
        
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $permisosManejoStockObj = Permission::whereIn('name', $permisosManejoStock)->get();
        $manejoStock->syncPermissions($permisosManejoStockObj);

        $this->command->info('Permisos modulares creados exitosamente.');
        $this->command->info('Roles actualizados: Super Admin, Administrativo, Manejo Stock');
    }
}

