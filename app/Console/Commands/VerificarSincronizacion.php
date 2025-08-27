<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Cliente;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class VerificarSincronizacion extends Command
{
    protected $signature = 'clientes:verificar {--vendedor= : Código del vendedor específico} {--todos : Verificar todos los vendedores}';
    protected $description = 'Verificar estado de sincronización de clientes';

    public function handle()
    {
        $vendedor = $this->option('vendedor');
        $todos = $this->option('todos');
        
        if ($vendedor) {
            $this->verificarVendedor($vendedor);
        } elseif ($todos) {
            $this->verificarTodos();
        } else {
            $this->error('Debes especificar --vendedor=CODIGO o --todos');
            return 1;
        }
        
        return 0;
    }
    
    private function verificarVendedor($codigoVendedor)
    {
        $this->info("🔍 Verificando sincronización para vendedor: {$codigoVendedor}");
        
        // Contar clientes en base local
        $clientesLocales = Cliente::where('codigo_vendedor', $codigoVendedor)
                                 ->where('activo', true)
                                 ->count();
        
        // Verificar cache de sincronización
        $usuarios = User::where('codigo_vendedor', $codigoVendedor)->get();
        $cacheStatus = [];
        
        foreach ($usuarios as $usuario) {
            $cacheKey = "sincronizacion_clientes_{$usuario->id}_" . date('Y-m-d');
            $cacheStatus[$usuario->email] = Cache::has($cacheKey) ? '✅ Sincronizado hoy' : '❌ No sincronizado hoy';
        }
        
        // Mostrar resultados
        $this->info("📊 Resultados:");
        $this->info("   - Clientes en base local: {$clientesLocales}");
        
        foreach ($cacheStatus as $email => $status) {
            $this->info("   - Usuario {$email}: {$status}");
        }
        
        // Verificar última sincronización
        $ultimaSincronizacion = Cliente::where('codigo_vendedor', $codigoVendedor)
                                      ->where('activo', true)
                                      ->max('ultima_sincronizacion');
        
        if ($ultimaSincronizacion) {
            $this->info("   - Última sincronización: " . $ultimaSincronizacion->diffForHumans());
        } else {
            $this->info("   - Última sincronización: Nunca");
        }
    }
    
    private function verificarTodos()
    {
        $this->info("🔍 Verificando sincronización para todos los vendedores...");
        
        // Obtener todos los vendedores únicos
        $vendedores = Cliente::where('activo', true)
                            ->distinct()
                            ->pluck('codigo_vendedor');
        
        if ($vendedores->isEmpty()) {
            $this->warn("⚠️ No hay clientes sincronizados en la base local");
            return;
        }
        
        $this->info("📋 Vendedores encontrados: " . $vendedores->implode(', '));
        
        foreach ($vendedores as $vendedor) {
            $this->line("");
            $this->verificarVendedor($vendedor);
        }
        
        // Resumen general
        $this->line("");
        $this->info("📊 Resumen General:");
        $this->info("   - Total de vendedores: " . $vendedores->count());
        $this->info("   - Total de clientes: " . Cliente::where('activo', true)->count());
        
        // Verificar usuarios sin sincronización
        $usuariosSinSincronizacion = User::whereHas('roles', function($query) {
            $query->where('name', 'Vendedor');
        })->get()->filter(function($usuario) {
            $cacheKey = "sincronizacion_clientes_{$usuario->id}_" . date('Y-m-d');
            return !Cache::has($cacheKey);
        });
        
        if ($usuariosSinSincronizacion->isNotEmpty()) {
            $this->warn("⚠️ Usuarios sin sincronización hoy:");
            foreach ($usuariosSinSincronizacion as $usuario) {
                $this->warn("   - {$usuario->email} ({$usuario->codigo_vendedor})");
            }
        }
    }
} 