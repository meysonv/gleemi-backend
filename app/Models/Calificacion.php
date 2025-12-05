<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Calificacion extends Model
{
    use HasFactory;

    protected $table = 'calificacion';
    protected $primaryKey = 'idCalificacion';
    public $timestamps = false;

    protected $fillable = [
        'servicio_id',
        'usuario_id',
        'puntuacion',
        'comentario',
        'fecha'
    ];

    protected $casts = [
        'fecha' => 'datetime',
        'puntuacion' => 'integer'
    ];

    // Relaciones
    public function servicio()
    {
        return $this->belongsTo(Servicio::class, 'servicio_id', 'idServicio');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id', 'idUsuario');
    }
}
