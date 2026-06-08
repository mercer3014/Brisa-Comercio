<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Aduana extends Model
{
    protected $table = 'aduana';
    protected $primaryKey = 'aduana_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = ['fuente_id', 'codigo', 'descripcion'];

    public function fuente(): BelongsTo
    {
        return $this->belongsTo(FuenteDatos::class, 'fuente_id', 'fuente_id');
    }

    public function operaciones(): HasMany
    {
        return $this->hasMany(OperacionComercioExterior::class, 'aduana_id', 'aduana_id');
    }
}
