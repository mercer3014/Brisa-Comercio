<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// NOTA: lOgica de pagos/descargas pospuesta (fase posterior). Modelo creado para mapear la tabla.
class UsuarioPerfilPublico extends Model
{
    protected $table = 'usuario_perfil_publico';
    protected $primaryKey = 'perfil_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $guarded = [];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id', 'usuario_id');
    }
}
