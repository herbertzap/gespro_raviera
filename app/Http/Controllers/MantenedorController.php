<?php

namespace App\Http\Controllers;

use App\Models\Bodega;
use App\Models\Ubicacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MantenedorController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:Super Admin']);
    }

    /**
     * Mostrar listado de bodegas y ubicaciones
     */
    public function bodegas()
    {
        $bodegas = Bodega::with(['ubicaciones' => function ($query) {
            $query->orderBy('codigo');
        }])->orderBy('nombre_bodega')->get();

        return view('mantenedor.bodegas', [
            'bodegas' => $bodegas,
        ]);
    }

    /**
     * Crear nueva bodega
     */
    public function crearBodega(Request $request)
    {
        $data = $request->validate([
            'empresa' => ['required', 'string', 'max:10'],
            'kosu' => ['required', 'string', 'max:10'],
            'kobo' => ['required', 'string', 'max:10'],
            'nombre_bodega' => ['required', 'string', 'max:200'],
            'centro_costo' => ['nullable', 'string', 'max:10'],
        ]);

        try {
            // Verificar si ya existe una bodega con el mismo kobo
            $existe = Bodega::where('kobo', $data['kobo'])->first();
            if ($existe) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya existe una bodega con el código KOBO: ' . $data['kobo'],
                ], 400);
            }

            $bodega = Bodega::create($data);

            return response()->json([
                'success' => true,
                'message' => 'Bodega creada correctamente.',
                'bodega' => $bodega->load('ubicaciones'),
            ]);
        } catch (\Throwable $e) {
            Log::error('Error creando bodega', [
                'data' => $data,
                'exception' => $e,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al crear la bodega: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualizar bodega
     */
    public function actualizarBodega(Request $request, $id)
    {
        $bodega = Bodega::findOrFail($id);

        $data = $request->validate([
            'empresa' => ['required', 'string', 'max:10'],
            'kosu' => ['required', 'string', 'max:10'],
            'kobo' => ['required', 'string', 'max:10'],
            'nombre_bodega' => ['required', 'string', 'max:200'],
            'centro_costo' => ['nullable', 'string', 'max:10'],
        ]);

        try {
            // Verificar si ya existe otra bodega con el mismo kobo
            $existe = Bodega::where('kobo', $data['kobo'])
                ->where('id', '!=', $id)
                ->first();
            if ($existe) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya existe otra bodega con el código KOBO: ' . $data['kobo'],
                ], 400);
            }

            $bodega->update($data);

            return response()->json([
                'success' => true,
                'message' => 'Bodega actualizada correctamente.',
                'bodega' => $bodega->load('ubicaciones'),
            ]);
        } catch (\Throwable $e) {
            Log::error('Error actualizando bodega', [
                'bodega_id' => $id,
                'data' => $data,
                'exception' => $e,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la bodega: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Eliminar bodega
     */
    public function eliminarBodega($id)
    {
        try {
            $bodega = Bodega::findOrFail($id);

            // Verificar si tiene ubicaciones asociadas
            if ($bodega->ubicaciones()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar la bodega porque tiene ubicaciones asociadas.',
                ], 400);
            }

            $bodega->delete();

            return response()->json([
                'success' => true,
                'message' => 'Bodega eliminada correctamente.',
            ]);
        } catch (\Throwable $e) {
            Log::error('Error eliminando bodega', [
                'bodega_id' => $id,
                'exception' => $e,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la bodega: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Crear nueva ubicación
     */
    public function crearUbicacion(Request $request)
    {
        $data = $request->validate([
            'bodega_id' => ['required', 'exists:bodegas,id'],
            'codigo' => ['required', 'string', 'max:50'],
            'descripcion' => ['nullable', 'string', 'max:200'],
        ]);

        try {
            // Obtener la bodega para obtener el kobo
            $bodega = Bodega::findOrFail($data['bodega_id']);
            
            // Verificar si ya existe una ubicación con el mismo código en la bodega
            $existe = Ubicacion::where('bodega_id', $data['bodega_id'])
                ->where('codigo', $data['codigo'])
                ->first();
            if ($existe) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya existe una ubicación con el código: ' . $data['codigo'] . ' en esta bodega.',
                ], 400);
            }

            // Agregar kobo de la bodega a los datos
            $data['kobo'] = $bodega->kobo;
            
            $ubicacion = Ubicacion::create($data);

            return response()->json([
                'success' => true,
                'message' => 'Ubicación creada correctamente.',
                'ubicacion' => $ubicacion->load('bodega'),
            ]);
        } catch (\Throwable $e) {
            Log::error('Error creando ubicación', [
                'data' => $data,
                'exception' => $e,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al crear la ubicación: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualizar ubicación
     */
    public function actualizarUbicacion(Request $request, $id)
    {
        $ubicacion = Ubicacion::findOrFail($id);

        $data = $request->validate([
            'codigo' => ['required', 'string', 'max:50'],
            'descripcion' => ['nullable', 'string', 'max:200'],
        ]);

        try {
            // Verificar si ya existe otra ubicación con el mismo código en la misma bodega
            $existe = Ubicacion::where('bodega_id', $ubicacion->bodega_id)
                ->where('codigo', $data['codigo'])
                ->where('id', '!=', $id)
                ->first();
            if ($existe) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya existe otra ubicación con el código: ' . $data['codigo'] . ' en esta bodega.',
                ], 400);
            }

            $ubicacion->update($data);

            return response()->json([
                'success' => true,
                'message' => 'Ubicación actualizada correctamente.',
                'ubicacion' => $ubicacion->load('bodega'),
            ]);
        } catch (\Throwable $e) {
            Log::error('Error actualizando ubicación', [
                'ubicacion_id' => $id,
                'data' => $data,
                'exception' => $e,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la ubicación: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Eliminar ubicación
     */
    public function eliminarUbicacion($id)
    {
        try {
            $ubicacion = Ubicacion::findOrFail($id);
            $ubicacion->delete();

            return response()->json([
                'success' => true,
                'message' => 'Ubicación eliminada correctamente.',
            ]);
        } catch (\Throwable $e) {
            Log::error('Error eliminando ubicación', [
                'ubicacion_id' => $id,
                'exception' => $e,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la ubicación: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Carga masiva de ubicaciones desde archivo Excel
     */
    public function cargaMasivaUbicaciones(Request $request)
    {
        $request->validate([
            'bodega_id' => ['required', 'exists:bodegas,id'],
            'kobo' => ['required', 'string', 'max:10'],
            'archivo_excel' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'], // Max 10MB
        ]);

        try {
            $bodega = Bodega::findOrFail($request->bodega_id);
            
            // Validar que el kobo coincida con el de la bodega
            if ($bodega->kobo !== $request->kobo) {
                return response()->json([
                    'success' => false,
                    'message' => 'El KOBO del archivo no coincide con el de la bodega seleccionada.',
                ], 400);
            }

            $archivo = $request->file('archivo_excel');
            $spreadsheet = IOFactory::load($archivo->getPathname());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            if (count($rows) < 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'El archivo Excel está vacío o no tiene datos.',
                ], 400);
            }

            // Función para normalizar texto (quitar acentos)
            $normalizar = function($texto) {
                $texto = trim($texto ?? '');
                $texto = strtoupper($texto);
                // Convertir acentos a letras normales
                $texto = str_replace(
                    ['Á', 'É', 'Í', 'Ó', 'Ú', 'Ñ', 'á', 'é', 'í', 'ó', 'ú', 'ñ'],
                    ['A', 'E', 'I', 'O', 'U', 'N', 'A', 'E', 'I', 'O', 'U', 'N'],
                    $texto
                );
                // Remover BOM y caracteres invisibles
                $texto = preg_replace('/[\x00-\x1F\x7F]/', '', $texto);
                return $texto;
            };
            
            // Buscar índices de columnas (primera fila son encabezados)
            $headers = array_map($normalizar, $rows[0]);
            
            // Buscar columnas de manera flexible
            $koboIndex = false;
            $ubicacionIndex = false;
            $descripcionIndex = false;
            
            foreach ($headers as $idx => $header) {
                if (strpos($header, 'KOBO') !== false) {
                    $koboIndex = $idx;
                }
                if (strpos($header, 'UBICACION') !== false) {
                    $ubicacionIndex = $idx;
                }
                if (strpos($header, 'DESCRIPCION') !== false) {
                    $descripcionIndex = $idx;
                }
            }

            // Log para debug
            \Log::info('Carga masiva ubicaciones - Headers encontrados:', [
                'headers_raw' => $rows[0],
                'headers_procesados' => $headers,
                'koboIndex' => $koboIndex,
                'ubicacionIndex' => $ubicacionIndex,
                'descripcionIndex' => $descripcionIndex,
            ]);

            if ($koboIndex === false || $ubicacionIndex === false) {
                return response()->json([
                    'success' => false,
                    'message' => 'El archivo Excel debe tener las columnas: KOBO, UBICACION. La columna DESCRIPCION es opcional. Headers encontrados: ' . implode(', ', array_filter($headers)),
                ], 400);
            }

            $creadas = 0;
            $omitidas = [];
            $errores = [];

            // Procesar desde la fila 2 (índice 1)
            for ($i = 1; $i < count($rows); $i++) {
                $row = $rows[$i];
                
                // Verificar que la fila tenga datos
                if (empty($row[$ubicacionIndex])) {
                    continue;
                }

                $kobo = trim($row[$koboIndex] ?? '');
                $codigo = trim($row[$ubicacionIndex] ?? '');
                $descripcion = trim($row[$descripcionIndex] ?? '');

                // Validar que el kobo coincida
                if ($kobo !== $bodega->kobo) {
                    $errores[] = "Fila " . ($i + 1) . ": El KOBO '$kobo' no coincide con el de la bodega. Se esperaba '{$bodega->kobo}'";
                    continue;
                }

                // Validar que el código no esté vacío
                if (empty($codigo)) {
                    $errores[] = "Fila " . ($i + 1) . ": El código de ubicación está vacío";
                    continue;
                }

                // Verificar si la ubicación ya existe
                $existe = Ubicacion::where('bodega_id', $bodega->id)
                    ->where('codigo', $codigo)
                    ->first();

                if ($existe) {
                    $omitidas[] = $codigo;
                    continue;
                }

                // Crear la ubicación
                try {
                    Ubicacion::create([
                        'bodega_id' => $bodega->id,
                        'kobo' => $bodega->kobo,
                        'codigo' => $codigo,
                        'descripcion' => $descripcion ?: null,
                    ]);
                    $creadas++;
                } catch (\Exception $e) {
                    $errores[] = "Fila " . ($i + 1) . ": Error al crear ubicación '$codigo': " . $e->getMessage();
                    Log::error('Error creando ubicación en carga masiva', [
                        'fila' => $i + 1,
                        'codigo' => $codigo,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $mensaje = "Proceso completado. Se crearon {$creadas} ubicaciones.";
            if (count($omitidas) > 0) {
                $mensaje .= " Se omitieron " . count($omitidas) . " ubicaciones que ya existían.";
            }
            if (count($errores) > 0) {
                $mensaje .= " Se encontraron " . count($errores) . " errores.";
            }

            return response()->json([
                'success' => true,
                'message' => $mensaje,
                'creadas' => $creadas,
                'omitidas' => $omitidas,
                'errores' => $errores,
            ]);

        } catch (\Exception $e) {
            Log::error('Error en carga masiva de ubicaciones', [
                'bodega_id' => $request->bodega_id,
                'exception' => $e,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al procesar el archivo: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Descargar plantilla Excel para carga masiva
     */
    public function descargarPlantilla()
    {
        try {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Encabezados
            $sheet->setCellValue('A1', 'KOBO');
            $sheet->setCellValue('B1', 'UBICACION');
            $sheet->setCellValue('C1', 'DESCRIPCION');

            // Estilo para encabezados
            $sheet->getStyle('A1:C1')->getFont()->setBold(true);
            $sheet->getStyle('A1:C1')->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFE0E0E0');

            // Ejemplo de datos
            $sheet->setCellValue('A2', 'PSI');
            $sheet->setCellValue('B2', '1Z1E01C08');
            $sheet->setCellValue('C2', 'Ejemplo de ubicación');

            // Ajustar ancho de columnas
            $sheet->getColumnDimension('A')->setWidth(15);
            $sheet->getColumnDimension('B')->setWidth(20);
            $sheet->getColumnDimension('C')->setWidth(30);

            $writer = new Xlsx($spreadsheet);
            
            $response = new StreamedResponse(function() use ($writer) {
                $writer->save('php://output');
            });

            $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            $response->headers->set('Content-Disposition', 'attachment;filename="plantilla_ubicaciones.xlsx"');
            $response->headers->set('Cache-Control', 'max-age=0');

            return $response;

        } catch (\Exception $e) {
            Log::error('Error generando plantilla Excel', [
                'exception' => $e,
            ]);

            return redirect()->back()
                ->with('error', 'Error al generar la plantilla: ' . $e->getMessage());
        }
    }
}

