<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pais extends Model
{
    protected $table = 'pais';
    protected $primaryKey = 'pais_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'fuente_id', 'zona_id', 'codigo_pais', 'nombre', 'iso_alpha2', 'iso_alpha3',
    ];

    public function fuente(): BelongsTo
    {
        return $this->belongsTo(FuenteDatos::class, 'fuente_id', 'fuente_id');
    }

    /**
     * Zona principal del pais (FK directa).
     */
    public function zona(): BelongsTo
    {
        return $this->belongsTo(ZonaGeoeconomica::class, 'zona_id', 'zona_id');
    }

    /**
     * Zonas adicionales a las que pertenece el pais (puente N:M pais_zona).
     */
    public function zonas(): BelongsToMany
    {
        return $this->belongsToMany(
            ZonaGeoeconomica::class,
            'pais_zona',
            'pais_id',
            'zona_id'
        );
    }

    public function operaciones(): HasMany
    {
        return $this->hasMany(OperacionComercioExterior::class, 'pais_id', 'pais_id');
    }
}
