<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LimpiarNombresProductos extends Command
{
    protected $signature = 'productos:limpiar-nombres';
    protected $description = 'Limpiar nombres de productos en MySQL removiendo información de múltiplos y unidades';

    public function handle()
    {
        $this->info('Iniciando limpieza de nombres de productos...');
        
        $productos = DB::table('productos')->select('KOPR', 'NOKOPR')->get();
        
        $this->info('Total de productos a procesar: ' . $productos->count());
        
        $actualizados = 0;
        $sinCambios = 0;
        
        foreach ($productos as $producto) {
            $nombreOriginal = $producto->NOKOPR;
            $nombreLimpio = $this->limpiarNombreProducto($nombreOriginal);
            
            if ($nombreOriginal !== $nombreLimpio) {
                DB::table('productos')
                    ->where('KOPR', $producto->KOPR)
                    ->update([
                        'NOKOPR' => $nombreLimpio,
                        'updated_at' => now()
                    ]);
                
                $actualizados++;
                
                if ($this->getOutput()->isVerbose()) {
                    $this->line("Actualizado: {$producto->KOPR}");
                    $this->line("  Original: {$nombreOriginal}");
                    $this->line("  Limpio: {$nombreLimpio}");
                }
            } else {
                $sinCambios++;
            }
        }
        
        $this->info("Limpieza completada:");
        $this->info("- Productos actualizados: {$actualizados}");
        $this->info("- Productos sin cambios: {$sinCambios}");
        
        return 0;
    }
    
    /**
     * Limpiar nombre del producto removiendo información adicional como "Múltiplo: X" y unidades
     */
    private function limpiarNombreProducto($nombreProducto)
    {
        if (empty($nombreProducto)) {
            return $nombreProducto;
        }
        
        $nombreLimpio = $nombreProducto;
        
        // Remover patrones entre corchetes como "[30Unid.X.Paq]Múltiplo: 30"
        $nombreLimpio = preg_replace('/\[[^\]]*\]\s*[Mm][úu]?ltiplo:\s*\d+.*$/i', '', $nombreLimpio);
        // Remover patrones entre corchetes como "[30Unid.X.Paq]"
        $nombreLimpio = preg_replace('/\[[^\]]*\]/i', '', $nombreLimpio);
        
        // Remover patrones como "KILOMúltiplo: 20" (sin espacio antes de Múltiplo)
        $nombreLimpio = preg_replace('/[Kk][Ii][Ll][Oo][Mm][úu]?ltiplo:\s*\d+.*$/i', '', $nombreLimpio);
        
        // Remover patrones como "xxxxxxxmultiplo: X" o "xxxxxmultiplo: X" al final (case insensitive)
        $nombreLimpio = preg_replace('/\s*xxxxxxx?multiplo:\s*\d+.*$/i', '', $nombreLimpio);
        
        // Remover "Múltiplo: X" o "multiplo: X" al final (con o sin acento, case insensitive)
        $nombreLimpio = preg_replace('/\s*[Mm][úu]ltiplo:\s*\d+.*$/i', '', $nombreLimpio);
        
        // Remover "MULTIPLO: X" al final
        $nombreLimpio = preg_replace('/\s*MULTIPLO:\s*\d+.*$/i', '', $nombreLimpio);
        
        // Remover "UN.Múltiplo: X" o "UN.MULTIPLO: X" al final
        $nombreLimpio = preg_replace('/\s*UN\.\s*[Mm][úu]?ltiplo:\s*\d+.*$/i', '', $nombreLimpio);
        
        // Remover patrones como "Unidad [30Unid.X.Paq]" o "Unidad [30Unid.X.Paq]Múltiplo: 30"
        $nombreLimpio = preg_replace('/\s*[Uu]nidad\s*\[[^\]]*\].*$/i', '', $nombreLimpio);
        
        // Remover la palabra "adicional" si aparece
        $nombreLimpio = preg_replace('/\s*adicional\s*/i', ' ', $nombreLimpio);
        
        // Limpiar espacios múltiples y recortar
        $nombreLimpio = preg_replace('/\s+/', ' ', trim($nombreLimpio));
        
        return $nombreLimpio;
    }
}
