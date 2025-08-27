<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class LimpiarCacheSincronizacion extends Command
{
    protected $signature = 'clientes:limpiar-cache {--usuario= : ID del usuario específico} {--todos : Limpiar cache de todos los usuarios}';
    protected $description = 'Limpiar cache de sincronización de clientes';

    public function handle()
    {
        $usuario = $this->option('usuario');
        $todos = $this->option('todos');
        
        if ($usuario) {
            $this->limpiarCacheUsuario($usuario);
        } elseif ($todos) {
            $this->limpiarCacheTodos();
        } else {
            $this->error('Debes especificar --usuario=ID o --todos');
            return 1;
        }
        
        return 0;
    }
    
    private function limpiarCacheUsuario($userId)
    {
        $this->info("🧹 Limpiando cache de sincronización para usuario: {$userId}");
        
        // Limpiar cache de hoy
        $cacheKey = "sincronizacion_clientes_{$userId}_" . date('Y-m-d');
        Cache::forget($cacheKey);
        
        // Limpiar cache de ayer también por si acaso
        $cacheKeyAyer = "sincronizacion_clientes_{$userId}_" . date('Y-m-d', strtotime('-1 day'));
        Cache::forget($cacheKeyAyer);
        
        $this->info("✅ Cache limpiado para usuario {$userId}");
    }
    
    private function limpiarCacheTodos()
    {
        $this->info("🧹 Limpiando cache de sincronización para todos los usuarios...");
        
        // Obtener todas las claves de cache que empiecen con "sincronizacion_clientes_"
        $keys = Cache::get('sincronizacion_clientes_*');
        
        if ($keys) {
            foreach ($keys as $key) {
                Cache::forget($key);
            }
        }
        
        // También limpiar manualmente las claves más comunes
        $usuarios = \App\Models\User::whereHas('roles', function($query) {
            $query->where('name', 'Vendedor');
        })->pluck('id');
        
        foreach ($usuarios as $userId) {
            $cacheKey = "sincronizacion_clientes_{$userId}_" . date('Y-m-d');
            Cache::forget($cacheKey);
            
            $cacheKeyAyer = "sincronizacion_clientes_{$userId}_" . date('Y-m-d', strtotime('-1 day'));
            Cache::forget($cacheKeyAyer);
        }
        
        $this->info("✅ Cache limpiado para todos los usuarios");
    }
} 