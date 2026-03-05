<?php

namespace App\Helpers;

class ProductoHelper
{
    /**
     * Limpia el nombre del producto para uso en NVV/MAEDDO: quita "Múltiplo: X",
     * variantes con mojibake (MÃºltiplo) y otros sufijos que no deben ir al nombre en SQL.
     */
    public static function limpiarNombreParaNVV($nombreProducto)
    {
        if (empty($nombreProducto) || !is_string($nombreProducto)) {
            return $nombreProducto;
        }

        $nombreLimpio = $nombreProducto;

        // Mojibake: "MÃºltiplo" (UTF-8 interpretado como Latin1)
        $nombreLimpio = preg_replace('/MÃºltiplo:\s*\d+.*$/u', '', $nombreLimpio);
        // Con o sin espacio antes, pegado al final (ej: COLONIALMúltiplo: 8)
        $nombreLimpio = preg_replace('/[Mm][úu]ltiplo:\s*\d+.*$/iu', '', $nombreLimpio);
        $nombreLimpio = preg_replace('/\s*MULTIPLO:\s*\d+.*$/i', '', $nombreLimpio);
        // Corchetes y sufijos tipo [30Unid.X.Paq]Múltiplo: 30
        $nombreLimpio = preg_replace('/\[[^\]]*\]\s*[Mm][úu]?ltiplo:\s*\d+.*$/iu', '', $nombreLimpio);
        $nombreLimpio = preg_replace('/\[[^\]]*\]/u', '', $nombreLimpio);
        $nombreLimpio = preg_replace('/\s*UN\.\s*[Mm][úu]?ltiplo:\s*\d+.*$/iu', '', $nombreLimpio);
        $nombreLimpio = preg_replace('/\s*adicional\s*/i', ' ', $nombreLimpio);

        $nombreLimpio = preg_replace('/\s+/', ' ', trim($nombreLimpio));
        return $nombreLimpio;
    }
}
