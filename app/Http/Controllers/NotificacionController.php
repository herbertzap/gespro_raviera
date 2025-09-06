<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Notificacion;
use App\Services\NotificacionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class NotificacionController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Obtener notificaciones para el navbar
     */
    public function obtenerParaNavbar()
    {
        try {
            $usuario = Auth::user();
            
            // Obtener notificaciones del usuario
            $notificacionesUsuario = Notificacion::obtenerNoLeidas($usuario->id, 3);
            
            // Obtener notificaciones globales
            $notificacionesGlobales = Notificacion::obtenerGlobalesNoLeidas(2);
            
            // Combinar y limitar a 5
            $todasLasNotificaciones = $notificacionesUsuario->concat($notificacionesGlobales)
                ->sortByDesc('created_at')
                ->take(5);

            $html = '';
            $contador = 0;

            if ($todasLasNotificaciones->count() > 0) {
                foreach ($todasLasNotificaciones as $notificacion) {
                    $contador++;
                    $html .= $this->generarHtmlNotificacion($notificacion);
                }
                
                // Mostrar badge de notificaciones
                $html .= '<script>document.getElementById("notificationBadge").style.display = "block";</script>';
            } else {
                $html = '<li class="nav-link"><div class="text-center py-3"><p class="text-muted mb-0">No hay notificaciones nuevas</p></div></li>';
            }

            return response()->json([
                'success' => true,
                'html' => $html,
                'contador' => $contador
            ]);

        } catch (\Exception $e) {
            Log::error('Error obteniendo notificaciones para navbar: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'html' => '<li class="nav-link"><div class="text-center py-3"><p class="text-danger mb-0">Error cargando notificaciones</p></div></li>'
            ]);
        }
    }

    /**
     * Marcar notificación como leída
     */
    public function marcarComoLeida(Request $request, $id)
    {
        try {
            $notificacion = Notificacion::findOrFail($id);
            
            // Verificar que la notificación pertenece al usuario o es global
            if ($notificacion->usuario_id && $notificacion->usuario_id !== Auth::id()) {
                return response()->json(['success' => false, 'message' => 'No autorizado'], 403);
            }

            $notificacion->marcarComoLeida();

            return response()->json([
                'success' => true,
                'message' => 'Notificación marcada como leída'
            ]);

        } catch (\Exception $e) {
            Log::error('Error marcando notificación como leída: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al marcar notificación como leída'
            ], 500);
        }
    }

    /**
     * Obtener todas las notificaciones del usuario
     */
    public function index(Request $request)
    {
        $usuario = Auth::user();
        
        $query = Notificacion::where(function($q) use ($usuario) {
            $q->where('usuario_id', $usuario->id)
              ->orWhereNull('usuario_id'); // Notificaciones globales
        });

        // Filtros
        if ($request->has('estado') && $request->estado !== '') {
            $query->where('estado', $request->estado);
        }

        if ($request->has('tipo') && $request->tipo !== '') {
            $query->where('tipo', $request->tipo);
        }

        if ($request->has('prioridad') && $request->prioridad !== '') {
            $query->where('prioridad', $request->prioridad);
        }

        $notificaciones = $query->orderBy('created_at', 'desc')->paginate(20);

        return view('notificaciones.index', compact('notificaciones'));
    }

    /**
     * Ver notificación específica
     */
    public function show($id)
    {
        $notificacion = Notificacion::findOrFail($id);
        
        // Verificar que la notificación pertenece al usuario o es global
        if ($notificacion->usuario_id && $notificacion->usuario_id !== Auth::id()) {
            abort(403, 'No autorizado');
        }

        // Marcar como leída si no lo está
        if ($notificacion->estado === 'no_leida') {
            $notificacion->marcarComoLeida();
        }

        return view('notificaciones.show', compact('notificacion'));
    }

    /**
     * Marcar todas las notificaciones como leídas
     */
    public function marcarTodasComoLeidas()
    {
        try {
            $usuario = Auth::user();
            
            Notificacion::where('usuario_id', $usuario->id)
                ->where('estado', 'no_leida')
                ->update([
                    'estado' => 'leida',
                    'fecha_leida' => now()
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Todas las notificaciones marcadas como leídas'
            ]);

        } catch (\Exception $e) {
            Log::error('Error marcando todas las notificaciones como leídas: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al marcar notificaciones como leídas'
            ], 500);
        }
    }

    /**
     * Archivar notificación
     */
    public function archivar($id)
    {
        try {
            $notificacion = Notificacion::findOrFail($id);
            
            // Verificar que la notificación pertenece al usuario
            if ($notificacion->usuario_id && $notificacion->usuario_id !== Auth::id()) {
                return response()->json(['success' => false, 'message' => 'No autorizado'], 403);
            }

            $notificacion->archivar();

            return response()->json([
                'success' => true,
                'message' => 'Notificación archivada'
            ]);

        } catch (\Exception $e) {
            Log::error('Error archivando notificación: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al archivar notificación'
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de notificaciones
     */
    public function estadisticas()
    {
        try {
            $estadisticas = NotificacionService::obtenerEstadisticas();
            
            return response()->json([
                'success' => true,
                'estadisticas' => $estadisticas
            ]);

        } catch (\Exception $e) {
            Log::error('Error obteniendo estadísticas de notificaciones: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estadísticas'
            ], 500);
        }
    }

    /**
     * Generar HTML para una notificación
     */
    private function generarHtmlNotificacion(Notificacion $notificacion): string
    {
        $icono = $notificacion->icono_completo;
        $color = $notificacion->color_badge;
        $tiempo = $notificacion->tiempo_transcurrido;
        $url = $notificacion->url_accion ?: '#';
        
        return '
        <li class="nav-link">
            <a href="' . $url . '" class="nav-item dropdown-item" onclick="marcarNotificacionComoLeida(' . $notificacion->id . ')">
                <div class="d-flex align-items-center">
                    <div class="mr-3">
                        <i class="' . $icono . ' text-' . $color . '"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h6 class="mb-1">' . $notificacion->titulo . '</h6>
                        <p class="mb-1 text-muted small">' . $notificacion->mensaje . '</p>
                        <small class="text-muted">' . $tiempo . '</small>
                    </div>
                    <div class="ml-2">
                        <span class="badge badge-' . $color . ' badge-sm">' . ucfirst($notificacion->prioridad) . '</span>
                    </div>
                </div>
            </a>
        </li>';
    }
}