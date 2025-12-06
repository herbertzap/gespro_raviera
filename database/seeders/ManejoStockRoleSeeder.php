<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class ManejoStockRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => 'Manejo Stock', 'guard_name' => 'web']);

        $permisos = [
            'ver_dashboard',
            'view_stock',
            'manage_stock',
            'reserve_stock',
        ];

        foreach ($permisos as $permiso) {
            Permission::firstOrCreate([
                'name' => $permiso,
                'guard_name' => 'web',
            ]);
        }

        $permissions = Permission::whereIn('name', $permisos)->where('guard_name', 'web')->get();
        $role->syncPermissions($permissions);
    }
}
