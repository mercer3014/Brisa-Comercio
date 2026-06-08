<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MedioTransporte extends Model
{
    protected $table = 'medio_transporte';
    protected $primaryKey = 'medio_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = ['codigo', 'descripcion'];

    public function operaciones(): HasMany
    {
        return $this->hasMany(OperacionComercioExterior::class, 'medio_id', 'medio_id');
    }
}
