<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FuenteDatos extends Model
{
    protected $table = 'fuente_datos';
    protected $primaryKey = 'fuente_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'organizacion_id', 'url_fuente', 'fecha_descarga',
        'version_nomenclatura', 'observaciones',
    ];

    protected $casts = [
        'fecha_descarga' => 'date',
        'creado_en' => 'datetime',
    ];

    public function organizacion(): BelongsTo
    {
        return $this->belongsTo(Organizacion::class, 'organizacion_id', 'organizacion_id');
    }
}
