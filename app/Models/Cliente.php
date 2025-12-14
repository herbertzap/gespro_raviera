<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Cliente extends Model
{
    use HasFactory;

    protected $table = 'clientes';
    
    protected $fillable = [
        'codigo_cliente',
        'nombre_cliente', 
        'direccion',
        'telefono',
        'email',
        'rut_cliente',
        'codigo_vendedor',
        'region',
        'comuna',
        'lista_precios_codigo',
        'lista_precios_nombre',
        'bloqueado',
        'activo',
        'ultima_sincronizacion',
        'condicion_pago',
        'dias_credito',
        'credito_total',
        'credito_utilizado',
        'credito_disponible',
        'comentario_administracion',
        'requiere_autorizacion_credito',
        'requiere_autorizacion_retraso',
        'dias_retraso_facturas'
    ];

    protected $casts = [
        'bloqueado' => 'boolean',
        'activo' => 'boolean',
        'ultima_sincronizacion' => 'datetime',
        'requiere_autorizacion_credito' => 'boolean',
        'requiere_autorizacion_retraso' => 'boolean',
        'credito_total' => 'decimal:2',
        'credito_utilizado' => 'decimal:2',
        'credito_disponible' => 'decimal:2'
    ];

    /**
     * Sincronizar clientes desde SQL Server
     */
    public static function sincronizarDesdeSQLServer($codigoVendedor = null)
    {
        try {
            $cobranzaService = new \App\Services\CobranzaService();
            
            // Obtener clientes desde SQL Server
            $clientesExternos = $cobranzaService->getClientesPorVendedor($codigoVendedor);
            
            $sincronizados = 0;
            $actualizados = 0;
            
            foreach ($clientesExternos as $clienteExterno) {
                // Buscar si ya existe en local
                $clienteLocal = self::where('codigo_cliente', $clienteExterno['CODIGO_CLIENTE'])->first();
                
                // Determinar si el cliente está bloqueado (comparar valor desde SQL Server)
                $bloqueadoSQL = isset($clienteExterno['BLOQUEADO']) ? trim($clienteExterno['BLOQUEADO']) : '0';
                $estaBloqueado = ($bloqueadoSQL == '1' || $bloqueadoSQL == 1 || $bloqueadoSQL === true);
                
                $datosCliente = [
                    'codigo_cliente' => $clienteExterno['CODIGO_CLIENTE'],
                    'nombre_cliente' => $clienteExterno['NOMBRE_CLIENTE'],
                    'direccion' => $clienteExterno['DIRECCION'] ?? '',
                    'telefono' => $clienteExterno['TELEFONO'] ?? '',
                    'codigo_vendedor' => $clienteExterno['CODIGO_VENDEDOR'],
                    'region' => $clienteExterno['REGION'] ?? '',
                    'comuna' => $clienteExterno['COMUNA'] ?? '',
                    'bloqueado' => $estaBloqueado, // Siempre actualizar desde SQL Server
                    'activo' => true, // Solo sincronizamos clientes activos
                    'ultima_sincronizacion' => now()
                ];
                
                if ($clienteLocal) {
                    // SIEMPRE actualizar cliente existente, especialmente el campo bloqueado
                    // Esto asegura que si el cliente fue bloqueado en SQL Server, se refleje localmente
                    $clienteLocal->update($datosCliente);
                    $actualizados++;
                    
                    // Log para debugging si el cliente cambió de estado
                    if ($clienteLocal->wasChanged('bloqueado')) {
                        \Log::info("Cliente {$clienteExterno['CODIGO_CLIENTE']} cambió estado bloqueado: " . ($estaBloqueado ? 'BLOQUEADO' : 'DESBLOQUEADO'));
                    }
                } else {
                    // Crear nuevo cliente
                    self::create($datosCliente);
                    $sincronizados++;
                }
            }
            
            // Marcar como inactivos los clientes que ya no están en SQL Server
            if ($codigoVendedor) {
                $clientesCodigos = collect($clientesExternos)->pluck('CODIGO_CLIENTE')->toArray();
                self::where('codigo_vendedor', $codigoVendedor)
                    ->whereNotIn('codigo_cliente', $clientesCodigos)
                    ->update(['activo' => false]);
            }
            
            \Log::info("Sincronización completada: {$sincronizados} nuevos, {$actualizados} actualizados");
            
            return [
                'success' => true,
                'nuevos' => $sincronizados,
                'actualizados' => $actualizados,
                'total' => count($clientesExternos)
            ];
            
        } catch (\Exception $e) {
            \Log::error('Error sincronizando clientes: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtener clientes activos por vendedor
     */
    public static function getClientesActivosPorVendedor($codigoVendedor)
    {
        return self::where('codigo_vendedor', $codigoVendedor)
                   ->where('activo', true)
                   ->orderBy('nombre_cliente')
                   ->get();
    }

    /**
     * Buscar cliente por código
     */
    public static function buscarPorCodigo($codigoCliente, $codigoVendedor = null)
    {
        $query = self::where('codigo_cliente', $codigoCliente)
                     ->where('activo', true);
        
        if ($codigoVendedor) {
            $query->where('codigo_vendedor', $codigoVendedor);
        }
        
        return $query->first();
    }

    /**
     * Buscar clientes por nombre
     */
    public static function buscarPorNombre($nombre, $codigoVendedor = null)
    {
        // Dividir la búsqueda en términos individuales
        $terminos = array_filter(explode(' ', trim($nombre)));
        
        $query = self::where('activo', true);
        
        if (count($terminos) > 1) {
            // Búsqueda con múltiples términos: todos los términos deben estar en el nombre
            $query->where(function($q) use ($terminos) {
                foreach ($terminos as $termino) {
                    $q->where('nombre_cliente', 'LIKE', "%{$termino}%");
                }
            });
        } else {
            // Búsqueda simple: por nombre
            $query->where('nombre_cliente', 'LIKE', "%{$nombre}%");
        }
        
        if ($codigoVendedor) {
            $query->where('codigo_vendedor', $codigoVendedor);
        }
        
        return $query->orderBy('nombre_cliente')->get();
    }

    /**
     * Verificar si el cliente puede generar cotizaciones
     */
    public function puedeGenerarCotizacion()
    {
        // Verificar si está bloqueado
        if ($this->bloqueado) {
            return [
                'puede' => false,
                'motivo' => 'Cliente bloqueado'
            ];
        }
        
        // Si no tiene lista de precios, asignar una por defecto
        if (empty($this->lista_precios_codigo) || $this->lista_precios_codigo == '0') {
            // Asignar lista de precios por defecto
            $this->update([
                'lista_precios_codigo' => '01',
                'lista_precios_nombre' => 'Lista General'
            ]);
            $this->refresh();
        }
        
        return [
            'puede' => true,
            'motivo' => 'Cliente válido'
        ];
    }

    /**
     * Obtener información completa del cliente (incluyendo datos externos si es necesario)
     */
    public function obtenerInformacionCompleta()
    {
        // Si la información local es reciente (menos de 1 hora), usar local
        if ($this->ultima_sincronizacion && $this->ultima_sincronizacion->diffInHours(now()) < 1) {
            return $this;
        }
        
        // Si no, sincronizar desde SQL Server
        try {
            $cobranzaService = new \App\Services\CobranzaService();
            $clienteExterno = $cobranzaService->getClienteInfoCompleto($this->codigo_cliente);
            
            if ($clienteExterno) {
                // Actualizar datos locales
                $this->update([
                    'nombre_cliente' => $clienteExterno['NOMBRE_CLIENTE'] ?? $this->nombre_cliente,
                    'direccion' => $clienteExterno['DIRECCION'] ?? $this->direccion,
                    'telefono' => $clienteExterno['TELEFONO'] ?? $this->telefono,
                    'region' => $clienteExterno['REGION'] ?? $this->region,
                    'comuna' => $clienteExterno['COMUNA'] ?? $this->comuna,
                    'lista_precios_codigo' => $clienteExterno['LISTA_PRECIOS_CODIGO'] ?? $this->lista_precios_codigo,
                    'lista_precios_nombre' => $clienteExterno['LISTA_PRECIOS_NOMBRE'] ?? $this->lista_precios_nombre,
                    'bloqueado' => !empty($clienteExterno['BLOQUEADO']) && $clienteExterno['BLOQUEADO'] != '0',
                    'ultima_sincronizacion' => now()
                ]);
                
                $this->refresh();
            }
            
        } catch (\Exception $e) {
            \Log::error('Error obteniendo información completa del cliente: ' . $e->getMessage());
        }
        
        return $this;
    }

    /**
     * Sincronizar todos los datos del cliente desde SQL Server
     * Incluye: datos básicos, crédito, y actualiza última sincronización
     */
    public function sincronizarDatosCompletos()
    {
        try {
            $cobranzaService = new \App\Services\CobranzaService();
            
            // 1. Sincronizar datos básicos del cliente
            $clienteExterno = $cobranzaService->getClienteInfoCompleto($this->codigo_cliente);
            
            if ($clienteExterno) {
                // Actualizar datos básicos
                $this->update([
                    'nombre_cliente' => $clienteExterno['NOMBRE_CLIENTE'] ?? $this->nombre_cliente,
                    'direccion' => $clienteExterno['DIRECCION'] ?? $this->direccion,
                    'telefono' => $clienteExterno['TELEFONO'] ?? $this->telefono,
                    'email' => $clienteExterno['EMAIL'] ?? $this->email,
                    'region' => $clienteExterno['REGION'] ?? $this->region,
                    'comuna' => $clienteExterno['COMUNA'] ?? $this->comuna,
                    'codigo_vendedor' => $clienteExterno['CODIGO_VENDEDOR'] ?? $this->codigo_vendedor,
                    'lista_precios_codigo' => $clienteExterno['LISTA_PRECIOS_CODIGO'] ?? $this->lista_precios_codigo,
                    'lista_precios_nombre' => $clienteExterno['LISTA_PRECIOS_NOMBRE'] ?? $this->lista_precios_nombre,
                    'bloqueado' => !empty($clienteExterno['BLOQUEADO']) && $clienteExterno['BLOQUEADO'] != '0',
                    'condicion_pago' => $clienteExterno['CONDICION_PAGO'] ?? $this->condicion_pago,
                    'dias_credito' => intval($clienteExterno['DIAS_CREDITO'] ?? $this->dias_credito ?? 0),
                    'rut_cliente' => $clienteExterno['RUT_CLIENTE'] ?? $this->rut_cliente,
                    'ultima_sincronizacion' => now()
                ]);
            }
            
            // 2. Sincronizar datos de crédito
            $creditoInfo = $cobranzaService->getCreditoCliente($this->codigo_cliente);
            
            if ($creditoInfo) {
                $this->update([
                    'credito_total' => floatval($creditoInfo['credito_total'] ?? 0),
                    'credito_utilizado' => floatval($creditoInfo['credito_total_util'] ?? 0),
                    'credito_disponible' => floatval($creditoInfo['credito_total_disp'] ?? 0),
                    'ultima_sincronizacion' => now()
                ]);
            }
            
            // Refrescar el modelo para tener los datos actualizados
            $this->refresh();
            
            \Log::info("Cliente {$this->codigo_cliente} sincronizado correctamente");
            
        } catch (\Exception $e) {
            \Log::error("Error sincronizando datos completos del cliente {$this->codigo_cliente}: " . $e->getMessage());
        }
        
        return $this;
    }
}
