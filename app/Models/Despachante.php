<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Despachante extends Model
{
    protected $table = 'despachante';
    protected $primaryKey = 'despachante_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = ['nombre'];

    public function operaciones(): HasMany
    {
        return $this->hasMany(OperacionComercioExterior::class, 'despachante_id', 'despachante_id');
    }
}
