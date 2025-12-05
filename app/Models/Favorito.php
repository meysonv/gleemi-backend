<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Favorito extends Model
{
    use HasFactory;

    protected $table = 'favorito';
    protected $primaryKey = 'idFavorito';
    public $timestamps = false;

    protected $fillable = [
        'usuario_id',
        'servicio_id'
    ];

    protected $casts = [
        'fecha' => 'datetime',
    ];

    // Relaciones
    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id', 'idUsuario');
    }

    public function servicio()
    {
        return $this->belongsTo(Servicio::class, 'servicio_id', 'idServicio');
    }
}