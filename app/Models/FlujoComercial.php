<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FlujoComercial extends Model
{
    protected $table = 'flujo_comercial';
    protected $primaryKey = 'flujo_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = ['codigo_flujo', 'descripcion'];

    public function operaciones(): HasMany
    {
        return $this->hasMany(OperacionComercioExterior::class, 'flujo_id', 'flujo_id');
    }
}
