<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategoriaEconomicaGce extends Model
{
    protected $table = 'categoria_economica_gce';
    protected $primaryKey = 'gce_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = ['fuente_id', 'codigo_gce', 'descripcion', 'revision'];

    public function fuente(): BelongsTo
    {
        return $this->belongsTo(FuenteDatos::class, 'fuente_id', 'fuente_id');
    }

    public function operaciones(): HasMany
    {
        return $this->hasMany(OperacionComercioExterior::class, 'gce_id', 'gce_id');
    }
}
