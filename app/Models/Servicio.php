<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Servicio extends Model
{
    use HasFactory;

    protected $table = 'servicio';
    protected $primaryKey = 'idServicio';
    public $timestamps = false;

    protected $fillable = [
        'usuario_id',
        'titulo',
        'descripcion',
        'precio',
        'estado',
        'imagenes'
    ];

    protected $casts = [
        'precio' => 'decimal:2',
        'fechaPublicacion' => 'datetime',
    ];

    // ACCESSOR: Cuando Laravel LEE el campo imagenes de la BD, lo convierte a array
    public function getImagenesAttribute($value)
    {
        // Si es null o vacío, retornar array vacío
        if (is_null($value) || $value === '') {
            return [];
        }

        // Si ya es array
        if (is_array($value)) {
            return $value;
        }

        // Si no es string, retornar vacío
        if (!is_string($value)) {
            return [];
        }

        // Limpiar el string: quitar escapes innecesarios
        $cleaned = stripslashes($value);

        // Intentar decodificar el JSON
        $decoded = json_decode($cleaned, true);

        // Si falló la decodificación, intentar con el valor original
        if (json_last_error() !== JSON_ERROR_NONE) {
            $decoded = json_decode($value, true);
        }

        // ← QUITAR TODO EL BLOQUE DEL LOG

        return is_array($decoded) ? $decoded : [];
    }

    // MUTATOR: Asegura que se guarda correctamente
    public function setImagenesAttribute($value)
    {
        if (is_null($value)) {
            $this->attributes['imagenes'] = null;
            return;
        }

        if (is_string($value)) {
            $this->attributes['imagenes'] = $value;
            return;
        }

        if (is_array($value)) {
            // Asegurar que no haya escapes dobles
            $this->attributes['imagenes'] = json_encode($value, JSON_UNESCAPED_SLASHES);
            return;
        }

        $this->attributes['imagenes'] = null;
    }

    // RELACIONES
    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id', 'idUsuario');
    }

    public function calificaciones()
    {
        return $this->hasMany(Calificacion::class, 'servicio_id', 'idServicio');
    }

    public function favoritos()
    {
        return $this->hasMany(Favorito::class, 'servicio_id', 'idServicio');
    }

    public function pagos()
    {
        return $this->hasMany(Pago::class, 'servicio_id', 'idServicio');
    }

    // SCOPES
    public function scopeActivos($query)
    {
        return $query->where('estado', 'activo');
    }

    public function scopeInactivos($query)
    {
        return $query->where('estado', 'inactivo');
    }

    // ACCESSORS ADICIONALES
    public function getCalificacionPromedioAttribute()
    {
        return $this->calificaciones()->avg('puntuacion');
    }

    public function getTotalCalificacionesAttribute()
    {
        return $this->calificaciones()->count();
    }

    public function getPrimeraImagenAttribute()
    {
        $imagenes = $this->imagenes;
        return !empty($imagenes) && is_array($imagenes) ? $imagenes[0] : null;
    }
}
