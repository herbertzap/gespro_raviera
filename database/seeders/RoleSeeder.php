<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Crear permisos
        $permissions = [
            // Permisos generales
            'ver_dashboard',
            'ver_perfil',
            'editar_perfil',
            
            // Permisos de vendedor
            'ver_cotizaciones',
            'crear_cotizaciones',
            'editar_cotizaciones',
            'ver_clientes',
            'ver_productos',
            'ver_dashboard_vendedor',
            
            // Permisos de compras
            'ver_aprobaciones',
            'aprobar_compras',
            'gestionar_stock',
            'ver_dashboard_compras',
            
            // Permisos de supervisor
            'aprobar_supervisor',
            'ver_todas_cotizaciones',
            'ver_dashboard_supervisor',
            
            // Permisos de finanzas
            'ver_cobranza',
            'ver_reportes',
            'gestionar_facturacion',
            'ver_dashboard_finanzas',
            
            // Permisos administrativos
            'ver_todo',
            'crear_usuarios',
            'gestionar_usuarios',
            'gestionar_roles',
            'ver_reportes_completos',
            'gestionar_configuracion',
            'ver_dashboard_admin'
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Crear roles y asignar permisos
        $roles = [
            'Vendedor' => [
                'ver_dashboard',
                'ver_perfil',
                'editar_perfil',
                'ver_cotizaciones',
                'crear_cotizaciones',
                'editar_cotizaciones',
                'ver_clientes',
                'ver_productos',
                'ver_dashboard_vendedor'
            ],
            'Compras' => [
                'ver_dashboard',
                'ver_perfil',
                'editar_perfil',
                'ver_aprobaciones',
                'aprobar_compras',
                'gestionar_stock',
                'ver_dashboard_compras'
            ],
            'Supervisor' => [
                'ver_dashboard',
                'ver_perfil',
                'editar_perfil',
                'ver_aprobaciones',
                'aprobar_supervisor',
                'ver_todas_cotizaciones',
                'ver_clientes',
                'ver_dashboard_supervisor'
            ],
            'Finanzas' => [
                'ver_dashboard',
                'ver_perfil',
                'editar_perfil',
                'ver_cobranza',
                'ver_reportes',
                'gestionar_facturacion',
                'ver_dashboard_finanzas'
            ],
            'Super Admin' => [
                // El Super Admin tiene todos los permisos
            ]
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::firstOrCreate(['name' => $roleName]);
            
            if ($roleName === 'Super Admin') {
                // El administrativo tiene todos los permisos
                $role->syncPermissions(Permission::all());
            } else {
                $role->syncPermissions($rolePermissions);
            }
        }
    }
}