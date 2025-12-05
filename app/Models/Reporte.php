<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reporte extends Model
{
    use HasFactory;

    protected $table = 'reporte';
    protected $primaryKey = 'idReporte';
    public $timestamps = false;

    protected $fillable = [
        'admin_id',
        'tipo',
        'parametros',
        'fechaGeneracion', // ← AGREGAR ESTO
        'archivo'
    ];

    protected $casts = [
        'fechaGeneracion' => 'datetime',
        // QUITA 'parametros' => 'array' porque lo guardamos como JSON string
    ];

    // Relaciones
    public function admin()
    {
        return $this->belongsTo(Usuario::class, 'admin_id', 'idUsuario');
    }
}
