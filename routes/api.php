<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ServicioController;
use App\Http\Controllers\Api\V1\CalificacionController;
use App\Http\Controllers\Api\V1\FavoritoController;
use App\Http\Controllers\Api\V1\ChatController;
use App\Http\Controllers\Api\V1\PagoController;
use App\Http\Controllers\Api\V1\Admin\AdminController;
use Illuminate\Support\Facades\Route;


// Ruta de prueba
Route::get('/test', function () {
    return response()->json([
        'success' => true,
        'message' => '¡API Gleemi funcionando!',
        'version' => 'v1'
    ]);
});

// Versión 1 de la API
Route::prefix('v1')->group(function () {

    // ========== Rutas públicas ==========
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    // Servicios públicos (rutas específicas ANTES de rutas con parámetros)
    Route::get('/servicios', [ServicioController::class, 'index']);
    Route::get('/servicios/{id}', [ServicioController::class, 'show'])->where('id', '[0-9]+');

    // Calificaciones públicas
    Route::get('/calificaciones/servicio/{id}', [CalificacionController::class, 'porServicio']);

    // ========== Rutas protegidas ==========
    Route::middleware('auth:sanctum')->group(function () {

        // Auth
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);

        // Servicios (rutas específicas PRIMERO)
        Route::get('/servicios/mis-publicaciones', [ServicioController::class, 'misPublicaciones']);
        Route::get('/mis-servicios', [ServicioController::class, 'misServicios']);
        Route::post('/servicios', [ServicioController::class, 'store']);
        Route::put('/servicios/{id}', [ServicioController::class, 'update'])->where('id', '[0-9]+');
        Route::delete('/servicios/{id}', [ServicioController::class, 'destroy'])->where('id', '[0-9]+');

        // Calificaciones
        Route::get('/calificaciones/mi-calificacion/{servicioId}', [CalificacionController::class, 'miCalificacion']);
        Route::post('/calificaciones', [CalificacionController::class, 'store']);
        Route::put('/calificaciones/{id}', [CalificacionController::class, 'update']);
        Route::delete('/calificaciones/{id}', [CalificacionController::class, 'destroy']);

        // Favoritos
        Route::get('/favoritos', [FavoritoController::class, 'index']);
        Route::post('/favoritos', [FavoritoController::class, 'store']);
        Route::delete('/favoritos/{servicioId}', [FavoritoController::class, 'destroy']);

        // Chat
        Route::get('/chat/conversaciones', [ChatController::class, 'conversaciones']);
        Route::get('/chat/mensajes/{usuarioId}', [ChatController::class, 'mensajes']);
        Route::post('/chat/enviar', [ChatController::class, 'enviar']);
        Route::get('/chat/servicios-contactados', [ChatController::class, 'serviciosContactados']); // ← AGREGAR ESTA LÍNEA

        // Pagos
        Route::post('/pagos', [PagoController::class, 'store']);
        Route::get('/pagos/realizados', [PagoController::class, 'realizados']);
        Route::get('/pagos/recibidos', [PagoController::class, 'recibidos']);

        // ========== Rutas de Administrador ==========
        Route::middleware('admin')->prefix('admin')->group(function () {

            // Dashboard
            Route::get('/dashboard', [AdminController::class, 'dashboard']);

            // Gestión de usuarios
            Route::get('/usuarios', [AdminController::class, 'usuarios']);
            Route::get('/usuarios/{id}', [AdminController::class, 'verUsuario']);
            Route::patch('/usuarios/{id}/toggle', [AdminController::class, 'toggleUsuario']);
            Route::delete('/usuarios/{id}', [AdminController::class, 'eliminarUsuario']);

            // Supervisión de servicios
            Route::get('/servicios', [AdminController::class, 'servicios']);
            Route::patch('/servicios/{id}/estado', [AdminController::class, 'cambiarEstadoServicio']);
            Route::delete('/servicios/{id}', [AdminController::class, 'eliminarServicio']); // ← AGREGAR

            // Supervisión de chats
            Route::get('/chats', [AdminController::class, 'chats']);
            Route::get('/chats/conversaciones', [AdminController::class, 'conversacionesAgrupadas']); // ← AGREGAR
            Route::get('/chats/conversacion/{usuario1}/{usuario2}', [AdminController::class, 'conversacionCompleta']); // ← AGREGAR
            Route::delete('/chats/{id}', [AdminController::class, 'eliminarChat']);

            // Supervisión de pagos
            Route::get('/pagos', [AdminController::class, 'pagos']);
            Route::patch('/pagos/{id}/estado', [AdminController::class, 'cambiarEstadoPago']);

            // Supervisión de calificaciones
            Route::get('/calificaciones', [AdminController::class, 'calificaciones']);
            Route::delete('/calificaciones/{id}', [AdminController::class, 'eliminarCalificacion']);

            // Reportes
            Route::get('/reportes', [AdminController::class, 'reportes']);
            Route::post('/reportes', [AdminController::class, 'generarReporte']);
        });
    });
});
