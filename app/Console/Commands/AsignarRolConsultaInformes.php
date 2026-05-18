<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\ConsultaInformesRoleSeeder;
use Illuminate\Console\Command;

class AsignarRolConsultaInformes extends Command
{
    protected $signature = 'roles:consulta-informes
                            {user? : ID, email o RUT del usuario}
                            {--sin-seed : No ejecutar el seeder del rol}';

    protected $description = 'Crea el rol Consulta Informes y lo asigna a un usuario (informes NVV y facturas, solo lectura)';

    public function handle(): int
    {
        if (! $this->option('sin-seed')) {
            $this->call('db:seed', ['--class' => ConsultaInformesRoleSeeder::class, '--force' => true]);
        }

        $identificador = $this->argument('user');
        if ($identificador === null) {
            $this->info('Rol "Consulta Informes" listo. Asigne el rol al usuario desde Mantenedor de usuarios o ejecute:');
            $this->line('  php artisan roles:consulta-informes {id|email|rut}');

            return self::SUCCESS;
        }

        $user = $this->resolverUsuario($identificador);
        if (! $user) {
            $this->error("Usuario no encontrado: {$identificador}");

            return self::FAILURE;
        }

        if ($user->hasRole('Consulta Informes')) {
            $this->warn("{$user->name} (ID {$user->id}) ya tiene el rol Consulta Informes.");
        } else {
            $user->assignRole('Consulta Informes');
            $this->info("Rol Consulta Informes asignado a {$user->name} (ID {$user->id}, {$user->email}).");
        }

        $roles = $user->getRoleNames()->implode(', ');
        $this->line("Roles actuales: {$roles}");

        return self::SUCCESS;
    }

    private function resolverUsuario(string $identificador): ?User
    {
        if (ctype_digit($identificador)) {
            return User::find((int) $identificador);
        }

        return User::where('email', $identificador)
            ->orWhere('rut', $identificador)
            ->first();
    }
}
