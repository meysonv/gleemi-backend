<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Usuario extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'usuario';
    protected $primaryKey = 'idUsuario';
    public $timestamps = false;

    protected $fillable = [
        'rol',
        'nombre',
        'apellido',
        'email',
        'contraseña',
        'telefono',
        'foto',
        'activo'
    ];

    protected $hidden = [
        'contraseña',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'fechaRegistro' => 'datetime',
    ];

    // Sobrescribir el método de password para usar 'contraseña'
    public function getAuthPassword()
    {
        return $this->contraseña;
    }

    // Relaciones
    public function servicios()
    {
        return $this->hasMany(Servicio::class, 'usuario_id', 'idUsuario');
    }

    public function calificaciones()
    {
        return $this->hasMany(Calificacion::class, 'usuario_id', 'idUsuario');
    }

    public function favoritos()
    {
        return $this->hasMany(Favorito::class, 'usuario_id', 'idUsuario');
    }

    public function pagosRealizados()
    {
        return $this->hasMany(Pago::class, 'pagador_id', 'idUsuario');
    }

    public function pagosRecibidos()
    {
        return $this->hasMany(Pago::class, 'receptor_id', 'idUsuario');
    }

    public function chatsEnviados()
    {
        return $this->hasMany(Chat::class, 'emisor_id', 'idUsuario');
    }

    public function chatsRecibidos()
    {
        return $this->hasMany(Chat::class, 'receptor_id', 'idUsuario');
    }

    public function reportes()
    {
        return $this->hasMany(Reporte::class, 'admin_id', 'idUsuario');
    }

    // Scopes
    public function scopeAdmins($query)
    {
        return $query->where('rol', 'admin');
    }

    public function scopeRegistrados($query)
    {
        return $query->where('rol', 'registrado');
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', 1);
    }
}