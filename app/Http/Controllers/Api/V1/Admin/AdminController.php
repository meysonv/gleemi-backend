<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use App\Models\Servicio;
use App\Models\Chat;
use App\Models\Pago;
use App\Models\Calificacion;
use App\Models\Reporte;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class AdminController extends Controller
{
    public function dashboard(): JsonResponse
    {
        $stats = [
            'total_usuarios' => Usuario::count(),
            'usuarios_activos' => Usuario::where('activo', 1)->count(),
            'total_servicios' => Servicio::count(),
            'servicios_activos' => Servicio::where('estado', 'activo')->count(),
            'total_pagos' => Pago::count(),
            'monto_total_pagos' => Pago::where('estado', 'completado')->sum('monto'),
            'total_calificaciones' => Calificacion::count(),
            'promedio_calificaciones' => round(Calificacion::avg('puntuacion'), 2),
            'total_chats' => Chat::count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    public function usuarios(Request $request): JsonResponse
    {
        $query = Usuario::query();

        if ($request->filled('rol')) {
            $query->where('rol', $request->rol);
        }

        if ($request->filled('activo')) {
            $query->where('activo', $request->activo);
        }

        if ($request->filled('buscar')) {
            $query->where(function($q) use ($request) {
                $q->where('nombre', 'like', '%' . $request->buscar . '%')
                  ->orWhere('apellido', 'like', '%' . $request->buscar . '%')
                  ->orWhere('email', 'like', '%' . $request->buscar . '%');
            });
        }

        $usuarios = $query->orderBy('fechaRegistro', 'desc')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $usuarios
        ]);
    }

    public function verUsuario($id): JsonResponse
    {
        $usuario = Usuario::with([
            'servicios',
            'calificaciones',
            'pagosRealizados',
            'pagosRecibidos'
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $usuario
        ]);
    }

    public function toggleUsuario($id): JsonResponse
    {
        $usuario = Usuario::findOrFail($id);
        $usuario->activo = !$usuario->activo;
        $usuario->save();

        return response()->json([
            'success' => true,
            'message' => $usuario->activo ? 'Usuario activado' : 'Usuario desactivado',
            'data' => $usuario
        ]);
    }

    public function eliminarUsuario($id): JsonResponse
    {
        $usuario = Usuario::findOrFail($id);

        if ($usuario->rol === 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar un administrador'
            ], 403);
        }

        $usuario->delete();

        return response()->json([
            'success' => true,
            'message' => 'Usuario eliminado exitosamente'
        ]);
    }

    public function servicios(Request $request): JsonResponse
    {
        $query = Servicio::with('usuario');

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('buscar')) {
            $query->where(function($q) use ($request) {
                $q->where('titulo', 'like', '%' . $request->buscar . '%')
                  ->orWhere('descripcion', 'like', '%' . $request->buscar . '%');
            });
        }

        $servicios = $query->orderBy('fechaPublicacion', 'desc')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $servicios
        ]);
    }

    public function cambiarEstadoServicio($id, Request $request): JsonResponse
    {
        $request->validate([
            'estado' => 'required|in:activo,inactivo,eliminado'
        ]);

        $servicio = Servicio::findOrFail($id);
        $servicio->estado = $request->estado;
        $servicio->save();

        return response()->json([
            'success' => true,
            'message' => 'Estado del servicio actualizado',
            'data' => $servicio
        ]);
    }

    public function eliminarServicio($id): JsonResponse
    {
        $servicio = Servicio::findOrFail($id);

        //verifica si tiene relaciones importantes
        // if ($servicio->calificaciones()->count() > 0 || $servicio->pagos()->count() > 0) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'No se puede eliminar un servicio con calificaciones o pagos asociados'
        //     ], 400);
        // }

        $servicio->delete();

        return response()->json([
            'success' => true,
            'message' => 'Servicio eliminado exitosamente'
        ]);
    }

    public function chats(Request $request): JsonResponse
    {
        $query = Chat::with(['emisor', 'receptor']);

        if ($request->filled('usuario_id')) {
            $query->where(function($q) use ($request) {
                $q->where('emisor_id', $request->usuario_id)
                  ->orWhere('receptor_id', $request->usuario_id);
            });
        }

        if ($request->filled('buscar')) {
        $query->where('mensaje', 'like', '%' . $request->buscar . '%');
        }

        $chats = $query->orderBy('fechaEnvio', 'desc')
            ->paginate(50);

        return response()->json([
            'success' => true,
            'data' => $chats
        ]);
    }

    // ← AGREGAR: Método para obtener conversaciones agrupadas
    public function conversacionesAgrupadas(Request $request): JsonResponse
    {
        $conversaciones = Chat::selectRaw('
            LEAST(emisor_id, receptor_id) as usuario1_id,
            GREATEST(emisor_id, receptor_id) as usuario2_id,
            COUNT(*) as total_mensajes,
            MAX(fechaEnvio) as ultimo_mensaje
        ')
        ->groupBy('usuario1_id', 'usuario2_id')
        ->orderBy('ultimo_mensaje', 'desc')
        ->paginate(15);

        // Cargar datos de usuarios
        foreach ($conversaciones as $conversacion) {
            $conversacion->usuario1 = Usuario::find($conversacion->usuario1_id);
            $conversacion->usuario2 = Usuario::find($conversacion->usuario2_id);
        }

        return response()->json([
            'success' => true,
            'data' => $conversaciones
        ]);
    }

    // ← AGREGAR: Método para obtener conversación completa entre 2 usuarios
    public function conversacionCompleta($usuario1Id, $usuario2Id): JsonResponse
    {
        $mensajes = Chat::with(['emisor', 'receptor'])
            ->where(function($q) use ($usuario1Id, $usuario2Id) {
                $q->where(function($sub) use ($usuario1Id, $usuario2Id) {
                    $sub->where('emisor_id', $usuario1Id)
                    ->where('receptor_id', $usuario2Id);
                })->orWhere(function($sub) use ($usuario1Id, $usuario2Id) {
                    $sub->where('emisor_id', $usuario2Id)
                    ->where('receptor_id', $usuario1Id);
                });
            })
            ->orderBy('fechaEnvio', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $mensajes
        ]);
    }


    public function eliminarChat($id): JsonResponse
    {
        $chat = Chat::findOrFail($id);
        $chat->delete();

        return response()->json([
            'success' => true,
            'message' => 'Mensaje eliminado'
        ]);
    }

    public function pagos(Request $request): JsonResponse
    {
        $query = Pago::with(['pagador', 'receptor', 'servicio']);

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('fecha_desde')) {
            $query->whereDate('fechaPago', '>=', $request->fecha_desde);
        }

        if ($request->filled('fecha_hasta')) {
            $query->whereDate('fechaPago', '<=', $request->fecha_hasta);
        }

        $pagos = $query->orderBy('fechaPago', 'desc')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $pagos
        ]);
    }

    public function cambiarEstadoPago($id, Request $request): JsonResponse
    {
        $request->validate([
            'estado' => 'required|in:pendiente,completado,fallido'
        ]);

        $pago = Pago::findOrFail($id);
        $pago->estado = $request->estado;
        $pago->save();

        return response()->json([
            'success' => true,
            'message' => 'Estado del pago actualizado',
            'data' => $pago
        ]);
    }

    public function calificaciones(Request $request): JsonResponse
    {
        $query = Calificacion::with(['usuario', 'servicio']);

        if ($request->filled('servicio_id')) {
            $query->where('servicio_id', $request->servicio_id);
        }

        if ($request->filled('usuario_id')) {
            $query->where('usuario_id', $request->usuario_id);
        }

        // ← AGREGAR: Filtro por rango de puntuación
        if ($request->filled('puntuacion_min')) {
            $query->where('puntuacion', '>=', $request->puntuacion_min);
        }

        if ($request->filled('puntuacion_max')) {
            $query->where('puntuacion', '<=', $request->puntuacion_max);
        }

        // ← AGREGAR: Filtro por palabra clave en comentarios
        if ($request->filled('buscar')) {
            $query->where('comentario', 'like', '%' . $request->buscar . '%');
        }

        // ← AGREGAR: Filtro por rango de fechas
        if ($request->filled('fecha_desde')) {
            $query->whereDate('fecha', '>=', $request->fecha_desde);
        }

        if ($request->filled('fecha_hasta')) {
            $query->whereDate('fecha', '<=', $request->fecha_hasta);
        }

        $calificaciones = $query->orderBy('fecha', 'desc')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $calificaciones
        ]);
    }

    public function eliminarCalificacion($id): JsonResponse
    {
        $calificacion = Calificacion::findOrFail($id);
        $calificacion->delete();

        return response()->json([
            'success' => true,
            'message' => 'Calificación eliminada'
        ]);
    }

    public function generarReporte(Request $request): JsonResponse
    {
        $request->validate([
            'tipo' => 'required|in:usuarios,servicios,pagos,chats,calificaciones',
            'fecha_desde' => 'nullable|date',
            'fecha_hasta' => 'nullable|date',
        ]);

        $parametros = [
            'fecha_desde' => $request->fecha_desde,
            'fecha_hasta' => $request->fecha_hasta,
        ];

        // Obtener los datos del reporte
        $datos = $this->obtenerDatosReporte($request->tipo, $parametros);

        // Crear el registro del reporte
        $reporte = Reporte::create([
            'admin_id' => Auth::id(),
            'tipo' => $request->tipo,
            'parametros' => json_encode($parametros),
            'fechaGeneracion' => now(),
            'archivo' => null
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Reporte generado exitosamente',
            'data' => [
                'reporte' => $reporte,
                'estadisticas' => [
                    'total_registros' => count($datos),
                    'fecha_desde' => $request->fecha_desde,
                    'fecha_hasta' => $request->fecha_hasta,
                    'tipo' => $request->tipo,
                ],
                'datos' => $datos
            ]
        ]);
    }

    private function obtenerDatosReporte($tipo, $parametros)
    {
        $query = null;
        $campoFecha = null;

        switch ($tipo) {
            case 'usuarios':
                $query = Usuario::query();
                $campoFecha = 'fechaRegistro';
                break;
            case 'servicios':
                $query = Servicio::with('usuario');
                $campoFecha = 'fechaPublicacion';
                break;
            case 'pagos':
                $query = Pago::with(['pagador', 'receptor', 'servicio']);
                $campoFecha = 'fechaPago';
                break;
            case 'chats':
                $query = Chat::with(['emisor', 'receptor']);
                $campoFecha = 'fechaEnvio';
                break;
            case 'calificaciones':
                $query = Calificacion::with(['usuario', 'servicio']);
                $campoFecha = 'fecha';
                break;
        }

        // Aplicar filtros de fecha si existen
        if (!empty($parametros['fecha_desde']) && $query && $campoFecha) {
            $query->whereDate($campoFecha, '>=', $parametros['fecha_desde']);
        }

        if (!empty($parametros['fecha_hasta']) && $query && $campoFecha) {
            $query->whereDate($campoFecha, '<=', $parametros['fecha_hasta']);
        }

        return $query ? $query->get() : [];
    }

    public function reportes(Request $request): JsonResponse
    {
        $reportes = Reporte::with('admin')
            ->orderBy('fechaGeneracion', 'desc')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $reportes
        ]);
    }
}
