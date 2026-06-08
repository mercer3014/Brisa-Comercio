<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GrupoActividad extends Model
{
    protected $table = 'grupo_actividad';
    protected $primaryKey = 'grupo_actividad_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = ['fuente_id', 'codigo', 'descripcion', 'clasificacion_mayor'];

    public function fuente(): BelongsTo
    {
        return $this->belongsTo(FuenteDatos::class, 'fuente_id', 'fuente_id');
    }

    public function actividades(): HasMany
    {
        return $this->hasMany(ActividadCiiu::class, 'grupo_actividad_id', 'grupo_actividad_id');
    }
}
