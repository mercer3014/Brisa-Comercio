<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ZonaGeoeconomica extends Model
{
    protected $table = 'zona_geoeconomica';
    protected $primaryKey = 'zona_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = ['fuente_id', 'codigo_zona', 'descripcion'];

    public function fuente(): BelongsTo
    {
        return $this->belongsTo(FuenteDatos::class, 'fuente_id', 'fuente_id');
    }

    /**
     * Paises cuya zona principal es esta (FK directa pais.zona_id).
     */
    public function paises(): HasMany
    {
        return $this->hasMany(Pais::class, 'zona_id', 'zona_id');
    }
}
