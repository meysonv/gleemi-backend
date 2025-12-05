<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Favorito;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class FavoritoController extends Controller
{
    public function index(): JsonResponse
    {
        $favoritos = Favorito::with('servicio.usuario')
            ->where('usuario_id', Auth::id())
            ->orderBy('fecha', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $favoritos
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'servicio_id' => 'required|exists:servicio,idServicio'
        ]);

        $existe = Favorito::where('usuario_id', Auth::id())
            ->where('servicio_id', $validated['servicio_id'])
            ->exists();

        if ($existe) {
            return response()->json([
                'success' => false,
                'message' => 'Este servicio ya está en tus favoritos'
            ], 400);
        }

        $favorito = Favorito::create([
            'usuario_id' => Auth::id(),
            'servicio_id' => $validated['servicio_id']
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Servicio agregado a favoritos',
            'data' => $favorito
        ], 201);
    }

    public function destroy($servicioId): JsonResponse
    {
        $favorito = Favorito::where('usuario_id', Auth::id())
            ->where('servicio_id', $servicioId)
            ->first();

        if (!$favorito) {
            return response()->json([
                'success' => false,
                'message' => 'Favorito no encontrado'
            ], 404);
        }

        $favorito->delete();

        return response()->json([
            'success' => true,
            'message' => 'Servicio eliminado de favoritos'
        ]);
    }
}