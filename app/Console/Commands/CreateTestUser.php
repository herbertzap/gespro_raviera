<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class CreateTestUser extends Command
{
    protected $signature = 'user:create-test {role} {email} {name} {--codigo=} {--password=password}';
    protected $description = 'Crear usuario de prueba con rol específico';

    public function handle()
    {
        $role = $this->argument('role');
        $email = $this->argument('email');
        $name = $this->argument('name');
        $codigo = $this->option('codigo');
        $password = $this->option('password');

        // Validar rol
        $rolesValidos = ['Super Admin', 'Vendedor', 'Supervisor', 'Compras', 'Bodega'];
        if (!in_array($role, $rolesValidos)) {
            $this->error("Rol inválido. Roles válidos: " . implode(', ', $rolesValidos));
            return 1;
        }

        // Verificar si el usuario ya existe
        if (User::where('email', $email)->exists()) {
            $this->error("El usuario con email {$email} ya existe.");
            return 1;
        }

        // Crear usuario
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'codigo_vendedor' => $codigo,
        ]);

        // Asignar rol
        $user->assignRole($role);

        $this->info("✅ Usuario creado exitosamente:");
        $this->info("📧 Email: {$email}");
        $this->info("🔑 Contraseña: {$password}");
        $this->info("👤 Rol: {$role}");
        if ($codigo) {
            $this->info("🏷️ Código Vendedor: {$codigo}");
        }

        return 0;
    }
}
