<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BitacoraAuditoria extends Model
{
    protected $table = 'bitacora_auditoria';
    protected $primaryKey = 'bitacora_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'usuario_id', 'accion', 'entidad_afectada', 'registro_afectado',
        'valores_anteriores', 'valores_nuevos', 'ip_origen', 'fecha_hora',
    ];

    protected $casts = [
        'valores_anteriores' => 'array',
        'valores_nuevos' => 'array',
        'fecha_hora' => 'datetime',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id', 'usuario_id');
    }
}
