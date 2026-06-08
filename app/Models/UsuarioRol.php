<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Puente N:M entre usuario y rol. PK compuesta (usuario_id, rol_id).
 */
class UsuarioRol extends Pivot
{
    protected $table = 'usuario_rol';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = ['usuario_id', 'rol_id', 'asignado_en'];

    protected $casts = [
        'asignado_en' => 'datetime',
    ];
}
