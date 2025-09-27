<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateSuperAdmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'admin:create-super-admin';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crear el super administrador del sistema';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Creando super administrador...');

        // Verificar si ya existe
        $existingAdmin = User::where('email', 'herbert.zapata19@gmail.com')->first();
        if ($existingAdmin) {
            $this->warn('El super administrador ya existe');
            return 0;
        }

        // Generar contraseña temporal
        $tempPassword = Str::random(12);

        try {
            // Crear usuario super admin
            $admin = User::create([
                'name' => 'Herbert Zapata',
                'email' => 'herbert.zapata19@gmail.com',
                'password' => Hash::make($tempPassword),
                'es_vendedor' => false,
                'primer_login' => true,
                'fecha_ultimo_cambio_password' => now()
            ]);

            // Asignar rol Super Admin
            $admin->assignRole('Super Admin');

            $this->info('Super administrador creado exitosamente');
            $this->info('Email: herbert.zapata19@gmail.com');
            $this->info('Contraseña temporal: ' . $tempPassword);
            $this->warn('IMPORTANTE: Cambiar la contraseña en el primer login');

            return 0;

        } catch (\Exception $e) {
            $this->error('Error al crear super administrador: ' . $e->getMessage());
            return 1;
        }
    }
}