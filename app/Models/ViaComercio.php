<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ViaComercio extends Model
{
    protected $table = 'via_comercio';
    protected $primaryKey = 'via_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = ['codigo', 'descripcion'];

    public function operaciones(): HasMany
    {
        return $this->hasMany(OperacionComercioExterior::class, 'via_id', 'via_id');
    }
}
