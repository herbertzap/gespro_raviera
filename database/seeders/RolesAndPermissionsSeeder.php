<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run()
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Crear permisos
        $permissions = [
            // Dashboard
            'view_dashboard',
            
            // Clientes
            'view_clients',
            'manage_clients',
            
            // Productos
            'view_products',
            'search_products',
            'manage_products',
            
            // Cotizaciones
            'create_quotation',
            'view_quotations',
            'edit_quotations',
            'delete_quotations',
            
            // Notas de Venta
            'create_sale_note',
            'view_sale_notes',
            'edit_sale_notes',
            'approve_sale_notes',
            'delete_sale_notes',
            
            // Cobranza
            'view_collections',
            'manage_collections',
            
            // Stock
            'view_stock',
            'manage_stock',
            'reserve_stock',
            
            // Compras
            'view_purchases',
            'manage_purchases',
            
            // Bodega
            'view_warehouse',
            'manage_warehouse',
            
            // Reportes
            'view_reports',
            'export_reports',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Crear roles
        $superAdmin = Role::create(['name' => 'Super Admin']);
        $vendedor = Role::create(['name' => 'Vendedor']);
        $supervisor = Role::create(['name' => 'Supervisor']);
        $compras = Role::create(['name' => 'Compras']);
        $bodega = Role::create(['name' => 'Bodega']);

        // Asignar permisos a Vendedor
        $vendedor->givePermissionTo([
            'view_dashboard',
            'view_clients',
            'search_products',
            'create_quotation',
            'view_quotations',
            'edit_quotations',
            'create_sale_note',
            'view_sale_notes',
            'view_collections',
            'view_stock',
        ]);

        // Asignar permisos a Supervisor
        $supervisor->givePermissionTo([
            'view_dashboard',
            'view_clients',
            'manage_clients',
            'view_products',
            'search_products',
            'manage_products',
            'view_quotations',
            'edit_quotations',
            'delete_quotations',
            'view_sale_notes',
            'approve_sale_notes',
            'view_collections',
            'manage_collections',
            'view_stock',
            'manage_stock',
            'reserve_stock',
            'view_reports',
            'export_reports',
        ]);

        // Asignar permisos a Compras
        $compras->givePermissionTo([
            'view_dashboard',
            'view_products',
            'manage_products',
            'view_purchases',
            'manage_purchases',
            'view_stock',
            'view_reports',
            'export_reports',
        ]);

        // Asignar permisos a Bodega
        $bodega->givePermissionTo([
            'view_dashboard',
            'view_products',
            'view_stock',
            'manage_stock',
            'reserve_stock',
            'view_warehouse',
            'manage_warehouse',
            'view_reports',
            'export_reports',
        ]);

        // Asignar TODOS los permisos a Super Admin
        $superAdmin->givePermissionTo($permissions);
    }
}
