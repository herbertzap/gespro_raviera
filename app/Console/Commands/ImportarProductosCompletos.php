<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Producto;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImportarProductosCompletos extends Command
{
    protected $signature = 'productos:importar-completos {archivo?}';
    protected $description = 'Importar productos completos desde archivo SQL de SQL Server';

    public function handle()
    {
        $archivo = $this->argument('archivo') ?? 'MAEPR_202508271133.sql';
        $rutaArchivo = base_path($archivo);

        if (!file_exists($rutaArchivo)) {
            $this->error("❌ El archivo {$archivo} no existe en la raíz del proyecto");
            return 1;
        }

        $this->info("🔄 Iniciando importación de productos desde {$archivo}...");
        
        try {
            // Leer el archivo SQL
            $contenido = file_get_contents($rutaArchivo);
            
            // Extraer las inserciones INSERT
            preg_match_all('/INSERT INTO.*?VALUES\s*\((.*?)\)/s', $contenido, $matches);
            
            if (empty($matches[1])) {
                $this->error("❌ No se encontraron inserciones INSERT en el archivo");
                return 1;
            }

            $this->info("📊 Encontradas " . count($matches[1]) . " inserciones de productos");
            
            // Limpiar tabla existente
            $this->info("🧹 Limpiando tabla productos existente...");
            Producto::truncate();
            
            $contador = 0;
            $creados = 0;
            $errores = 0;

            foreach ($matches[1] as $values) {
                $contador++;
                
                if ($contador % 100 == 0) {
                    $this->info("Procesando producto {$contador} de " . count($matches[1]));
                }

                try {
                    $producto = $this->parsearValores($values);
                    
                    if ($producto && !empty($producto['KOPR'])) {
                        Producto::create($producto);
                        $creados++;
                    }
                } catch (\Exception $e) {
                    $errores++;
                    if ($errores <= 10) { // Solo mostrar los primeros 10 errores
                        $this->warn("Error en producto {$contador}: " . $e->getMessage());
                    }
                }
            }

            $this->info("✅ Importación completada!");
            $this->info("📈 Productos creados: {$creados}");
            $this->info("❌ Errores: {$errores}");
            $this->info("📊 Total procesados: {$contador}");

            // Verificar resultado final
            $totalFinal = Producto::count();
            $this->info("🎯 Total productos en tabla: {$totalFinal}");

        } catch (\Exception $e) {
            $this->error("❌ Error durante la importación: " . $e->getMessage());
            Log::error("Error importando productos", [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return 1;
        }

        return 0;
    }

    /**
     * Parsear valores de INSERT para convertirlos a array
     */
    private function parsearValores($values)
    {
        // Limpiar y dividir los valores
        $values = trim($values);
        $valores = $this->splitValues($values);
        
        // Mapear a la estructura de la tabla productos
        $producto = [
            'TIPR' => $this->limpiarValor($valores[0] ?? '', 10),
            'KOPR' => $this->limpiarValor($valores[1] ?? '', 20),
            'NOKOPR' => $this->limpiarValor($valores[2] ?? '', 255),
            'KOPRRA' => $this->limpiarValor($valores[3] ?? '', 20),
            'NOKOPRRA' => $this->limpiarValor($valores[4] ?? '', 255),
            'KOPRTE' => $this->limpiarValor($valores[5] ?? '', 20),
            'UD01PR' => $this->limpiarValor($valores[6] ?? '', 10),
            'UD02PR' => $this->limpiarValor($valores[7] ?? '', 10),
            'RLUD' => $this->parsearFloat($valores[8] ?? 1.0),
            'POIVPR' => $this->parsearFloat($valores[9] ?? 0.0),
            'RGPR' => $this->limpiarValor($valores[10] ?? '', 10),
            'MRPR' => $this->limpiarValor($valores[11] ?? '', 50),
            'FMPR' => $this->limpiarValor($valores[12] ?? '', 50),
            'PFPR' => $this->limpiarValor($valores[13] ?? '', 50),
            'HFPR' => $this->limpiarValor($valores[14] ?? '', 50),
            'DIVISIBLE' => $this->parsearBoolean($valores[15] ?? 'N'),
            'FECRPR' => $this->parsearFecha($valores[16] ?? null),
            'DIVISIBLE2' => $this->parsearBoolean($valores[17] ?? 'N'),
            'estado' => 0,
            'ultima_sincronizacion' => now(),
        ];

        return $producto;
    }

    /**
     * Dividir valores de INSERT considerando comillas
     */
    private function splitValues($values)
    {
        $result = [];
        $current = '';
        $inQuotes = false;
        $quoteChar = null;
        
        for ($i = 0; $i < strlen($values); $i++) {
            $char = $values[$i];
            
            if (($char === "'" || $char === '"') && !$inQuotes) {
                $inQuotes = true;
                $quoteChar = $char;
                continue;
            }
            
            if ($char === $quoteChar && $inQuotes) {
                $inQuotes = false;
                $quoteChar = null;
                continue;
            }
            
            if ($char === ',' && !$inQuotes) {
                $result[] = trim($current);
                $current = '';
                continue;
            }
            
            $current .= $char;
        }
        
        if (!empty($current)) {
            $result[] = trim($current);
        }
        
        return $result;
    }

    /**
     * Limpiar valor y aplicar límite de longitud
     */
    private function limpiarValor($valor, $maxLength)
    {
        $valor = trim($valor, "'\"");
        $valor = str_replace("''", "'", $valor); // Manejar comillas escapadas
        return substr($valor, 0, $maxLength);
    }

    /**
     * Parsear valor float
     */
    private function parsearFloat($valor)
    {
        $valor = trim($valor, "'\"");
        return is_numeric($valor) ? (float)$valor : 0.0;
    }

    /**
     * Parsear valor boolean
     */
    private function parsearBoolean($valor)
    {
        $valor = trim($valor, "'\"");
        return in_array(strtoupper($valor), ['S', '1', 'TRUE', 'YES']) ? 1 : 0;
    }

    /**
     * Parsear fecha
     */
    private function parsearFecha($valor)
    {
        if (empty($valor) || $valor === 'NULL') {
            return null;
        }
        
        $valor = trim($valor, "'\"");
        
        // Intentar parsear diferentes formatos de fecha
        $formats = ['Y-m-d H:i:s', 'Y-m-d', 'd/m/Y', 'm/d/Y'];
        
        foreach ($formats as $format) {
            $date = \DateTime::createFromFormat($format, $valor);
            if ($date !== false) {
                return $date->format('Y-m-d H:i:s');
            }
        }
        
        return null;
    }
}
