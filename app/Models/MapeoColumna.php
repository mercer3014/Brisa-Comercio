<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MapeoColumna extends Model
{
    protected $table = 'mapeo_columna';
    protected $primaryKey = 'mapeo_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'perfil_id', 'nombre_columna_origen', 'campo_canonico',
        'guardar', 'a_extra', 'nota',
    ];

    protected $casts = [
        'guardar' => 'boolean',
        'a_extra' => 'boolean',
    ];

    public function perfil(): BelongsTo
    {
        return $this->belongsTo(PerfilMapeo::class, 'perfil_id', 'perfil_id');
    }
}
