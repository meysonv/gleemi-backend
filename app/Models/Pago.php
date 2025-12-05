<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pago extends Model
{
    use HasFactory;

    protected $table = 'pago';
    protected $primaryKey = 'idPago';
    public $timestamps = false;

    protected $fillable = [
        'pagador_id',
        'receptor_id',
        'servicio_id',
        'monto',
        'estado'
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'fechaPago' => 'datetime',
    ];

    // Relaciones
    public function pagador()
    {
        return $this->belongsTo(Usuario::class, 'pagador_id', 'idUsuario');
    }

    public function receptor()
    {
        return $this->belongsTo(Usuario::class, 'receptor_id', 'idUsuario');
    }

    public function servicio()
    {
        return $this->belongsTo(Servicio::class, 'servicio_id', 'idServicio');
    }

    // Scopes
    public function scopePendientes($query)
    {
        return $query->where('estado', 'pendiente');
    }

    public function scopeCompletados($query)
    {
        return $query->where('estado', 'completado');
    }

    public function scopeFallidos($query)
    {
        return $query->where('estado', 'fallido');
    }
}