<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Puente N:M entre país y zona_geoeconomica. PK compuesta (pais_id, zona_id),
 * sin columna identity.
 */
class PaisZona extends Pivot
{
    protected $table = 'pais_zona';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = ['pais_id', 'zona_id'];
}
