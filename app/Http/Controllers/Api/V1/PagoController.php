<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Pago;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class PagoController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'receptor_id' => 'required|exists:usuario,idUsuario',
            'servicio_id' => 'required|exists:servicio,idServicio',
            'monto' => 'required|numeric|min:0'
        ]);

        $pago = Pago::create([
            'pagador_id' => Auth::id(),
            'receptor_id' => $validated['receptor_id'],
            'servicio_id' => $validated['servicio_id'],
            'monto' => $validated['monto'],
            'estado' => 'completado'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pago realizado exitosamente',
            'data' => $pago
        ], 201);
    }

    public function realizados(): JsonResponse
    {
        $pagos = Pago::with(['receptor', 'servicio'])
            ->where('pagador_id', Auth::id())
            ->orderBy('fechaPago', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $pagos
        ]);
    }

    public function recibidos(): JsonResponse
    {
        $pagos = Pago::with(['pagador', 'servicio'])
            ->where('receptor_id', Auth::id())
            ->orderBy('fechaPago', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $pagos
        ]);
    }
}