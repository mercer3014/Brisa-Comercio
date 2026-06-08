<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoOperacion extends Model
{
    protected $table = 'tipo_operacion';
    protected $primaryKey = 'tipo_operacion_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = ['nombre', 'base_valoracion'];

    public function operaciones(): HasMany
    {
        return $this->hasMany(OperacionComercioExterior::class, 'tipo_operacion_id', 'tipo_operacion_id');
    }
}
