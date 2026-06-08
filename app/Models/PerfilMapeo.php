<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PerfilMapeo extends Model
{
    protected $table = 'perfil_mapeo';
    protected $primaryKey = 'perfil_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'organizacion_id', 'tipo_flujo', 'etiqueta_version', 'descripcion', 'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'creado_en' => 'datetime',
    ];

    public function organizacion(): BelongsTo
    {
        return $this->belongsTo(Organizacion::class, 'organizacion_id', 'organizacion_id');
    }

    public function columnas(): HasMany
    {
        return $this->hasMany(MapeoColumna::class, 'perfil_id', 'perfil_id');
    }
}
