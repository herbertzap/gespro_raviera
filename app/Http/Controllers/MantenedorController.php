<?php

namespace App\Http\Controllers;

use App\Models\Bodega;
use App\Models\Ubicacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MantenedorController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'permission:ver_mantenedor']);
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
}

