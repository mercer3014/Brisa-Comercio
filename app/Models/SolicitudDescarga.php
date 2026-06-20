<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// NOTA: lOgica de pagos/descargas pospuesta (fase posterior). Modelo creado para mapear la tabla.
class SolicitudDescarga extends Model
{
    protected $table = 'solicitud_descarga';
    protected $primaryKey = 'solicitud_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'parametros' => 'array',
        'fecha_solicitud' => 'datetime',
        'fecha_listo' => 'datetime',
        'fecha_expiracion' => 'datetime',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id', 'usuario_id');
    }

    public function organizacion(): BelongsTo
    {
        return $this->belongsTo(Organizacion::class, 'organizacion_id', 'organizacion_id');
    }
}
