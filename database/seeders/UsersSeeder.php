<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UsersSeeder extends Seeder
{
    public function run()
    {
        // Crear usuario de Compras
        $comprasUser = User::create([
            'name' => 'Usuario Compras',
            'email' => 'compras@wuayna.com',
            'password' => Hash::make('password'),
            'codigo_vendedor' => 'COMP001',
            'email_verified_at' => now(),
        ]);
        $comprasUser->assignRole('Compras');

        // Crear usuario de Picking
        $pickingUser = User::create([
            'name' => 'Usuario Picking',
            'email' => 'picking@wuayna.com',
            'password' => Hash::make('password'),
            'codigo_vendedor' => 'PICK001',
            'email_verified_at' => now(),
        ]);
        $pickingUser->assignRole('Picking');

        // Crear usuario de Picking Operativo (no puede aprobar, solo imprimir y guardar cantidades)
        $pickingOperativoUser = User::create([
            'name' => 'Usuario Picking Operativo',
            'email' => 'picking.operativo@wuayna.com',
            'password' => Hash::make('password'),
            'codigo_vendedor' => 'PICKOP001',
            'email_verified_at' => now(),
        ]);
        $pickingOperativoUser->assignRole('Picking Operativo');

        // Crear usuario de Bodega
        $bodegaUser = User::create([
            'name' => 'Usuario Bodega',
            'email' => 'bodega@wuayna.com',
            'password' => Hash::make('password'),
            'codigo_vendedor' => 'BOD001',
            'email_verified_at' => now(),
        ]);
        $bodegaUser->assignRole('Bodega');

        // Crear usuario Supervisor adicional
        $supervisorUser = User::create([
            'name' => 'Supervisor Compras',
            'email' => 'supervisor@wuayna.com',
            'password' => Hash::make('password'),
            'codigo_vendedor' => 'SUP001',
            'email_verified_at' => now(),
        ]);
        $supervisorUser->assignRole('Supervisor');

        $this->command->info('Usuarios de prueba creados exitosamente:');
        $this->command->info('- compras@wuayna.com (password: password)');
        $this->command->info('- picking@wuayna.com (password: password)');
        $this->command->info('- picking.operativo@wuayna.com (password: password) - NO puede aprobar, solo imprimir y guardar cantidades');
        $this->command->info('- bodega@wuayna.com (password: password)');
        $this->command->info('- supervisor@wuayna.com (password: password)');
    }
}
