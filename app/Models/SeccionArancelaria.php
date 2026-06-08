<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SeccionArancelaria extends Model
{
    protected $table = 'seccion_arancelaria';
    protected $primaryKey = 'seccion_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = ['fuente_id', 'codigo_seccion', 'descripcion'];

    public function fuente(): BelongsTo
    {
        return $this->belongsTo(FuenteDatos::class, 'fuente_id', 'fuente_id');
    }

    public function capitulos(): HasMany
    {
        return $this->hasMany(CapituloArancelario::class, 'seccion_id', 'seccion_id');
    }
}
