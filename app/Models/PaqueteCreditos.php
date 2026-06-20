<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// NOTA: lOgica de pagos/descargas pospuesta (fase posterior). Modelo creado para mapear la tabla.
class PaqueteCreditos extends Model
{
    protected $table = 'paquete_creditos';
    protected $primaryKey = 'paquete_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'activo' => 'boolean',
        'precio_usd' => 'decimal:2',
    ];
}
