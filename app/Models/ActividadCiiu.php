<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ActividadCiiu extends Model
{
    protected $table = 'actividad_ciiu';
    protected $primaryKey = 'ciiu_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = ['grupo_actividad_id', 'codigo_ciiu', 'descripcion', 'revision'];

    public function grupo(): BelongsTo
    {
        return $this->belongsTo(GrupoActividad::class, 'grupo_actividad_id', 'grupo_actividad_id');
    }

    public function operaciones(): HasMany
    {
        return $this->hasMany(OperacionComercioExterior::class, 'ciiu_id', 'ciiu_id');
    }
}
