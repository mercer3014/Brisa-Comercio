<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncidenciaCalidad extends Model
{
    protected $table = 'incidencia_calidad';
    protected $primaryKey = 'incidencia_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'carga_id', 'regla_id', 'descripcion', 'severidad', 'numero_fila',
        'valor_detectado', 'estado_tratamiento', 'fecha_deteccion',
    ];

    protected $casts = [
        'fecha_deteccion' => 'datetime',
    ];

    public function carga(): BelongsTo
    {
        return $this->belongsTo(CargaArchivo::class, 'carga_id', 'carga_id');
    }

    public function regla(): BelongsTo
    {
        return $this->belongsTo(ReglaValidacion::class, 'regla_id', 'regla_id');
    }
}
