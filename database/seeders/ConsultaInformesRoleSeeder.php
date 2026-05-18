<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ConsultaInformesRoleSeeder extends Seeder
{
    public function run(): void
    {
        $nombresPermisos = [
            'ver_dashboard',
            'ver_perfil',
            'editar_perfil',
            'ver_informes',
        ];

        foreach ($nombresPermisos as $nombre) {
            Permission::firstOrCreate(
                ['name' => $nombre, 'guard_name' => 'web'],
                ['name' => $nombre, 'guard_name' => 'web']
            );
        }

        $role = Role::firstOrCreate(
            ['name' => 'Consulta Informes', 'guard_name' => 'web'],
            ['name' => 'Consulta Informes', 'guard_name' => 'web']
        );

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $role->syncPermissions(
            Permission::whereIn('name', $nombresPermisos)->where('guard_name', 'web')->get()
        );
    }
}
