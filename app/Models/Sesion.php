<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sesion extends Model
{
    protected $table = 'sesion';
    protected $primaryKey = 'sesion_id';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'usuario_id', 'token_hash', 'fecha_inicio', 'fecha_expiracion',
        'ip_origen', 'user_agent', 'activa',
    ];

    protected $casts = [
        'fecha_inicio' => 'datetime',
        'fecha_expiracion' => 'datetime',
        'activa' => 'boolean',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id', 'usuario_id');
    }
}
