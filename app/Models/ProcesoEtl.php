<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcesoEtl extends Model
{
    protected $table = 'proceso_etl';
    protected $primaryKey = 'proceso_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'carga_id', 'estado', 'fecha_inicio', 'fecha_fin',
        'filas_procesadas', 'mensaje_log',
    ];

    protected $casts = [
        'fecha_inicio' => 'datetime',
        'fecha_fin' => 'datetime',
    ];

    public function carga(): BelongsTo
    {
        return $this->belongsTo(CargaArchivo::class, 'carga_id', 'carga_id');
    }
}
