<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class CreateVendedorUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:create-vendedores';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crear usuarios vendedores LCB y GMB para pruebas';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Creando usuarios vendedores para pruebas...');

        // Verificar si el rol Vendedor existe
        $vendedorRole = Role::where('name', 'Vendedor')->first();
        
        if (!$vendedorRole) {
            $this->error('El rol "Vendedor" no existe. Creando el rol...');
            $vendedorRole = Role::create(['name' => 'Vendedor']);
            $this->info('Rol "Vendedor" creado exitosamente.');
        }

        // Datos de los vendedores
        $vendedores = [
            [
                'codigo' => 'LCB',
                'nombre' => 'LUIS CASANGA BERRIOS',
                'email' => 'lcb@wuayna.com',
                'password' => 'password123',
                'codigo_vendedor' => 'LCB',
                'total_nvv' => 106,
                'unidades_pendientes' => 3583.0,
                'valor_pendiente' => 3404629.0
            ],
            [
                'codigo' => 'GMB',
                'nombre' => 'GEORGE MERINO BENITEZ',
                'email' => 'gmb@wuayna.com',
                'password' => 'password123',
                'codigo_vendedor' => 'GMB',
                'total_nvv' => 80,
                'unidades_pendientes' => 1482.0,
                'valor_pendiente' => 1234238.0
            ]
        ];

        foreach ($vendedores as $vendedor) {
            // Verificar si el usuario ya existe
            $existingUser = User::where('email', $vendedor['email'])->first();
            
            if ($existingUser) {
                $this->warn("El usuario {$vendedor['codigo']} ya existe. Actualizando datos...");
                
                // Actualizar datos del usuario existente
                $existingUser->update([
                    'name' => $vendedor['nombre'],
                    'codigo_vendedor' => $vendedor['codigo_vendedor'],
                    'updated_at' => now()
                ]);
                
                // Asegurar que tenga el rol de vendedor
                if (!$existingUser->hasRole('Vendedor')) {
                    $existingUser->assignRole('Vendedor');
                }
                
                $this->info("Usuario {$vendedor['codigo']} actualizado exitosamente.");
            } else {
                // Crear nuevo usuario
                $user = User::create([
                    'name' => $vendedor['nombre'],
                    'email' => $vendedor['email'],
                    'password' => Hash::make($vendedor['password']),
                    'codigo_vendedor' => $vendedor['codigo_vendedor'],
                    'email_verified_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                
                // Asignar rol de vendedor
                $user->assignRole('Vendedor');
                
                $this->info("Usuario {$vendedor['codigo']} creado exitosamente.");
            }
        }

        // Mostrar resumen de usuarios creados
        $this->newLine();
        $this->info('=== RESUMEN DE USUARIOS VENDEDORES ===');
        $this->table(
            ['Código', 'Nombre', 'Email', 'Contraseña', 'Rol'],
            [
                ['LCB', 'LUIS CASANGA BERRIOS', 'lcb@wuayna.com', 'password123', 'Vendedor'],
                ['GMB', 'GEORGE MERINO BENITEZ', 'gmb@wuayna.com', 'password123', 'Vendedor']
            ]
        );

        $this->newLine();
        $this->info('=== DATOS DE PRUEBA ===');
        $this->info('LCB - Total NVV: 106, Unidades Pendientes: 3,583, Valor Pendiente: $3,404,629');
        $this->info('GMB - Total NVV: 80, Unidades Pendientes: 1,482, Valor Pendiente: $1,234,238');

        $this->newLine();
        $this->info('✅ Usuarios vendedores creados/actualizados exitosamente.');
        $this->info('Puedes iniciar sesión con cualquiera de estos usuarios para probar el dashboard.');
        
        return 0;
    }
}
