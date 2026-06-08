<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReglaValidacion extends Model
{
    protected $table = 'regla_validacion';
    protected $primaryKey = 'regla_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'nombre', 'descripcion', 'campo_objetivo', 'expresion', 'severidad', 'activa',
    ];

    protected $casts = [
        'activa' => 'boolean',
    ];

    public function incidencias(): HasMany
    {
        return $this->hasMany(IncidenciaCalidad::class, 'regla_id', 'regla_id');
    }
}
