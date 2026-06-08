<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClasificacionTnt extends Model
{
    protected $table = 'clasificacion_tnt';
    protected $primaryKey = 'tnt_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = ['codigo_tnt', 'descripcion', 'clase'];

    public function operaciones(): HasMany
    {
        return $this->hasMany(OperacionComercioExterior::class, 'tnt_id', 'tnt_id');
    }
}
