<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:100',
            'apellido' => 'required|string|max:100',
            'email' => 'required|string|email|max:100|unique:usuario,email',
            'password' => 'required|string|min:8|confirmed',
            'telefono' => 'nullable|string|max:15',
        ]);

        $usuario = Usuario::create([
            'rol' => 'registrado',
            'nombre' => $validated['nombre'],
            'apellido' => $validated['apellido'],
            'email' => $validated['email'],
            'contraseña' => Hash::make($validated['password']),
            'telefono' => $validated['telefono'] ?? null,
            'activo' => 1,
        ]);

        $token = $usuario->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Usuario registrado exitosamente',
            'user' => [
                'id' => $usuario->idUsuario,
                'nombre' => $usuario->nombre,
                'apellido' => $usuario->apellido,
                'email' => $usuario->email,
                'rol' => $usuario->rol,
                'telefono' => $usuario->telefono,
                'foto' => $usuario->foto,
            ],
            'token' => $token,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $usuario = Usuario::where('email', $request->email)->where('activo', 1)->first();

        if (!$usuario || !Hash::check($request->password, $usuario->contraseña)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales proporcionadas son incorrectas.'],
            ]);
        }

        $token = $usuario->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login exitoso',
            'user' => [
                'id' => $usuario->idUsuario,
                'nombre' => $usuario->nombre,
                'apellido' => $usuario->apellido,
                'email' => $usuario->email,
                'rol' => $usuario->rol,
                'telefono' => $usuario->telefono,
                'foto' => $usuario->foto,
            ],
            'token' => $token,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Sesión cerrada exitosamente',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $usuario = $request->user();
        
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $usuario->idUsuario,
                'nombre' => $usuario->nombre,
                'apellido' => $usuario->apellido,
                'email' => $usuario->email,
                'rol' => $usuario->rol,
                'telefono' => $usuario->telefono,
                'foto' => $usuario->foto,
                'fechaRegistro' => $usuario->fechaRegistro,
            ],
        ]);
    }
}