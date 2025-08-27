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
                
                $datosCliente = [
                    'codigo_cliente' => $clienteExterno['CODIGO_CLIENTE'],
                    'nombre_cliente' => $clienteExterno['NOMBRE_CLIENTE'],
                    'direccion' => $clienteExterno['DIRECCION'] ?? '',
                    'telefono' => $clienteExterno['TELEFONO'] ?? '',
                    'codigo_vendedor' => $clienteExterno['CODIGO_VENDEDOR'],
                    'region' => $clienteExterno['REGION'] ?? '',
                    'comuna' => $clienteExterno['COMUNA'] ?? '',
                    'bloqueado' => !empty($clienteExterno['BLOQUEADO']) && $clienteExterno['BLOQUEADO'] != '0',
                    'activo' => true, // Solo sincronizamos clientes activos
                    'ultima_sincronizacion' => now()
                ];
                
                if ($clienteLocal) {
                    // Actualizar cliente existente
                    $clienteLocal->update($datosCliente);
                    $actualizados++;
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
        $query = self::where('nombre_cliente', 'LIKE', "%{$nombre}%")
                     ->where('activo', true);
        
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
}
