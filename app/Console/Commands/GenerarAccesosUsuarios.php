<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class GenerarAccesosUsuarios extends Command
{
    protected $signature = 'usuarios:generar-accesos {--reset-passwords : Resetear contraseñas a una genérica} {--password= : Contraseña genérica a usar (default: "password123")}';
    protected $description = 'Genera un archivo TXT con los accesos de todos los usuarios (email, roles y contraseñas)';

    public function handle()
    {
        $resetPasswords = $this->option('reset-passwords');
        $genericPassword = $this->option('password') ?: 'password123';
        
        $this->info('Generando archivo de accesos de usuarios...');
        
        $users = User::with('roles')->get();
        
        if ($users->isEmpty()) {
            $this->warn('No se encontraron usuarios en el sistema.');
            return 1;
        }
        
        $output = [];
        $output[] = "==========================================";
        $output[] = "ACCESOS DE USUARIOS - " . date('d/m/Y H:i:s');
        $output[] = "==========================================";
        $output[] = "";
        
        foreach ($users as $user) {
            $roles = $user->roles->pluck('name')->toArray();
            $rolPrincipal = !empty($roles) ? implode(', ', $roles) : 'Sin rol';
            
            // Si se solicita resetear contraseñas, hacerlo
            if ($resetPasswords) {
                // Nota: No usar Hash::make() aquí porque el modelo User tiene un cast 'hashed' que hashea automáticamente
                $user->password = $genericPassword; // El cast 'hashed' del modelo se encargará del hashing
                $user->primer_login = true;
                $user->save();
                $password = $genericPassword;
                $this->info("Contraseña reseteada para: {$user->email}");
            } else {
                // Las contraseñas están hasheadas, no se pueden obtener
                $password = "*** No se puede obtener (hasheada) - Use --reset-passwords para generar nueva ***";
            }
            
            $output[] = strtoupper($rolPrincipal);
            $output[] = "";
            $output[] = $user->email;
            $output[] = "";
            $output[] = "pass: " . $password;
            $output[] = "";
            $output[] = "------------------------------------------";
            $output[] = "";
        }
        
        $filename = storage_path('app/accesos_usuarios_' . date('Y-m-d_His') . '.txt');
        file_put_contents($filename, implode("\n", $output));
        
        $this->info("✅ Archivo generado exitosamente: {$filename}");
        $this->info("Total de usuarios: " . $users->count());
        
        if ($resetPasswords) {
            $this->warn("⚠️  Las contraseñas han sido reseteadas a: {$genericPassword}");
            $this->warn("⚠️  Todos los usuarios deberán usar esta contraseña para iniciar sesión.");
        } else {
            $this->info("💡 Para resetear todas las contraseñas a una genérica, use: php artisan usuarios:generar-accesos --reset-passwords");
        }
        
        return 0;
    }
}

