<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Chat extends Model
{
    use HasFactory;

    protected $table = 'chat';
    protected $primaryKey = 'idChat';
    public $timestamps = true;
    const CREATED_AT = 'fechaEnvio';
    const UPDATED_AT = null;

    protected $fillable = [
        'emisor_id',
        'receptor_id',
        'servicio_id', // ← AGREGAR
        'mensaje'
    ];

    protected $casts = [
        'fechaEnvio' => 'datetime',
    ];

    // Relaciones
    public function emisor()
    {
        return $this->belongsTo(Usuario::class, 'emisor_id', 'idUsuario');
    }

    public function receptor()
    {
        return $this->belongsTo(Usuario::class, 'receptor_id', 'idUsuario');
    }

    // ← AGREGAR ESTA RELACIÓN
    public function servicio()
    {
        return $this->belongsTo(Servicio::class, 'servicio_id', 'idServicio');
    }

    // Scopes
    public function scopeEntreUsuarios($query, $usuario1Id, $usuario2Id)
    {
        return $query->where(function($q) use ($usuario1Id, $usuario2Id) {
            $q->where('emisor_id', $usuario1Id)
              ->where('receptor_id', $usuario2Id);
        })->orWhere(function($q) use ($usuario1Id, $usuario2Id) {
            $q->where('emisor_id', $usuario2Id)
              ->where('receptor_id', $usuario1Id);
        });
    }

    public function scopeDeUsuario($query, $usuarioId)
    {
        return $query->where('emisor_id', $usuarioId)
                     ->orWhere('receptor_id', $usuarioId);
    }
}
