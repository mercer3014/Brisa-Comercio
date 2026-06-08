<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Puente N:M entre rol y permiso. PK compuesta (rol_id, permiso_id).
 */
class RolPermiso extends Pivot
{
    protected $table = 'rol_permiso';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = ['rol_id', 'permiso_id'];
}
