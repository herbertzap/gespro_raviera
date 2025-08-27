<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExportarProductosCompletos extends Command
{
    protected $signature = 'productos:exportar-completos {archivo?}';
    protected $description = 'Exportar todos los productos desde SQL Server a archivo SQL';

    public function handle()
    {
        $archivo = $this->argument('archivo') ?? 'MAEPR_COMPLETO_' . date('YmdHis') . '.sql';
        $rutaArchivo = base_path($archivo);

        $this->info("🔄 Iniciando exportación de productos desde SQL Server...");
        
        try {
            // Configuración de conexión SQL Server
            $host = env('SQLSRV_EXTERNAL_HOST');
            $port = env('SQLSRV_EXTERNAL_PORT', '1433');
            $database = env('SQLSRV_EXTERNAL_DATABASE');
            $username = env('SQLSRV_EXTERNAL_USERNAME');
            $password = env('SQLSRV_EXTERNAL_PASSWORD');

            // Verificar total de productos
            $this->info("📊 Verificando total de productos en SQL Server...");
            $comandoCount = "SELECT COUNT(*) FROM MAEPR WHERE TIPR != 'D'";
            $resultadoCount = shell_exec("echo \"{$comandoCount}\" | tsql -S {$host} -p {$port} -U {$username} -P {$password} -D {$database} -o f 2>/dev/null");
            
            preg_match('/(\d+)/', $resultadoCount, $matches);
            $totalProductos = $matches[1] ?? 0;
            
            $this->info("📈 Total productos en SQL Server: {$totalProductos}");

            // Crear archivo SQL
            $this->info("📝 Creando archivo SQL: {$archivo}");
            
            $sqlHeader = "-- Exportación completa de productos desde SQL Server\n";
            $sqlHeader .= "-- Fecha: " . date('Y-m-d H:i:s') . "\n";
            $sqlHeader .= "-- Total productos: {$totalProductos}\n\n";
            
            file_put_contents($rutaArchivo, $sqlHeader);

            // Exportar todos los productos de una vez
            $this->info("📦 Exportando todos los productos...");
            
            $comandoExport = "SELECT 
                TIPR, KOPR, NOKOPR, KOPRRA, NOKOPRRA, KOPRTE, 
                UD01PR, UD02PR, RLUD, POIVPR, RGPR, MRPR, FMPR, PFPR, HFPR, 
                DIVISIBLE, FECRPR, DIVISIBLE2
            FROM MAEPR 
            WHERE TIPR != 'D' 
            ORDER BY KOPR";

            $resultado = shell_exec("echo \"{$comandoExport}\" | tsql -S {$host} -p {$port} -U {$username} -P {$password} -D {$database} -o f 2>/dev/null");
            
            if ($resultado) {
                $lineas = explode("\n", trim($resultado));
                $inserts = [];
                $contador = 0;
                
                foreach ($lineas as $linea) {
                    $linea = trim($linea);
                    if (empty($linea)) continue;
                    
                    $campos = explode("\t", $linea);
                    if (count($campos) >= 18) {
                        $valores = array_map(function($valor) {
                            if ($valor === 'NULL' || empty($valor)) {
                                return 'NULL';
                            }
                            // Escapar comillas simples
                            $valor = str_replace("'", "''", $valor);
                            return "'" . $valor . "'";
                        }, $campos);
                        
                        $insert = "INSERT INTO productos (TIPR, KOPR, NOKOPR, KOPRRA, NOKOPRRA, KOPRTE, UD01PR, UD02PR, RLUD, POIVPR, RGPR, MRPR, FMPR, PFPR, HFPR, DIVISIBLE, FECRPR, DIVISIBLE2, estado, ultima_sincronizacion) VALUES (" . implode(', ', $valores) . ", 0, NOW());";
                        $inserts[] = $insert;
                        $contador++;
                        
                        if ($contador % 1000 == 0) {
                            $this->info("✅ Procesados {$contador} productos...");
                        }
                    }
                }
                
                if (!empty($inserts)) {
                    file_put_contents($rutaArchivo, implode("\n", $inserts) . "\n", FILE_APPEND);
                    $this->info("✅ Exportados {$contador} productos");
                }
            }

            $this->info("✅ Exportación completada!");
            $this->info("📁 Archivo creado: {$archivo}");
            $this->info("📊 Total productos exportados: {$contador}");
            
            // Verificar archivo
            $tamanoArchivo = filesize($rutaArchivo);
            $this->info("📏 Tamaño del archivo: " . number_format($tamanoArchivo / 1024 / 1024, 2) . " MB");

        } catch (\Exception $e) {
            $this->error("❌ Error durante la exportación: " . $e->getMessage());
            Log::error("Error exportando productos", [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return 1;
        }

        return 0;
    }
}
