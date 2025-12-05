<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\Usuario;
use App\Models\Servicio;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ChatController extends Controller
{
    public function conversaciones(): JsonResponse
    {
        $userId = Auth::id();

        $conversaciones = Chat::select('emisor_id', 'receptor_id', DB::raw('MAX(fechaEnvio) as ultima_fecha'))
            ->where('emisor_id', $userId)
            ->orWhere('receptor_id', $userId)
            ->groupBy('emisor_id', 'receptor_id')
            ->orderBy('ultima_fecha', 'desc')
            ->get();

        $usuariosIds = collect();
        foreach ($conversaciones as $conv) {
            $otroUsuarioId = $conv->emisor_id == $userId ? $conv->receptor_id : $conv->emisor_id;
            $usuariosIds->push($otroUsuarioId);
        }

        $usuarios = Usuario::whereIn('idUsuario', $usuariosIds->unique())
            ->get();

        return response()->json([
            'success' => true,
            'data' => $usuarios
        ]);
    }

    public function mensajes($usuarioId): JsonResponse
    {
        $currentUserId = Auth::id();

        $mensajes = Chat::with(['emisor', 'receptor'])
            ->where(function($query) use ($currentUserId, $usuarioId) {
                $query->where('emisor_id', $currentUserId)
                      ->where('receptor_id', $usuarioId);
            })
            ->orWhere(function($query) use ($currentUserId, $usuarioId) {
                $query->where('emisor_id', $usuarioId)
                      ->where('receptor_id', $currentUserId);
            })
            ->orderBy('fechaEnvio', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $mensajes
        ]);
    }

    public function enviar(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'receptor_id' => 'required|exists:usuario,idUsuario',
            'mensaje' => 'required|string',
            'servicio_id' => 'nullable|exists:servicio,idServicio' // ← AGREGAR
        ]);

        $chat = Chat::create([
            'emisor_id' => Auth::id(),
            'receptor_id' => $validated['receptor_id'],
            'mensaje' => $validated['mensaje'],
            'servicio_id' => $validated['servicio_id'] ?? null // ← AGREGAR
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Mensaje enviado',
            'data' => $chat->load(['emisor', 'receptor'])
        ], 201);
    }

    // ← AGREGAR ESTE NUEVO MÉTODO
    public function serviciosContactados(): JsonResponse
    {
        $userId = Auth::id();

        // Obtener IDs de servicios únicos que has contactado
        $serviciosIds = Chat::where('emisor_id', $userId)
            ->whereNotNull('servicio_id')
            ->distinct()
            ->pluck('servicio_id');

        // Si no hay servicios contactados, retornar array vacío
        if ($serviciosIds->isEmpty()) {
            return response()->json([
                'success' => true,
                'data' => []
            ]);
        }

        // Obtener los servicios EXACTAMENTE igual que misPublicaciones
        $servicios = Servicio::with(['usuario', 'calificaciones'])
            ->withCount('calificaciones')
            ->whereIn('idServicio', $serviciosIds)
            ->orderBy('fechaPublicacion', 'desc')
            ->get()
            ->map(function($servicio) use ($userId) {
                // Solo agregar la fecha de contacto
                $primerContacto = Chat::where('emisor_id', $userId)
                    ->where('servicio_id', $servicio->idServicio)
                    ->orderBy('fechaEnvio', 'asc')
                    ->first();

                $servicio->fecha_contacto = $primerContacto ? $primerContacto->fechaEnvio : null;

                return $servicio;
            });

        return response()->json([
            'success' => true,
            'data' => $servicios
        ]);
    }
}
