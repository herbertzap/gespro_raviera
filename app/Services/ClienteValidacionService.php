<?php

namespace App\Services;

use App\Models\Cliente;
use Illuminate\Support\Facades\Log;

class ClienteValidacionService
{
    /**
     * Validar cliente para generar nota de venta
     */
    public static function validarClienteParaNotaVenta($codigoCliente, $montoNotaVenta = 0)
    {
        try {
            $cliente = Cliente::where('codigo_cliente', $codigoCliente)->first();
            
            if (!$cliente) {
                return [
                    'valido' => false,
                    'motivo' => 'Cliente no encontrado',
                    'requiere_autorizacion' => false,
                    'estado' => 'error'
                ];
            }

            $validaciones = [];
            $requiereAutorizacion = false;
            $motivos = [];

            // 1. Validación de crédito total
            $validacionCredito = self::validarCredito($cliente, $montoNotaVenta);
            $validaciones['credito'] = $validacionCredito;
            
            if (!$validacionCredito['valido']) {
                $requiereAutorizacion = true;
                $motivos[] = $validacionCredito['motivo'];
            }

            // 2. Validación de retraso en facturas
            $validacionRetraso = self::validarRetrasoFacturas($cliente);
            $validaciones['retraso'] = $validacionRetraso;
            
            if (!$validacionRetraso['valido']) {
                $requiereAutorizacion = true;
                $motivos[] = $validacionRetraso['motivo'];
            }

            // 3. Validación de bloqueo
            if ($cliente->bloqueado) {
                $validaciones['bloqueo'] = [
                    'valido' => false,
                    'motivo' => 'Cliente bloqueado',
                    'estado' => 'danger'
                ];
                $requiereAutorizacion = true;
                $motivos[] = 'Cliente bloqueado';
            } else {
                $validaciones['bloqueo'] = [
                    'valido' => true,
                    'motivo' => 'Cliente activo',
                    'estado' => 'success'
                ];
            }

            // Determinar estado general
            $estadoGeneral = 'success';
            if ($requiereAutorizacion) {
                $estadoGeneral = 'warning';
            }
            if (in_array('Cliente bloqueado', $motivos)) {
                $estadoGeneral = 'danger';
            }

            return [
                'valido' => !$requiereAutorizacion,
                'requiere_autorizacion' => $requiereAutorizacion,
                'motivo' => implode(', ', $motivos),
                'estado' => $estadoGeneral,
                'validaciones' => $validaciones,
                'cliente' => $cliente
            ];

        } catch (\Exception $e) {
            Log::error('Error validando cliente: ' . $e->getMessage());
            return [
                'valido' => false,
                'motivo' => 'Error en validación: ' . $e->getMessage(),
                'requiere_autorizacion' => true,
                'estado' => 'error'
            ];
        }
    }

    /**
     * Validar crédito del cliente
     */
    private static function validarCredito($cliente, $montoNotaVenta)
    {
        // Obtener información de crédito actualizada desde SQL Server
        $cobranzaService = new CobranzaService();
        $creditoInfo = $cobranzaService->getCreditoCliente($cliente->codigo_cliente);
        
        if (!$creditoInfo) {
            return [
                'valido' => false,
                'motivo' => 'No se pudo obtener información de crédito',
                'estado' => 'warning'
            ];
        }

        $creditoTotal = $creditoInfo['credito_total'] ?? 0;
        $creditoUtilizado = $creditoInfo['credito_total_util'] ?? 0;
        $creditoDisponible = $creditoInfo['credito_total_disp'] ?? 0;

        // Actualizar datos del cliente
        $cliente->update([
            'credito_total' => $creditoTotal,
            'credito_utilizado' => $creditoUtilizado,
            'credito_disponible' => $creditoDisponible,
            'requiere_autorizacion_credito' => false
        ]);

        // Validar si el monto de la nota de venta excede el crédito disponible
        if ($montoNotaVenta > $creditoDisponible) {
            $cliente->update(['requiere_autorizacion_credito' => true]);
            
            return [
                'valido' => false,
                'motivo' => "Crédito insuficiente. Disponible: $" . number_format($creditoDisponible, 0, ',', '.') . 
                           ", Nota de venta: $" . number_format($montoNotaVenta, 0, ',', '.'),
                'estado' => 'warning',
                'credito_total' => $creditoTotal,
                'credito_utilizado' => $creditoUtilizado,
                'credito_disponible' => $creditoDisponible
            ];
        }

        return [
            'valido' => true,
            'motivo' => "Crédito disponible: $" . number_format($creditoDisponible, 0, ',', '.'),
            'estado' => 'success',
            'credito_total' => $creditoTotal,
            'credito_utilizado' => $creditoUtilizado,
            'credito_disponible' => $creditoDisponible
        ];
    }

    /**
     * Validar retraso en facturas
     */
    private static function validarRetrasoFacturas($cliente)
    {
        try {
            $cobranzaService = new CobranzaService();
            $facturasPendientes = $cobranzaService->getFacturasPendientesCliente($cliente->codigo_cliente);
            
            $diasMaximos = $cliente->dias_credito ?? 30;
            $facturasRetrasadas = [];
            $diasRetrasoMaximo = 0;

            foreach ($facturasPendientes as $factura) {
                $fechaVencimiento = \Carbon\Carbon::parse($factura['fecha_vencimiento']);
                $diasRetraso = now()->diffInDays($fechaVencimiento, false);
                
                if ($diasRetraso > $diasMaximos) {
                    $facturasRetrasadas[] = [
                        'numero' => $factura['numero_factura'],
                        'dias_retraso' => $diasRetraso,
                        'monto' => $factura['saldo']
                    ];
                    $diasRetrasoMaximo = max($diasRetrasoMaximo, $diasRetraso);
                }
            }

            // Actualizar días de retraso en el cliente
            $cliente->update([
                'dias_retraso_facturas' => $diasRetrasoMaximo,
                'requiere_autorizacion_retraso' => count($facturasRetrasadas) > 0
            ]);

            if (count($facturasRetrasadas) > 0) {
                return [
                    'valido' => false,
                    'motivo' => "Facturas con retraso de hasta {$diasRetrasoMaximo} días. Máximo permitido: {$diasMaximos} días",
                    'estado' => 'warning',
                    'facturas_retrasadas' => $facturasRetrasadas,
                    'dias_retraso_maximo' => $diasRetrasoMaximo
                ];
            }

            return [
                'valido' => true,
                'motivo' => "Sin facturas en retraso. Máximo permitido: {$diasMaximos} días",
                'estado' => 'success'
            ];

        } catch (\Exception $e) {
            Log::error('Error validando retraso de facturas: ' . $e->getMessage());
            return [
                'valido' => false,
                'motivo' => 'Error verificando facturas pendientes',
                'estado' => 'warning'
            ];
        }
    }

    /**
     * Validar stock de productos
     * Usa stock FÍSICO para determinar si requiere aprobación de compras
     */
    public static function validarStockProductos($productos)
    {
        $productosSinStock = [];
        $productosConStockInsuficiente = [];

        foreach ($productos as $producto) {
            // Usar stock_fisico si está disponible, sino usar stock_disponible como fallback
            $stockFisico = $producto['stock_fisico'] ?? ($producto['stock_disponible'] ?? 0);
            $cantidadSolicitada = $producto['cantidad'] ?? 0;

            // Validar usando stock FÍSICO (no disponible)
            // Si stock físico >= cantidad pedida → NO requiere compras
            // Si stock físico < cantidad pedida → SÍ requiere compras
            if ($stockFisico == 0) {
                $productosSinStock[] = [
                    'codigo' => $producto['codigo'],
                    'nombre' => $producto['nombre'],
                    'stock_fisico' => $stockFisico,
                    'stock_disponible' => $producto['stock_disponible'] ?? 0,
                    'cantidad_solicitada' => $cantidadSolicitada
                ];
            } elseif ($stockFisico < $cantidadSolicitada) {
                $productosConStockInsuficiente[] = [
                    'codigo' => $producto['codigo'],
                    'nombre' => $producto['nombre'],
                    'stock_fisico' => $stockFisico,
                    'stock_disponible' => $producto['stock_disponible'] ?? 0,
                    'cantidad_solicitada' => $cantidadSolicitada,
                    'faltante' => $cantidadSolicitada - $stockFisico
                ];
            }
        }

        $requiereAutorizacion = count($productosSinStock) > 0 || count($productosConStockInsuficiente) > 0;

        return [
            'valido' => !$requiereAutorizacion,
            'requiere_autorizacion' => $requiereAutorizacion,
            'productos_sin_stock' => $productosSinStock,
            'productos_stock_insuficiente' => $productosConStockInsuficiente,
            'estado' => $requiereAutorizacion ? 'warning' : 'success',
            'motivo' => $requiereAutorizacion ? 
                'Productos sin stock físico o con stock físico insuficiente' : 
                'Stock físico suficiente para todos los productos'
        ];
    }

    /**
     * Obtener resumen de validaciones
     */
    public static function obtenerResumenValidaciones($codigoCliente, $montoNotaVenta = 0, $productos = [])
    {
        $validacionCliente = self::validarClienteParaNotaVenta($codigoCliente, $montoNotaVenta);
        $validacionStock = self::validarStockProductos($productos);

        $requiereAutorizacion = $validacionCliente['requiere_autorizacion'] || $validacionStock['requiere_autorizacion'];
        
        // Determinar estado general
        $estadoGeneral = 'success';
        if ($requiereAutorizacion) {
            $estadoGeneral = 'warning';
        }
        if ($validacionCliente['estado'] === 'danger') {
            $estadoGeneral = 'danger';
        }

        return [
            'cliente' => $validacionCliente,
            'stock' => $validacionStock,
            'requiere_autorizacion' => $requiereAutorizacion,
            'estado_general' => $estadoGeneral,
            'puede_generar' => !$requiereAutorizacion && $validacionCliente['estado'] !== 'danger'
        ];
    }
}
