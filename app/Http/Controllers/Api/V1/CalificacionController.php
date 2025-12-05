<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Calificacion;
use App\Models\Servicio;
use App\Models\Chat; // ← AGREGAR ESTE IMPORT
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CalificacionController extends Controller
{
    /**
     * Obtener calificaciones de un servicio
     */
    public function porServicio($id): JsonResponse
    {
        try {
            $calificaciones = Calificacion::where('servicio_id', $id)
                ->with('usuario:idUsuario,nombre,apellido,foto')
                ->orderBy('fecha', 'desc')
                ->get();

            $promedio = $calificaciones->avg('puntuacion');
            $total = $calificaciones->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'calificaciones' => $calificaciones,
                    'promedio' => $promedio ? round($promedio, 1) : 0,
                    'total' => $total
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener calificaciones: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar las calificaciones'
            ], 500);
        }
    }

    /**
     * Crear una calificación
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'servicio_id' => 'required|exists:servicio,idServicio',
                'puntuacion' => 'required|integer|min:1|max:5',
                'comentario' => 'nullable|string|max:500'
            ]);

            $usuario = Auth::user();
            $servicio = Servicio::findOrFail($validated['servicio_id']);

            // Verificar que no sea el dueño del servicio
            if ($servicio->usuario_id === $usuario->idUsuario) {
                return response()->json([
                    'success' => false,
                    'message' => 'No puedes calificar tu propio servicio'
                ], 403);
            }

            // ← CAMBIAR ESTA VALIDACIÓN: Verificar que el usuario haya contactado este servicio
            $haContactado = Chat::where('emisor_id', $usuario->idUsuario)
                ->where('servicio_id', $validated['servicio_id'])
                ->exists();

            if (!$haContactado) {
                return response()->json([
                    'success' => false,
                    'message' => 'Debes contratar el servicio para poder calificarlo'
                ], 403);
            }

            // Verificar si ya calificó este servicio
            $calificacionExistente = Calificacion::where('servicio_id', $validated['servicio_id'])
                ->where('usuario_id', $usuario->idUsuario)
                ->first();

            if ($calificacionExistente) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya has calificado este servicio. Puedes editar tu calificación existente.'
                ], 409);
            }

            // Crear la calificación
            $calificacion = Calificacion::create([
                'servicio_id' => $validated['servicio_id'],
                'usuario_id' => $usuario->idUsuario,
                'puntuacion' => $validated['puntuacion'],
                'comentario' => $validated['comentario'] ?? null,
                'fecha' => now()
            ]);

            $calificacion->load('usuario:idUsuario,nombre,apellido,foto');

            return response()->json([
                'success' => true,
                'message' => 'Calificación enviada exitosamente',
                'data' => $calificacion
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error al crear calificación: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al enviar la calificación'
            ], 500);
        }
    }

    /**
     * Actualizar una calificación
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $calificacion = Calificacion::findOrFail($id);

            // Verificar que sea el dueño de la calificación
            if ($calificacion->usuario_id !== Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permiso para editar esta calificación'
                ], 403);
            }

            $validated = $request->validate([
                'puntuacion' => 'sometimes|integer|min:1|max:5',
                'comentario' => 'nullable|string|max:500'
            ]);

            $calificacion->update($validated);
            $calificacion->load('usuario:idUsuario,nombre,apellido,foto');

            return response()->json([
                'success' => true,
                'message' => 'Calificación actualizada exitosamente',
                'data' => $calificacion
            ]);

        } catch (\Exception $e) {
            Log::error('Error al actualizar calificación: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la calificación'
            ], 500);
        }
    }

    /**
     * Eliminar una calificación
     */
    public function destroy($id): JsonResponse
    {
        try {
            $calificacion = Calificacion::findOrFail($id);

            // Verificar que sea el dueño de la calificación
            if ($calificacion->usuario_id !== Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permiso para eliminar esta calificación'
                ], 403);
            }

            $calificacion->delete();

            return response()->json([
                'success' => true,
                'message' => 'Calificación eliminada exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al eliminar calificación: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la calificación'
            ], 500);
        }
    }

    /**
     * Obtener la calificación del usuario actual para un servicio específico
     */
    public function miCalificacion($servicioId): JsonResponse
    {
        try {
            $calificacion = Calificacion::where('servicio_id', $servicioId)
                ->where('usuario_id', Auth::id())
                ->with('usuario:idUsuario,nombre,apellido,foto')
                ->first();

            if (!$calificacion) {
                return response()->json([
                    'success' => true,
                    'data' => null
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $calificacion
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener mi calificación: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar tu calificación'
            ], 500);
        }
    }
}
