<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organizacion extends Model
{
    protected $table = 'organizacion';
    protected $primaryKey = 'organizacion_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = ['nombre', 'sigla', 'pais_iso3', 'url', 'activo'];

    protected $casts = [
        'activo' => 'boolean',
        'creado_en' => 'datetime',
    ];

    public function fuentes(): HasMany
    {
        return $this->hasMany(FuenteDatos::class, 'organizacion_id', 'organizacion_id');
    }

    public function perfiles(): HasMany
    {
        return $this->hasMany(PerfilMapeo::class, 'organizacion_id', 'organizacion_id');
    }

    public function cargas(): HasMany
    {
        return $this->hasMany(CargaArchivo::class, 'organizacion_id', 'organizacion_id');
    }

    public function operaciones(): HasMany
    {
        return $this->hasMany(OperacionComercioExterior::class, 'organizacion_id', 'organizacion_id');
    }
}
