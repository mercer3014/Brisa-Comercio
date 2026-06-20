<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// NOTA: lOgica de pagos/descargas pospuesta (fase posterior). Modelo creado para mapear la tabla.
class CompraCreditos extends Model
{
    protected $table = 'compra_creditos';
    protected $primaryKey = 'compra_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'monto_usd' => 'decimal:2',
        'fecha' => 'datetime',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id', 'usuario_id');
    }

    public function paquete(): BelongsTo
    {
        return $this->belongsTo(PaqueteCreditos::class, 'paquete_id', 'paquete_id');
    }
}
