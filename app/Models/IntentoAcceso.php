<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntentoAcceso extends Model
{
    protected $table = 'intento_acceso';
    protected $primaryKey = 'intento_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'nombre_usuario_intento', 'exito', 'ip_origen', 'motivo_fallo', 'fecha_hora',
    ];

    protected $casts = [
        'exito' => 'boolean',
        'fecha_hora' => 'datetime',
    ];
}
