<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Servicio;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ServicioController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Servicio::with(['usuario', 'calificaciones'])
            ->where('estado', 'activo');

        if ($request->has('precio_min')) {
            $query->where('precio', '>=', $request->precio_min);
        }

        if ($request->has('precio_max')) {
            $query->where('precio', '<=', $request->precio_max);
        }

        if ($request->has('buscar')) {
            $query->where(function($q) use ($request) {
                $q->where('titulo', 'like', '%' . $request->buscar . '%')
                  ->orWhere('descripcion', 'like', '%' . $request->buscar . '%');
            });
        }

        $servicios = $query->orderBy('fechaPublicacion', 'desc')
            ->paginate(12);

        return response()->json([
            'success' => true,
            'data' => $servicios
        ]);
    }

    public function show($id): JsonResponse
    {
        $servicio = Servicio::with(['usuario', 'calificaciones.usuario'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $servicio
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'titulo' => 'required|string|max:150',
            'descripcion' => 'required|string',
            'precio' => 'required|numeric|min:0',
            'imagenes' => 'nullable|array|max:5',
            'imagenes.*' => 'string' // Base64 images
        ]);

        $imagenesGuardadas = [];

        // Procesar imágenes en base64
        if (isset($validated['imagenes']) && is_array($validated['imagenes'])) {
            foreach ($validated['imagenes'] as $imagenBase64) {
                $imagenesGuardadas[] = $this->guardarImagenBase64($imagenBase64);
            }
        }

        $servicio = Servicio::create([
            'usuario_id' => Auth::id(),
            'titulo' => $validated['titulo'],
            'descripcion' => $validated['descripcion'],
            'precio' => $validated['precio'],
            'imagenes' => json_encode($imagenesGuardadas, JSON_UNESCAPED_SLASHES),
            'estado' => 'activo'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Servicio publicado exitosamente',
            'data' => $servicio->load('usuario')
        ], 201);
    }

    /**
     * Actualizar un servicio
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $servicio = Servicio::findOrFail($id);

            // Verificar que el usuario sea el dueño del servicio
            if ($servicio->usuario_id !== Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permiso para editar este servicio'
                ], 403);
            }

            $validated = $request->validate([
                'titulo' => 'sometimes|string|max:150',
                'descripcion' => 'sometimes|string',
                'precio' => 'sometimes|numeric|min:0',
                'estado' => 'sometimes|in:activo,inactivo,eliminado',
                'imagenes' => 'sometimes|array|max:5',
                'imagenes.*' => 'string'
            ]);

            // IMPORTANTE: Solo procesar imágenes si vienen en el request
            if (isset($validated['imagenes'])) {
                $imagenesGuardadas = [];

                foreach ($validated['imagenes'] as $imagen) {
                    // Si es una URL completa (imagen existente), extraer solo la ruta
                    if (strpos($imagen, '/storage/') !== false) {
                        // Extraer la ruta relativa: "servicios/xxxxx.jpeg"
                        $ruta = substr($imagen, strpos($imagen, '/storage/') + 9);
                        $imagenesGuardadas[] = $ruta;
                    }
                    // Si es base64 (nueva imagen), guardarla
                    elseif (strpos($imagen, 'data:image') === 0) {
                        $imagenesGuardadas[] = $this->guardarImagenBase64($imagen);
                    }
                    // Si ya es una ruta relativa, mantenerla
                    else {
                        $imagenesGuardadas[] = $imagen;
                    }
                }

                // Solo eliminar imágenes viejas si se están subiendo nuevas imágenes base64
                $hayImagenesNuevas = false;
                foreach ($validated['imagenes'] as $imagen) {
                    if (strpos($imagen, 'data:image') === 0) {
                        $hayImagenesNuevas = true;
                        break;
                    }
                }

                if ($hayImagenesNuevas) {
                    // Eliminar solo las imágenes que ya no están en la nueva lista
                    $imagenesAnteriores = $servicio->imagenes;
                    if ($imagenesAnteriores && is_array($imagenesAnteriores)) {
                        foreach ($imagenesAnteriores as $imagenVieja) {
                            if (!in_array($imagenVieja, $imagenesGuardadas)) {
                                Storage::disk('public')->delete($imagenVieja);
                            }
                        }
                    }
                }

                $validated['imagenes'] = json_encode($imagenesGuardadas, JSON_UNESCAPED_SLASHES);
            }

            // Actualizar solo los campos que vienen en el request
            $servicio->update($validated);

            // Recargar el servicio con sus relaciones
            $servicio->load('usuario', 'calificaciones');

            return response()->json([
                'success' => true,
                'message' => 'Servicio actualizado correctamente',
                'data' => $servicio
            ]);
        } catch (\Exception $e) {
            Log::error('Error al actualizar servicio: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el servicio: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar un servicio
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        try {
            $servicio = Servicio::findOrFail($id);

            // Verificar que el usuario sea el dueño del servicio
            if ($servicio->usuario_id !== Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permiso para eliminar este servicio'
                ], 403);
            }

            // Eliminar imágenes del storage si existen
            if ($servicio->imagenes) {
                $imagenes = is_string($servicio->imagenes)
                    ? json_decode($servicio->imagenes, true)
                    : $servicio->imagenes;

                if (is_array($imagenes)) {
                    foreach ($imagenes as $imagen) {
                        Storage::disk('public')->delete($imagen);
                    }
                }
            }

            // Eliminar el servicio permanentemente
            $servicio->delete();

            return response()->json([
                'success' => true,
                'message' => 'Servicio eliminado correctamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al eliminar servicio: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el servicio: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener servicios del usuario autenticado (servicios contratados)
     */
    public function misServicios(): JsonResponse
    {
        $servicios = Servicio::where('usuario_id', Auth::id())
            ->whereIn('estado', ['activo', 'inactivo'])
            ->with('usuario')
            ->orderBy('fechaPublicacion', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $servicios
        ]);
    }

    /**
     * Obtener las publicaciones del usuario autenticado
     */
    public function misPublicaciones(Request $request): JsonResponse
    {
        try {
            $usuario = $request->user();

            if (!$usuario) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado'
                ], 401);
            }

            $servicios = Servicio::where('usuario_id', $usuario->idUsuario)
                ->with(['usuario', 'calificaciones'])
                ->withCount('calificaciones')
                ->orderBy('fechaPublicacion', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $servicios
            ]);
        } catch (\Exception $e) {
            Log::error('Error en misPublicaciones: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar las publicaciones: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Guardar imagen en base64
     */
    private function guardarImagenBase64($imagenBase64)
    {
        // Extraer el tipo de imagen y los datos
        if (preg_match('/^data:image\/(\w+);base64,/', $imagenBase64, $type)) {
            $imagenBase64 = substr($imagenBase64, strpos($imagenBase64, ',') + 1);
            $type = strtolower($type[1]);

            // Decodificar
            $imagenBase64 = base64_decode($imagenBase64);

            if ($imagenBase64 === false) {
                throw new \Exception('Error al decodificar imagen');
            }

            // Generar nombre único
            $nombreArchivo = 'servicios/' . uniqid() . '.' . $type;

            // Guardar en storage/app/public
            Storage::disk('public')->put($nombreArchivo, $imagenBase64);

            return $nombreArchivo;
        }

        throw new \Exception('Formato de imagen inválido');
    }
}
