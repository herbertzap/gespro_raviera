<?php

namespace App\Helpers;

class EstadoHelper
{
    /**
     * Obtener el color del estado
     */
    public static function getEstadoColor($estado)
    {
        $colores = [
            'borrador' => 'secondary',
            'enviada' => 'info',
            'pendiente' => 'warning',
            'pendiente_picking' => 'warning',
            'aprobada_supervisor' => 'primary',
            'aprobada_compras' => 'primary',
            'aprobada_picking' => 'success',
            'rechazada' => 'danger',
            'enviada_sql' => 'info',
            'nvv_generada' => 'success',
            'nvv_facturada' => 'success',
            'despachada' => 'success'
        ];
        return $colores[$estado] ?? 'secondary';
    }

    /**
     * Obtener el icono del tipo de acción
     */
    public static function getEstadoIcon($tipoAccion)
    {
        $iconos = [
            'creacion' => 'plus',
            'envio' => 'send',
            'aprobacion' => 'check-2',
            'rechazo' => 'simple-remove',
            'separacion' => 'split',
            'insercion_sql' => 'cloud-upload-94',
            'generacion_nvv' => 'notes',
            'facturacion' => 'money-coins',
            'despacho' => 'delivery-fast'
        ];
        return $iconos[$tipoAccion] ?? 'info';
    }

    /**
     * Obtener el nombre del estado
     */
    public static function getEstadoNombre($estado)
    {
        $nombres = [
            'borrador' => 'Borrador',
            'enviada' => 'Enviada',
            'pendiente' => 'Pendiente de Aprobación',
            'pendiente_picking' => 'Pendiente de Picking',
            'aprobada_supervisor' => 'Aprobada por Supervisor',
            'aprobada_compras' => 'Aprobada por Compras',
            'aprobada_picking' => 'Aprobada por Picking',
            'rechazada' => 'Rechazada',
            'enviada_sql' => 'Enviada a SQL Server',
            'nvv_generada' => 'NVV Generada',
            'nvv_facturada' => 'NVV Facturada',
            'despachada' => 'Despachada'
        ];
        return $nombres[$estado] ?? $estado;
    }

    /**
     * Obtener el nombre del tipo de acción
     */
    public static function getTipoAccionNombre($tipoAccion)
    {
        $nombres = [
            'creacion' => 'Creación',
            'envio' => 'Envío',
            'aprobacion' => 'Aprobación',
            'rechazo' => 'Rechazo',
            'separacion' => 'Separación de Productos',
            'insercion_sql' => 'Inserción en SQL Server',
            'generacion_nvv' => 'Generación de NVV',
            'facturacion' => 'Facturación',
            'despacho' => 'Despacho'
        ];
        return $nombres[$tipoAccion] ?? $tipoAccion;
    }
}
